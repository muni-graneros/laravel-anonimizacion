<?php

namespace Anonimizacion\Http;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Esta API responde JSON, pida el cliente lo que pida.
 *
 * Las rutas del paquete se registran con `loadRoutesFrom`, sin el grupo `api` de
 * la aplicación. Sin él, `$request->validate()` falla a la manera web:
 * redirección 302 a la raíz con los errores en sesión. Y los consumidores para
 * los que existe esta API son justamente los que no son PHP —n8n, curl, los
 * micros de Python—, que mandan `Accept: * / *` por defecto: recibían un 302 a
 * `http://localhost` en vez de un 422 con el motivo, y en una ruta sin sesión
 * eso puede terminar en 500 al intentar `withInput()`.
 *
 * Se fija la cabecera en la petición entrante, que es lo que Laravel mira en
 * `expectsJson()`. No se usa el grupo `api` de la aplicación a propósito: un
 * paquete no puede dar por hecho qué middleware trae ese grupo en cada
 * instalación —throttle ajeno, Sanctum, lo que sea—, y el límite propio ya está
 * declarado en las rutas.
 */
class ForzarJson
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
