<?php

use Anonimizacion\Boveda;
use Anonimizacion\BovedaEnCache;
use Anonimizacion\BovedaId;
use Anonimizacion\Excepciones\BovedaExpirada;
use Illuminate\Support\Facades\Cache;

it('guarda y recupera el mapeo', function () {
    $boveda = new Boveda;
    $boveda->agregar('[RUT_1_a3f9]', '12.345.678-5');

    $id = BovedaId::nueva();
    app(BovedaEnCache::class)->guardar($id, $boveda);

    expect(app(BovedaEnCache::class)->recuperar($id)->resolver('[RUT_1_a3f9]'))
        ->toBe('12.345.678-5');
});

it('guarda el contenido CIFRADO: el valor real nunca queda legible en el store', function () {
    // Redis no cifra en reposo y hace snapshots a disco. Sin esto, los RUT de
    // los vecinos terminan en un .rdb y en todo backup del volumen.
    $boveda = new Boveda;
    $boveda->agregar('[RUT_1_a3f9]', '12.345.678-5');

    $id = BovedaId::nueva();
    app(BovedaEnCache::class)->guardar($id, $boveda);

    $crudo = Cache::store('array')->get('anon:'.$id->valor);

    expect($crudo)->toBeString()->not->toContain('12.345.678-5');
});

it('falla explícitamente cuando la bóveda ya expiró', function () {
    app(BovedaEnCache::class)->recuperar(BovedaId::nueva());
})->throws(BovedaExpirada::class);

it('no filtra los valores al imprimirla ni al serializarla', function () {
    // Un dd() o un stack trace no pueden mostrar datos de un vecino.
    $boveda = new Boveda;
    $boveda->agregar('[RUT_1_a3f9]', '12.345.678-5');

    expect((string) $boveda)->not->toContain('12.345.678-5')
        ->and(json_encode($boveda))->not->toContain('12.345.678-5')
        ->and(print_r($boveda->__debugInfo(), true))->not->toContain('12.345.678-5');
});
