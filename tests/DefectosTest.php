<?php

use Anonimizacion\Anonimizador;
use Anonimizacion\Boveda;
use Anonimizacion\BovedaId;
use Anonimizacion\Contratos\ProveedorExterno;
use Anonimizacion\Contratos\RepositorioDeBoveda;
use Anonimizacion\ProveedorAnonimizado;

it('consultar el veredicto NO crea ni guarda una bóveda', function () {
    // Guardar una bóveda para una consulta que no va a usarla escribe datos
    // personales en Redis sin necesidad y desperdicia una escritura por turno.
    $espia = new class implements RepositorioDeBoveda
    {
        public int $guardadas = 0;

        public function guardar(BovedaId $id, Boveda $boveda): void
        {
            $this->guardadas++;
        }

        public function recuperar(BovedaId $id): Boveda
        {
            return new Boveda;
        }
    };

    app()->instance(RepositorioDeBoveda::class, $espia);

    $decorado = new ProveedorAnonimizado(
        new class implements ProveedorExterno
        {
            public function preguntar(string $texto): string
            {
                return '';
            }
        },
        app(Anonimizador::class),
    );

    $decorado->veredictoDe('Soy Juan, RUT 12.345.678-5');

    expect($espia->guardadas)->toBe(0);
});

it('rechaza un boveda_id con formato inválido en vez de usarlo como clave', function () {
    // El id llega del cliente y termina en la clave del store. Solo se acepta
    // el formato que genera el paquete: 32 hexadecimales.
    $this->withHeader('X-Service-Token', 'tok-lic')
        ->postJson('/anonimizacion/restaurar', [
            'texto' => 'hola',
            'boveda_id' => '../../etc/passwd',
        ])
        ->assertStatus(422);
});

it('un hallazgo anidado dentro de otro no corrompe el texto seguro ni la restauración', function () {
    // El teléfono "912345678" queda embebido dentro del correo
    // "juan912345678@gmail.com": ambos detectores lo marcan y sus rangos se
    // solapan. Sin filtrar el solapamiento, el reemplazo en dos pasadas corta
    // el texto en offsets que ya no corresponden al string alterado.
    $anon = app(Anonimizador::class);
    $r = $anon->amordazar('mi correo es juan912345678@gmail.com gracias');

    expect($r->textoSeguro)->not->toContain('gmail.com')
        ->and($r->textoSeguro)->not->toContain('juan912345678');

    $marcador = $r->textoSeguro;
    $respuesta = $anon->restaurar($marcador, $r->boveda);

    expect($respuesta)->toBe('mi correo es juan912345678@gmail.com gracias');
});

it('el límite de peticiones cuenta por consumidor y no por IP', function () {
    // Todos los sistemas del ecosistema salen por la misma IP interna: con un
    // throttle por IP, un consumidor ruidoso deja sin cuota a los demás.
    for ($i = 0; $i < 60; $i++) {
        $this->withHeader('X-Service-Token', 'tok-lic')
            ->postJson('/anonimizacion/amordazar', ['texto' => 'hola']);
    }

    // 'licencias' agotó su cuota...
    $this->withHeader('X-Service-Token', 'tok-lic')
        ->postJson('/anonimizacion/amordazar', ['texto' => 'hola'])
        ->assertStatus(429);

    // ...pero 'disc' sigue pudiendo trabajar.
    $this->withHeader('X-Service-Token', 'tok-disc')
        ->postJson('/anonimizacion/amordazar', ['texto' => 'hola'])
        ->assertOk();
});
