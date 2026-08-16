<?php

namespace Anonimizacion;

final class Hallazgo
{
    public function __construct(
        public readonly string $tipo,
        public readonly int $inicio,
        public readonly int $largo,
        public readonly string $valor,
    ) {}
}
