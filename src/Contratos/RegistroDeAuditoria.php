<?php

namespace Anonimizacion\Contratos;

/**
 * El paquete NO depende de laravel-muni-shared: hacerlo obligaría a KraftDo y a
 * muni-kit a arrastrar un paquete municipal. Los sistemas municipales enlazan
 * un adaptador hacia su Bitacora; el resto usa el default sobre el log.
 */
interface RegistroDeAuditoria
{
    /** @param array<string, mixed> $datos Nombres y cantidades. Nunca valores. */
    public function registrar(string $evento, array $datos): void;
}
