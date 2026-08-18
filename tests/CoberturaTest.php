<?php

use Anonimizacion\Anonimizador;
use Anonimizacion\Veredicto;

/**
 * Mide el motor contra el corpus y publica la línea base.
 *
 * No es una prueba de aprobado/reprobado sobre nombres y direcciones: el ciclo
 * 1A no los cubre y el corpus los anota a propósito para que el hueco se vea
 * medido, no supuesto. Lo que SÍ falla el test es una regresión en lo que ya
 * funciona (los tipos estructurados) o un falso positivo nuevo.
 */
it('mide la cobertura real del motor y no regresiona en lo que ya cubre', function () {
    $casos = require __DIR__.'/Corpus/mensajes.php';
    $anon = app(Anonimizador::class);

    $porTipo = [];
    $falsosPositivos = 0;

    foreach ($casos as [$mensaje, $esperados, $valores]) {
        $r = $anon->amordazar($mensaje);
        $detectados = $r->tipos;

        // Los casos de categoría sensible no se evalúan por tipo: el ciclo 1A
        // no tiene clasificador y su veredicto siempre es "permitido".
        if (in_array('SENSIBLE', $esperados, true)) {
            $porTipo['SENSIBLE']['esperados'] = ($porTipo['SENSIBLE']['esperados'] ?? 0) + 1;
            $porTipo['SENSIBLE']['aciertos'] = ($porTipo['SENSIBLE']['aciertos'] ?? 0)
                + ($r->veredicto === Veredicto::Vetado ? 1 : 0);

            continue;
        }

        foreach ($esperados as $tipo) {
            $porTipo[$tipo]['esperados'] = ($porTipo[$tipo]['esperados'] ?? 0) + 1;
            $porTipo[$tipo]['aciertos'] = ($porTipo[$tipo]['aciertos'] ?? 0)
                + (in_array($tipo, $detectados, true) ? 1 : 0);
        }

        // Detectar algo donde no había nada que detectar.
        if ($esperados === [] && $detectados !== []) {
            $falsosPositivos++;
        }

        // Todo valor anotado de un tipo que el motor dice cubrir debe salir del texto.
        foreach ($valores as $valor) {
            $tipoDelValor = $esperados[array_search($valor, $valores, true)] ?? null;

            if (in_array($tipoDelValor, ['rut', 'telefono', 'email', 'folio'], true)) {
                expect($r->textoSeguro)->not->toContain($valor);
            }
        }
    }

    // Se publica la medición para que quede en la salida del test y se pueda
    // comparar contra cualquier herramienta que se evalúe en el ciclo 1B.
    fwrite(STDERR, "\n  Cobertura del motor contra el corpus (".count($casos)." casos):\n");

    foreach ($porTipo as $tipo => $d) {
        $recall = $d['esperados'] > 0 ? ($d['aciertos'] / $d['esperados']) * 100 : 0;
        fwrite(STDERR, sprintf(
            "    %-10s %d/%d detectados  (%.0f%%)\n",
            $tipo, $d['aciertos'], $d['esperados'], $recall
        ));
    }

    fwrite(STDERR, "    falsos positivos: {$falsosPositivos}\n\n");

    // Lo que el ciclo 1A promete: los tipos estructurados, sin falsos positivos.
    foreach (['rut', 'telefono', 'email'] as $tipo) {
        expect($porTipo[$tipo]['aciertos'])->toBe(
            $porTipo[$tipo]['esperados'],
            "Regresión en la detección de {$tipo}"
        );
    }

    expect($falsosPositivos)->toBe(0, 'El motor marcó datos donde no los había');
});
