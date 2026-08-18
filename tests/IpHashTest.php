<?php

use Anonimizacion\Contratos\RegistroDeAuditoria;

it('la restauración deja rastro del consumidor y de un hash de IP, nunca la IP', function () {
    // La IP de un ciudadano es dato personal: se guarda un hash con sal para
    // poder correlacionar accesos sin almacenar la dirección.
    $registro = new class implements RegistroDeAuditoria
    {
        /** @var array<int, array{0: string, 1: array<string, mixed>}> */
        public array $eventos = [];

        public function registrar(string $evento, array $datos): void
        {
            $this->eventos[] = [$evento, $datos];
        }
    };

    app()->instance(RegistroDeAuditoria::class, $registro);

    $amordazado = $this->withHeader('X-Service-Token', 'tok-lic')
        ->postJson('/anonimizacion/amordazar', ['texto' => 'RUT 12.345.678-5'])->json();

    $this->withHeader('X-Service-Token', 'tok-lic')
        ->postJson('/anonimizacion/restaurar', [
            'texto' => $amordazado['texto_seguro'],
            'boveda_id' => $amordazado['boveda_id'],
        ])->assertOk();

    $restaurado = collect($registro->eventos)->last(fn ($e) => $e[0] === 'pii.acceso_api');

    expect($restaurado)->not->toBeNull()
        ->and($restaurado[1]['consumidor'])->toBe('licencias')
        ->and($restaurado[1]['ip_hash'])->toBeString()
        ->and(strlen($restaurado[1]['ip_hash']))->toBe(16)
        ->and(json_encode($restaurado[1]))->not->toContain('127.0.0.1');
});
