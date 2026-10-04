<?php

use Anonimizacion\Anonimizador;
use Anonimizacion\Contratos\ClasificadorSensible;
use Anonimizacion\Excepciones\ClasificadorAusente;
use Anonimizacion\SinClasificador;
use Anonimizacion\Veredicto;

/**
 * El paquete no puede dejar creer que veta contenido sensible cuando no lo hace.
 *
 * El enlace por defecto de `ClasificadorSensible` es `SinClasificador`, que
 * devuelve null siempre: ningún texto se veta nunca. Eso es correcto para un
 * sistema que solo necesita tapar el RUT, el teléfono y el correo —que es lo que
 * el paquete sí hace bien—, y está escrito en el README.
 *
 * El problema es el sistema que SÍ maneja datos del artículo 2 letra g de la Ley
 * 21.719: discapacidad manda «credencial de discapacidad de mi hijo que tiene
 * autismo» y sale el diagnóstico entero con solo el RUT tapado. Nada se lo
 * advierte: instala el paquete, lee «fail-closed» en el README, y cree que está
 * cubierto.
 *
 * Ahora un sistema declara si maneja datos sensibles. Si lo declara y no hay
 * clasificador real enlazado, el paquete se niega a funcionar en vez de dar una
 * falsa sensación de protección.
 */
it('sin declarar datos sensibles, el enlace por defecto sigue sirviendo', function () {
    // El caso de licencias o feria: RUT, teléfono y correo. No hace falta más.
    config()->set('anonimizacion.datos_sensibles', false);

    expect(app(Anonimizador::class)->amordazar('mi rut es 12.345.678-5')->veredicto)
        ->toBe(Veredicto::Permitido);
});

it('declarando datos sensibles sin clasificador real, se niega a arrancar', function () {
    config()->set('anonimizacion.datos_sensibles', true);

    expect(fn () => app(Anonimizador::class))->toThrow(ClasificadorAusente::class);
});

it('el mensaje dice qué hacer, no solo que falló', function () {
    config()->set('anonimizacion.datos_sensibles', true);

    try {
        app(Anonimizador::class);
        $this->fail('no lanzó');
    } catch (ClasificadorAusente $e) {
        expect($e->getMessage())
            ->toContain('ClasificadorSensible')
            ->toContain('datos_sensibles');
    }
});

it('con un clasificador real enlazado, arranca y veta', function () {
    config()->set('anonimizacion.datos_sensibles', true);

    app()->bind(ClasificadorSensible::class, fn () => new class implements ClasificadorSensible
    {
        public function categoriaDe(string $texto): ?string
        {
            return str_contains($texto, 'autismo') ? 'salud' : null;
        }
    });

    $resultado = app(Anonimizador::class)->amordazar('mi hijo tiene autismo');

    expect($resultado->veredicto)->toBe(Veredicto::Vetado)
        ->and($resultado->categoria)->toBe('salud');
});

it('SinClasificador se reconoce como «no hay clasificador», no como uno que no encuentra nada', function () {
    // La diferencia importa: un clasificador real que devuelve null está
    // diciendo «revisé y no hay»; SinClasificador está diciendo «no revisé».
    // Sin distinguirlos, la comprobación de arriba no puede existir.
    expect(new SinClasificador)->toBeInstanceOf(ClasificadorSensible::class)
        ->and((new SinClasificador)->categoriaDe('lo que sea'))->toBeNull();
});
