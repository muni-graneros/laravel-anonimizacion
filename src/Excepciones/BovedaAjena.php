<?php

namespace Anonimizacion\Excepciones;

use RuntimeException;

/**
 * La bóveda existe y no expiró, pero pertenece a OTRO consumidor. Tener
 * permiso de /restaurar no alcanza: cada sistema solo puede recuperar lo que
 * él mismo amordazó, nunca la bóveda de otro aunque conozca el id.
 */
class BovedaAjena extends RuntimeException
{
    public static function paraId(string $id): self
    {
        // El id no es dato personal; el dueño real y el contenido, sí, y no
        // se incluyen en el mensaje.
        return new self("La bóveda {$id} pertenece a otro consumidor.");
    }
}
