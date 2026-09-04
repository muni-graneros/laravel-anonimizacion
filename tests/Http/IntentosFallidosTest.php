<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Un 401 no puede ser gratis ni invisible.
 *
 * `VerificarTokenDeServicio` va ANTES de `throttle:anonimizacion` en la cadena,
 * así que quien manda un token inválido nunca llega al limitador: puede probar
 * a la velocidad que quiera contra el único punto de la API que devuelve datos
 * reales. Y el 401 era silencioso —el log quedaba vacío—, así que una rotación
 * mal hecha, con un consumidor golpeando con el token viejo, tampoco se veía.
 *
 * El limitador general cuenta por consumidor, y en un 401 no hay consumidor:
 * por eso hace falta uno propio, por origen.
 */
beforeEach(function () {
    RateLimiter::clear('anonimizacion:401:'.hash('sha256', '127.0.0.1'));
});

it('deja constancia del intento con token inválido', function () {
    Log::spy();

    $this->withHeader('X-Service-Token', 'el-que-no-es')
        ->postJson('/anonimizacion/amordazar', ['texto' => 'hola'])
        ->assertStatus(401);

    Log::shouldHaveReceived('warning')->withArgs(
        fn (string $mensaje, array $ctx) => $mensaje === 'pii.token_invalido'
    )->once();
});

it('el registro no lleva la IP ni el token, solo un hash del origen', function () {
    Log::spy();

    $this->withHeader('X-Service-Token', 'secreto-que-alguien-probo')
        ->postJson('/anonimizacion/amordazar', ['texto' => 'hola'])
        ->assertStatus(401);

    Log::shouldHaveReceived('warning')->withArgs(function (string $mensaje, array $ctx) {
        $plano = json_encode($ctx);

        return $mensaje === 'pii.token_invalido'
            && ! str_contains($plano, 'secreto-que-alguien-probo')
            && ! str_contains($plano, '127.0.0.1')
            && isset($ctx['ip_hash']);
    })->once();
});

it('a fuerza bruta responde 429 en vez de seguir aceptando intentos', function () {
    // Diez intentos entran; el once ya no. El tope es bajo a propósito: un
    // consumidor legítimo nunca falla el token, así que fallar diez veces en un
    // minuto solo pasa cuando alguien está probando o algo está mal configurado.
    for ($i = 0; $i < 10; $i++) {
        $this->withHeader('X-Service-Token', "intento-{$i}")
            ->postJson('/anonimizacion/amordazar', ['texto' => 'hola'])
            ->assertStatus(401);
    }

    $this->withHeader('X-Service-Token', 'intento-11')
        ->postJson('/anonimizacion/amordazar', ['texto' => 'hola'])
        ->assertStatus(429);
});

it('el bloqueo por intentos fallidos no afecta a quien tiene token bueno', function () {
    for ($i = 0; $i < 12; $i++) {
        $this->withHeader('X-Service-Token', "intento-{$i}")
            ->postJson('/anonimizacion/amordazar', ['texto' => 'hola']);
    }

    // El limitador de fallos NO puede convertirse en una forma de dejar fuera a
    // los demás: todos los sistemas del ecosistema salen por la misma IP interna.
    $this->withHeader('X-Service-Token', 'tok-lic')
        ->postJson('/anonimizacion/amordazar', ['texto' => 'hola'])
        ->assertOk();
});

it('un acierto limpia el contador de fallos de ese origen', function () {
    foreach (['malo-1', 'malo-2', 'malo-3'] as $token) {
        $this->withHeader('X-Service-Token', $token)
            ->postJson('/anonimizacion/amordazar', ['texto' => 'hola'])->assertStatus(401);
    }

    $this->withHeader('X-Service-Token', 'tok-lic')
        ->postJson('/anonimizacion/amordazar', ['texto' => 'hola'])->assertOk();

    // Tras el acierto vuelven a caber diez fallos: si no se limpiara, un
    // consumidor que arregló su token seguiría penalizado por los intentos de
    // antes.
    for ($i = 0; $i < 10; $i++) {
        $this->withHeader('X-Service-Token', "otro-{$i}")
            ->postJson('/anonimizacion/amordazar', ['texto' => 'hola'])
            ->assertStatus(401);
    }
});
