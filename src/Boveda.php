<?php

namespace Anonimizacion;

use JsonSerializable;

/**
 * El mapeo marcador → valor real. Contiene datos personales, así que las tres
 * vías por las que PHP imprime un objeto están neutralizadas a propósito.
 */
final class Boveda implements JsonSerializable
{
    /** @var array<string, string> */
    private array $mapa = [];

    /**
     * @param  ?string  $consumidor  Quién la creó (el token de servicio que
     *                               llamó a /amordazar). Null cuando el
     *                               paquete se usa como librería PHP sin la
     *                               API: ahí no hay consumidor que comprobar.
     */
    public function __construct(private readonly ?string $consumidor = null) {}

    public function agregar(string $marcador, string $valor): void
    {
        $this->mapa[$marcador] = $valor;
    }

    public function resolver(string $marcador): ?string
    {
        return $this->mapa[$marcador] ?? null;
    }

    /** @return array<int, string> */
    public function marcadores(): array
    {
        return array_keys($this->mapa);
    }

    /**
     * ¿Puede $consumidor restaurar esta bóveda? Sin dueño registrado (uso
     * como librería, sin pasar por la API) no hay nada que comprobar.
     */
    public function perteneceA(?string $consumidor): bool
    {
        return $this->consumidor === null || $this->consumidor === $consumidor;
    }

    /** @return array{consumidor: ?string, mapa: array<string, string>} */
    public function aArray(): array
    {
        return ['consumidor' => $this->consumidor, 'mapa' => $this->mapa];
    }

    /** @param array{consumidor?: ?string, mapa?: array<string, string>} $datos */
    public static function desdeArray(array $datos): self
    {
        $boveda = new self($datos['consumidor'] ?? null);
        $boveda->mapa = $datos['mapa'] ?? [];

        return $boveda;
    }

    public function __toString(): string
    {
        return '[Boveda: '.count($this->mapa).' entradas ocultas]';
    }

    /** @return array<string, int> */
    public function __debugInfo(): array
    {
        return ['entradas_ocultas' => count($this->mapa)];
    }

    /** @return array<string, int> */
    public function jsonSerialize(): array
    {
        return ['entradas_ocultas' => count($this->mapa)];
    }
}
