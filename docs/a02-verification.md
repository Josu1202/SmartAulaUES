# A02 · verificación local

## Parámetros finales del 22/10/2026 · 30 m/10 m

Configuración vigente: radio **30 m**, incertidumbre **>0 y ≤10 m** para docente y estudiante;
aceptación `distancia estimada + incertidumbre docente + incertidumbre estudiante ≤30 m`.
Solo QR/enlace presenciales. Referencia fija; adquisición ≤15 s, mejor lectura reciente, liberación,
reintentos y NFC/manual tras tres fallos conservados. Proximidad aproximada al docente, sin acreditar
presencia dentro del aula. No añade dependencias ni migraciones ni cambia env/clave/release.

Cada intento nuevo aplica los límites actuales también en portales abiertos. Una referencia guardada
>10 m requiere cerrar/reabrir. Replay devuelve la respuesta histórica ya resuelta, sin revalidarla ni
reescribir la evidencia. Cobertura nueva para ambas transiciones, además de límites 10 m/30 m.

| Control de esta revisión 30 m/10 m | Resultado observado |
| --- | --- |
| Carreras attendance-races.php, preflight en siaa_test | **6 comprobaciones correctas** contra PostgreSQL nativo |
| PHP completa, preflight lectura/escritura en siaa_test | **159 pruebas / 1077 aserciones**, sin warnings; 260,89 s; salida 0 |
| JavaScript, npm test | **39/39** |
| Build, npm run build | Correcto, 72 módulos, 35,68 s; aviso Sass mixed-decls heredado |
| Composer validate --strict | Correcto |
| Pint --test | Correcto, 169 archivos; verificación posterior del archivo de pruebas editado también correcta |

La ejecución enfocada intermedia tuvo 23 casos correctos y dos aserciones nuevas fallidas al comparar
el JSON del modelo recién creado con el formato normalizado de JSONB. Se corrigieron los fixtures
para capturar la referencia ya leída de PostgreSQL; no se alteró validación, esquema ni arnés/CI.
Esa ejecución intermedia no se presenta como validación final. La batería completa posterior cubre
esas comprobaciones además de los límites 10 m/30 m, privacidad, modalidades, NFC/manual y A01.

Recorrido simulado final **PASS** con Edge 155.0.4283.62, localhost:8091 y siaa_test:

- Docente y estudiante: selección de mejor lectura 18 m entre 30 → 18 → 25 m, rechazo por límite
  10 m sin POST de registro; ventana real del navegador de aproximadamente 15 s y watch liberado.
- Cancelar/ocultar libera adquisición y restaura controles; el estudiante mide sin portal ni POST.
- Tres denegaciones recomiendan NFC/manual; el cuarto intento selecciona 10 m para ambos roles
  y abre/registra correctamente. Mensaje final: proximidad aproximada y asistencia confirmada.
- En línea abre y registra con cero adquisiciones. Cero excepciones JavaScript.
- Cuatro medidas en 320/320/1440/390 px sin desbordamiento de página. Capturas móviles de docente
  y estudiante inspeccionadas. Auxiliares/capturas fuera del repositorio, en trial-30m.

Se verificó lectura/escritura efectiva de siaa_test antes de RefreshDatabase y de las carreras,
mediante scripts/check-test-database.php. Se conservó arnés/CI. Los fixtures de navegador comprobaron
la misma base y se retiraron por IDs/correos verificados; navegador y servidor temporal cerrados.
La copia .env.testing privada temporal se retiró. .env y la modificación previa de .env.example
conservan sus SHA-256; no se regeneró APP_KEY. Sin nuevas migraciones ni escrituras de desarrollo.
El build generado queda ignorado y Microsoft/ sigue fuera del incremento. Durante esa ejecución
de pruebas no se hizo staging/commit/push; la preparación posterior del índice no cambia estos resultados.

Los resultados que siguen en los registros históricos pertenecen a 50 m/15 m o 5 m/2,5 m;
**no constituyen validación de 30 m/10 m**. Los resultados de esta revisión se registran separadamente.
Configuración efectiva comprobada con `php artisan config:show geolocation`: 30, 10 y 15000 ms.
La comprobación física por HTTPS con dos dispositivos y esta configuración final está **pendiente**.
Sin commit, push ni merge. A02 no se declara completamente validado por pruebas automáticas.

## Registro histórico del 22/10/2026 · ensayo de 50 m/15 m (sustituido)

Por instrucción posterior del usuario, radio del servidor **50 m** e incertidumbre individual
**>0 y ≤15 m** para docente y estudiante, conservando `distancia + ambas incertidumbres ≤50 m`.
Sustituye los límites iniciales de 5 m/2,5 m de los registros históricos inferiores. Solo QR/enlace
presenciales (incluido Evento explícitamente presencial). No cambia NFC/manual ni clases en línea.
Proximidad aproximada a la referencia fija del docente; no acredita presencia dentro del aula.
Con incertidumbre de 15 m en ambos equipos, quedan 20 m para la distancia estimada entre centros.

Radio y precisión en config/geolocation.php, compartidos con mensajes Blade/JavaScript. Se mantienen
adquisición ≤15 s, mejor lectura reciente, cancelación/liberación, reintentos y alternativa tras tres
fallos. Sin migración nueva, variables env, coordenadas en logs ni historial de movimientos.

| Comprobación de esta configuración | Resultado observado |
| --- | --- |
| PHP completa en siaa_test, preflight lectura/escritura | **157 pruebas / 1049 aserciones**, sin warnings; 238,06 s; salida 0 |
| GeographicAttendanceTest, preflight en siaa_test | **23 pruebas / 218 aserciones**, sin warnings; 72,95 s |
| JavaScript | **39/39** |
| Build | Correcto, 72 módulos, 38,80 s; aviso Sass mixed-decls heredado |
| Composer validate --strict | Correcto |
| Pint --test | Correcto, 169 archivos |
| Carreras attendance-races.php | 6 comprobaciones nativas correctas |
| Lecturas colectivas attendance-summary.php | 2 comprobaciones nativas correctas en repetición aislada. La ejecución inicial agotó el plazo de observación del bloqueo; se registra también ese fallo. Sin modificar arnés ni tiempos. |

Recorrido de navegador simulado **PASS**, Edge 155.0.4283.62, localhost:8091 y siaa_test:

- Secuencia 30 → 18 → 25 m: mejor lectura 18 m, tres lecturas, rechazo sin POST y liberación
  después de una ventana de aproximadamente 15 s. Diagnóstico muestra requisito de 15 m.
- Cancelar/ocultar libera y restaura controles; el estudiante puede medir sin portal ni POST.
- Docente y estudiante: tres permisos denegados muestran NFC/manual; cuarto intento selecciona
  15 m y completa apertura y registro. Radio mostrado 50 m y aclaración de proximidad aproximada.
- En línea abre y registra con cero adquisiciones. Cero excepciones JavaScript.
- Cuatro medidas a 320/320/1440/390 px sin desbordamiento de página. Se inspeccionaron las
  capturas móviles del docente y del estudiante. Auxiliares fuera del repositorio en trial-50m.

Las pruebas incluyen docente/estudiante en el límite individual 15 m y rechazo de 15,0001 m,
ambos métodos, intento de alterar límites desde el cliente, suma exacta 20 + 15 + 15 = 50 aceptada,
20,000001 + 15 + 15 rechazada sin redondear, distancia central 49 m con incertidumbre insuficiente
y 81 m fuera del área. Se comprueban mensajes 50 m/15 m y alcance de proximidad en las pantallas.
La batería mantiene privacidad, autorización, modalidad, antigüedad, replay y A01.

Se retiraron los fixtures por IDs/correos verificados y se cerraron navegador y servidor temporal.
La copia privada temporal .env.testing se retiró; .env y el cambio previo de .env.example conservan
sus SHA-256. Tras la interrupción, los servicios Compose están detenidos: no se atribuye una recarga
operativa de app a esta comprobación. El frontend está compilado; al reanudar, seguir la guía de
actualización y recargar ambos dispositivos. Sin migraciones ni escrituras de desarrollo.

Prueba física con esta configuración **pendiente**. Los fallos reales reportados por el usuario
corresponden a los límites previos; no hay mediciones inventadas ni aceptación de la configuración
nueva. [Pasos sencillos para docente y estudiante](a02-location-measurement.md).
A02 sigue abierto y versión 0.3.2 conservada. Sin commit/push/merge/despliegue.

## Registro anterior del 22/10/2026 · adquisición y diagnóstico (5 m/2,5 m)

El usuario reportó bloqueo por precisión insuficiente en todos los dispositivos físicos probados,
sin valores/modelos individuales. Se implementó una mejora acotada para medir ese problema;
no se cambiaron 5 m, el límite individual 2,5 m, la fórmula ni la validación del servidor.
[Alcance y pasos de medición real](a02-location-measurement.md). A02 continúa abierto.

| Comprobación de esta revisión | Resultado observado |
| --- | --- |
| PHP completa en siaa_test, preflight de lectura/escritura | **156 pruebas / 1021 aserciones**, sin warnings; 348,84 s; salida 0 |
| JavaScript final | **38/38**, incluidos reloj simulado, deadline, selección y liberación |
| Build final | Correcto, 72 módulos, 37,56 s; aviso Sass mixed-decls heredado |
| Composer validate --strict | Correcto |
| Pint --test completo | Correcto, 169 archivos |
| Carreras attendance-races.php | 6 comprobaciones nativas correctas |
| Lecturas colectivas attendance-summary.php | **2 comprobaciones nativas correctas en repetición aislada**. Primera ejecución junto al navegador: resolución agotó plazo de observación; se conserva ese hallazgo, sin modificar arnés ni tiempos. |
| git diff --check | Correcto |

La batería PHP conserva el arnés/CI y usa RefreshDatabase solo en siaa_test, tras comprobar ambas
conexiones efectivas con scripts/check-test-database.php. Se preparó .env.testing privado temporal,
sin regenerar la clave, y se retiró al terminar. No se ejecutó migración ni otra escritura de desarrollo
para este incremento. .env y el cambio previo de .env.example conservaron sus huellas SHA-256.

Las pruebas JavaScript verifican selección de la candidata de mejor precisión reciente y desempate
por instante, descarte de lecturas inválidas/caducadas/futuras, precisión insuficiente tras la ventana,
deadline incluso sin respuesta del permiso, errores transitorios, denegación inmediata, cancelación,
visibilitychange/pagehide, callbacks tardíos, limpieza del watch con ID cero/timer/listeners, controles,
diagnóstico sin coordenadas, reintento tras tres fallos e idempotencia del paquete tras error de red.
Cancelar no cuenta como fallo ni crea/envía una operación. La medición diagnóstica no envía POST.

Se añadieron dos pruebas PHP de render/autorización: docente y estudiante inscritos pueden medir sin
portal vigente; terceros no acceden. Invitados y clases en línea no reciben controles de adquisición.
Consultar estas pantallas no crea portales, asistencias ni eventos. El resto de reglas A02/A01 se conserva.

Recorrido final de navegador **PASS** con Edge 155.0.4283.62, localhost:8091, siaa_test y datos ficticios:

- Secuencia simulada 20 → 8 → 12 m: muestra 8 m, tres lecturas, rechaza precisión y envía cero POST;
  ventana medida de aproximadamente 15 s con reloj real del navegador, watch liberado al terminar.
- Cancelar y ocultar la pestaña liberan adquisición/restauran controles y no reinician al volver.
- Estudiante mide en el detalle autorizado de su clase, sin portal abierto ni POST de asistencia.
- Docente y estudiante: tres permisos denegados muestran alternativa; el cuarto intento usa la mejor
  lectura reciente de 1,5 m y completa apertura/registro mediante las acciones existentes.
- En línea conserva apertura/registro sin iniciar ninguna adquisición. Cero excepciones JavaScript.
- Cuatro medidas/capturas: docente con fallo a 320 y éxito a 1440; estudiante sin portal a 320 y éxito
  a 390 px. Anchos de documento 320/1425/320/390: sin desbordamiento horizontal. Capturas inspeccionadas.

La simulación comprueba el flujo y la selección; no demuestra que dispositivos reales alcancen el
límite. El informe físico del usuario continúa siendo un fallo de precisión, pendiente de nuevas mediciones.
Los auxiliares, capturas y mediciones están fuera del repositorio en smartaulaues-a02-review.
Se retiraron los fixtures por IDs/correos verificados y se cerraron navegador y servidor temporal.
No se atribuyen resultados a CI remoto, GPS físico, Cloudflare o aceptación institucional.

Archivos de esta revisión: resources/js/geographic-access.js y attendance-live.js; nuevo parcial
resources/views/attendance/location-acquisition.blade.php; checkin y sessions/show; pruebas JS de
adquisición y GeographicAttendanceTest; guía nueva y continuidad README/AGENTS/CHANGELOG/estado/
decisiones/contrato/evidencia. Sin nueva migración, dependencia, configuración env ni versión.

## Registro histórico del 21/10/2026 · implementación inicial

Base `18c7596` (A01 integrado por PR #3 en el historial local), rama
`feature/p3a-a02-qr-geolocalizacion`. Release **0.3.2** conservada. Trabajo sin commit,
push, merge ni despliegue. [Contrato, reglas y pasos exactos](a02-geographic-access.md).
No se consultó CI remoto ni se realizó aceptación física GPS/NFC.

## Evidencia automatizada

Todos los comandos del proyecto usan Compose: PHP 8.4.26 y PostgreSQL 17.11 nativo del servicio db,
sin PGlite. Antes de RefreshDatabase y scripts con BD se verificaron las conexiones de lectura y
escritura efectivas a `siaa_test` con `scripts/check-test-database.php`, encadenado al comando.
Se preparó temporalmente `.env.testing` privado a partir de la configuración y clave existentes,
sin imprimir valores ni generar otra clave; se retiró al terminar. No se modificaron CI, phpunit.xml
ni tests/TestCase.php.

| Comprobación | Resultado observado |
| --- | --- |
| `php artisan test --compact --display-warnings --fail-on-warning` | **154 pruebas, 995 aserciones; sin warnings; 226,32 s; salida 0** |
| `npm test` | **23/23** |
| `npm run build` final | Correcto, 72 módulos, 24,69 s; aviso Sass mixed-decls heredado |
| `composer validate --strict` | Correcto |
| `composer check-platform-reqs` | Requisitos correctos, PHP 8.4.26 |
| `vendor/bin/pint --test` | Correcto, 169 archivos |
| `attendance-races.php --env=testing` | 6 comprobaciones correctas contra PostgreSQL nativo |
| `attendance-summary.php --env=testing` | 2 comprobaciones de lectura colectiva correctas |
| `git diff --check` | Correcto |

Los comandos reproducibles están en el contrato. La excepción de safe.directory para Composer/Pint
se limitó al proceso del contenedor mediante GIT_CONFIG_COUNT/KEY/VALUE, sin modificar Git global.

Cobertura A02: QR/enlace presenciales requieren evidencia aun con parámetros de evasión; en línea
y eventos explícitamente en línea siguen sin ubicación, también con portales antiguos. Evento sin
ubicación explícita falla; programación exige elegirla. Referencia fija, reapertura, portal antiguo
presencial, cambio de política, datos inválidos/no finitos/faltantes, precisión de ambos dispositivos,
antigüedad/límites temporales, dentro/fuera/incertidumbre y límite inclusivo de 5 m. Autorización,
inscripción, cuenta activa, privacidad/old_input, expiración/cierre, replay/UUID conflictivo, sin duplicados,
retención y conservación de A01. La batería existente cubre NFC/manual, permisos y finalización.

JavaScript prueba adquisición puntual/alta precisión/caché cero, errores de permisos/timeout/señal,
datos inválidos, falta de adquisición al construir o en línea, tres fallos y cuarto intento, incompatibilidad
inmediata, concurrencia de clics, UUID/paquete estable tras pérdida de respuesta y UUID nuevo tras rechazo.

Carreras: las cuatro previas (mismo UUID/cuerpo, métodos simultáneos, cuerpos distintos, finalización),
ahora con enlace presencial validado, más QR geográfico frente a cierre de portal y registro bloqueado
por un cierre real. Esta última comprueba `pg_stat_activity.wait_event_type = Lock`, refrescando la
instantánea estadística dentro de la transacción, antes de confirmar el cierre; después recibe 409.
Las dos lecturas consistentes A01 permanecen correctas. No se atribuyen esos resultados a CI.

Durante preparación se corrigieron los fixtures de cambio de actor (auth.session), comparación de JSONB
y refresco de estado del fixture tras un worker. Una invocación inicial de filtro fue interpretada por el
shell y se retiró; una batería intermedia se detuvo para incorporar la modalidad de eventos al esquema.
Esas ejecuciones no se presentan como evidencia final. No se relajó ninguna regla ni el arnés de pruebas.

## Migración de desarrollo realmente aplicada

Se comprobó entorno local, pgsql y lectura/escritura efectivas a `siaa`. Se revisó `migrate --pretend`
y se aplicó `2026_10_21_000100_add_portal_geographic_access` mediante `php artisan migrate`.
Solo columnas nullable y restricción de modo de evento; no hay backfill de modalidades/ubicaciones.

Un checkpoint temporal comparó hashes SHA-256 sobre todas las columnas anteriores, en orden estable,
y cantidades antes/después, sin imprimir datos ni contraseñas. Las **13 tablas conservaron exactamente
sus filas y valores anteriores**: users 23, ciclos 1, materias 2, grupos 2, cómputos 2, sesiones 9,
inscripciones 40, portales 2, asistencias 20, permisos 0, eventos 1, reportes 0 y auditoría 27.
Solo la tabla de migraciones registra la aplicación. Checkpoint retirado después de comprobarlo.
No se ejecutó migrate:fresh ni seed en desarrollo. No se regeneró APP_KEY.

## Revisión de navegador

Edge 155.0.4283.62 headless, servidor temporal separado en localhost:8091 contra `siaa_test`, cuentas
ficticias, sesiones/inscripción propias y ubicación simulada por protocolo de depuración. Resultado
final **PASS**, cero excepciones JavaScript. Se comprobó:

- Docente deniega ubicación: no se abre otro portal y el botón vuelve a estar disponible. Permitir
  ubicación y reintentar abre QR/enlace con referencia fija; HTML sin coordenadas.
- Estudiante entra como invitado desde enlace, autentica dentro del flujo y pulsa Registrar.
  Tres rechazos fuera del área muestran recomendación; un cuarto intento dentro se confirma.
- Precisión insuficiente y permiso denegado del estudiante muestran mensajes específicos y permiten
  reintento. Éxito cambia el botón a Asistencia registrada y retira la recomendación de fallo.
- Docente y estudiante con ubicación denegada en clase en línea completan apertura/registro, con
  **cero adquisiciones de ubicación** observadas en ambos navegadores.
- Cinco medidas: docente 1440/320 px; estudiante fuera 320, éxito 390 y en línea 320 px.
  Anchos de documento 1425/320/320/390/320, sin desbordamiento de página. Se inspeccionaron
  capturas finales; el botón de copiar enlace envuelve el texto dentro de su panel a 320 px.

Las capturas/mediciones y scripts auxiliares están fuera del repositorio, en la carpeta temporal
`smartaulaues-a02-review`. No forman parte del incremento. Las cuentas/sesiones/inscripción/portales
ficticios se retiraron por sus IDs/correos verificados; el navegador y servidor temporal se cerraron.
El ensayo usa localhost seguro para la API y coordenadas simuladas. No implica ejecución de Cloudflare
ni GPS físico/HTTPS real entre dispositivos. No se publica ninguna URL del túnel.

## Archivos del incremento

- Núcleo nuevo: `app/Services/Attendance/GeographicAccess.php`, `config/geolocation.php`.
- Migración nueva: `database/migrations/2026_10_21_000100_add_portal_geographic_access.php`.
  Modelos ajustados: ClassSession, AttendancePortal y AttendanceEvent.
- Acciones: PortalManager, RegisterAttendance, SessionWorkflow y Academic/AuditRecorder.
- HTTP: AttendanceController, CheckInController, SaveSessionRequest, RegistrationRejected,
  SecurityHeaders y bootstrap/app.php.
- Retención: `app/Console/Commands/PruneAttendanceLocation.php` y routes/console.php.
- Interfaz: resources/js/attendance-live.js, nuevo geographic-access.js, CSS localizado en app.css,
  sesiones form/show, checkin.blade.php y nuevo parcial geolocation-options.blade.php.
- Pruebas: nuevo GeographicAttendanceTest.php y geographic-access.test.js; actualización de
  AttendanceRegistrationTest.php y Concurrency/attendance-races.php.
- Continuidad: AGENTS, README, CHANGELOG, contrato/evidencia A02, decisiones, estado, fases,
  esquema, notas de cambio, nfc-contract, mobile-https y test-results.

Se mantienen .env, APP_KEY y la modificación previa de .env.example; no hay nuevas variables env,
dependencias, versión, staging ni cambios de CI. Microsoft/ y artefactos/capturas/perfiles de navegador,
credenciales y dependencias instaladas quedan fuera del incremento.

## Pendientes y límites

Aceptación física pendiente: dos celulares reales mediante HTTPS, separación medida dentro/fuera de
5 m, precisión real y denegaciones, reintentos/red y alternativas, modalidad en línea y NFC/manual.
La matriz de pasos está en el contrato. El criterio conservador puede impedir autorregistro incluso
en proximidad física; no se amplía el radio para ocultarlo. La API no garantiza ubicación verdadera.
Debe verificarse operación del scheduler para la limpieza diaria a 30 días y retención de respaldos.
Implementación local para revisión; A02 no se declara completamente aceptado sin evidencia física.
A03–A05/B01–B07 siguen pendientes; no cierre automático, snapshot, certificación ni release nueva.
