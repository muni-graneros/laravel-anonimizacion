<?php

namespace Anonimizacion\Detectores;

use Anonimizacion\Contratos\Detector;
use Anonimizacion\Hallazgo;

class DetectorRut implements Detector
{
    // El guión es opcional: en la práctica la gente lo tipea corrido tanto en
    // el formato con puntos ("12.345.6785") como en el plano ("123456785").
    // El único filtro contra falsos positivos sigue siendo el dígito
    // verificador, no el separador.
    private const PATRON = '/\b(\d{1,2}(?:\.\d{3}){2}|\d{7,8})-?([\dkK])\b/u';

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
