#!/usr/bin/env bash
#
# Corre la suite del paquete contra un Redis real y desechable.
#
# Existe porque la suite corre contra el store de array y la bóveda vive en
# Redis en producción. El array no expira de verdad, no serializa a texto y no
# ejercita el cliente: un TTL que nunca llega al motor, o un cifrado que
# funciona en memoria y revienta al viajar, dejan la anonimización caída con la
# suite en verde. La lección viene de tools/pest-mariadb.sh en muni-shared.
#
# Obligatorio antes de publicar una versión.
#
#   ./tools/pest-redis.sh                    # suite completa
#   ./tools/pest-redis.sh tests/BovedaEnRedisTest.php
#
set -euo pipefail

# Nombre y puerto POR CORRIDA: este repo lo pueden editar sesiones en paralelo.
# Con nombre fijo, la segunda corrida le vuela el contenedor a la primera en
# plena ejecución. El sufijo es el PID, único entre procesos vivos.
CONTENEDOR=${ANONIMIZACION_REDIS_CONTENEDOR:-anonimizacion-redis-test-$$}
# Puerto 0 = lo elige el kernel entre los libres; se consulta con `docker port`.
PUERTO_PEDIDO=${ANONIMIZACION_REDIS_PORT:-0}
IMAGEN=${ANONIMIZACION_REDIS_IMAGE:-redis:7-alpine}

cd "$(dirname "$0")/.."

docker rm -f "$CONTENEDOR" >/dev/null 2>&1 || true

# Sin persistencia a propósito: es un contenedor de pruebas y no tiene por qué
# dejar un .rdb con datos en el disco de nadie.
docker run --rm -d --name "$CONTENEDOR" \
    -p "127.0.0.1:$PUERTO_PEDIDO:6379" "$IMAGEN" \
    redis-server --save '' --appendonly no >/dev/null

limpiar() { docker rm -f "$CONTENEDOR" >/dev/null 2>&1 || true; }
trap limpiar EXIT

PUERTO=$(docker port "$CONTENEDOR" 6379/tcp | head -n1 | sed 's/.*://')

if [ -z "$PUERTO" ]; then
    echo "No se pudo averiguar el puerto publicado de $CONTENEDOR." >&2
    exit 1
fi

echo "Esperando a $IMAGEN en el puerto $PUERTO (contenedor $CONTENEDOR)…"

listo() { docker exec "$CONTENEDOR" redis-cli ping >/dev/null 2>&1; }

for _ in $(seq 1 30); do
    listo && break
    sleep 1
done

if ! listo; then
    echo "El contenedor $CONTENEDOR no respondió a tiempo." >&2
    exit 1
fi

ANONIMIZACION_REDIS_HOST=127.0.0.1 ANONIMIZACION_REDIS_PORT="$PUERTO" \
    vendor/bin/pest "$@"

# Y ahora la pregunta que el verde de arriba no contesta: ¿corrió CONTRA ESTO?
#
# Sin la variable la misma suite pasa contra el store de array y sale con 0. O
# sea que este script podría levantar un contenedor, ignorarlo y felicitarse.
# Se comprueba donde no se puede fingir: si la suite corrió acá, quedaron
# bóvedas escritas (viven 900 s).
#
# El patrón lleva comodín ADELANTE a propósito: la clave real no es
# "anon:{id}" sino "laravel-database-laravel-cache-anon:{id}", porque Laravel
# antepone el prefijo del caché y el de la conexión. Buscar 'anon:*' a secas
# no encuentra nada y este control da un falso negativo: pasó de verdad.
CLAVES=$(docker exec "$CONTENEDOR" redis-cli --scan --pattern '*anon:*' | wc -l | tr -d ' ')

if [ "$CLAVES" = "0" ]; then
    echo "La suite NO corrió contra este Redis: no quedó ninguna clave anon:*." >&2
    echo "El verde de arriba no vale." >&2
    exit 1
fi

echo "Verificado contra el motor: quedaron $CLAVES bóvedas escritas en este Redis."
