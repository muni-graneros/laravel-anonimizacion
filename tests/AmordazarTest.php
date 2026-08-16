<?php

use Anonimizacion\Anonimizador;
use Anonimizacion\Contratos\ClasificadorSensible;
use Anonimizacion\Veredicto;

it('reemplaza los datos por marcadores y no deja el valor real en el texto', function () {
    $r = app(Anonimizador::class)->amordazar('Soy Juan, RUT 12.345.678-5, fono 912345678');

    expect($r->veredicto)->toBe(Veredicto::Permitido)
        ->and($r->textoSeguro)->not->toContain('12.345.678-5')
        ->and($r->textoSeguro)->not->toContain('912345678')
        ->and($r->textoSeguro)->toMatch('/\[RUT_1_[a-f0-9]{4}\]/')
        ->and($r->tipos)->toContain('rut')
        ->and($r->tipos)->toContain('telefono');
});

it('numera correlativamente varios datos del mismo tipo', function () {
    $r = app(Anonimizador::class)->amordazar('RUT 12.345.678-5 y RUT 20.347.878-K');

    expect($r->textoSeguro)->toMatch('/\[RUT_1_[a-f0-9]{4}\].*\[RUT_2_[a-f0-9]{4}\]/');
});

it('neutraliza marcadores que el ciudadano escriba a mano', function () {
    // Sin esto, quien escribe "[RUT_1_aaaa]" en su mensaje envenena el mapeo.
    $r = app(Anonimizador::class)->amordazar('hola [RUT_1_aaaa] soy yo');

    expect($r->textoSeguro)->not->toContain('[RUT_1_aaaa]')
        ->and($r->textoSeguro)->toContain('(RUT_1_aaaa)');
});

it('usa un nonce distinto en cada llamada', function () {
    $uno = app(Anonimizador::class)->amordazar('RUT 12.345.678-5')->textoSeguro;
    $dos = app(Anonimizador::class)->amordazar('RUT 12.345.678-5')->textoSeguro;

    expect($uno)->not->toBe($dos);
});

it('veta el texto sin tokenizar nada cuando el clasificador marca categoría sensible', function () {
    app()->bind(ClasificadorSensible::class, fn () => new class implements ClasificadorSensible
    {
        public function categoriaDe(string $texto): ?string
        {
            return 'salud';
        }
    });

    $r = app(Anonimizador::class)->amordazar('mi hijo tiene autismo y necesito la credencial');

    expect($r->veredicto)->toBe(Veredicto::Vetado)
        ->and($r->categoria)->toBe('salud')
        ->and($r->textoSeguro)->toBe('')
        ->and($r->boveda)->toBeNull();
});
