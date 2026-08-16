<?php

namespace Anonimizacion;

use Illuminate\Support\ServiceProvider;

class AnonimizacionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/anonimizacion.php', 'anonimizacion');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/anonimizacion.php' => config_path('anonimizacion.php'),
        ], 'anonimizacion-config');
    }
}
