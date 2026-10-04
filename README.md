# laravel-anonimizacion

Reemplaza los datos personales de un texto por marcadores antes de que salga
hacia un servicio externo, y los reinserta al recibir la respuesta. El proveedor
externo nunca ve el RUT, el teléfono ni el correo de la persona.

> ### Lo que este paquete NO hace todavía
>
> **No detecta nombres, direcciones ni datos sensibles.** Tapa datos con forma
> reconocible —RUT, teléfono, correo, folio— y eso es todo. Un texto como
> «credencial de discapacidad de mi hijo, que tiene autismo» sale con el RUT
> tapado y el diagnóstico entero a la vista.
>
> Si tu sistema maneja datos del artículo 2 letra g de la Ley 21.719 —salud,
> situación de discapacidad, origen, creencias— pon `ANONIMIZACION_DATOS_SENSIBLES=true`
> y enlaza tu propio `ClasificadorSensible`. Con esa bandera y sin clasificador
> real, el paquete **se niega a arrancar** (`ClasificadorAusente`): es preferible
> a que alguien lo instale creyendo que veta.
>
> El clasificador propio llega en el ciclo 1B. Hasta entonces, este paquete
> reduce el riesgo legal; no lo elimina.

Es un paquete neutral: lo consumen por igual los sistemas municipales de
Graneros, la plataforma muni-kit y el ecosistema KraftDo. **No depende de
`laravel-muni-shared`** a propósito, para que un producto no municipal no tenga
que arrastrar un paquete municipal.

## Requisitos

- PHP **^8.3**
- Laravel **11, 12 o 13** (`illuminate/*` `^11.0|^12.0|^13.0`)
- Un store de caché para la bóveda. En producción, **Redis en una base propia**
  (ver más abajo); el store `array` solo sirve para tests.

## Instalación

El paquete es privado, así que se declara el repositorio VCS. Composer se
autentica con un PAT de GitHub configurado **fuera del repo**
(`composer config --global --auth github-oauth.github.com <token>` o la variable
`COMPOSER_AUTH`); el token no va nunca en `composer.json` ni en el `.env`
versionado.

```json
{
    "repositories": [
        { "type": "vcs", "url": "git@github-graneros:muni-graneros/laravel-anonimizacion.git" }
    ],
    "require": {
        "muni-graneros/laravel-anonimizacion": "^1.1"
    }
}
```

Versión publicada al día de hoy: **v1.1.0**. El paquete ya salió de `0.x`, así
que el caret se comporta como uno espera (`^1.1` acepta 1.2, 1.3…, no 2.0). Ojo
con el resto de los paquetes del ecosistema que siguen en `0.x`: ahí `^0.2`
**no** trae 0.3.

```bash
composer update muni-graneros/laravel-anonimizacion
php artisan vendor:publish --tag=anonimizacion-config
```

El service provider se descubre solo. `anonimizacion-config` es el **único** tag
publicable: el paquete no trae migraciones, vistas ni assets.

## Qué expone

| Pieza | Para qué |
|---|---|
| `Anonimizacion\Anonimizador` | El motor: `amordazar()` y `restaurar()` |
| `Anonimizacion\ProveedorAnonimizado` | Decorador que sostiene el invariante; es lo que se consume |
| `Anonimizacion\Resultado`, `Veredicto`, `Hallazgo`, `BovedaId` | Tipos de la respuesta |
| `Contratos\ProveedorExterno` | Lo que implementa tu cliente de IA |
| `Contratos\ClasificadorSensible` | El veto por categoría sensible (por defecto `SinClasificador`, que nunca veta) |
| `Contratos\RegistroDeAuditoria` | Dónde se registran los eventos (por defecto `AuditoriaEnLog`) |
| `Contratos\RepositorioDeBoveda` | Dónde vive el mapeo (por defecto `BovedaEnCache`) |
| `Contratos\Detector` | Para sumar un tipo de dato propio |
| `Excepciones\*` | `BovedaExpirada`, `BovedaAjena`, `BovedaNoDisponible`, `ApiSinTokens`, `ClasificadorAusente` |
| Rutas HTTP bajo `/anonimizacion` | Solo si `ANONIMIZACION_API_HABILITADA=true` |

Los cuatro detectores que vienen enlazados: `DetectorRut`, `DetectorTelefono`,
`DetectorEmail` y `DetectorFolio`.

## Uso como paquete

Se consume **siempre** el decorador `ProveedorAnonimizado`, nunca el proveedor
externo desnudo. Ese es el invariante del sistema: ningún texto llega al
proveedor sin pasar por `amordazar()`, y ninguna respuesta vuelve al ciudadano
sin pasar por `restaurar()`.

```php
use Anonimizacion\Contratos\ProveedorExterno;
use Anonimizacion\ProveedorAnonimizado;

// En un service provider del sistema:
$this->app->bind(ProveedorExterno::class, fn ($app) => new ProveedorAnonimizado(
    new MiClienteDeIa(config('services.ia.token')),   // tu cliente real
    $app->make(\Anonimizacion\Anonimizador::class),
));
```

```php
// En el consumidor:
$respuesta = app(ProveedorExterno::class)->preguntar($mensajeDelCiudadano);
```

Si el texto trae dato sensible, el decorador devuelve cadena vacía y **no llama
al proveedor**. El paquete no decide el reemplazo: cada sistema resuelve si cae
a su capa de preguntas frecuentes, a un modelo local o a atención humana.

```php
use Anonimizacion\ProveedorAnonimizado;
use Anonimizacion\Veredicto;

if ($proveedor->veredictoDe($mensaje) === Veredicto::Vetado) {
    return $this->respuestaCurada($mensaje);
}
```

## Uso por API

Para consumidores que no son PHP (n8n, servicios Python, otros sistemas). Se
habilita con `ANONIMIZACION_API_HABILITADA=true`; con la bandera en `false` las
rutas ni siquiera se registran.

```bash
# Amordazar
curl -s -X POST https://mi-sistema.local/anonimizacion/amordazar \
  -H "X-Service-Token: $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"texto":"Soy Juan, RUT 12.345.678-5"}'

# {"veredicto":"permitido","categoria":null,
#  "texto_seguro":"Soy Juan, RUT [RUT_1_a3f9]",
#  "boveda_id":"9f2c...","tipos":["rut"]}

# Restaurar (requiere estar en ANONIMIZACION_PUEDEN_RESTAURAR)
curl -s -X POST https://mi-sistema.local/anonimizacion/restaurar \
  -H "X-Service-Token: $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"texto":"Hola [RUT_1_a3f9]","boveda_id":"9f2c..."}'

# {"texto":"Hola 12.345.678-5"}

# Salud (sin token)
curl -s https://mi-sistema.local/anonimizacion/health
# {"servicio":"anonimizacion","version":"1.0.0","segundos_en_pie":184,"boveda":"ok"}

# Métricas Prometheus (CON token: el volumen por consumidor es información
# de negocio, no algo que deba quedar abierto)
curl -s https://mi-sistema.local/anonimizacion/metrics -H "X-Service-Token: $TOKEN"
# # TYPE anonimizacion_peticiones_total counter
# anonimizacion_peticiones_total{consumidor="licencias",operacion="amordazar"} 42
```

Detalles que hacen falta para programar contra esto:

- **`boveda_id` es `null`** cuando el texto no traía ningún dato personal: no se
  escribe una bóveda vacía por cada mensaje. `/restaurar` acepta que se lo
  omitan y devuelve el texto tal cual.
- Si se manda, `boveda_id` tiene que ser el que dio el paquete: **32
  hexadecimales**; cualquier otra cosa es 422.
- `/restaurar` responde **403** tanto cuando el consumidor no está autorizado
  como cuando la bóveda es de otro consumidor. Es el mismo error a propósito: no
  hay que decirle a quien pregunta si el id existe.
- Una bóveda vencida da error de restauración (`BovedaExpirada`), no un texto a
  medias.
- **`/health` toca la bóveda de verdad**: escribe y lee el store, y responde
  **503** con `"boveda":"caida"` si no contesta. Sirve como sonda de Uptime Kuma.
  No publica cuántos tokens hay cargados: cuántos sistemas consumen esta API es
  información de negocio.
- Todas las rutas responden **siempre JSON**, aunque el cliente mande
  `Accept: */*`; un fallo de validación es 422, nunca un 302 a la raíz.

`/restaurar` devuelve datos reales: tener un token válido no alcanza, hace falta
el permiso explícito. El token es servicio a servicio y **nunca** debe llegar al
navegador.

### Límites de tasa

- **60 peticiones por minuto y por consumidor** en `amordazar`, `restaurar` y
  `metrics`. Se cuenta por consumidor y no por IP: todos los sistemas del
  ecosistema salen por la misma IP interna, así que contar por IP haría que un
  consumidor ruidoso dejara sin cuota a los demás.
- **10 tokens inválidos por minuto y por origen**, con 429 y `Retry-After`. Este
  sí cuenta por IP, porque en un 401 todavía no hay consumidor. Un acierto limpia
  el contador, para que nadie pueda dejar fuera a los demás fallando a propósito.

## Variables de entorno

| Variable | Por defecto | Para qué |
|---|---|---|
| `ANONIMIZACION_TTL_BOVEDA` | `900` | Segundos que vive el mapeo marcador → valor real |
| `ANONIMIZACION_STORE_BOVEDA` | `redis` | Store de caché de la bóveda. Ver la advertencia de abajo |
| `ANONIMIZACION_PATRON_FOLIO` | vacío | Expresión regular del número de seguimiento de esta instalación |
| `ANONIMIZACION_DATOS_SENSIBLES` | `false` | Declara que el sistema maneja datos del art. 2 g de la Ley 21.719. En `true` **exige** un `ClasificadorSensible` real o el paquete no arranca |
| `ANONIMIZACION_API_HABILITADA` | `false` | Expone las rutas HTTP |
| `ANONIMIZACION_TOKENS` | vacío | `sistema:token,otro:token` — uno por consumidor, rotables por separado |
| `ANONIMIZACION_PUEDEN_RESTAURAR` | vacío | Consumidores autorizados a llamar `/restaurar` |

### Redis: tres cuidados que no son opcionales

La bóveda contiene datos personales. Antes de desplegar:

1. **Base de datos Redis separada** de colas y caché. Compartida, un `FLUSHALL`
   de mantención se lleva la bóveda por delante y un dump de la cola arrastra
   datos de vecinos.
2. **`requirepass` y sin puerto publicado al host.** Nada de `KEYS *` ni
   `MONITOR` sobre esa base en producción.
3. El paquete ya **cifra el contenido antes de escribirlo**, porque Redis no
   cifra en reposo y hace snapshots a disco: un `.rdb` filtrado debe ser
   ciphertext. No hace falta configurarlo, pero sí saber que la clave es la
   `APP_KEY` del sistema — rotarla invalida las bóvedas vivas, que expiran solas
   en 15 minutos.

**La clave real en Redis no es `anon:{id}`.** Laravel le antepone el prefijo del
caché y el de la conexión, y termina siendo algo como
`laravel-database-laravel-cache-anon:{id}`. Importa en dos momentos:

- Para auditar o purgar bóvedas hay que buscar `*anon:*`, no `anon:*`.
- El prefijo del caché es **compartido con toda la caché de la aplicación**, así
  que un `php artisan cache:clear` se lleva las bóvedas vivas por delante. No
  rompe nada — las peticiones en vuelo fallan con `BovedaExpirada` y el
  ciudadano reintenta — pero explica un pico de errores tras un despliegue.

**Fail-closed**: si la API está habilitada y `ANONIMIZACION_TOKENS` está vacío,
el middleware se niega a construirse (`ApiSinTokens`). Es deliberado: un `.env`
mal copiado no puede dejar abierto un endpoint que des-anonimiza datos.

## Auditoría

Por defecto los eventos van al log de Laravel con el tipo y la cantidad de datos,
**nunca el valor**. Los sistemas municipales pueden enviarlos a la bitácora del
módulo de privacidad enlazando un adaptador:

```php
use Anonimizacion\Contratos\RegistroDeAuditoria;
use Muni\Shared\Privacidad\Bitacora;

$this->app->bind(RegistroDeAuditoria::class, fn ($app) => new class($app->make(Bitacora::class)) implements RegistroDeAuditoria
{
    public function __construct(private readonly Bitacora $bitacora) {}

    public function registrar(string $evento, array $datos): void
    {
        $this->bitacora->registrarConstancia($evento, $datos);
    }
});
```

Eventos que emite:

| Evento | Qué lleva |
|---|---|
| `pii.amordazado` | Tipos y cantidad de datos tapados |
| `pii.vetado` | Categoría sensible que disparó el veto |
| `pii.restaurado` | Cantidad de marcadores repuestos |
| `pii.acceso_api` | Consumidor e `ip_hash`: quién des-anonimizó y desde dónde |
| `pii.token_invalido` | `ip_hash` y ruta del intento fallido; nunca el token |
| `anonimizacion.health_boveda_caida` | Motivo del fallo del store (al log, no a la respuesta) |

La IP es dato personal, así que se guarda un hash con sal (la `APP_KEY`):
permite correlacionar accesos del mismo origen sin almacenar la dirección, y no
se puede cruzar entre instalaciones distintas.

## Qué cubre hoy

Detecta y tokeniza **RUT** (validando el dígito verificador, para no marcar
montos ni folios), **teléfono** chileno —móvil, fijo de Santiago y fijo de
regiones, con separadores en cualquier posición—, **correo** y **folio de
seguimiento** con el patrón de cada instalación.

El veto por categoría sensible existe como contrato desde ahora, con una
implementación que nunca veta (`SinClasificador`). El ciclo 1B la reemplaza por
el clasificador real —y por la detección de nombres y direcciones— sin que
ningún consumidor cambie una línea.

### Cobertura medida, no supuesta

`tests/CoberturaTest.php` corre el motor contra un corpus de 16 mensajes
ciudadanos (`tests/Corpus/mensajes.php`, **todo inventado** — nunca se versiona
el mensaje ni el RUT de una persona real) y publica la medición en cada corrida:

| Tipo | Detectado |
|---|---|
| RUT | 3/3 (100%) |
| Teléfono | 3/3 (100%) |
| Correo | 3/3 (100%) |
| Nombre | 0/2 (0%) |
| Dirección | 0/2 (0%) |
| Categoría sensible | 0/3 (0%) |
| **Falsos positivos** | **0** |

Los tres ceros no son una sorpresa: son el ciclo 1B, anotados a propósito en el
corpus para que el hueco quede **medido** y no supuesto. Cualquier herramienta que
se evalúe para cubrirlos —Presidio, spaCy, lo que sea— se compara contra esta misma
vara, con datos y no por catálogo. El test falla si se regresiona en lo que ya
funciona o si aparece un falso positivo nuevo.

## Límites conocidos

Se declaran para que nadie prometa de más:

1. **La anonimización reduce el riesgo legal, no lo elimina.** Un texto sin RUT
   ni nombre puede seguir siendo reidentificable por contexto ("el vecino de la
   casa verde frente a la plaza que reclamó por los escombros"). Por eso el veto
   por categoría sensible es la parte más importante del diseño, no la
   tokenización.
2. **Mantenimiento continuo.** Cada tipo de dato nuevo (patente, número de
   licencia) necesita su detector. El diseño lo abarata, no lo elimina.
3. **Falsos negativos.** Ningún detector es perfecto. La revisión periódica de
   falsos negativos es parte de la operación, no una fase del proyecto.

## Desarrollo

```bash
composer install
composer test                                        # suite contra el store de array
composer test:redis                                  # la misma suite contra un Redis real
vendor/bin/pint                                      # estilo
vendor/bin/phpstan analyse --memory-limit=1G         # nivel 8, sin baseline
```

`composer test:redis` es **obligatorio antes de publicar una versión**: levanta un
Redis desechable, corre la suite contra él y después comprueba que realmente corrió
contra ese motor (si no quedaron bóvedas escritas, el verde no vale). El store de
array no expira de verdad ni serializa a texto — probar solo contra él ya ocultó
que la clave lleva prefijos y que phpredis prefija de nuevo al leer.

Toda protección se comprueba quitándola: si al borrar la guarda el test sigue
verde, el test no protege nada. La batería de `tests/JailbreakTest.php` y
`tests/FugaEnLogsTest.php` está verificada contra ese criterio.

Diseño y plan: `docs/superpowers/`.
