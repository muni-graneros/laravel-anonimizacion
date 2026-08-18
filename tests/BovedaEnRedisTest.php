<?php

use Anonimizacion\Boveda;
use Anonimizacion\BovedaEnCache;
use Anonimizacion\BovedaId;
use Anonimizacion\Tests\TestCase;
use Illuminate\Support\Facades\Redis;

// Estas comprobaciones solo tienen sentido contra el motor real: el store de
// array no expira de verdad ni serializa a texto. Se corren con tools/pest-redis.sh.
beforeEach(function () {
    if (! TestCase::hayRedis()) {
        $this->markTestSkipped('Sin Redis: correr con tools/pest-redis.sh');
    }
});

/**
 * Devuelve la clave de la bóveda tal como hay que pasarla a la fachada Redis.
 *
 * Dos trampas que el store de array escondía:
 *  - la clave real NO es "anon:{id}": Laravel le antepone el prefijo del caché
 *    y el de la conexión, y queda "laravel-database-laravel-cache-anon:{id}";
 *  - phpredis vuelve a prefijar al leer, pero KEYS devuelve el nombre completo.
 *    Pasarle a get() lo que devolvió keys() busca el prefijo dos veces y no
 *    encuentra nada: sin error, solo vacío y un TTL de -2.
 */
function claveRealDe(string $id): string
{
    $encontradas = Redis::keys('*anon:'.$id);

    expect($encontradas)->toHaveCount(1, "No quedó ninguna clave para la bóveda {$id}");

    $prefijoConexion = (string) config('database.redis.options.prefix', '');

    return $prefijoConexion !== '' && str_starts_with($encontradas[0], $prefijoConexion)
        ? substr($encontradas[0], strlen($prefijoConexion))
        : $encontradas[0];
}

it('la bóveda queda con una expiración real puesta por el motor', function () {
    // Que expire sola es la razón de usar Redis: si el TTL no llega al motor,
    // el mapeo con datos del vecino se queda vivo para siempre.
    $boveda = new Boveda;
    $boveda->agregar('[RUT_1_a3f9]', '12.345.678-5');

    $id = BovedaId::nueva();
    app(BovedaEnCache::class)->guardar($id, $boveda);

    $ttl = Redis::ttl(claveRealDe($id->valor));

    expect($ttl)->toBeGreaterThan(0)
        ->and($ttl)->toBeLessThanOrEqual(900);
});

it('lo que queda escrito en Redis es ciphertext, no el dato del vecino', function () {
    // Se lee por el cliente crudo, sin pasar por Laravel: es exactamente lo que
    // vería quien abra un dump .rdb o corra un GET contra la instancia.
    $boveda = new Boveda;
    $boveda->agregar('[RUT_1_a3f9]', '12.345.678-5');

    $id = BovedaId::nueva();
    app(BovedaEnCache::class)->guardar($id, $boveda);

    $crudo = (string) Redis::get(claveRealDe($id->valor));

    expect($crudo)->not->toBe('')
        ->and($crudo)->not->toContain('12.345.678-5')
        ->and($crudo)->not->toContain('RUT_1_a3f9');
});

it('sobrevive la ida y vuelta completa por el motor real', function () {
    $boveda = new Boveda;
    $boveda->agregar('[RUT_1_a3f9]', '12.345.678-5');
    $boveda->agregar('[EMAIL_1_a3f9]', 'juan@granero.cl');

    $id = BovedaId::nueva();
    app(BovedaEnCache::class)->guardar($id, $boveda);

    $recuperada = app(BovedaEnCache::class)->recuperar($id);

    expect($recuperada->resolver('[RUT_1_a3f9]'))->toBe('12.345.678-5')
        ->and($recuperada->resolver('[EMAIL_1_a3f9]'))->toBe('juan@granero.cl');
});
