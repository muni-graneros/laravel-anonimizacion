<?php

use Anonimizacion\Anonimizador;
use Anonimizacion\Contratos\ClasificadorSensible;
use Anonimizacion\Contratos\ProveedorExterno;
use Anonimizacion\ProveedorAnonimizado;
use Anonimizacion\Veredicto;

it('el proveedor jamás recibe el dato real y el ciudadano lo recibe de vuelta', function () {
    $espia = new class implements ProveedorExterno
    {
        public string $recibido = '';

        public function preguntar(string $texto): string
        {
            $this->recibido = $texto;

            // El proveedor responde con los mismos marcadores que recibió.
            return "Hola {$texto}";
        }
    };

    $decorado = new ProveedorAnonimizado($espia, app(Anonimizador::class));
    $respuesta = $decorado->preguntar('RUT 12.345.678-5');

    expect($espia->recibido)->not->toContain('12.345.678-5')
        ->and($respuesta)->toContain('12.345.678-5');
});

it('no llama al proveedor cuando el texto está vetado', function () {
    app()->bind(ClasificadorSensible::class, fn () => new class implements ClasificadorSensible
    {
        public function categoriaDe(string $texto): ?string
        {
            return 'salud';
        }
    });

    $espia = new class implements ProveedorExterno
    {
        public bool $llamado = false;

        public function preguntar(string $texto): string
        {
            $this->llamado = true;

            return '';
        }
    };

    $decorado = new ProveedorAnonimizado($espia, app(Anonimizador::class));

    expect($decorado->veredictoDe('dato de salud'))->toBe(Veredicto::Vetado)
        ->and($decorado->preguntar('dato de salud'))->toBe('')
        ->and($espia->llamado)->toBeFalse();
});
