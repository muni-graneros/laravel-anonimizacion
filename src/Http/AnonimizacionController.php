<?php

namespace Anonimizacion\Http;

use Anonimizacion\Anonimizador;
use Anonimizacion\BovedaId;
use Anonimizacion\Contratos\RegistroDeAuditoria;
use Anonimizacion\Excepciones\BovedaAjena;
use Anonimizacion\Metricas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class AnonimizacionController
{
    public const VERSION = '1.0.0';

    /** Marca de arranque del proceso, para informar el tiempo en pie. */
    private static ?float $inicio = null;

    public function __construct(
        private readonly Anonimizador $anonimizador,
        private readonly Metricas $metricas,
        private readonly RegistroDeAuditoria $auditoria,
    ) {
        self::$inicio ??= microtime(true);
    }

    public function amordazar(Request $request): JsonResponse
    {
        $datos = $request->validate(['texto' => ['required', 'string', 'max:20000']]);
        $consumidor = $this->consumidorDe($request);

        $this->metricas->contar($consumidor, 'amordazar');

        $resultado = $this->anonimizador->amordazar($datos['texto'], $consumidor);

        return response()->json([
            'veredicto' => $resultado->veredicto->value,
            'categoria' => $resultado->categoria,
            'texto_seguro' => $resultado->textoSeguro,
            'boveda_id' => $resultado->boveda?->valor,
            'tipos' => $resultado->tipos,
        ]);
    }

    public function restaurar(Request $request): JsonResponse
    {
        // Restaurar devuelve datos reales: tener un token válido no alcanza,
        // hace falta el permiso explícito.
        $autorizados = array_filter(array_map(
            'trim',
            explode(',', (string) config('anonimizacion.api.pueden_restaurar'))
        ));

        if (! in_array($request->attributes->get('consumidor'), $autorizados, true)) {
            return response()->json(['error' => 'sin permiso para restaurar'], 403);
        }

        $datos = $request->validate([
            'texto' => ['required', 'string', 'max:20000'],
            // El id va a la clave del store: solo se acepta el formato que
            // genera el paquete (32 hexadecimales), nunca texto libre.
            //
            // Es opcional porque `amordazar()` devuelve `boveda_id: null`
            // cuando el texto no traía ningún dato personal: no hay bóveda que
            // consultar y la respuesta vuelve tal cual. Exigirlo obligaría al
            // consumidor a inventarse un id para cerrar el ciclo.
            'boveda_id' => ['nullable', 'string', 'regex:/^[0-9a-f]{32}$/'],
        ]);

        $consumidor = $this->consumidorDe($request);
        $this->metricas->contar($consumidor, 'restaurar');

        // Evento propio de la API, distinto de 'pii.restaurado' que emite el
        // motor: registra QUIÉN des-anonimizó y desde dónde. La IP es dato
        // personal, así que va un hash con sal (la APP_KEY), no la dirección.
        $this->auditoria->registrar('pii.acceso_api', [
            'consumidor' => $consumidor,
            'ip_hash' => VerificarTokenDeServicio::hashDeIp((string) $request->ip()),
        ]);

        try {
            $id = isset($datos['boveda_id']) ? new BovedaId($datos['boveda_id']) : null;
            $texto = $this->anonimizador->restaurar($datos['texto'], $id, $consumidor);
        } catch (BovedaAjena) {
            // Mismo 403 que "sin permiso": no hay que distinguirle a quien
            // pregunta si el id existe pero es de otro, o si directamente no
            // tiene permiso de restaurar.
            return response()->json(['error' => 'sin permiso para restaurar'], 403);
        }

        return response()->json(['texto' => $texto]);
    }

    /**
     * Sonda del servicio.
     *
     * Toca la bóveda de verdad. Antes solo respondía 200 sin mirar el store: con
     * Redis apagado decía «sano» mientras la única operación que importa
     * -guardar y recuperar- iba a fallar. Una sonda que da verde en esa
     * situación es peor que no tener sonda, porque Uptime Kuma no avisa y nadie
     * mira.
     *
     * Y ya no publica `tokens_cargados`: cuántos sistemas consumen esta API es
     * información de negocio, igual que las métricas, que sí están tras token.
     */
    public function health(): JsonResponse
    {
        try {
            $store = Cache::store((string) config('anonimizacion.store_boveda'));

            // Valor distinto en cada sonda: si se leyera un valor fijo, una
            // clave que quedó de la corrida anterior daría «ok» aunque la
            // escritura de ahora no haya llegado a ningún lado.
            //
            // Y va como TEXTO, no como número: el store de Redis devuelve los
            // numéricos tal como los guardó -o sea, como string-, así que
            // comparar contra un int daba «caida» con Redis sano. En el store de
            // array volvía como int y el test pasaba: lo encontró
            // `tools/pest-redis.sh`, que existe exactamente para esto.
            $testigo = bin2hex(random_bytes(8));
            $store->put('anon:health', $testigo, 5);
            $boveda = $store->get('anon:health') === $testigo ? 'ok' : 'caida';
        } catch (\Throwable $e) {
            // El motivo NO va en la respuesta: puede traer el host y el puerto
            // del store. Va al log, que ya es de quien opera el servicio.
            $this->auditoria->registrar('anonimizacion.health_boveda_caida', [
                'motivo' => $e->getMessage(),
            ]);
            $boveda = 'caida';
        }

        return response()->json([
            'servicio' => 'anonimizacion',
            'version' => self::VERSION,
            'segundos_en_pie' => (int) (microtime(true) - (self::$inicio ?? microtime(true))),
            'boveda' => $boveda,
        ], $boveda === 'ok' ? 200 : 503);
    }

    public function metrics(): Response
    {
        return response($this->metricas->render(), 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
        ]);
    }

    private function consumidorDe(Request $request): string
    {
        $consumidor = $request->attributes->get('consumidor');

        return is_string($consumidor) ? $consumidor : 'desconocido';
    }
}
