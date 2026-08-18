<?php

namespace Anonimizacion\Detectores;

use Anonimizacion\Contratos\Detector;
use Anonimizacion\Hallazgo;

class DetectorFolio implements Detector
{
    // El formato del folio lo define cada instalación, no el paquete.
    public function __construct(private readonly string $patron) {}

    public function tipo(): string
    {
        return 'folio';
    }

    public function detectar(string $texto): array
    {
        if ($this->patron === '') {
            return [];
        }

        preg_match_all($this->patron, $texto, $coincidencias, PREG_OFFSET_CAPTURE);

        return array_map(
            fn (array $c) => new Hallazgo($this->tipo(), $c[1], strlen($c[0]), $c[0]),
            $coincidencias[0]
        );
    }
}
