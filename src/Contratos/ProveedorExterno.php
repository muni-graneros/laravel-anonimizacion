<?php

namespace Anonimizacion\Contratos;

interface ProveedorExterno
{
    public function preguntar(string $texto): string;
}
