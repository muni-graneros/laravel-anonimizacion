<?php

namespace Anonimizacion\Http;

use Anonimizacion\Contratos\RegistroDeAuditoria;
use Anonimizacion\Excepciones\ApiSinTokens;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class VerificarTokenDeServicio
{
    /** @var array<string, string> token => consumidor */
    private array $tokens;

    public function __construct()
    {
        $this->tokens = self::cargarTokens();

        // Fail-closed. El micro de OCR del ecosistema queda ABIERTO si falta la
        // variable; acá no puede pasar: un .env mal copiado no debe dejar
        // expuesto un endpoint que devuelve datos de vecinos.
        if ($this->tokens === []) {
            throw new ApiSinTokens;
        }
    }

    /** @return array<string, string> */
    public static function cargarTokens(): array
    {
        $tokens = [];

        foreach (explode(',', (string) config('anonimizacion.api.tokens')) as $par) {
            $par = trim($par);

            if (str_contains($par, ':')) {
                [$nombre, $token] = explode(':', $par, 2);

                if (trim($token) !== '') {
                    $tokens[trim($token)] = trim($nombre);
                }
            }
        }

        return $tokens;
    }

    /**
     * Cuántos fallos de token se toleran por minuto y origen.
     *
     * Es bajo a propósito: un consumidor legítimo no falla NUNCA el token —lo
     * lee de su `.env` y no cambia entre peticiones—, así que diez fallos en un
     * minuto solo pasan cuando alguien está probando o cuando una rotación quedó
     * a medias. Las dos cosas hay que verlas.
     */
    private const FALLOS_POR_MINUTO = 10;

    public function handle(Request $request, Closure $next): Response
    {
        // header() puede devolver array, string o null: se acepta solo el caso
        // string, cualquier otro se trata como token ausente.
        $recibido = $request->header('X-Service-Token');

        $consumidor = $this->consumidorDe(is_string($recibido) ? $recibido : '');

        // El límite de fallos va POR ORIGEN y es DISTINTO del general.
        //
        // `throttle:anonimizacion` corre después de este middleware, así que un
        // 401 nunca lo alcanzaba: se podía probar tokens a la velocidad que
        // diera la red contra el único endpoint que devuelve datos reales. Y no
        // podía contar por consumidor, que es como cuenta el general, porque en
        // un fallo de token no hay consumidor todavía.
        $clave = 'anonimizacion:401:'.hash('sha256', (string) $request->ip());

        if ($consumidor === null) {
            if (RateLimiter::tooManyAttempts($clave, self::FALLOS_POR_MINUTO)) {
                return response()->json(
                    ['error' => 'demasiados intentos'],
                    429,
                    ['Retry-After' => (string) RateLimiter::availableIn($clave)]
                );
            }

            RateLimiter::hit($clave, 60);

            // El 401 era silencioso: el log quedaba vacío, así que una rotación
            // mal hecha -un consumidor golpeando con el token viejo- no se veía
            // por ningún lado. Se registra el intento, nunca el token ni la IP:
            // la dirección es dato personal y va con el mismo hash con sal que
            // usa el resto del paquete.
            $this->auditoria()->registrar('pii.token_invalido', [
                'ip_hash' => self::hashDeIp((string) $request->ip()),
                'ruta' => $request->path(),
            ]);

            return response()->json(['error' => 'token inválido'], 401);
        }

        // Un acierto limpia el contador: quien arregló su token no puede quedar
        // penalizado por los intentos anteriores. Y, sobre todo, esto impide que
        // el limitador de fallos sirva para dejar fuera a los demás: todos los
        // sistemas del ecosistema salen por la misma IP interna.
        RateLimiter::clear($clave);

        $request->attributes->set('consumidor', $consumidor);

        return $next($request);
    }

    /**
     * Hash con sal de la IP: permite correlacionar intentos del mismo origen sin
     * almacenar la dirección, que es dato personal. La sal es la APP_KEY, así
     * que el hash no se puede cruzar entre instalaciones distintas.
     */
    public static function hashDeIp(string $ip): string
    {
        return substr(hash_hmac('sha256', $ip, (string) config('app.key')), 0, 16);
    }

    /**
     * Se resuelve al vuelo y no por constructor: este middleware se instancia
     * antes de que el contenedor tenga por qué haber resuelto la auditoría, y
     * pedirla en el constructor obligaría a construirla también en las
     * peticiones que ni siquiera llegan a fallar.
     */
    private function auditoria(): RegistroDeAuditoria
    {
        return app(RegistroDeAuditoria::class);
    }

    /**
     * Recorre TODOS los tokens sin cortar en el primer acierto: salir antes
     * filtraría por tiempo cuál token existe.
     */
    private function consumidorDe(string $recibido): ?string
    {
        $encontrado = null;

        foreach ($this->tokens as $token => $nombre) {
            if (hash_equals($token, $recibido)) {
                $encontrado = $nombre;
            }
        }

        return $encontrado;
    }
}
