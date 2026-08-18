<?php

namespace Anonimizacion\Excepciones;

use RuntimeException;

class ApiSinTokens extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            'La API de anonimización está habilitada pero ANONIMIZACION_TOKENS está vacío. '.
            'Se niega a arrancar: un endpoint que des-anonimiza datos personales no puede quedar abierto.'
        );
    }
}
