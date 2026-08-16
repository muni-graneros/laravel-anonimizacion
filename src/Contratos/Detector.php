<?php

namespace Anonimizacion\Contratos;

use Anonimizacion\Hallazgo;

interface Detector
{
    /** Etiqueta del tipo de dato: 'rut', 'telefono', 'email'... */
    public function tipo(): string;

    /** @return array<int, Hallazgo> ordenados por posición de inicio */
    public function detectar(string $texto): array;
}
