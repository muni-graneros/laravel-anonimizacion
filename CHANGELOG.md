# Changelog

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/);
versionado según [SemVer](https://semver.org/lang/es/).

## [No publicado]

Sin cambios de código desde `v1.1.0`.

## [1.1.0] — 2026-09-03

Todo lo de abajo está en el tag `v1.1.0` y **no** en `v1.0.0`. Se anota acá y no
dentro de `[1.0.0]` porque el CHANGELOG de esa versión se reescribió después de
publicarla: quien instalara `^1.0` recibía el tag —sin `CoberturaTest` ni la
sección «Cobertura medida» del README— y leía un CHANGELOG que prometía otra
cosa. Un CHANGELOG que describe algo distinto de lo que trae el paquete es peor
que no tenerlo.

### Agregado

- **Corpus de evaluación y medición de cobertura** (`CoberturaTest`): la
  cobertura del motor se mide en cada corrida contra un corpus inventado, en vez
  de afirmarse en el README.
- **Detección de teléfono fijo** (Santiago y regiones), además del móvil. El
  público de una municipalidad incluye a mucha gente que deja el de la casa.
- **`anonimizacion.datos_sensibles`**: un sistema que declara manejar datos del
  artículo 2 letra g de la Ley 21.719 y no enlaza un `ClasificadorSensible` real
  ya no arranca, en vez de tapar solo el RUT y parecer protegido.
- **Límite y registro de los intentos con token inválido**: 10 por minuto y
  origen, con evento `pii.token_invalido` (hash de la IP, nunca la dirección ni
  el token).
- **`/health` comprueba la bóveda**: escribe y lee el store, y responde 503 si no
  contesta.

### Cambiado

- `boveda_id` es **nulo** cuando el texto no traía ningún dato personal, y
  `/restaurar` lo acepta ausente: ya no se escribe una bóveda vacía por cada
  mensaje sin PII.
- `/health` dejó de publicar `tokens_cargados`, que revelaba sin token cuántos
  sistemas consumen la API.
- Las rutas del paquete responden **siempre JSON**: antes, un cliente que no
  pedía JSON —curl, n8n, los micros de Python— recibía un 302 a la raíz en vez
  de un 422 con el motivo.
- El paquete vive en la organización `muni-graneros`, no en la cuenta personal.

### Arreglado

- **Fuga de PII por el trace de una excepción**: si el store fallaba, la
  excepción se propagaba con el texto del ciudadano entre los argumentos de la
  llamada, y el handler de Laravel lo escribía en `laravel.log`. Ahora se corta
  la cadena con `BovedaNoDisponible`, que no encadena la original.
- `composer.json` declara lo que el paquete usa de verdad (`illuminate/routing`,
  `http`, `cache`, `validation`, `psr/log`).

## [1.0.0] — 2026-08-16

Primera versión utilizable: el ciclo 1A del diseño, con el motor determinista
completo. Cubre datos estructurados; la detección de nombres y direcciones y el
clasificador de dato sensible llegan en el ciclo 1B.

### Agregado

- Detección y tokenización de **RUT** (validando el dígito verificador),
  **teléfono** móvil chileno, **correo** y **folio de seguimiento** con patrón
  configurable por instalación.
- **Bóveda de mapeo** cifrada antes de escribirse, con expiración automática de
  15 minutos, y con `__toString()`, `__debugInfo()` y `jsonSerialize()`
  neutralizados para que un `dd()` o un stack trace no muestren datos de nadie.
- **Marcadores con nonce por petición** y neutralización de los que escriba el
  ciudadano, para que nadie pueda inyectar entradas falsas al mapeo.
- **Validación de la vuelta**: un marcador que el modelo haya inventado se
  elimina, nunca se deja crudo ni se adivina a qué persona apunta.
- `ProveedorAnonimizado`, el decorador que sostiene el invariante en código: nada
  llega al proveedor sin amordazar, nada vuelve al ciudadano sin restaurar.
- **API HTTP** con tokens por consumidor rotables por separado, comparación
  timing-safe, permiso propio para `/restaurar`, límite por consumidor y
  **fail-closed**: sin tokens configurados el middleware no se construye.
- `/health` con versión y tiempo en pie, y `/metrics` en formato Prometheus
  etiquetado por consumidor (con token).
- **Auditoría** por interfaz propia, sin depender de `laravel-muni-shared`, con
  adaptador opcional hacia la `Bitacora` del módulo de privacidad. Registra tipos
  y cantidades, nunca valores; los accesos por API llevan `ip_hash` con sal.
- `composer test:redis`: la suite contra un Redis desechable, con verificación de
  que realmente corrió contra el motor.

### Notas de despliegue

- La bóveda necesita una **base Redis separada** de colas y caché.
- La clave real no es `anon:{id}` sino `laravel-database-laravel-cache-anon:{id}`;
  para auditar o purgar hay que buscar `*anon:*`. Un `cache:clear` de la
  aplicación borra las bóvedas vivas porque comparten prefijo.
- La anonimización **reduce** el riesgo legal, no lo elimina: un texto sin RUT ni
  nombre puede seguir siendo reidentificable por contexto.
