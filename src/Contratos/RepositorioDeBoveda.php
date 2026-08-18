<?php

namespace Anonimizacion\Contratos;

use Anonimizacion\Boveda;
use Anonimizacion\BovedaId;
use Anonimizacion\Excepciones\BovedaExpirada;

interface RepositorioDeBoveda
{
    public function guardar(BovedaId $id, Boveda $boveda): void;

    /** @throws BovedaExpirada */
    public function recuperar(BovedaId $id): Boveda;
}
