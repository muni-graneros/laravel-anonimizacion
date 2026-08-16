<?php

it('registra la configuración del paquete', function () {
    expect(config('anonimizacion.ttl_boveda'))->toBe(900)
        ->and(config('anonimizacion.api.habilitada'))->toBeFalse()
        ->and(config('anonimizacion.api.tokens'))->toBe('');
});

it('trae Redis como store por defecto de la bóveda', function () {
    // El TestCase pisa el store a 'array' para no depender de un Redis vivo en
    // la suite, así que el default real se comprueba sobre el archivo mismo.
    $defaults = require __DIR__.'/../config/anonimizacion.php';

    expect($defaults['store_boveda'])->toBe('redis');
});
