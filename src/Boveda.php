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

    /** @return array<string, string> */
    public function aArray(): array
    {
        return $this->mapa;
    }

    /** @param array<string, string> $mapa */
    public static function desdeArray(array $mapa): self
    {
        $boveda = new self;
        $boveda->mapa = $mapa;

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
