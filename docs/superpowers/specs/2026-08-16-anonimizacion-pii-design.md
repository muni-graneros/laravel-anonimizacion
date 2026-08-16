# Anonimización de PII — diseño

Fecha: 2026-08-16
Estado: aprobado, pendiente de plan de implementación
Repo: `buguenocesar92/laravel-anonimizacion` (privado)

## 1. Problema

La Municipalidad evalúa usar IA generativa externa para atención ciudadana sin
entregar datos personales al proveedor. La técnica es *PII redaction/tokenization*:
el backend reemplaza los datos personales por marcadores antes de que el texto
salga de la infraestructura, y los reinserta al recibir la respuesta.

El mismo problema aparece en los tres ecosistemas de César, con dueños distintos:

- **Graneros** (municipal, `laravel-muni-shared` y los sistemas Filament).
- **muni-kit** (producto de la empresa nueva con Gastón, multi-municipio).
- **KraftDo** (ecosistema privado, otra red).

Ninguno tiene hoy una capa de anonimización. La Ley 21.719 entra en vigencia el
2026-12-01.

## 2. Decisiones cerradas

No re-litigar; están razonadas en el historial de brainstorming.

| Decisión | Valor | Motivo |
|---|---|---|
| Forma de despliegue | Paquete instalable, N instancias | Nada cruza fronteras entre municipios; encaja con "una BD por municipio" de muni-kit |
| Corpus del RAG (ciclo futuro) | Solo guías de trámites, público | El LLM nunca recibe PII desde el corpus; solo desde lo que escribe el ciudadano |
| Propiedad del paquete | Repo neutral en cuenta personal | Evita que el producto de la empresa dependa de un paquete municipal, y evita el fork (precedente caro: `muni-ui` → `kraftdo-ui`) |
| Dato sensible en lenguaje libre | **No sale**: veto | Cierra el punto ciego que la propuesta original admite; salud es dato sensible con régimen reforzado |
| Consumo | Paquete Composer **y** API HTTP por token | Pedido explícito; hay consumidores que no son PHP |
| Topología del servicio | **Sidecar por instalación**, no central | Un servicio central haría que un municipio mandara PII de sus vecinos a un servidor que aloja a otro |

## 3. Alcance

### Ciclo 1A (este spec)

- Paquete PHP con el motor determinista: RUT, teléfono, email, folio de seguimiento.
- Bóveda de mapeo en Redis, cifrada, con TTL.
- API HTTP con tokens por consumidor, fail-closed.
- Métricas y bitácora de auditoría.

### Ciclo 1B

- Microservicio Python con NER en español (nombres, direcciones).
- Clasificador de categoría sensible (el veto).
- Batería de medición previa a elegir herramienta.

### Fuera de alcance

RAG, embeddings, MariaDB `VECTOR`, canal de WhatsApp, router de intenciones,
y la elección del proveedor externo de IA. Ninguno bloquea a 1A.

## 4. Arquitectura

```
                    ┌──────────────────────────────┐
  Laravel  ────────▶│  Paquete (en proceso)        │
  (10 sistemas)     │  motor determinista + bóveda │──┐
                    └──────────────────────────────┘  │
                                                      ├──▶ Redis (bóveda cifrada, TTL)
  n8n / Python  ───▶┌──────────────────────────────┐  │
  otros lenguajes   │  API HTTP (X-Service-Token)  │──┘
                    └──────────────┬───────────────┘
                                   │ (ciclo 1B, opcional)
                                   ▼
                    ┌──────────────────────────────┐
                    │  micro NER es + veto sensible│
                    └──────────────────────────────┘
```

El paquete es la única implementación del motor. La API es una fachada HTTP sobre
el mismo código, no una reimplementación.

## 5. Contrato público

Dos operaciones y un veredicto.

```php
interface Anonimizador
{
    public function amordazar(string $texto): Resultado;

    /** @throws BovedaExpirada */
    public function restaurar(string $respuesta, BovedaId $boveda): string;
}
```

`Resultado` es inmutable:

```php
final class Resultado
{
    public function __construct(
        public readonly Veredicto $veredicto,   // Permitido | Vetado
        public readonly string $textoSeguro,    // vacío si Vetado
        public readonly ?BovedaId $boveda,      // null si Vetado
        public readonly array $tipos,           // ['rut', 'direccion'] — NUNCA valores
    ) {}
}
```

**El paquete no decide el fallback.** Ante `Vetado` devuelve la categoría y cada
sistema resuelve si cae a la capa FAQ, al modelo local o a atención humana. Así no
se le impone política a muni-kit.

### Detectores

```php
interface Detector
{
    public function tipo(): string;            // 'rut', 'telefono', ...
    public function detectar(string $texto): array;  // Hallazgo[]
}
```

`Hallazgo` lleva tipo, posición de inicio y largo. Agregar un tipo de dato nuevo es
una clase nueva registrada en config; no se toca el motor.

Detectores del ciclo 1A:

| Detector | Regla |
|---|---|
| `DetectorRut` | Formatos con y sin puntos, con guión, dígito verificador K. **Valida el DV**: un RUT que no valida no se tokeniza, para no llenar de falsos positivos |
| `DetectorTelefono` | Móvil y fijo chilenos, con y sin `+56` |
| `DetectorEmail` | RFC pragmático, no el completo |
| `DetectorFolio` | Patrón configurable por instalación (ej. `RC-\d{4}-\d{5}`) |

Nota sobre RUT: el ecosistema tiene dos normalizaciones que nunca calzan entre sí
(`RutHelper::normalize()` produce `11111111-1`, la columna `nro_documento_norm`
produce `111111111`). Este paquete **no compara contra la base de datos**, así que
el conflicto no aplica; el detector acepta ambas formas como entrada.

## 6. API HTTP

Reusa el patrón verificado en `plataforma-graneros/ocr/app.py:55-83`.

```
POST /amordazar   → { veredicto, texto_seguro, boveda_id, tipos }
POST /restaurar   → { texto }
GET  /health      → sin token; informa versión, uptime y si hay tokens cargados
GET  /metrics     → Prometheus, etiquetado por consumidor
```

Autenticación: header `X-Service-Token`, con `SERVICE_TOKENS="sistema:token,otro:token"`
para tokens por consumidor rotables y revocables por separado. La comparación es
timing-safe y recorre **todos** los tokens, sin cortar en el primer acierto, para no
filtrar información por tiempo.

Sobre el patrón heredado se aplican tres cambios:

1. **Fail-closed.** El servicio de OCR queda abierto si falta la variable de entorno
   (documentado en `ocr/app.py:45`). Aquí no: sin `SERVICE_TOKENS`, el servicio **no
   arranca**. Un `.env` mal copiado en producción no puede dejar expuesto un endpoint
   que des-anonimiza datos de vecinos.
2. **Scope por consumidor.** `/restaurar` es la operación peligrosa: devuelve datos
   reales. Un consumidor puede tener permiso de `/amordazar` sin tenerlo de `/restaurar`.
3. **Throttle por token**, y `ip_hash` en la bitácora.

El token nunca viaja al navegador. Es servicio-a-servicio, igual que se resolvió en
`VozService`.

## 7. Seguridad

### Invariante central

> Ningún texto llega al proveedor externo sin pasar por `amordazar()`, y ninguna
> respuesta llega al ciudadano sin pasar por `restaurar()`.

Se sostiene con un cliente HTTP decorado que ya lleva ambas operaciones puestas, más
un test que falla si alguien llama al proveedor por fuera. No se confía en la
disciplina de quien escribe el código.

### Marcadores no falsificables

El ciudadano escribe por un canal abierto; nada le impide escribir `[RUT_1]` en su
mensaje para envenenar el mapeo. Dos capas, el mismo patrón que ya usa `Redactor`
para neutralizar `</datos>`:

1. Antes de tokenizar se neutraliza cualquier cosa con forma de marcador que venga
   del usuario.
2. Los marcadores reales llevan un nonce por request: `[RUT_1_a3f9]`. El atacante no
   puede adivinarlos de una conversación a otra.

### Validación de la vuelta

Los marcadores presentes en la respuesta deben ser un **subconjunto** de los de la
bóveda. Un marcador que el modelo alucinó no se deja crudo ni se intenta adivinar:
se elimina. Sin esta regla, un `[PERSONA_2]` inventado puede mapear al vecino
equivocado.

### El input es dato, no instrucción

El texto del ciudadano va delimitado y la regla se re-afirma **después** del input.
Se prueba con jailbreaks reales antes de dar la tarea por buena.

## 8. La bóveda

Redis, que ya está en el scaffold — no agrega infraestructura. La razón concreta de
elegirlo es el **TTL nativo**: la bóveda debe morir sola, sin un job de limpieza que
se puede caer.

```
SETEX anon:{boveda_id} {ttl} {payload_cifrado}
```

Tres cuidados, no opcionales porque el contenido es PII:

1. **Cifrado antes de entrar** (`Crypt::encryptString`). Redis no cifra en reposo y
   hace snapshots RDB/AOF a disco por defecto. Sin esto terminan RUT y direcciones de
   vecinos en un `.rdb` que nadie sabía que existía, y en todo backup del volumen.
   Cifrando, un dump filtrado es ciphertext.
2. **Base de datos Redis separada** de colas y caché. Compartida, un `FLUSHALL` de
   mantención o un dump de la cola se lleva la bóveda por delante.
3. `requirepass`, sin puerto publicado al host, y prohibido `KEYS *` / `MONITOR`
   sobre esa base en producción.

Además, la clase `Boveda` neutraliza `__toString()`, `__debugInfo()` y `jsonSerialize()`
para que un `dd()` o un stack trace no la impriman.

TTL por defecto: 15 minutos, configurable. Cubre una respuesta asíncrona por cola sin
dejar el mapeo vivo más de lo necesario.

## 9. Auditoría

El paquete **no depende de `laravel-muni-shared`**: hacerlo obligaría a KraftDo y a
muni-kit a arrastrar un paquete municipal, que es precisamente lo que la decisión de
neutralidad evita. En su lugar define su propia interfaz:

```php
interface RegistroDeAuditoria
{
    public function registrar(string $evento, array $datos): void;
}
```

Con dos implementaciones que se envían en el paquete: una sobre el log de Laravel
(por defecto) y un adaptador opcional hacia
`Bitacora::registrarConstancia(string $evento, array $datos)` de `laravel-muni-shared`
(verificado en `src/Privacidad/Bitacora.php:341`), que ya respeta la regla de guardar
nombres de campo y nunca valores. Los sistemas municipales enganchan el adaptador; los
demás usan el default.

Eventos: `pii.amordazado` (tipos y cantidad), `pii.vetado` (categoría), `pii.restaurado`
(consumidor e `ip_hash`). Nunca el valor real ni el texto.

## 10. Verificación

Ciclo estricto por tarea: test que falla, implementación mínima, test verde, commit.

- Un test por detector, con casos reales chilenos. Para RUT, incluye DV inválido que
  **no** debe tokenizarse.
- **Batería de jailbreaks** que debe fallar antes del fix y pasar después: marcador
  inyectado por el usuario, delimitador roto, "ignora las instrucciones anteriores".
- **Test de fuga**: se corre un flujo completo y se afirma que ningún valor real de la
  bóveda aparece en los logs de Laravel ni en la serialización del job encolado.
- **Test de arranque fail-closed**: sin `SERVICE_TOKENS`, el servicio no levanta.
- **Test del invariante**: llamar al proveedor sin pasar por el decorador falla.
- PHPStan nivel 8 sin baseline y Pint, como el resto del ecosistema.

Cada protección se comprueba quitándola: un test que pasa por vacío no protege nada
(lección repetida tres veces en el ciclo 1 de muni-kit).

## 11. Selección de herramientas (ciclo 1B)

Son tres problemas distintos y no hay una sola herramienta buena para los tres.

| Capa | Opción | Motivo |
|---|---|---|
| RUT, teléfono, email, folio | PHP + regex con validación de DV | Determinista, microsegundos, cero dependencias. Meter IA aquí sería más lento y peor |
| Nombres y direcciones | NER español (spaCy `es_core_news_*`, posiblemente vía Presidio) | Regex no puede resolverlo. Va en micro Python, patrón ya usado en `ocr` y `tts` |
| Categoría sensible (veto) | Léxico curado + umbral primero; embeddings solo si no alcanza | Clasificador binario sobre dominio acotado |

Presidio es el estándar del rubro, pero su calidad documentada es mayor en inglés que
en español: **se mide antes de adoptarlo, no se asume**. Mismo criterio que se aplicó
a PaddleOCR, que se descartó con datos (empataba en calidad y era 3x más lento).

## 12. Límites conocidos

Se declaran explícitamente para que nadie prometa de más:

1. **La anonimización reduce el riesgo legal, no lo elimina.** Un texto sin RUT ni
   nombre puede seguir siendo reidentificable por contexto ("el vecino de la casa
   verde frente a la plaza que reclamó por los escombros"). Por eso el veto por
   categoría sensible es la parte más importante del diseño, no la tokenización.
2. **Mantenimiento continuo.** Cada tipo de dato nuevo (patente, número de licencia)
   necesita su detector. El diseño lo abarata, no lo elimina.
3. **Falsos negativos.** Ningún detector es perfecto; la revisión periódica de
   falsos negativos es parte de la operación, no una fase del proyecto.

## 13. Costo del tramo externo

Cálculo verificado contra precios vigentes de Haiku 4.5 ($1 por millón de tokens de
entrada, $5 de salida): una consulta típica de ~4.000 tokens de entrada y ~400 de
salida cuesta ~0,006 USD, ~5,7 CLP. A 15.000 consultas/mes, ~85.500 CLP.

Corrección al cálculo original: el *prompt caching* baja las lecturas a ~0,1x, pero en
un RAG el contexto recuperado **cambia en cada consulta**, así que solo cachea la parte
estable (system prompt y catálogo fijo de trámites). El ahorro real ronda 25-30% del
total, no el 90% del titular. La API de lotes (50% de descuento) no aplica: la atención
por mensajería es síncrona.

## 14. Ciclos siguientes

- **Ciclo 2**: motor RAG. Reusa `licencias-graneros/app/Services/Asistente/`
  (`RecuperadorGuias`, `Faq`, umbral de match, sinónimos de verbos), que ya cubre
  buena parte, más embeddings sobre MariaDB `VECTOR` (soporte confirmado en 11.8.8).
- **Ciclo 3**: router de intenciones. `ConsultaDeclarada` y `ConsultasDatos` de
  licencias ya son exactamente esto.
- **Ciclo 4**: canal ciudadano. Específico de cada instalación; no es reusable entre
  los tres ecosistemas.
