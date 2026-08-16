<?php

use Anonimizacion\Anonimizador;
use Illuminate\Support\Facades\Log;

it('no escribe ningún valor real en el log durante un flujo completo', function () {
    $capturado = '';

    Log::listen(function ($mensaje) use (&$capturado) {
        $capturado .= $mensaje->message.json_encode($mensaje->context);
    });

    $anon = app(Anonimizador::class);
    $r = $anon->amordazar('Soy Juan, RUT 12.345.678-5, fono 912345678, juan@granero.cl');
    $anon->restaurar($r->textoSeguro, $r->boveda);

    expect($capturado)
        ->not->toContain('12.345.678-5')
        ->not->toContain('912345678')
        ->not->toContain('juan@granero.cl');
});

it('tampoco filtra valores por la API', function () {
    $capturado = '';

    Log::listen(function ($mensaje) use (&$capturado) {
        $capturado .= $mensaje->message.json_encode($mensaje->context);
    });

    $this->withHeader('X-Service-Token', 'tok-lic')
        ->postJson('/anonimizacion/amordazar', ['texto' => 'RUT 12.345.678-5'])
        ->assertOk();

    expect($capturado)->not->toContain('12.345.678-5');
});
