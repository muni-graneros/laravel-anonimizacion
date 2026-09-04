<?php

namespace Anonimizacion\Detectores;

use Anonimizacion\Contratos\Detector;
use Anonimizacion\Hallazgo;

class DetectorTelefono implements Detector
{
    // Teléfono chileno, móvil y fijo.
    //
    // Los bordes son lookarounds de dígito y no \b: entre el "56" y el "9" de
    // "+56912345678" no hay frontera de palabra, así que \b nunca coincidiría.
    //
    // Todos son de nueve dígitos después del +56:
    // - Móvil: 9 + 8 dígitos.
    // - Fijo de Santiago: 2 + 8 dígitos.
    // - Fijo de región: código de dos dígitos (32-72) + 7 dígitos.
    //
    // Los fijos faltaban, y el público de una municipalidad incluye a mucha
    // gente que deja el de la casa; en Graneros eso es un `72 2 ...`, que en una
    // comuna chica identifica un domicilio con bastante precisión.
    //
    // Los separadores se admiten entre CUALQUIER par de dígitos y no en
    // posiciones fijas: la misma persona escribe `72 2 471000`, `722471000` y
    // `72-2471000`, y un patrón con separadores en sitios fijos deja pasar dos
    // de las tres.
    //
    // El móvil va PRIMERO en la alternancia: PCRE se queda con la primera rama
    // que coincide, y las tres empiezan igual tras el prefijo.
    private const PATRON = '/(?<!\d)(?:\+?56[\s-]?)?(?:9(?:[\s-]?\d){8}|2(?:[\s-]?\d){8}|[3-7]\d(?:[\s-]?\d){7})(?!\d)/u';

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
