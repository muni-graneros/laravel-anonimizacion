<?php

namespace Anonimizacion\Http;

use Anonimizacion\Anonimizador;
use Anonimizacion\BovedaId;
use Anonimizacion\Contratos\RegistroDeAuditoria;
use Anonimizacion\Excepciones\BovedaAjena;
use Anonimizacion\Metricas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            'boveda_id' => ['required', 'string', 'regex:/^[0-9a-f]{32}$/'],
        ]);

        $consumidor = $this->consumidorDe($request);
        $this->metricas->contar($consumidor, 'restaurar');

        // Evento propio de la API, distinto de 'pii.restaurado' que emite el
        // motor: registra QUIÉN des-anonimizó y desde dónde. La IP es dato
        // personal, así que va un hash con sal (la APP_KEY), no la dirección.
        $this->auditoria->registrar('pii.acceso_api', [
            'consumidor' => $consumidor,
            'ip_hash' => $this->hashDeIp((string) $request->ip()),
        ]);

        try {
            $texto = $this->anonimizador->restaurar($datos['texto'], new BovedaId($datos['boveda_id']), $consumidor);
        } catch (BovedaAjena) {
            // Mismo 403 que "sin permiso": no hay que distinguirle a quien
            // pregunta si el id existe pero es de otro, o si directamente no
            // tiene permiso de restaurar.
            return response()->json(['error' => 'sin permiso para restaurar'], 403);
        }

        return response()->json(['texto' => $texto]);
    }

    public function health(): JsonResponse
    {
        return response()->json([
            'servicio' => 'anonimizacion',
            'version' => self::VERSION,
            'segundos_en_pie' => (int) (microtime(true) - (self::$inicio ?? microtime(true))),
            'tokens_cargados' => count(VerificarTokenDeServicio::cargarTokens()),
        ]);
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

    /**
     * Hash con sal de la IP: permite correlacionar accesos del mismo origen sin
     * almacenar la dirección, que es dato personal. La sal es la APP_KEY, así
     * que el hash no se puede cruzar entre instalaciones distintas.
     */
    private function hashDeIp(string $ip): string
    {
        return substr(hash_hmac('sha256', $ip, (string) config('app.key')), 0, 16);
    }
}
