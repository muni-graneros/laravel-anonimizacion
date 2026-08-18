# laravel-anonimizacion

Reemplaza los datos personales de un texto por marcadores antes de que salga
hacia un servicio externo, y los reinserta al recibir la respuesta. El proveedor
externo nunca ve el RUT, el teléfono ni el correo de la persona.

Es un paquete neutral: lo consumen por igual los sistemas municipales de
Graneros, la plataforma muni-kit y el ecosistema KraftDo. **No depende de
`laravel-muni-shared`** a propósito, para que un producto no municipal no tenga
que arrastrar un paquete municipal.

## Instalación

El paquete es privado, así que se declara el repositorio VCS:

```json
{
    "repositories": [
        { "type": "vcs", "url": "git@github.com:buguenocesar92/laravel-anonimizacion.git" }
    ],
    "require": {
        "buguenocesar92/laravel-anonimizacion": "^1.0"
    }
}
```

```bash
composer update buguenocesar92/laravel-anonimizacion
php artisan vendor:publish --tag=anonimizacion-config
```

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
habilita con `ANONIMIZACION_API_HABILITADA=true`.

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
# {"servicio":"anonimizacion","tokens_cargados":2}
```

`/restaurar` devuelve datos reales: tener un token válido no alcanza, hace falta
el permiso explícito. El token es servicio a servicio y **nunca** debe llegar al
navegador.

El límite es de **60 peticiones por minuto y por consumidor**, no por IP: todos
los sistemas del ecosistema salen por la misma IP interna, así que contar por IP
haría que un consumidor ruidoso dejara sin cuota a los demás.

## Variables de entorno

| Variable | Por defecto | Para qué |
|---|---|---|
| `ANONIMIZACION_TTL_BOVEDA` | `900` | Segundos que vive el mapeo marcador → valor real |
| `ANONIMIZACION_STORE_BOVEDA` | `redis` | Store de caché de la bóveda. Ver la advertencia de abajo |
| `ANONIMIZACION_PATRON_FOLIO` | vacío | Expresión regular del número de seguimiento de esta instalación |
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

**Fail-closed**: si la API está habilitada y `ANONIMIZACION_TOKENS` está vacío,
el middleware se niega a construirse. Es deliberado: un `.env` mal copiado no
puede dejar abierto un endpoint que des-anonimiza datos.

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

Eventos: `pii.amordazado` (tipos y cantidad), `pii.vetado` (categoría),
`pii.restaurado` (cantidad de marcadores).

## Qué cubre hoy

Detecta y tokeniza **RUT** (validando el dígito verificador, para no marcar
montos ni folios), **teléfono** móvil chileno, **correo** y **folio de
seguimiento** con el patrón de cada instalación.

El veto por categoría sensible existe como contrato desde ahora, con una
implementación que nunca veta (`SinClasificador`). El ciclo 1B la reemplaza por
el clasificador real —y por la detección de nombres y direcciones— sin que
ningún consumidor cambie una línea.

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
composer test                                        # Pest
vendor/bin/pint                                      # estilo
vendor/bin/phpstan analyse --memory-limit=1G         # nivel 8, sin baseline
```

Toda protección se comprueba quitándola: si al borrar la guarda el test sigue
verde, el test no protege nada. La batería de `tests/JailbreakTest.php` y
`tests/FugaEnLogsTest.php` está verificada contra ese criterio.

Diseño y plan: `docs/superpowers/`.
