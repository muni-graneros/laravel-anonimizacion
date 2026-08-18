<?php

namespace Anonimizacion\Contratos;

interface ClasificadorSensible
{
    /** Categoría sensible detectada ('salud', 'origen'...), o null si no hay. */
    public function categoriaDe(string $texto): ?string;
}
