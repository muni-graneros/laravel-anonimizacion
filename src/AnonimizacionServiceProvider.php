<?php

namespace Anonimizacion;

use Anonimizacion\Contratos\ClasificadorSensible;
use Anonimizacion\Contratos\RegistroDeAuditoria;
use Anonimizacion\Contratos\RepositorioDeBoveda;
use Anonimizacion\Detectores\DetectorEmail;
use Anonimizacion\Detectores\DetectorFolio;
use Anonimizacion\Detectores\DetectorRut;
use Anonimizacion\Detectores\DetectorTelefono;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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

        // Enlace por defecto: nunca veta. El ciclo 1B lo sustituye por el
        // clasificador real sin que ningún consumidor cambie una línea.
        $this->app->bind(ClasificadorSensible::class, SinClasificador::class);

        $this->app->bind(RegistroDeAuditoria::class, fn ($app) => new AuditoriaEnLog($app['log']));

        $this->app->singleton(Metricas::class, fn ($app) => new Metricas(
            $app['cache']->store((string) config('anonimizacion.store_boveda')),
        ));

        $this->app->bind(Anonimizador::class, fn ($app) => new Anonimizador(
            [
                new DetectorRut,
                new DetectorTelefono,
                new DetectorEmail,
                new DetectorFolio((string) config('anonimizacion.patron_folio')),
            ],
            $app->make(RepositorioDeBoveda::class),
            $app->make(ClasificadorSensible::class),
            $app->make(RegistroDeAuditoria::class),
        ));
    }

    public function boot(): void
    {
        // El límite cuenta por consumidor y no por IP: todos los sistemas
        // del ecosistema salen por la misma IP interna, así que un consumidor
        // ruidoso dejaría sin cuota a los demás.
        RateLimiter::for('anonimizacion', fn (Request $request) => Limit::perMinute(60)
            ->by((string) $request->attributes->get('consumidor', $request->ip())));

        if (config('anonimizacion.api.habilitada')) {
            $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        }

        $this->publishes([
            __DIR__.'/../config/anonimizacion.php' => config_path('anonimizacion.php'),
        ], 'anonimizacion-config');
    }
}
