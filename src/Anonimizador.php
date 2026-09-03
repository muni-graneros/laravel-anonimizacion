<?php

namespace Anonimizacion;

use Anonimizacion\Contratos\ClasificadorSensible;
use Anonimizacion\Contratos\Detector;
use Anonimizacion\Contratos\RegistroDeAuditoria;
use Anonimizacion\Contratos\RepositorioDeBoveda;
use Anonimizacion\Excepciones\BovedaAjena;
use Anonimizacion\Excepciones\BovedaExpirada;

class Anonimizador
{
    /** @param array<int, Detector> $detectores */
    public function __construct(
        private readonly array $detectores,
        private readonly RepositorioDeBoveda $boveda,
        private readonly ClasificadorSensible $clasificador,
        private readonly RegistroDeAuditoria $auditoria,
    ) {}

    /**
     * Solo clasifica: no tokeniza, no crea bóveda y no escribe en el store.
     * Sirve para decidir el camino antes de gastar trabajo.
     */
    public function categoriaSensibleDe(string $texto): ?string
    {
        return $this->clasificador->categoriaDe($texto);
    }

    /**
     * @param  ?string  $consumidor  Quién amordaza (el consumidor de la API).
     *                               Queda grabado como dueño de la bóveda:
     *                               nadie más va a poder restaurarla, aunque
     *                               tenga permiso general de /restaurar.
     */
    public function amordazar(string $texto, ?string $consumidor = null): Resultado
    {
        // El veto va PRIMERO: si el texto trae dato sensible no sale, y no se
        // gasta trabajo ni se crea una bóveda que después habría que expirar.
        $categoria = $this->clasificador->categoriaDe($texto);

        if ($categoria !== null) {
            $this->auditoria->registrar('pii.vetado', ['categoria' => $categoria]);

            return Resultado::vetado($categoria);
        }

        $texto = $this->neutralizarMarcadores($texto);

        $hallazgos = [];
        foreach ($this->detectores as $detector) {
            array_push($hallazgos, ...$detector->detectar($texto));
        }

        // Se numera de adelante hacia atrás para que [RUT_1] sea el primero que
        // aparece en el texto, pero se REEMPLAZA de atrás hacia adelante: al
        // revés, cada sustitución correría la posición de las siguientes.
        usort($hallazgos, fn (Hallazgo $a, Hallazgo $b) => $a->inicio <=> $b->inicio);

        $hallazgos = $this->descartarSolapados($hallazgos);

        $nonce = bin2hex(random_bytes(2));
        $boveda = new Boveda($consumidor);
        $numeros = [];
        $tipos = [];
        $marcadores = [];

        foreach ($hallazgos as $i => $hallazgo) {
            $numeros[$hallazgo->tipo] = ($numeros[$hallazgo->tipo] ?? 0) + 1;
            $etiqueta = strtoupper($hallazgo->tipo);
            $marcadores[$i] = "[{$etiqueta}_{$numeros[$hallazgo->tipo]}_{$nonce}]";
            $tipos[$hallazgo->tipo] = true;
        }

        foreach (array_reverse($hallazgos, true) as $i => $hallazgo) {
            $boveda->agregar($marcadores[$i], $hallazgo->valor);
            $texto = substr_replace($texto, $marcadores[$i], $hallazgo->inicio, $hallazgo->largo);
        }

        $id = BovedaId::nueva();
        $this->boveda->guardar($id, $boveda);

        $this->auditoria->registrar('pii.amordazado', [
            'tipos' => array_keys($tipos),
            'cantidad' => count($hallazgos),
        ]);

        return new Resultado(Veredicto::Permitido, $texto, $id, array_keys($tipos));
    }

    /**
     * Reinserta los valores reales. Los marcadores de la respuesta deben ser un
     * SUBCONJUNTO de los de la bóveda: uno que el modelo haya inventado se
     * elimina, nunca se deja crudo ni se intenta adivinar a qué persona apunta.
     *
     * @param  ?string  $consumidor  Quién pide restaurar. Debe coincidir con
     *                               el que amordazó, o se rechaza aunque el
     *                               llamador tenga permiso general de
     *                               /restaurar: la bóveda de un consumidor
     *                               no es la de otro.
     *
     * @throws BovedaExpirada
     * @throws BovedaAjena
     */
    public function restaurar(string $respuesta, BovedaId $id, ?string $consumidor = null): string
    {
        $boveda = $this->boveda->recuperar($id);

        if (! $boveda->perteneceA($consumidor)) {
            throw BovedaAjena::paraId($id->valor);
        }

        $this->auditoria->registrar('pii.restaurado', [
            'marcadores' => count($boveda->marcadores()),
        ]);

        return preg_replace_callback(
            '/\[[A-Z]+_\d+_[a-f0-9]{4}\]/u',
            fn (array $c) => $boveda->resolver($c[0]) ?? '',
            $respuesta,
        ) ?? $respuesta;
    }

    /**
     * Descarta hallazgos cuyo rango se solapa con uno ya aceptado: dos
     * detectores pueden marcar el mismo tramo de texto (p. ej. un teléfono
     * detectado DENTRO de un correo). Reemplazar ambos por separado corrompe
     * el texto porque los offsets se calcularon sobre el string original.
     *
     * Requiere que $hallazgos venga ordenado por `inicio` ascendente. Cuando
     * dos empiezan igual, se queda con el más largo (el que cubre más).
     *
     * @param  array<int, Hallazgo>  $hallazgos
     * @return array<int, Hallazgo>
     */
    private function descartarSolapados(array $hallazgos): array
    {
        $aceptados = [];
        $ultimo = null;
        $finAceptado = -1;

        foreach ($hallazgos as $hallazgo) {
            $inicio = $hallazgo->inicio;
            $fin = $hallazgo->inicio + $hallazgo->largo;

            if ($ultimo !== null && $inicio < $finAceptado) {
                // Se solapa con el último aceptado. Si es más largo (empieza
                // igual pero cubre más), reemplaza al anterior; si no, se
                // descarta.
                if ($inicio === $ultimo->inicio && $hallazgo->largo > $ultimo->largo) {
                    array_pop($aceptados);
                    $aceptados[] = $ultimo = $hallazgo;
                    $finAceptado = $fin;
                }

                continue;
            }

            $aceptados[] = $ultimo = $hallazgo;
            $finAceptado = $fin;
        }

        return $aceptados;
    }

    /**
     * Cualquier cosa con forma de marcador que venga del usuario se desactiva
     * cambiando los corchetes por paréntesis: el mensaje se sigue entendiendo
     * y deja de poder inyectar entradas falsas al mapeo.
     */
    private function neutralizarMarcadores(string $texto): string
    {
        return preg_replace('/\[([A-Z]+_\d+(?:_[a-f0-9]+)?)\]/u', '($1)', $texto) ?? $texto;
    }
}
