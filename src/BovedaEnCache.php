<?php

namespace Anonimizacion;

use Anonimizacion\Contratos\RepositorioDeBoveda;
use Anonimizacion\Excepciones\BovedaExpirada;
use Illuminate\Contracts\Cache\Factory as Cache;
use Illuminate\Contracts\Encryption\StringEncrypter;

class BovedaEnCache implements RepositorioDeBoveda
{
    public function __construct(
        private readonly Cache $cache,
        private readonly StringEncrypter $cifrador,
        private readonly string $store,
        private readonly int $ttl,
    ) {}

    public function guardar(BovedaId $id, Boveda $boveda): void
    {
        // Se cifra ANTES de entrar al store: aunque alguien haga un dump del
        // Redis o quede un snapshot en disco, lo que obtiene es ciphertext.
        $this->cache->store($this->store)->put(
            $this->clave($id),
            $this->cifrador->encryptString(json_encode($boveda->aArray(), JSON_THROW_ON_ERROR)),
            $this->ttl,
        );
    }

    public function recuperar(BovedaId $id): Boveda
    {
        $cifrado = $this->cache->store($this->store)->get($this->clave($id));

        if (! is_string($cifrado)) {
            throw BovedaExpirada::paraId($id->valor);
        }

        /** @var array{consumidor?: ?string, mapa?: array<string, string>} $datos */
        $datos = json_decode($this->cifrador->decryptString($cifrado), true, 512, JSON_THROW_ON_ERROR);

        return Boveda::desdeArray($datos);
    }

    private function clave(BovedaId $id): string
    {
        return 'anon:'.$id->valor;
    }
}
