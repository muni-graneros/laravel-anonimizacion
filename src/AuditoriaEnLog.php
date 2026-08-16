<?php

namespace Anonimizacion;

use Anonimizacion\Contratos\RegistroDeAuditoria;
use Psr\Log\LoggerInterface;

class AuditoriaEnLog implements RegistroDeAuditoria
{
    public function __construct(private readonly LoggerInterface $log) {}

    public function registrar(string $evento, array $datos): void
    {
        $this->log->info($evento, $datos);
    }
}
