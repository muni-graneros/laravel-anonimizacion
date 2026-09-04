<?php

use Anonimizacion\Detectores\DetectorTelefono;

/**
 * El teléfono fijo también identifica a una persona.
 *
 * El detector solo cubría el móvil (9 + 8 dígitos). El README lo acota así, pero
 * el público de una municipalidad incluye a mucha gente que deja el fijo de la
 * casa: en Graneros eso es un `72 2 ...`. Un fijo de red fija en una comuna
 * chica identifica un domicilio con bastante precisión.
 *
 * Los códigos de área en Chile son de un dígito para Santiago (2) y de dos para
 * el resto (32-72...), seguidos de 7 dígitos.
 */
function detectar(string $texto): array
{
    return array_map(
        fn ($h) => $h->valor,
        (new DetectorTelefono)->detectar($texto)
    );
}

it('detecta el fijo de la zona', function (string $texto) {
    expect(detectar($texto))->not->toBeEmpty("«{$texto}» pasó sin marcar");
})->with([
    'llámeme al 72 2 471000',
    'mi fono es +56722471000',
    'fijo 722471000',
    'el de la oficina: 56 72 2471000',
    'en Santiago es 2 2345 6789',
    '+56223456789',
]);

it('sigue detectando el móvil', function (string $texto) {
    expect(detectar($texto))->not->toBeEmpty("«{$texto}» pasó sin marcar");
})->with([
    'mi celular es 912345678',
    '+56912345678',
    '9 1234 5678',
    '+56 9 1234 5678',
]);

it('no marca cosas que no son teléfonos', function (string $texto) {
    expect(detectar($texto))->toBeEmpty("«{$texto}» se marcó como teléfono");
})->with([
    // Un año, un monto, un número de ley: nada de esto es un teléfono.
    'la ley 21.719 lo exige',
    'son 12345 pesos',
    'el año 2026',
    'documento 1234',
]);

it('el número completo entra en el hallazgo, no un trozo', function () {
    // Si el patrón cortara a la mitad, el texto quedaría con la mitad del
    // teléfono a la vista y el marcador al lado: peor que no detectarlo, porque
    // parece que se protegió.
    expect(detectar('el fono es 722471000 y nada más'))->toBe(['722471000']);
});
