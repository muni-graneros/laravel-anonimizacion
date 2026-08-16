<?php

namespace Anonimizacion;

use Anonimizacion\Contratos\RepositorioDeBoveda;
use Illuminate\Support\ServiceProvider;

class AnonimizacionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/anonimizacion.php', 'anonimizacion');

        $this->app->singleton(BovedaEnCache::class, fn ($app) => new BovedaEnCache(
            $app['cache'],
            $app['encrypter'],
            (string) config('anonimizacion.store_boveda'),
            (int) config('anonimizacion.ttl_boveda'),
        ));

        $this->app->bind(RepositorioDeBoveda::class, BovedaEnCache::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/anonimizacion.php' => config_path('anonimizacion.php'),
        ], 'anonimizacion-config');
    }
}
