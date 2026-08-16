<?php

namespace Anonimizacion\Http;

use Anonimizacion\Excepciones\ApiSinTokens;
use Closure;
use Illuminate\Http\Request;

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

    public function handle(Request $request, Closure $next)
    {
        $consumidor = $this->consumidorDe((string) $request->header('X-Service-Token', ''));

        if ($consumidor === null) {
            return response()->json(['error' => 'token inválido'], 401);
        }

        $request->attributes->set('consumidor', $consumidor);

        return $next($request);
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
