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

    protected function defineEnvironment($app): void
    {
        // La bóveda se cifra, así que la suite necesita una clave. Es de
        // pruebas y descartable: la real vive en el .env de cada instalación.
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        // La bóveda se prueba contra el store de array: el contrato que importa
        // es "guarda cifrado y expira", no el motor que lo almacena.
        $app['config']->set('anonimizacion.store_boveda', 'array');

        // Las rutas se registran en el boot del provider, así que la API tiene
        // que quedar configurada acá: hacerlo en un beforeEach llegaría tarde y
        // las rutas no existirían. Tokens de prueba, sin ningún valor real.
        $app['config']->set('anonimizacion.api.habilitada', true);
        $app['config']->set('anonimizacion.api.tokens', 'licencias:tok-lic,disc:tok-disc');
        $app['config']->set('anonimizacion.api.pueden_restaurar', 'licencias');
    }
}
