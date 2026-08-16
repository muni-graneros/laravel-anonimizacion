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
        // La bóveda se prueba contra el store de array: el contrato que importa
        // es "guarda cifrado y expira", no el motor que lo almacena.
        $app['config']->set('anonimizacion.store_boveda', 'array');
    }
}
