<?php

use Anonimizacion\Anonimizador;
use Anonimizacion\Boveda;
use Anonimizacion\BovedaId;
use Anonimizacion\Contratos\RepositorioDeBoveda;
use Anonimizacion\Excepciones\BovedaNoDisponible;

/**
 * Si Redis se cae, el RUT del vecino no puede terminar en el log.
 *
 * `guardar()` lanza cuando el store falla —y está bien que lance: fail-closed,
 * el proveedor externo no llega a ver nada—. El problema es cómo se registra esa
 * excepción: el handler de Laravel escribe `getTraceAsString()`, y el trace de
 * PHP incluye los ARGUMENTOS de cada llamada salvo que el `php.ini` traiga
 * `zend.exception_ignore_args=1`. El primer argumento de `amordazar()` es el
 * texto completo del ciudadano.
 *
 * O sea: el día que Redis se cae, el mensaje entero con el RUT sin tapar se
 * escribe en `laravel.log`, que es exactamente lo que este paquete existe para
 * evitar. `FugaEnLogsTest` no lo cubría porque solo prueba el camino feliz.
 *
 * El paquete no puede confiar en el `php.ini` del contenedor de otro, así que
 * corta la cadena él mismo: relanza una excepción propia desde un punto donde el
 * texto no es argumento de nada.
 */
function repositorioQueFalla(): void
{
    app()->bind(RepositorioDeBoveda::class, fn () => new class implements RepositorioDeBoveda
    {
        public function guardar(BovedaId $id, Boveda $boveda): void
        {
            throw new RuntimeException('Connection refused [tcp://127.0.0.1:6379]');
        }

        public function recuperar(BovedaId $id): Boveda
        {
            throw new RuntimeException('Connection refused [tcp://127.0.0.1:6379]');
        }
    });
}

it('sigue fallando cerrado: si no se puede guardar, no se devuelve texto', function () {
    repositorioQueFalla();

    expect(fn () => app(Anonimizador::class)->amordazar('mi rut es 12.345.678-5'))
        ->toThrow(BovedaNoDisponible::class);
});

it('el texto del ciudadano no aparece en el trace de la excepción', function () {
    repositorioQueFalla();

    // Se fuerza el peor caso: el ini que SÍ guarda argumentos. Es el de
    // desarrollo, y el paquete no puede dar por hecho el de producción.
    $original = ini_get('zend.exception_ignore_args');
    @ini_set('zend.exception_ignore_args', '0');

    try {
        app(Anonimizador::class)->amordazar('el rut de mi mamá es 12.345.678-5');
        $this->fail('no lanzó');
    } catch (BovedaNoDisponible $e) {
        $rastro = $e->getTraceAsString().' '.$e->getMessage();

        expect($rastro)->not->toContain('12.345.678-5', 'el RUT quedó en el trace de la excepción')
            ->and($rastro)->not->toContain('mi mamá');
    } finally {
        @ini_set('zend.exception_ignore_args', (string) $original);
    }
});

it('la causa original se conserva para poder depurar', function () {
    repositorioQueFalla();

    try {
        app(Anonimizador::class)->amordazar('mi rut es 12.345.678-5');
        $this->fail('no lanzó');
    } catch (BovedaNoDisponible $e) {
        // Qué falló sí se necesita saber; con qué texto, no.
        expect($e->getMessage())->toContain('bóveda')
            ->and($e->motivo)->toContain('Connection refused');
    }
});

it('restaurar también corta la cadena', function () {
    repositorioQueFalla();

    try {
        app(Anonimizador::class)->restaurar(
            'la respuesta con [RUT_1_abcd]',
            new BovedaId(str_repeat('a', 32))
        );
        $this->fail('no lanzó');
    } catch (BovedaNoDisponible $e) {
        expect($e->getTraceAsString())->not->toContain('RUT_1_abcd');
    }
});
