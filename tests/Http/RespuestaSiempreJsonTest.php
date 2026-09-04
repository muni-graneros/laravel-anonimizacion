<?php

use Illuminate\Cache\ArrayStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Un cliente que no pide JSON tiene que recibir JSON igual.
 *
 * Las rutas se registraban con `loadRoutesFrom` sin el grupo `api`, así que
 * `$request->validate()` fallaba a la manera web: redirección 302 a la raíz. Los
 * consumidores para los que existe esta API son justamente los que no son PHP
 * —n8n, curl, los micros de Python—, y todos mandan `Accept` comodín por defecto.
 * Recibían un 302 a `http://localhost` en vez de un 422 con el motivo, y en una
 * ruta sin sesión eso puede terminar en 500 al intentar `withInput()`.
 */
it('valida con 422 y no con una redirección aunque el cliente no pida JSON', function () {
    $this->withHeaders(['X-Service-Token' => 'tok-lic', 'Accept' => '*/*'])
        ->post('/anonimizacion/amordazar', [])
        ->assertStatus(422)
        ->assertJsonStructure(['message', 'errors' => ['texto']]);
});

it('el token inválido también responde JSON sin pedirlo', function () {
    $this->withHeaders(['X-Service-Token' => 'no', 'Accept' => '*/*'])
        ->post('/anonimizacion/amordazar', ['texto' => 'hola'])
        ->assertStatus(401)
        ->assertJson(['error' => 'token inválido']);
});

it('health responde JSON sin pedirlo', function () {
    $this->withHeaders(['Accept' => '*/*'])
        ->get('/anonimizacion/health')
        ->assertHeader('content-type', 'application/json');
});

it('el caso normal, con Accept: application/json, sigue igual', function () {
    $this->withHeader('X-Service-Token', 'tok-lic')
        ->postJson('/anonimizacion/amordazar', [])
        ->assertStatus(422);
});

/**
 * `/health` decía «sano» con la bóveda caída.
 *
 * No tocaba el store: respondía 200 con Redis apagado. Una sonda que da verde
 * cuando la operación que importa -guardar y recuperar la bóveda- va a fallar es
 * peor que no tener sonda, porque Uptime Kuma no avisa y nadie mira.
 *
 * Y publicaba `tokens_cargados` sin token: cuántos sistemas consumen esta API es
 * información de negocio, igual que las métricas, que sí están tras token.
 */
it('health comprueba la bóveda de verdad', function () {
    $this->getJson('/anonimizacion/health')
        ->assertOk()
        ->assertJson(['servicio' => 'anonimizacion', 'boveda' => 'ok']);
});

it('health responde 503 si la bóveda no está', function () {
    Log::spy();

    // Se imita el caso real: `Cache::store()` devuelve el repositorio sin
    // problema —el driver está bien configurado— y es la ESCRITURA la que
    // revienta porque Redis no contesta. Mockear la fachada entera no serviría:
    // rompería también las llamadas del framework y el fallo sería otro.
    config()->set('cache.stores.caida', ['driver' => 'caida']);
    config()->set('anonimizacion.store_boveda', 'caida');

    Cache::extend('caida', fn () => Cache::repository(new class extends ArrayStore
    {
        public function put($key, $value, $seconds): bool
        {
            throw new RuntimeException('Connection refused [tcp://127.0.0.1:6379]');
        }
    }));

    $this->getJson('/anonimizacion/health')
        ->assertStatus(503)
        ->assertJson(['boveda' => 'caida']);
});

it('el 503 no filtra el host ni el puerto del store', function () {
    Log::spy();
    config()->set('cache.stores.caida', ['driver' => 'caida']);
    config()->set('anonimizacion.store_boveda', 'caida');

    Cache::extend('caida', fn () => Cache::repository(new class extends ArrayStore
    {
        public function put($key, $value, $seconds): bool
        {
            throw new RuntimeException('Connection refused [tcp://10.0.0.7:6379]');
        }
    }));

    $cuerpo = $this->getJson('/anonimizacion/health')->assertStatus(503)->content();

    // Quien sondea el servicio puede ser cualquiera: el motivo va al log, que ya
    // es de quien lo opera.
    expect($cuerpo)->not->toContain('10.0.0.7')->and($cuerpo)->not->toContain('6379');
});

it('health no publica cuántos consumidores hay', function () {
    $this->getJson('/anonimizacion/health')
        ->assertOk()
        ->assertJsonMissingPath('tokens_cargados');
});
