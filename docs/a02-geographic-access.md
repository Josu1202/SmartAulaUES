# A02 · QR y enlace con validación geográfica

Trabajo local del 21/10/2026, rama `feature/p3a-a02-qr-geolocalizacion`, base `18c7596`
(merge de A01 comprobado en el checkout). Versión **0.3.2** conservada; sin commit, push,
merge ni despliegue. Evidencia y límites de esta ejecución: [a02-verification.md](a02-verification.md).

Revisión del 22/10: [adquisición acotada, diagnóstico y pasos de medición real](a02-location-measurement.md).
El usuario reportó bloqueo por precisión insuficiente en los dispositivos físicos probados. Se mejora
la adquisición para medir ese problema. Posteriormente el usuario acuerda **30 m** de radio
y **10 m** de incertidumbre máxima para docente y estudiante, conservando la fórmula. Sustituye los límites
iniciales de 5 m/2,5 m y el ensayo posterior de 50 m/15 m. **Valida proximidad aproximada al docente, no presencia dentro del aula**.
No da por aceptado A02 ni demuestra todavía viabilidad física con estos parámetros.

## Modalidad y compatibilidad

Se reutiliza `class_sessions.modality`: `in_person`, `online`, `event`. La guía v2, página 15,
describe Evento como «NFC/QR según el lugar»; no demuestra que todos los eventos sean no presenciales.
Se añade únicamente `event_attendance_mode` nullable (`in_person`/`online`) para hacer explícita esa
decisión al programar/reprogramar un evento. En las otras modalidades se ignora y guarda NULL.
No se reclasifican filas existentes: presencial/en línea conservan su significado; eventos antiguos
quedan sin ubicación definida y QR/enlace fallan con error de configuración. NFC/manual continúan.
Un evento programado puede reprogramarse con motivo para definir su ubicación. Las sesiones ya en
curso conservan la restricción existente de no reprogramarse: usar NFC/manual en ese caso.

El servidor determina la modalidad efectiva; el navegador no puede cambiarla ni el radio.
Los nuevos portales guardan esa modalidad en `access_modality`. Si difiere de la sesión, QR/enlace
se rechazan hasta cerrar/reabrir. `SessionWorkflow` solo reprograma sesiones programadas sin historial;
no añade una vía para cambiar la modalidad durante un portal abierto. La comprobación del snapshot
también protege ante cambios realizados por una vía externa al workflow.

Portales presenciales anteriores a A02, sin referencia, rechazan QR/enlace con indicación de cerrar
y reabrir; la consola del docente muestra el aviso. No se inventan coordenadas. Portales antiguos en
línea conservan el flujo sin ubicación. NFC y manual conservan autorización y reglas vigentes.

## Aplicación a portales existentes

El portal persiste `access_modality` y `geo_reference` (coordenadas, incertidumbre e instante),
**no** una copia del radio ni del máximo individual. Cada solicitud nueva se valida con 30 m/10 m
tras cargar la configuración actual del servidor. Una referencia previa ≤10 m puede seguir usándose,
siempre fija; si supera 10 m, QR/enlace devuelven `geo_reference` y exigen cerrar y abrir otro portal.
NFC/manual conservan sus reglas. Para la prueba física final, cerrar los portales anteriores y abrir
uno nuevo, recargando ambos dispositivos para obtener configuración y mensajes vigentes.

No se actualizan referencias ni evidencias históricas en lote. El replay autorizado conserva la
respuesta ya resuelta antes del cambio, incluso si esa lectura ya no pasaría los límites actuales;
no es una validación nueva. Adquirir otra lectura tras rechazo requiere una UUID nueva.

## Adquisición y decisión

Al abrir QR/enlace presencial, el usuario autorizado inicia una adquisición de hasta 15 segundos y
selecciona la mejor lectura reciente disponible. También puede iniciarla mediante Medir precisión sin
registrar; no se consulta ubicación al entrar a la página, abrir solo NFC/manual o registrar en línea.
Estudiante autenticado:
abrir QR/enlace → leer explicación → pulsar **Registrar asistencia** → permitir ubicación → esperar
confirmación del servidor. No hay seguimiento fuera de ese intento, mapas, servicios externos ni cola offline.

Parámetros centralizados en `config/geolocation.php`, compartidos con JavaScript mediante Blade:

| Parámetro | Valor |
| --- | --- |
| Radio del servidor (`radius_m`) | 30 m |
| Adquisición | `watchPosition` limitado al intento, `enableHighAccuracy: true`; `clearWatch` al terminar/cancelar/salir |
| Caché admitida por el navegador | `maximumAge: 0` |
| Duración máxima del intento y timeout solicitado a la API | 15 000 ms; temporizador propio incluye espera del permiso |
| Antigüedad al recibir una lectura nueva | ≤ 30 000 ms |
| Tolerancia a una hora futura | ≤ 5 000 ms |
| Precisión reportada por cada dispositivo (`max_accuracy_m`) | > 0 y ≤ 10 m |

Latitud/longitud/precisión/timestamp deben ser números JSON finitos, con latitud [-90,90], longitud
[-180,180], timestamp positivo en milisegundos Unix. No se aceptan strings numéricos, NaN, infinitos,
campos ausentes, precisión cero o negativa. Se comprueba la referencia también al registrar.
La referencia debe haber sido reciente al abrir el portal; no caduca a los 30 s durante ese portal.
Permanece fija hasta el cierre/vencimiento: para cambiarla se abre una nueva ventana que cierra la anterior.
La lectura del estudiante incluye el ID del portal y no puede ser anterior a su apertura salvo los 5 s
de tolerancia. Un token/evidencia de otro portal no habilita ese registro.

Distancia `d` mediante Haversine, radio terrestre medio 6 371 008,8 m. Sean `aD` y `aE` las precisiones
reportadas por docente y estudiante. Se acepta exactamente cuando **d + aD + aE ≤ 30 m**.
Si `d - (aD + aE) > 30 m`, se indica fuera del área; en los demás casos no aceptados, incertidumbre
insuficiente. No se redondea antes de decidir ni se amplía el radio. El valor guardado de distancia
se redondea a cuatro decimales después de validar. El límite inclusivo tiene prueba automatizada.

Este criterio deliberadamente conservador puede impedir autorregistro incluso muy cerca del docente,
especialmente en interiores. La API reporta estimaciones, puede ignorar la petición de alta precisión
y no garantiza ubicación física real: [especificación W3C Geolocation](https://www.w3.org/TR/geolocation/).
Coordenadas y hora provienen del cliente; no existe atestación de hardware ni protección absoluta contra
ubicación falsificada. Los 30 m son una regla de aceptación informática, no una certificación GPS.
Con incertidumbres de 10 m en ambos equipos, solo se aceptan hasta 10 m entre los centros estimados.
Puede aceptar un punto fuera del aula: comprueba proximidad aproximada a la referencia del docente,
no paredes, planta, edificio ni presencia dentro del aula.
El timeout de la API puede excluir la espera del permiso; el temporizador propio limita igualmente
el intento. El diagnóstico muestra incertidumbre y requisito, sin coordenadas ni historial de movimientos.

## Transacción, idempotencia y reintentos

La adquisición ocurre antes del POST y fuera de cualquier transacción. `RegisterAttendance` sigue
siendo el único registrador. Conserva advisory lock global UUID → ciclo → grupo → cómputo → sesión
→ portal/lector → estudiante/credencial. Geografía se valida con los locks ya tomados, después de
portal, token, cuenta e inscripción, antes de crear asistencia/auditoría. El cierre usa el mismo protocolo.
No se altera `AttendanceSummary` ni el calculador A01. Fallos geográficos no crean asistencias,
ausencias ni permisos. Solo finalizar la clase genera las ausencias según la lógica existente.

El hash canónico anterior se conserva para paquetes sin ubicación; si incluyen ubicación, incorpora
su fingerprint SHA-256 (claves superiores ordenadas; sin persistir el cuerpo). La evidencia completa
forma parte de la identidad de la operación, incluidos timestamp e ID del portal.
El replay autorizado ocurre antes de volver a comprobar antigüedad o cierre: una operación terminada
recupera su resultado aun después de vencer la lectura/portal. Cambiar evidencia con el mismo UUID da 409.
Nueva lectura tras rechazo definitivo usa nuevo UUID. Sin respuesta/red/5xx/408/429, se mantiene el
paquete exacto en memoria para reenviarlo; si el servidor nunca lo procesó y caducó, devolverá rechazo
y entonces se puede adquirir otra lectura. No se confirma presencia antes de una respuesta válida.

Botón deshabilitado durante adquisición/envío, protegido además contra llamadas simultáneas;
se restablece tras error. Errores de permiso, disponibilidad, timeout, precisión, caducidad, área y portal
tienen mensajes diferentes. Tras tres fallos geográficos en esa página (o de inmediato ante navegador/
contexto incompatible) se muestra la recomendación solicitada de acercarse/NFC/manual. No bloquea un
cuarto intento ni concede permisos manuales. El contador no se persiste ni se envía al servidor.

## Privacidad, acceso y retención

- `geo_reference` del portal contiene solo latitud/longitud/precisión/timestamp del abridor autorizado.
  Actor de apertura y modalidad quedan vinculados a la auditoría existente. Coordenadas excluidas del
  snapshot de auditoría, serialización del modelo y respuestas HTTP.
- Un evento aceptado guarda `geo_validation`: portal, distancia, precisiones, instante y resultado.
  Nunca coordenadas del estudiante. Se oculta en serialización y no tiene un endpoint/listado nuevo.
  Acceso técnico limitado a operadores autorizados de BD; la interfaz mantiene las Policies existentes.
- Denegaciones/timeout locales no escriben en BD. Rechazos recibidos por el servidor conservan solo
  el evento mínimo ya necesario para idempotencia (UUID/hash/actor/respuesta segura), sin lectura ni
  evidencia geográfica adicional. No se crea otra tabla/historial de intentos geográficos fallidos.
- `attendance:prune-location` borra referencias de portales vencidos hace más de 30 días y evidencia
  de eventos recibidos hace más de 30 días. Conserva asistencia, auditoría, UUID/hash y respuesta para
  no perder idempotencia. Declarado diario en scheduler; requiere que el operador ejecute Laravel
  scheduler. Sin scheduler, ejecutar manualmente a diario; no afirmar que el cron ya está instalado.
  Respaldos conservan su política operativa de retención y deben tratarse como privados.
- `location` se excluye de `_old_input`. No se añade a URLs, logs ni mensajes. No instalar middleware
  de registro de cuerpos que capture ubicación o tokens. Revisar también observabilidad del hosting.

`Permissions-Policy` permite `geolocation=(self)` únicamente en `sessions.show` y `checkin.show`.
Otras rutas mantienen `geolocation=()`, cámara/micrófono bloqueados y el resto de cabeceras intactas.
HTTPS móvil sigue el soporte existente de proxy y [mobile-https.md](mobile-https.md).

## Aplicar y comprobar en el equipo

Desde PowerShell en la raíz de esta rama, con copia de seguridad de la instalación compartida:

```powershell
git status --short
git branch --show-current
docker compose up -d app
docker compose exec -T app php artisan migrate:status
docker compose exec -T app php artisan migrate --pretend
docker compose exec -T app php artisan migrate
docker compose run --rm node npm run build
docker compose exec -T app php artisan optimize:clear
docker compose restart app
```

Solo hay una migración nueva: `2026_10_21_000100_add_portal_geographic_access.php`, con columnas
nullable y sin actualización de datos anteriores. Es reversible retirando esas columnas, pero se pierde
la evidencia A02 si se revierte. No usar migrate:fresh en desarrollo. No cambian variables de entorno,
dependencias, versión ni APP_KEY. `.env.example` conserva exactamente el cambio previo del usuario.

Para pruebas automatizadas preparar un `.env.testing` privado con clave existente y conexión de la base
desechable. En Compose se usa `db:5432`; no imprimir ni versionar credenciales. Cada comando con BD
debe verificar primero lectura y escritura efectivas de `siaa_test`:

```powershell
docker compose exec -T -e APP_ENV=testing -e DB_DATABASE=siaa_test app sh -c 'php scripts/check-test-database.php && php artisan test --compact --display-warnings --fail-on-warning'
docker compose exec -T -e APP_ENV=testing -e DB_DATABASE=siaa_test app sh -c 'php scripts/check-test-database.php && php tests/Concurrency/attendance-races.php --env=testing && php tests/Concurrency/attendance-summary.php --env=testing'
docker compose run --rm node npm test
docker compose run --rm node npm run build
docker compose exec -T -e GIT_CONFIG_COUNT=1 -e GIT_CONFIG_KEY_0=safe.directory -e GIT_CONFIG_VALUE_0=/var/www/html app composer validate --strict
docker compose exec -T -e GIT_CONFIG_COUNT=1 -e GIT_CONFIG_KEY_0=safe.directory -e GIT_CONFIG_VALUE_0=/var/www/html app vendor/bin/pint --test
```

RefreshDatabase reconstruye solo `siaa_test`; no ejecutar dos baterías de BD simultáneamente.
Para limpieza geográfica: `docker compose exec -T app php artisan attendance:prune-location`.
El servicio operativo de scheduler definitivo continúa siendo una responsabilidad de operación B07.

## Prueba física HTTPS pendiente

No basta con localhost o geolocalización simulada. Aplicar primero la migración y build anteriores,
y seguir **solo los pasos de arranque/configuración HTTPS** de [mobile-https.md](mobile-https.md),
sin ejecutar sus instrucciones históricas de commit/push. El usuario configura su propia URL real;
el agente no modifica `.env` ni publica el túnel como parte de esta entrega.

1. Obtener la URL HTTPS real de los logs del túnel; abrirla directamente en los dos celulares.
   Usar cuentas distintas, docente asignado y estudiante activo con inscripción vigente; sesión en horario.
2. Cerrar los portales previos y abrir uno nuevo para obtener una referencia de esta prueba.
   Programar clase presencial (o Evento con ubicación Presencial), iniciarla, seleccionar QR/enlace
   y abrir registro. Permitir ubicación del docente y esperar apertura confirmada. Si reporta >10 m,
   verificar el rechazo y probar mejor señal o abrir solamente NFC/manual. No ajustar el radio.
3. Con el estudiante cerca del punto de referencia inicial, abrir QR/enlace, autenticar y pulsar Registrar.
   Confirmar lectura puntual y resultado. Anotar separación física medida, precisión reportada y resultado;
   estar físicamente dentro no garantiza aceptación si distancia más ambas incertidumbres no cabe en 30 m.
4. Alejar al estudiante más de 30 m del punto inicial y reintentar; debe rechazarse o indicar incertidumbre,
   sin crear ausencias ni nuevas presencias. Mover al docente después de abrir: la referencia sigue inicial.
5. Denegar permiso del docente: QR/enlace no se abren; reintentar o abrir solo NFC/manual. Denegar
   permiso del estudiante: no se registra; restablecer permiso del sitio y reintentar sin recargar.
6. Probar interior/señal deficiente: precisión insuficiente tiene mensaje propio. Acumular tres fallos:
   aparece la recomendación exacta y un cuarto intento sigue disponible. No aparecen controles manuales
   para el estudiante. Probar navegador incompatible/contexto inseguro: recomendación inmediata.
7. Interrumpir la red durante el envío y restaurarla: Reintentar envío conserva la operación. Confirmar
   una sola asistencia. Cerrar portal desde otro dispositivo durante el envío: rechazar tras cierre o
   recuperar un éxito ya confirmado antes, sin inventar un registro posterior.
8. Clase En línea y Evento En línea: abrir y registrar por ambos métodos sin diálogo de ubicación.
   Portal presencial antiguo: aviso de cerrar/reabrir. Evento antiguo sin ubicación: error de configuración.
9. Comprobar NFC/manual autorizados, finalizar clase y revisar que solo la finalización genera ausencias;
   revisar resultados A01 y que ningún listado/URL muestre coordenadas.

Registrar fecha, commit/base y diff local, modelos/SO/navegadores, red, permisos, separación medida,
tiempos, precisión y resultado de cada caso con datos de prueba. No publicar coordenadas ni tokens en
capturas. Hasta completar este ensayo, la precisión física y aceptación completa de A02 quedan pendientes.
