<?php

namespace Anonimizacion;

use Anonimizacion\Contratos\ClasificadorSensible;

/**
 * Implementación nula: nunca veta. Es el enlace por defecto del ciclo 1A; el
 * ciclo 1B la sustituye por el clasificador real sin que ningún consumidor
 * tenga que cambiar una línea.
 */
class SinClasificador implements ClasificadorSensible
{
    public function categoriaDe(string $texto): ?string
    {
        return null;
    }
}
