<?php

it('registra la configuración del paquete', function () {
    expect(config('anonimizacion.ttl_boveda'))->toBe(900);
});

it('trae valores por defecto seguros: Redis para la bóveda y la API apagada', function () {
    // El TestCase pisa el store y habilita la API para poder ejercitarla, así
    // que los defaults reales se comprueban sobre el archivo mismo. Importan:
    // la API no se expone salvo que alguien la encienda a propósito, y sin
    // tokens configurados el middleware se niega a construirse.
    $defaults = require __DIR__.'/../config/anonimizacion.php';

    expect($defaults['store_boveda'])->toBe('redis')
        ->and($defaults['api']['habilitada'])->toBeFalse()
        ->and($defaults['api']['tokens'])->toBe('')
        ->and($defaults['api']['pueden_restaurar'])->toBe('');
});
