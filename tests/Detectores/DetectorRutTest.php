<?php

use Anonimizacion\Detectores\DetectorRut;

it('encuentra un RUT con puntos y guión', function () {
    $hallazgos = (new DetectorRut)->detectar('soy Juan, RUT 12.345.678-5 y vivo acá');

    expect($hallazgos)->toHaveCount(1)
        ->and($hallazgos[0]->tipo)->toBe('rut')
        ->and($hallazgos[0]->valor)->toBe('12.345.678-5');
});

it('encuentra un RUT sin puntos', function () {
    expect((new DetectorRut)->detectar('mi rut es 12345678-5'))->toHaveCount(1);
});

it('acepta el dígito verificador K en cualquier caja', function () {
    expect((new DetectorRut)->detectar('rut 20.347.878-k'))->toHaveCount(1)
        ->and((new DetectorRut)->detectar('rut 20.347.878-K'))->toHaveCount(1);
});

it('NO marca un RUT cuyo dígito verificador no valida', function () {
    // Sin esta guarda el detector tokeniza cualquier número con forma de RUT
    // y llena de falsos positivos los mensajes con montos o folios.
    expect((new DetectorRut)->detectar('el monto fue 12.345.678-9'))->toBeEmpty();
});

it('informa la posición y el largo exactos para poder reemplazar', function () {
    $texto = 'RUT 12.345.678-5 fin';
    $h = (new DetectorRut)->detectar($texto)[0];

    expect(substr($texto, $h->inicio, $h->largo))->toBe('12.345.678-5');
});

it('encuentra un RUT sin guión, escrito corrido', function () {
    // El guión no es obligatorio en la vida real: la gente lo tipea corrido.
    $hallazgos = (new DetectorRut)->detectar('mi rut es 123456785 gracias');

    expect($hallazgos)->toHaveCount(1)
        ->and($hallazgos[0]->valor)->toBe('123456785');
});

it('encuentra un RUT sin guión pero con puntos', function () {
    expect((new DetectorRut)->detectar('mi rut es 12.345.6785'))->toHaveCount(1);
});

it('NO marca un número de 9 dígitos corrido cuyo dígito verificador no valida', function () {
    // Sin la validación de DV, cualquier número de 9 dígitos (un teléfono con
    // el 56 pegado, un monto) se marcaría como RUT.
    expect((new DetectorRut)->detectar('pagué 123456789 pesos'))->toBeEmpty();
});
