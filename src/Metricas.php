<?php

namespace Anonimizacion;

use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Contadores de uso por consumidor y operación, en formato Prometheus.
 *
 * A mano y sin dependencias, como los micros del ecosistema. Netdata ya ve CPU
 * y RAM del contenedor; esto añade la métrica de negocio: quién llama, a qué y
 * cuántas veces. Nunca guarda texto del ciudadano, solo nombres y conteos.
 */
class Metricas
{
    private const INDICE = 'anon:metricas:indice';

    public function __construct(private readonly Cache $cache) {}

    public function contar(string $consumidor, string $operacion): void
    {
        $clave = $this->clave($consumidor, $operacion);

        // increment() es atómico en Redis; add() lo inicializa si no existía.
        $this->cache->add($clave, 0, 86400);
        $this->cache->increment($clave);

        // Índice de combinaciones vistas, para poder renderizarlas después.
        // Reescribirlo es idempotente: una carrera puede perder una entrada
        // recién creada, que vuelve a agregarse en la siguiente petición.
        $indice = $this->indice();
        $par = $consumidor.'|'.$operacion;

        if (! in_array($par, $indice, true)) {
            $indice[] = $par;
            $this->cache->put(self::INDICE, $indice, 86400);
        }
    }

    public function render(): string
    {
        $lineas = [
            '# HELP anonimizacion_peticiones_total Peticiones atendidas por consumidor y operación.',
            '# TYPE anonimizacion_peticiones_total counter',
        ];

        foreach ($this->indice() as $par) {
            [$consumidor, $operacion] = explode('|', $par, 2);
            $total = (int) $this->cache->get($this->clave($consumidor, $operacion), 0);

            $lineas[] = sprintf(
                'anonimizacion_peticiones_total{consumidor="%s",operacion="%s"} %d',
                $this->escapar($consumidor),
                $this->escapar($operacion),
                $total,
            );
        }

        return implode("\n", $lineas)."\n";
    }

    /** @return array<int, string> */
    private function indice(): array
    {
        $indice = $this->cache->get(self::INDICE, []);

        return is_array($indice) ? $indice : [];
    }

    private function clave(string $consumidor, string $operacion): string
    {
        return 'anon:metricas:'.md5($consumidor.'|'.$operacion);
    }

    /** Prometheus exige escapar la barra invertida y la comilla en las etiquetas. */
    private function escapar(string $valor): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $valor);
    }
}
