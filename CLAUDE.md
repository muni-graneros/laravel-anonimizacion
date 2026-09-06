# laravel-anonimizacion

Paquete Composer privado de la **Municipalidad de Graneros**
(`muni-graneros/laravel-anonimizacion`), pero **neutral**: lo consumen por
igual sistemas municipales, muni-kit y KraftDo. Por eso no depende de
`laravel-muni-shared` a propósito.

## Qué es

Reemplaza datos personales de forma reconocible (RUT, teléfono, correo, folio)
por marcadores antes de que un texto salga hacia un servicio externo (por
ejemplo un embedder remoto), y los reinserta al recibir la respuesta. El
proveedor externo nunca ve el dato real.

## Qué NO es (léelo antes de tocar `src/Detectores` o `src/Anonimizador.php`)

- **No detecta nombres, direcciones ni datos sensibles.** Solo tapa lo que
  tiene forma reconocible por patrón. Un texto con un diagnóstico médico
  entero sale intacto salvo el RUT.
- Si el sistema consumidor maneja datos del art. 2 letra g de la Ley 21.719
  (salud, discapacidad, origen, creencias), debe declarar
  `datos_sensibles => true` y enlazar su propio `ClasificadorSensible`. **Sin
  clasificador real y con esa bandera activa, el paquete se niega a
  arrancar** (`Excepciones\ClasificadorAusente`) — es intencional, no un bug
  que "arreglar" relajando la comprobación.
- El clasificador de datos sensibles propio (ciclo 1B) todavía no existe.
  Hasta entonces este paquete reduce el riesgo legal, no lo elimina. No
  vender ni documentar esto como "anonimización completa".

## FAIL-CLOSED — qué NO se puede relajar y por qué

Este paquete existe para cumplir Ley 21.719 (minimización y pseudonimización
antes de enviar datos a un servicio externo). El diseño es **fail-closed** en
puntos concretos; tocarlos sin entender el porqué reintroduce fuga de PII:

- `SinClasificador` + `ClasificadorAusente`: sin clasificador, **no arranca**
  con `datos_sensibles => true`. No cambiar a un warning ni a un default
  permisivo.
- `Boveda` / `BovedaEnCache`: si no hubo hallazgos, **no se crea bóveda**
  (`SinHallazgosNoHayBovedaTest.php`) — evita guardar texto sin necesidad.
  `BovedaExpirada` y `BovedaAjena` deben seguir cortando en seco, no
  degradar a "mejor esfuerzo".
- `AuditoriaEnLog` e `IpHashTest`: la IP se audita **hasheada**, nunca en
  claro. No cambiar a loguear la IP real "para depurar mejor".
- `FugaEnLogsTest.php` y `FugaPorExcepcionTest.php`: ninguna excepción ni log
  puede filtrar el texto original o el contenido de la bóveda. Si agregás un
  log nuevo en `Anonimizador.php` o en `Http/`, correr estos dos tests antes
  de dar el cambio por bueno.
- `JailbreakTest.php` y `VetoExplicitoTest.php`: cubren intentos de que el
  texto de entrada anule las reglas de amordazamiento. Un cambio en
  `AmordazarTest`-relacionado sin correr `JailbreakTest` no está probado.

Si un subagente "necesita" que algo de esto pase para hacer avanzar una
feature, es señal de que el fix va en el consumidor, no acá.

## Versión publicada

`v1.1.0` (tags `v1.0.0`, `v1.1.0` en `origin`). El CHANGELOG bajo
`[No publicado]` ya tiene contenido — **no está en ningún tag todavía**: no
asumir que lo de esa sección ya llegó a un consumidor.

## Comandos reales

- `composer test` → `pest` (suite completa contra el store de array).
- `composer test:redis` → `tools/pest-redis.sh` — **obligatorio antes de
  publicar**: la bóveda vive en Redis en producción y el store de array no
  expira ni serializa igual.

## Cómo se prueba

`composer test` para el día a día. Antes de publicar o de tocar
`BovedaEnCache.php`, `Boveda.php` o cualquier `Excepciones/Boveda*`, correr
`composer test:redis` — el array no reproduce expiración real.

## Cómo se publica

**César hace el push y el tag.** Antes de pedírselo:

1. `composer test` y `composer test:redis` en verde.
2. CHANGELOG: mover lo de `[No publicado]` a una sección de versión real con
   fecha, sin reescribir el contenido de un tag ya publicado (pasó una vez:
   ver nota al inicio del CHANGELOG sobre `v1.0.0`).

## Consumidores

Solo `laravel-rag` (v1.1.0), como `require-dev`, pin `dev-develop`, vía path
repository (`../laravel-anonimizacion`). Si cambia un contrato público
(`Contratos/Detector`, `Contratos/ProveedorExterno`, `Contratos/ClasificadorSensible`,
`Contratos/RepositorioDeBoveda`), correr la suite de `laravel-rag` antes de dar
el cambio por cerrado — especialmente `VectorizadorAnonimizadoTest.php`.

## Qué NO hacer acá

- No relajar ninguno de los puntos fail-closed de la sección de arriba.
- No agregar un detector nuevo sin su propio test en `tests/Detectores/` y sin
  medir su efecto en `CoberturaTest.php`.
- No prometer en el README algo que la suite no cubre — ya pasó una vez con el
  CHANGELOG de `v1.0.0` y costó confianza.
- No hacer que este paquete dependa de `laravel-muni-shared`: es neutral a
  propósito para que KraftDo lo use sin arrastrar código municipal.
