<?php

namespace Anonimizacion;

use Anonimizacion\Contratos\RegistroDeAuditoria;
use Psr\Log\LoggerInterface;

class AuditoriaEnLog implements RegistroDeAuditoria
{
    public function __construct(private readonly LoggerInterface $log) {}

    /**
     * Eventos que denuncian un problema, no una operación normal.
     *
     * Van como `warning` para que se puedan filtrar y alertar sin leer el resto
     * del flujo. `pii.amordazado` ocurre en cada petición y ahogaría la señal;
     * un token inválido o un veto son cosas que alguien tiene que mirar.
     */
    private const AVISOS = ['pii.token_invalido', 'pii.vetado'];

    public function registrar(string $evento, array $datos): void
    {
        if (in_array($evento, self::AVISOS, true)) {
            $this->log->warning($evento, $datos);

            return;
        }

        $this->log->info($evento, $datos);
    }
}
