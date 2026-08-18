<?php

use Anonimizacion\Anonimizador;
use Anonimizacion\BovedaId;
use Anonimizacion\Excepciones\BovedaExpirada;

it('devuelve los valores reales al ciudadano', function () {
    $anon = app(Anonimizador::class);
    $r = $anon->amordazar('Soy Juan, RUT 12.345.678-5');

    // El marcador que generó el motor, tal cual quedó en el texto seguro.
    preg_match('/\[RUT_1_[a-f0-9]{4}\]/', $r->textoSeguro, $m);
    $marcador = $m[0];

    $respuesta = $anon->restaurar("Hola, tu RUT {$marcador} está registrado", $r->boveda);

    expect($respuesta)->toBe('Hola, tu RUT 12.345.678-5 está registrado');
});

it('ELIMINA un marcador que el modelo inventó en vez de dejarlo o adivinarlo', function () {
    // Un [RUT_9_...] alucinado que se dejara crudo confundiría al vecino, y
    // adivinarlo podría mapearlo a los datos de OTRA persona.
    $anon = app(Anonimizador::class);
    $r = $anon->amordazar('RUT 12.345.678-5');

    $respuesta = $anon->restaurar('Revisa [RUT_9_ffff] por favor', $r->boveda);

    expect($respuesta)->toBe('Revisa  por favor')
        ->and($respuesta)->not->toContain('RUT_9');
});

it('falla si la bóveda ya expiró', function () {
    app(Anonimizador::class)->restaurar('texto', BovedaId::nueva());
})->throws(BovedaExpirada::class);
