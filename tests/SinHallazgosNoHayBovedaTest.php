<?php

use Anonimizacion\Anonimizador;
use Anonimizacion\Boveda;
use Anonimizacion\BovedaId;
use Anonimizacion\Contratos\RepositorioDeBoveda;
use Anonimizacion\Excepciones\BovedaExpirada;
use Anonimizacion\Veredicto;

/**
 * Un texto sin datos personales no necesita bóveda.
 *
 * `amordazar()` creaba una bóveda y la escribía cifrada en Redis SIEMPRE, aunque
 * no hubiera encontrado nada que guardar: `hola sin datos` devolvía un
 * `boveda_id` que apuntaba a un mapeo vacío.
 *
 * En un chatbot la mayoría de los turnos no trae PII, así que eso es un ida y
 * vuelta a Redis por turno para nada. Y peor: si la conversación dura más de los
 * 900 segundos del TTL, restaurar esa bóveda vacía lanza `BovedaExpirada` y
 * rompe un turno que nunca tuvo un dato que proteger.
 */
it('no escribe nada cuando no encontró datos personales', function () {
    $escrituras = 0;

    app()->bind(RepositorioDeBoveda::class, fn () => new class($escrituras) implements RepositorioDeBoveda
    {
        public function __construct(public int &$escrituras) {}

        public function guardar(BovedaId $id, Boveda $boveda): void
        {
            $this->escrituras++;
        }

        public function recuperar(BovedaId $id): Boveda
        {
            throw new BovedaExpirada('no debería llegar acá');
        }
    });

    $resultado = app(Anonimizador::class)->amordazar('hola, quiero información de la feria');

    expect($resultado->veredicto)->toBe(Veredicto::Permitido)
        ->and($resultado->boveda)->toBeNull('se creó una bóveda para un texto sin datos personales')
        ->and($escrituras)->toBe(0, 'se escribió en el store sin tener nada que guardar');
});

it('sí escribe cuando encontró algo', function () {
    $resultado = app(Anonimizador::class)->amordazar('mi rut es 12.345.678-5');

    expect($resultado->boveda)->not->toBeNull()
        ->and($resultado->tipos)->toContain('rut');
});

it('restaurar sin bóveda devuelve el texto tal cual', function () {
    // El otro lado de lo mismo: el consumidor que recibió `boveda_id: null`
    // tiene que poder cerrar el ciclo sin inventarse un id.
    $texto = app(Anonimizador::class)->restaurar('la respuesta del modelo', null);

    expect($texto)->toBe('la respuesta del modelo');
});

it('la API devuelve boveda_id nulo y acepta que se lo omitan al restaurar', function () {
    $respuesta = $this->withHeader('X-Service-Token', 'tok-lic')
        ->postJson('/anonimizacion/amordazar', ['texto' => 'hola, sin datos'])
        ->assertOk();

    expect($respuesta->json('boveda_id'))->toBeNull();

    $this->withHeader('X-Service-Token', 'tok-lic')
        ->postJson('/anonimizacion/restaurar', ['texto' => 'respuesta'])
        ->assertOk()
        ->assertJson(['texto' => 'respuesta']);
});
