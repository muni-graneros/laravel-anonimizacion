<?php

use Anonimizacion\Excepciones\ApiSinTokens;
use Anonimizacion\Http\VerificarTokenDeServicio;

it('rechaza una petición sin token', function () {
    $this->postJson('/anonimizacion/amordazar', ['texto' => 'RUT 12.345.678-5'])
        ->assertStatus(401);
});

it('rechaza un token que no está en la lista', function () {
    $this->withHeader('X-Service-Token', 'inventado')
        ->postJson('/anonimizacion/amordazar', ['texto' => 'hola'])
        ->assertStatus(401);
});

it('amordaza para un consumidor autorizado', function () {
    $r = $this->withHeader('X-Service-Token', 'tok-lic')
        ->postJson('/anonimizacion/amordazar', ['texto' => 'RUT 12.345.678-5'])
        ->assertOk();

    expect($r->json('texto_seguro'))->not->toContain('12.345.678-5')
        ->and($r->json('veredicto'))->toBe('permitido')
        ->and($r->json('boveda_id'))->toBeString()
        ->and($r->json('tipos'))->toContain('rut');
});

it('niega /restaurar a un consumidor sin ese permiso', function () {
    // Restaurar devuelve datos reales: es la operación peligrosa y va con su
    // propio permiso, no basta con tener un token válido.
    $this->withHeader('X-Service-Token', 'tok-disc')
        ->postJson('/anonimizacion/restaurar', ['texto' => 'hola', 'boveda_id' => 'x'])
        ->assertStatus(403);
});

it('restaura de punta a punta para quien sí tiene el permiso', function () {
    $amordazado = $this->withHeader('X-Service-Token', 'tok-lic')
        ->postJson('/anonimizacion/amordazar', ['texto' => 'RUT 12.345.678-5'])
        ->json();

    $r = $this->withHeader('X-Service-Token', 'tok-lic')
        ->postJson('/anonimizacion/restaurar', [
            'texto' => $amordazado['texto_seguro'],
            'boveda_id' => $amordazado['boveda_id'],
        ])->assertOk();

    expect($r->json('texto'))->toBe('RUT 12.345.678-5');
});

it('niega restaurar la bóveda de OTRO consumidor aunque quien pide tenga permiso de restaurar', function () {
    // 'disc' no tiene permiso de /restaurar, pero SÍ puede /amordazar y crear
    // su propia bóveda. 'licencias' tiene permiso de /restaurar, pero eso no
    // lo habilita a leer la bóveda de un consumidor distinto: cada sistema
    // solo puede recuperar lo que él mismo amordazó.
    $amordazado = $this->withHeader('X-Service-Token', 'tok-disc')
        ->postJson('/anonimizacion/amordazar', ['texto' => 'RUT 12.345.678-5'])
        ->json();

    $r = $this->withHeader('X-Service-Token', 'tok-lic')
        ->postJson('/anonimizacion/restaurar', [
            'texto' => $amordazado['texto_seguro'],
            'boveda_id' => $amordazado['boveda_id'],
        ]);

    $r->assertStatus(403);
    expect($r->getContent())->not->toContain('12.345.678-5');
});

it('expone /health sin token', function () {
    // Sin `tokens_cargados`: cuántos sistemas consumen esta API es información
    // de negocio, igual que las métricas, que sí están tras token. Ver
    // RespuestaSiempreJsonTest para lo que /health sí comprueba ahora.
    $this->getJson('/anonimizacion/health')
        ->assertOk()
        ->assertJson(['servicio' => 'anonimizacion'])
        ->assertJsonMissingPath('tokens_cargados');
});

it('NO ARRANCA si la API está habilitada y no hay tokens', function () {
    // El servicio de OCR queda abierto si falta la variable. Acá no: un .env mal
    // copiado no puede dejar expuesto un endpoint que des-anonimiza.
    config()->set('anonimizacion.api.tokens', '');

    new VerificarTokenDeServicio;
})->throws(ApiSinTokens::class);
