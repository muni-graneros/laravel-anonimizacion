# Changelog

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/);
versionado según [SemVer](https://semver.org/lang/es/).

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
- **Corpus de evaluación y medición de cobertura** (`tests/CoberturaTest.php`):
  publica en cada corrida cuánto detecta el motor por tipo. Línea base: RUT,
  teléfono y correo al 100%, cero falsos positivos; nombres, direcciones y
  categoría sensible al 0%, que es el ciclo 1B. Todos los datos del corpus son
  inventados.

### Notas de despliegue

- La bóveda necesita una **base Redis separada** de colas y caché.
- La clave real no es `anon:{id}` sino `laravel-database-laravel-cache-anon:{id}`;
  para auditar o purgar hay que buscar `*anon:*`. Un `cache:clear` de la
  aplicación borra las bóvedas vivas porque comparten prefijo.
- La anonimización **reduce** el riesgo legal, no lo elimina: un texto sin RUT ni
  nombre puede seguir siendo reidentificable por contexto.
