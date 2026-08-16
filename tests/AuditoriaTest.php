<?php

use Anonimizacion\Anonimizador;
use Anonimizacion\Contratos\ClasificadorSensible;
use Anonimizacion\Contratos\RegistroDeAuditoria;

beforeEach(function () {
    $this->registro = new class implements RegistroDeAuditoria
    {
        /** @var array<int, array{0: string, 1: array<string, mixed>}> */
        public array $eventos = [];

        public function registrar(string $evento, array $datos): void
        {
            $this->eventos[] = [$evento, $datos];
        }
    };

    app()->instance(RegistroDeAuditoria::class, $this->registro);
});

it('registra el tipo y la cantidad, NUNCA el valor', function () {
    app(Anonimizador::class)->amordazar('RUT 12.345.678-5 fono 912345678');

    [$evento, $datos] = $this->registro->eventos[0];

    expect($evento)->toBe('pii.amordazado')
        ->and($datos['tipos'])->toContain('rut')
        ->and($datos['cantidad'])->toBe(2)
        ->and(json_encode($datos))->not->toContain('12.345.678-5');
});

it('registra el veto con su categoría', function () {
    app()->bind(ClasificadorSensible::class, fn () => new class implements ClasificadorSensible
    {
        public function categoriaDe(string $texto): ?string
        {
            return 'salud';
        }
    });

    app(Anonimizador::class)->amordazar('texto con dato de salud');

    expect($this->registro->eventos[0][0])->toBe('pii.vetado')
        ->and($this->registro->eventos[0][1]['categoria'])->toBe('salud');
});

it('registra la restauración sin incluir los valores devueltos', function () {
    $anon = app(Anonimizador::class);
    $r = $anon->amordazar('RUT 12.345.678-5');
    $anon->restaurar($r->textoSeguro, $r->boveda);

    $ultimo = end($this->registro->eventos);

    expect($ultimo[0])->toBe('pii.restaurado')
        ->and($ultimo[1]['marcadores'])->toBe(1)
        ->and(json_encode($ultimo[1]))->not->toContain('12.345.678-5');
});
