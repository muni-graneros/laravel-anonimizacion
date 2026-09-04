<?php

namespace Anonimizacion\Contratos;

use Anonimizacion\Boveda;
use Anonimizacion\BovedaId;
use Anonimizacion\Excepciones\BovedaExpirada;
use Anonimizacion\Excepciones\BovedaNoDisponible;

interface RepositorioDeBoveda
{
    /**
     * Una implementación puede lanzar lo que le dé su motor (Redis caído, disco
     * lleno). `Anonimizador` la traduce a BovedaNoDisponible antes de dejarla
     * salir: la excepción cruda arrastra en su trace los argumentos de las
     * llamadas de arriba, y uno de ellos es el texto del ciudadano.
     *
     * @throws BovedaNoDisponible
     */
    public function guardar(BovedaId $id, Boveda $boveda): void;

    /** @throws BovedaExpirada|BovedaNoDisponible */
    public function recuperar(BovedaId $id): Boveda;
}
