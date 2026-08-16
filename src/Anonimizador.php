<?php

namespace Anonimizacion;

use Anonimizacion\Contratos\ClasificadorSensible;
use Anonimizacion\Contratos\Detector;
use Anonimizacion\Contratos\RepositorioDeBoveda;
use Anonimizacion\Excepciones\BovedaExpirada;

class Anonimizador
{
    /** @param array<int, Detector> $detectores */
    public function __construct(
        private readonly array $detectores,
        private readonly RepositorioDeBoveda $boveda,
        private readonly ClasificadorSensible $clasificador,
    ) {}

    public function amordazar(string $texto): Resultado
    {
        // El veto va PRIMERO: si el texto trae dato sensible no sale, y no se
        // gasta trabajo ni se crea una bóveda que después habría que expirar.
        $categoria = $this->clasificador->categoriaDe($texto);

        if ($categoria !== null) {
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

        $nonce = bin2hex(random_bytes(2));
        $boveda = new Boveda;
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

        return new Resultado(Veredicto::Permitido, $texto, $id, array_keys($tipos));
    }

    /**
     * Reinserta los valores reales. Los marcadores de la respuesta deben ser un
     * SUBCONJUNTO de los de la bóveda: uno que el modelo haya inventado se
     * elimina, nunca se deja crudo ni se intenta adivinar a qué persona apunta.
     *
     * @throws BovedaExpirada
     */
    public function restaurar(string $respuesta, BovedaId $id): string
    {
        $boveda = $this->boveda->recuperar($id);

        return preg_replace_callback(
            '/\[[A-Z]+_\d+_[a-f0-9]{4}\]/u',
            fn (array $c) => $boveda->resolver($c[0]) ?? '',
            $respuesta,
        ) ?? $respuesta;
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
