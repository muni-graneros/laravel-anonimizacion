<?php

namespace Anonimizacion\Detectores;

use Anonimizacion\Contratos\Detector;
use Anonimizacion\Hallazgo;

class DetectorTelefono implements Detector
{
    // Móvil chileno: 9 + 8 dígitos, con separadores opcionales y +56 opcional.
    // Los bordes son lookarounds de dígito y no \b: entre el "56" y el "9" de
    // "+56912345678" no hay frontera de palabra, así que \b nunca coincidiría.
    private const PATRON = '/(?<!\d)(?:\+?56[\s-]?)?9[\s-]?\d{4}[\s-]?\d{4}(?!\d)/u';

    public function tipo(): string
    {
        return 'telefono';
    }

    public function detectar(string $texto): array
    {
        preg_match_all(self::PATRON, $texto, $coincidencias, PREG_OFFSET_CAPTURE);

        return array_map(
            fn (array $c) => new Hallazgo($this->tipo(), $c[1], strlen($c[0]), $c[0]),
            $coincidencias[0]
        );
    }
}
