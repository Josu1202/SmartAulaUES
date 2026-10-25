# A05 · decisiones definitivas

La operación posterior mantiene estos contratos y añade el escenario neutral, traducciones,
presentación de auditoría, paginación, reintentos medidos y Compose/Apache reproducibles.
[Guía vigente](operacion-local-y-produccion.md) e [informe nuevo](operacion-verificacion.md).
Los contenedores/seeder identificables de la evidencia inicial no son el entorno de revisión actual.

Fecha de trabajo: 24/10/2026, America/El_Salvador. Base exacta de origin/main:
`6273a307f3411a5e1ba40c427153cdf39e545a0e`, merge de A04 por PR #6.
Rama `feature/p3a-a05-integracion-final`, worktree `../SmartAulaUES-A05`.
El checkout habitual mantiene sus cambios previos. No se cambia la versión 0.3.2 ni se libera.

## Tiempo, sesiones y cierres

La instrucción A05 sustituye la espera diaria de A03. Una sesión con inicio real registrado se
finaliza cuando `ends_at <= now()` en UTC, en la siguiente ejecución del procesador. La ventana
de registro es `[starts_at, ends_at)` y también exige portal/lector vigentes: el instante final
ya no admite una escritura nueva. El replay autorizado de un evento completado conserva su
respuesta antes de estos bloqueos. No se usa una hora del teléfono para decidir el vencimiento.

Cruzar medianoche no cambia la regla. `finalized_at` registra el procesamiento real, no una hora
retroactiva ficticia. Un portal vencido no finaliza la sesión. La finalización manual anticipada
conserva motivo y auditoría. Finalizar cierra portales y lectores y conserva las presencias.

Una programada sin evidencia real sigue siendo programada hasta el cierre del cómputo, donde se
clasifica `unstarted` si es consistente; no produce ausencias ni denominador. Un inicio antiguo
desconocido no se inventa. Los permisos posteriores siguen siendo posibles tras finalizar;
los pendientes bloquean el cierre definitivo. Fechas finales de ciclos/cómputos siguen siendo
inclusivas en America/El_Salvador y se cierran después de terminar ese día. `reopened_at` sigue
exigiendo recierre manual. Ni la reactivación ni la reasignación reabren recursos.

## Ejecución operativa

Compose incorpora un servicio `scheduler` con `schedule:work`, `init` y reinicio supervisado.
El procesador se programa cada minuto. Usa un advisory lock PostgreSQL de sesión liberado
en `finally`; también cubre invocaciones manuales simultáneas y
no depende de que dos procesos compartan una caché local. Una caída de conexión libera ese lock.
Se retira el `withoutOverlapping(30)` de esta tarea: un proceso terminado abruptamente podía
dejar su lock de caché durante treinta minutos e impedir la recuperación del nuevo worker.
La exclusión se conserva en PostgreSQL sin ese bloqueo residual; el prune diario mantiene su
protección de solapamiento independiente.

`operation_runs` identifica inicio, final y errores de la última ejecución. El comando
`attendance:scheduler-status --max-age=180` y el healthcheck detectan ejecución ausente, incompleta,
antigua o con errores. Una ejecución sin errores puede tener impedimentos académicos: estos
permanecen en los cómputos, no se fuerzan. Bajo operación normal, el retraso de inicio es menor
de un minuto **más** el tiempo de procesamiento. Interrupciones, carga o averías no tienen
un retraso máximo garantizado; el indicador a tres minutos exige atención operativa.

Se mantiene el prune geográfico diario. Cola `sync` sigue siendo suficiente para los avisos
internos actuales; no se introduce un worker de cola sin trabajo que lo requiera. El servicio
HTTP de Compose continúa siendo de desarrollo. Hosting definitivo requiere su servidor web,
HTTPS y supervisión documentados; su validación no se deduce de estas pruebas locales.

## Año, egreso y acceso

`study_year` representa exclusivamente 1–5. `graduated=true` representa Egresado y exige
`study_year=NULL`. `entry_year` sigue siendo ingreso. La interfaz y CSV aceptan el literal
`egresado` en el campo de selección/año; UserManager lo convierte a los dos campos coherentes.
No se almacena un año 0 o 6 para representar egreso.

Egresado activo conserva acceso, matrícula existente, asistencia/permiso aplicables a esa
matrícula e historial. No admite una matrícula nueva; no se retiran ni borran inscripciones
automáticamente. Suspender la cuenta conserva la historia y bloquea su acceso. Esto evita que
un cambio de clasificación modifique resultados cerrados. No se implementa promoción anual.

Los registros legados sin clasificación permanecen como «Sin asignar», sin inferir egreso.
La plantilla heredada `users` conserva su compatibilidad sin inventar años; las plantillas
`students` y actualización de años exigen clasificación. La migración bloquea cualquier año
6–10 preexistente y exige clasificarlo explícitamente **antes** de modificar el esquema.
No interpreta esos años como egreso ni los reemplaza silenciosamente. El seeder de demostración
clasifica únicamente cuentas nuevas con años 1–5; no sobrescribe cuentas anteriores.

## CSV y contraseñas

Las plantillas de cuentas y matrículas son distintas. Se conservan UTF-8/BOM, encabezados
exactos, 500 filas/1 MB y lote manual de 100. Cada revisión tiene nonce propio, propietario,
vencimiento exclusivo a 15 minutos y URL con identidad. Hasta diez revisiones simultáneas
por sesión; cargar otro archivo no reemplaza otras pestañas. Datos y hash permanecen del lado
del servidor, nunca como filas editables ocultas enviadas por el cliente.

Confirmar revalida filas, destino, matrícula/cupo y una huella de las cuentas revisadas. Los
cambios concurrentes de clasificación/identidad no se sobrescriben. Los locks existentes de
coordinación serializan confirmaciones con altas/ediciones de cuentas; luego se respeta el orden
académico para matrículas. La transacción incluye todas las filas y su auditoría. Repetir una
confirmación consumida informa que expiró/fue confirmada y no duplica. No se restablece una
contraseña existente. Las búsquedas de revisión se cargan por lote.

Todas las cuentas nuevas por CSV/lote usan la contraseña provisional común elegida por
Coordinación, almacenada solamente como hash. `must_change_password` bloquea las rutas académicas
hasta el cambio propio, incluida una sesión ya abierta. Un restablecimiento administrativo
también activa ese requisito. El cambio propio revoca sesiones y obliga a entrar de nuevo.
Una invitación QR/enlace/etiqueta muestra la acción de cambio; después debe abrirse de nuevo
el acceso vigente. No se persiste su token para atravesar ese paso ni se extiende su ventana.

Se conserva bcrypt y se rechazan nuevas contraseñas con más de 72 bytes UTF-8 o NUL, manteniendo
el mínimo de 12 caracteres. Evita truncamiento silencioso sin cambiar hashes existentes.
Fuente primaria: [manual PHP de password_hash](https://www.php.net/manual/en/function.password-hash.php).

## NFC individual, lote y teléfono del estudiante

La emisión individual conserva preparar → escribir → leer → confirmar. El lote es una cola
explícita de estudiantes de la página filtrada; no un endpoint que emita todos los tokens.
Se prepara únicamente la credencial del turno actual. Cada confirmación verifica el token
exacto, bloquea al estudiante/credencial y reemplaza la activa solo al confirmar correctamente.
Una confirmación repetida de la misma credencial activa/token devuelve éxito sin otra auditoría;
una revocada jamás se reactiva. Cancelar conserva activaciones terminadas y la credencial
anterior si su reemplazo aún no se confirmó; la pendiente vence. Recargar exige seleccionar
los pendientes de nuevo: no se guarda un token en localStorage. Límites de emisión 20/min,
confirmación 60/min y revocación 20/min, además de autorización por rol.

Web NFC lee/escribe NDEF; no ofrece HCE ni intercambio entre teléfonos. Fuente primaria:
[Chrome, capacidades y límites de Web NFC](https://developer.chrome.com/docs/capabilities/nfc).
Se añade una etiqueta compartida con URL temporal de portal. El docente la escribe para la
ventana actual. El estudiante autenticado puede leerla desde su página, o abrir su URL con
el sistema del teléfono. QR/enlace quedan como alternativas. El POST valida su propia identidad,
matrícula, estado, ventanas, idempotencia y la misma geolocalización A02 cuando es presencial.

`nfc_tag` se registra separado de `nfc`: es **acceso declarado mediante etiqueta compartida**,
no prueba de que el cliente leyó físicamente una etiqueta. Un enlace copiado obtiene las mismas
validaciones; no gana evidencia de presencia. No hay emulación ni app nativa nueva. Se conservan
30 m, precisión individual >0 y ≤10 m, evidencia ≤30 s y tolerancia futura ≤5 s, sin coordenadas
del estudiante persistidas. Proximidad al docente no equivale a presencia dentro del aula.

## Reasignación, formularios y lectura

Cambiar docente valida atómicamente conflictos con sesiones futuras del destinatario y bloquea
sesiones en curso. Comparte el lock del docente con programación/reprogramación, cierra accesos
anteriores y conserva `recorded_by`, resolutores y actores de auditoría. El docente nuevo asume
también los permisos pendientes e historia del grupo. El anterior pierde consulta y gestión,
porque la política actual autoriza únicamente grupos asignados; no se introduce una copropiedad
histórica implícita. Coordinación conserva consulta. El registrador vuelve a autorizar tras
esperar locks, además del control previo al replay.

Los formularios comparten nombres de dominio pero tienen `_form` y scopes por acción/recurso.
Solo el enviado recupera sus valores y errores; cambiar IDs HTML no era suficiente. Campos de
contraseña/archivo no se repueblan. Los errores generales siguen visibles. GET de grupo usa un
diagnóstico ligero sin `FOR UPDATE` ni captura colectiva de desactivación; confirmar conserva
la validación íntegra bajo locks. Sus sesiones se paginan independientemente de resultados.

Sesiones y permisos comparten `OfferingCatalog`: Actual/Historial depende del estado del ciclo,
no de su fecha. Permisos incluye por defecto todos los grupos actuales, incluso suspendidos,
para que los pendientes sean localizables. Los demás filtros permiten consultarlos expresamente.
Los avisos de sesión se limitan a matrículas aplicables en su fecha y cuentas activas.

El cierre manual de cómputos también exige grupo activo; se corrigió un hueco no cubierto por
la matriz A04. El procesador del sistema conserva su excepción para grupos desactivados.
Cerrar/reabrir un ciclo es una operación del ciclo y no exige habilitar todos sus grupos.

## Límites del incremento

`composer audit --locked` identificó dos avisos de dependencias bloqueadas. El resolver mínimo,
sin añadir paquetes, seleccionó `symfony/http-foundation` y `symfony/yaml` 7.4.21 compatibles
con Laravel 13/PHP 8.4, en lugar de 8.0.15 vulnerable. Se conserva composer.json y se actualiza
composer.lock. Fuentes: [CVE HTTP Foundation](https://symfony.com/cve-2026-108082) y
[CVE YAML](https://symfony.com/cve-2026-109413). Se repite la auditoría y la regresión con ese lock;
la auditoría de avisos no equivale a una revisión formal de todo el código de terceros.

No se implementan PDF/CSV de resultados, ranking, noticias ni otros módulos B de Persona 3.
El acceso «Resultados y cierres» dirige a los resultados existentes; noticias sigue pendiente.
No se ejecuta commit, push, merge, PR, release ni despliegue. Hardware NFC/GPS/HTTPS del teléfono,
SMTP y hosting final tienen aceptación externa independiente. No se promete ausencia absoluta
de defectos ni rendimiento de producción a partir del entorno local.
