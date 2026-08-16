<?php

namespace Anonimizacion\Excepciones;

use RuntimeException;

class BovedaExpirada extends RuntimeException
{
    public static function paraId(string $id): self
    {
        // El id no es dato personal; el contenido sí, y no se incluye.
        return new self("La bóveda {$id} expiró o no existe.");
    }
}
