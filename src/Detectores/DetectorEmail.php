<?php

namespace Anonimizacion\Detectores;

use Anonimizacion\Contratos\Detector;
use Anonimizacion\Hallazgo;

class DetectorEmail implements Detector
{
    // Pragmático a propósito: el RFC completo acepta cosas que ningún vecino
    // escribe, y el patrón se vuelve imposible de auditar.
    private const PATRON = '/\b[\w.+-]+@[\w-]+(?:\.[\w-]+)+\b/u';

    public function tipo(): string
    {
        return 'email';
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
