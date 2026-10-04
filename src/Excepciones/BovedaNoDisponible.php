<?php

namespace Anonimizacion\Excepciones;

use RuntimeException;

/**
 * El store de la bóveda falló (Redis caído, credenciales malas, disco lleno).
 *
 * Existe para CORTAR LA CADENA DE EXCEPCIONES, no solo para nombrar el error.
 *
 * El handler de Laravel registra `getTraceAsString()`, y el trace de PHP incluye
 * los argumentos de cada llamada salvo que el `php.ini` traiga
 * `zend.exception_ignore_args=1` (lo trae `php.ini-production`, no el de
 * desarrollo). El primer argumento de `Anonimizador::amordazar()` es el texto
 * completo del ciudadano: si la excepción del store se propaga tal cual, el día
 * que Redis se cae el mensaje entero con el RUT sin tapar se escribe en
 * `laravel.log`. Que es exactamente lo que este paquete existe para evitar.
 *
 * Este paquete no puede dar por hecho el `php.ini` del contenedor de otro, así
 * que se lanza desde un punto donde el texto NO es argumento de nada y NO se
 * encadena con `previous`: encadenarla volvería a traer el trace de abajo.
 * El motivo se guarda como texto, que es lo único que hace falta para depurar.
 */
class BovedaNoDisponible extends RuntimeException
{
    public function __construct(public readonly string $motivo)
    {
        parent::__construct(
            'No se pudo operar la bóveda de anonimización. No se devuelve texto: '.
            'sin bóveda no hay forma de restaurar, y seguir sería mandar datos '.
            "sin poder recuperarlos. Motivo: {$motivo}"
        );
    }
}
