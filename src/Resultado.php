<?php

namespace Anonimizacion;

final class Resultado
{
    /** @param array<int, string> $tipos */
    public function __construct(
        public readonly Veredicto $veredicto,
        public readonly string $textoSeguro,
        public readonly ?BovedaId $boveda,
        public readonly array $tipos,
        public readonly ?string $categoria = null,
    ) {}

    public static function vetado(string $categoria): self
    {
        return new self(Veredicto::Vetado, '', null, [], $categoria);
    }
}
