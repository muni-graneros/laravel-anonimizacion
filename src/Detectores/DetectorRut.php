<?php

namespace Anonimizacion\Detectores;

use Anonimizacion\Contratos\Detector;
use Anonimizacion\Hallazgo;

class DetectorRut implements Detector
{
    private const PATRON = '/\b(\d{1,2}(?:\.\d{3}){2}|\d{7,8})-([\dkK])\b/u';

    public function tipo(): string
    {
        return 'rut';
    }

    public function detectar(string $texto): array
    {
        preg_match_all(self::PATRON, $texto, $coincidencias, PREG_OFFSET_CAPTURE);

        $hallazgos = [];

        foreach ($coincidencias[0] as $i => [$valor, $inicio]) {
            $cuerpo = str_replace('.', '', $coincidencias[1][$i][0]);
            $dv = strtoupper($coincidencias[2][$i][0]);

            // Un número con forma de RUT pero con DV inválido es un monto o un
            // folio, no una persona. Tokenizarlo sería un falso positivo.
            if ($this->digitoVerificador($cuerpo) !== $dv) {
                continue;
            }

            $hallazgos[] = new Hallazgo($this->tipo(), $inicio, strlen($valor), $valor);
        }

        return $hallazgos;
    }

    private function digitoVerificador(string $cuerpo): string
    {
        $suma = 0;
        $multiplicador = 2;

        foreach (array_reverse(str_split($cuerpo)) as $digito) {
            $suma += (int) $digito * $multiplicador;
            $multiplicador = $multiplicador === 7 ? 2 : $multiplicador + 1;
        }

        $resto = 11 - ($suma % 11);

        return match ($resto) {
            11 => '0',
            10 => 'K',
            default => (string) $resto,
        };
    }
}
