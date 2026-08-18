<?php

namespace Anonimizacion\Tests;

use Anonimizacion\AnonimizacionServiceProvider;
use Orchestra\Testbench\TestCase as Base;

abstract class TestCase extends Base
{
    /** @return array<int, class-string> */
    protected function getPackageProviders($app): array
    {
        return [AnonimizacionServiceProvider::class];
    }

    /**
     * ¿Hay un Redis a mano para correr la suite contra el motor de producción?
     *
     * La suite corre contra el store de array y para casi todo alcanza. No
     * alcanza para la bóveda: el array no expira de verdad, no serializa a
     * texto y no ejercita el cliente. Un cifrado que "funciona" en memoria y
     * revienta en Redis deja la anonimización caída con la suite en verde.
     *
     *   ./tools/pest-redis.sh
     *
     * Sin la variable no cambia nada: array, como siempre.
     */
    public static function hayRedis(): bool
    {
        return getenv('ANONIMIZACION_REDIS_HOST') !== false;
    }

    public static function storeDePrueba(): string
    {
        return self::hayRedis() ? 'redis' : 'array';
    }

    protected function defineEnvironment($app): void
    {
        // La bóveda se cifra, así que la suite necesita una clave. Es de
        // pruebas y descartable: la real vive en el .env de cada instalación.
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        if (self::hayRedis()) {
            $app['config']->set('database.redis.client', 'phpredis');
            $app['config']->set('database.redis.default', [
                'host' => getenv('ANONIMIZACION_REDIS_HOST'),
                'port' => getenv('ANONIMIZACION_REDIS_PORT') ?: '6379',
                'database' => 0,
            ]);
            $app['config']->set('cache.stores.redis', [
                'driver' => 'redis',
                'connection' => 'default',
                'lock_connection' => 'default',
            ]);
        }

        $app['config']->set('anonimizacion.store_boveda', self::storeDePrueba());

        // Las rutas se registran en el boot del provider, así que la API tiene
        // que quedar configurada acá: hacerlo en un beforeEach llegaría tarde y
        // las rutas no existirían. Tokens de prueba, sin ningún valor real.
        $app['config']->set('anonimizacion.api.habilitada', true);
        $app['config']->set('anonimizacion.api.tokens', 'licencias:tok-lic,disc:tok-disc');
        $app['config']->set('anonimizacion.api.pueden_restaurar', 'licencias');
    }
}
