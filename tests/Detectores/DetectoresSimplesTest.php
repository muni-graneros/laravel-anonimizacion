<?php

use Anonimizacion\Detectores\DetectorEmail;
use Anonimizacion\Detectores\DetectorFolio;
use Anonimizacion\Detectores\DetectorTelefono;

it('encuentra móviles chilenos con y sin código de país', function () {
    expect((new DetectorTelefono)->detectar('llámame al +56912345678'))->toHaveCount(1)
        ->and((new DetectorTelefono)->detectar('mi fono 912345678'))->toHaveCount(1)
        ->and((new DetectorTelefono)->detectar('el 9 1234 5678'))->toHaveCount(1);
});

it('no confunde un año ni un monto con un teléfono', function () {
    expect((new DetectorTelefono)->detectar('en 2026 pagué 45000'))->toBeEmpty();
});

it('encuentra correos', function () {
    $h = (new DetectorEmail)->detectar('escríbeme a juan.perez@granero.cl gracias');

    expect($h)->toHaveCount(1)->and($h[0]->valor)->toBe('juan.perez@granero.cl');
});

it('encuentra el folio con el patrón configurado por la instalación', function () {
    $h = (new DetectorFolio('/\bRC-\d{4}-\d{5}\b/u'))->detectar('mi seguimiento es RC-2026-00451');

    expect($h)->toHaveCount(1)
        ->and($h[0]->tipo)->toBe('folio')
        ->and($h[0]->valor)->toBe('RC-2026-00451');
});

it('sin patrón configurado no detecta folios', function () {
    expect((new DetectorFolio(''))->detectar('RC-2026-00451'))->toBeEmpty();
});
