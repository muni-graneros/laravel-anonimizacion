<?php

namespace Anonimizacion;

final class BovedaId
{
    public function __construct(public readonly string $valor) {}

    public static function nueva(): self
    {
        return new self(bin2hex(random_bytes(16)));
    }
}
