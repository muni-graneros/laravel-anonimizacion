<?php

it('el health informa versión y tiempo en pie, además de los tokens', function () {
    $r = $this->getJson('/anonimizacion/health')->assertOk();

    expect($r->json('servicio'))->toBe('anonimizacion')
        ->and($r->json('version'))->toBeString()
        ->and($r->json('segundos_en_pie'))->toBeInt()
        ->and($r->json('tokens_cargados'))->toBe(2);
});

it('expone métricas en formato Prometheus etiquetadas por consumidor', function () {
    $this->withHeader('X-Service-Token', 'tok-lic')
        ->postJson('/anonimizacion/amordazar', ['texto' => 'RUT 12.345.678-5']);

    $cuerpo = $this->withHeader('X-Service-Token', 'tok-lic')
        ->get('/anonimizacion/metrics')
        ->assertOk()
        ->getContent();

    expect($cuerpo)->toContain('# TYPE anonimizacion_peticiones_total counter')
        ->and($cuerpo)->toMatch('/anonimizacion_peticiones_total\{consumidor="licencias",operacion="amordazar"\} [1-9]/');
});

it('las métricas no llevan ningún dato personal, solo nombres y conteos', function () {
    $this->withHeader('X-Service-Token', 'tok-lic')
        ->postJson('/anonimizacion/amordazar', ['texto' => 'RUT 12.345.678-5 juan@granero.cl']);

    $cuerpo = $this->withHeader('X-Service-Token', 'tok-lic')
        ->get('/anonimizacion/metrics')
        ->assertOk()   // sin esto el test pasa por vacío: un 404 tampoco trae el RUT
        ->getContent();

    expect($cuerpo)->toContain('anonimizacion_peticiones_total')
        ->and($cuerpo)->not->toContain('12.345.678-5')
        ->and($cuerpo)->not->toContain('juan@granero.cl');
});

it('las métricas exigen token: no quedan expuestas a cualquiera', function () {
    $this->get('/anonimizacion/metrics')->assertStatus(401);
});
