# A05 · verificación local por escenarios

> Evidencia conservada de la revisión A05 inicial. El estado actual de servicios/datos y las
> comprobaciones nuevas están en [operacion-verificacion.md](operacion-verificacion.md).
> Para repetir pruebas o arrancar, usar [la guía vigente](operacion-local-y-produccion.md),
> no los antiguos auxiliares ni la BD compartida de revisión descritos históricamente aquí.

Informe de trabajo del 24/10/2026. Base `6273a307f3411a5e1ba40c427153cdf39e545a0e`,
rama `feature/p3a-a05-integracion-final`, versión 0.3.2. La entrega y los resultados corresponden
al worktree `C:\Users\jozzz\Downloads\SmartAulaUES-A05`, sin commit ni publicación.

## Entorno, datos y reproducción

PHP 8.4.26 en la imagen Docker del proyecto, PostgreSQL 17 nativo y Node 24 en contenedor.
No se usaron SQLite/PGlite como sustitutos de las carreras nativas. Antes de las suites se
ejecutó `docker exec siaa-a05 php scripts/check-test-database.php`: **PASS testing, PostgreSQL,
lectura y escritura efectivas en siaa_test**. Solo esa base desechable fue reconstruida por
RefreshDatabase; desarrollo quedó intacto. Las suites, carreras y scheduler se ejecutan por
turnos, sin compartir simultáneamente los fixtures.

Los comandos de cada escenario se ejecutan en `siaa-a05`, desde `/var/www/html`. Para repetir
un caso PHP: `docker exec siaa-a05 php artisan test --filter=NOMBRE --display-warnings --fail-on-warning`,
sustituyendo NOMBRE por el método o clase indicado. El comando completo, el inventario de casos
y los resultados consolidados se detallan al final. Los filtros no reemplazan la regresión completa.

Los casos funcionales crean fixtures transaccionales: Coordinación, docentes, estudiantes,
matrículas aplicables/futuras/retiradas, estados académicos y recursos. El seeder manual
`A05AcceptanceSeeder` prepara además una revisión identificable por nombres/correos A05:
años 1–5/Egresado, cuenta inactiva y provisional, ciclos actual/vencido/ cerrado/reabierto,
grupo desactivado, permiso pendiente y credenciales pendiente/activa/revocada/vencida.
Las pruebas completan los estados finalizado/cancelado/sin inicio y permisos aprobados/rechazados.
Ninguno representa datos reales ni aprobación institucional.

## Recorrido académico y contratos conservados

### V01 · recorrido integral (A, C, D, H)

- **Rol y datos:** Coordinación, docente y dos estudiantes importados; clase en línea, matrícula aplicable y permiso posterior.
- **Pasos/prueba:** `A05JourneyTest::test_import_password_attendance_permission_snapshot_history_and_reopening_form_one_journey`. Importar CSV, cambiar ambas contraseñas, crear grupo/cómputo/matrículas, programar/iniciar, registrar por enlace, avanzar a ends_at, procesar, pedir permiso, intentar cierre con pendiente, aprobar, cerrar cómputo/ciclo, leer historial y reabrir con motivo.
- **Esperado:** presencia y justificación coherentes; pendiente bloquea snapshot; consulta estudiantil privada; reapertura conserva versión y exige recierre manual.
- **Observado:** recorrido aprobado; cierre inicial sin snapshot mientras había pendiente, resultado cerrado 100.00 %, otra fila no expuesta, hash histórico intacto tras reapertura.
- **Estado/evidencia:** ejecutada y aprobada, caso citado en la suite PHP. Corrige integración temporal/provisional; no acredita HTTPS ni un dispositivo real.

### V02 · 8 finalizadas + 2 sin inicio (contratos A01/A03 y A05-A)

- **Rol y datos:** estudiante con ocho presencias; dos clases programadas nunca iniciadas.
- **Pasos/prueba:** `test_eight_completed_and_two_never_started_produce_one_hundred_percent_and_frozen_snapshot`; cerrar tras la fecha final local y después marcar al estudiante Egresado.
- **Esperado/observado:** las dos programadas pasan a sin inicio, sin ausencias; fracción exacta **8/8**, 100 %, fuente snapshot y hash conservado después del egreso. Ejecutada y aprobada.
- **Evidencia adicional:** `AcademicClosureTest::test_ten_scheduled_eight_finished_two_unstarted_freeze_exact_a01_thresholds_and_safe_explanations` y `AttendanceCalculationTest` verifican umbral exacto 75 %, redondeo que no cambia conclusión, justificadas aprobadas, cero evaluables e incidencias propias.
- **Límite:** resultados sintéticos y cálculo servidor; no constituye una aprobación normativa externa.

### V03 · snapshots, reapertura y privacidad (H)

- **Rol/datos:** Coordinación, docente propio/ajeno y estudiantes de un grupo cerrado.
- **Pasos/pruebas:** `AcademicClosureTest`, `AttendanceResultsTableTest`, `AuthorizationTest`: cerrar, cambiar datos posteriores, solicitar versión anterior, intentar reabrir sin motivo/con ciclo cerrado, alterar un snapshot y acceder a filas ajenas.
- **Esperado:** versión inmutable, orden ciclo antes del cómputo, rechazo de corrupción y lecturas no autorizadas; cada estudiante recibe solo su resultado.
- **Observado:** regresiones de cierre/privacidad conservadas; no se entrega ReportVersion colectivo al estudiante. Evidencia individual en el listado de casos PHP.
- **Estado:** ver resultado final de la regresión completa. No se implementan exportaciones ni ranking de Persona 3.

## Tiempo y operación

### V04 · portal, frontera exacta y registro tardío (A)

- **Rol/datos:** docente/estudiante; sesión 16:00–17:00 UTC, portal de un minuto.
- **Pasos/pruebas:** `test_expiring_portal_does_not_finish_class_and_scheduler_ends_it_exactly_at_ends_at` y `test_scheduler_delay_blocks_new_writes_but_replays_completed_event`.
- **Esperado:** portal vencido rechaza registro sin terminar clase; antes de ends_at no finaliza; exactamente en ends_at finaliza/cierra recursos. Scheduler retrasado rechaza nueva UUID y recupera el evento ya confirmado.
- **Observado:** 409 para escritura nueva fuera de ventana, replay correcto, una sola asistencia y una auditoría automática tras repetir procesamiento; healthcheck falla a 181 s sin ejecución nueva.
- **Estado/evidencia:** ambas ejecutadas y aprobadas. Reloj controlado, distinto de la prueba operativa real V07.

### V05 · medianoche, inicio desconocido y permisos (A)

- **Rol/datos:** sistema/docente; sesión cruzando medianoche de El Salvador, otra sin started_at fiable y permisos preventivos/pendientes.
- **Pasos/pruebas:** `AcademicClosureTest`: `test_cross_midnight_schedule_is_not_truncated_and_legacy_start_is_not_invented`, `test_start_two_seconds_before_midnight_finalizes_without_waiting_twenty_four_hours_and_manual_finish_requires_reason_early`, y casos de recuperación de inicio y permisos.
- **Esperado:** se respeta ends_at UTC, sin truncar a medianoche ni inventar inicio; finalización anticipada exige motivo; pendiente no bloquea fin de clase, sí cierre definitivo.
- **Observado:** contrato actualizado en los tests anteriores; el procesador no fabrica ausencias de programadas y mantiene los impedimentos académicos. Evidencia en casos PHP.
- **Estado:** ver regresión completa. No se prueba sincronización NTP del hosting final.

### V06 · exclusión y concurrencia nativa (B, G, J)

- **Rol/datos:** procesos PHP separados, conexiones PostgreSQL distintas y fixtures propios; no conexión serializada compartida.
- **Comandos:** `php tests/Concurrency/{attendance-races,attendance-summary,academic-closures,offering-management,a05-integration}.php --env=testing`, uno por vez.
- **Esperado:** registro único/replay/conflicto de UUID, cierre contra registro/corrección/permiso/matrícula, lectura colectiva coherente, desactivación contra escrituras, reemplazo NFC/CSV duplicados y reasignación contra horario en ambos órdenes. El comando duplicado de cierres debe terminar sin sobrescribir salud.
- **Observado/evidencia:** cada línea PASS identifica el resultado y los scripts verifican espera de lock real donde corresponde. Logs privados `storage/logs/a05-native-*.txt`; resultados por script al final.
- **Defecto/corrección:** validación de horario ausente, confirmación NFC no idempotente y CI sin A04. Se serializan con los locks compartidos y se amplía CI.
- **Estado:** consolidado al final; no equivale a carga productiva ni CI remoto.
- **Resultado nativo:** ejecutadas y aprobadas **37 comprobaciones**: registro 6, lectura colectiva 2,
  cierres 8, administración A04 15 y A05 6. Las primeras cinco de A05 verifican espera de locks;
  la sexta comprueba que el advisory try-lock hace terminar el comando independiente duplicado.

### V07 · scheduler detenido, recuperado y repetido (B)

- **Rol/datos:** servicio `schedule:work` real contra siaa_test; una clase iniciada vencida y otra nunca iniciada, preparadas mientras estaba detenido.
- **Pasos:** comprobar proceso detenido, preparar fixture, iniciar worker supervisado, observar una ejecución de minuto, consultar estado/salud, detener y arrancar de nuevo, repetir procesamiento y comprobar auditoría/resultados.
- **Esperado:** iniciada finalizada una sola vez, nunca iniciada aún programada (el cómputo sigue vigente), sin resultados artificiales; salud reciente sin errores. Una terminación abrupta no deja un lock de caché de treinta minutos.
- **Evidencia:** `storage/app/private/a05-evidence/scheduler-*.json*`, logs privados del servicio y comandos de salud. El procedimiento exacto y resultado observado se consolidan al final.
- **Límite:** servicio Docker local; el hosting definitivo no fue accesible. `compose config --quiet` solo valida configuración, no demuestra ejecución.
- **Observado/estado:** ejecutada y aprobada. Con worker detenido se preparó el fixture a
  **23:42:50 UTC**. Antes de procesar: in_progress/scheduled, cero resultados y auditorías,
  indicador sin ejecución reciente (salida 1 esperada). Ejecución real **23:44:11–23:44:24 UTC**:
  completed/scheduled, seis resultados y una auditoría automática; errores 0.
  Se terminó abruptamente el PHP del worker aislado; Docker `restart: unless-stopped` lo reinició
  sin intervención y RestartCount pasó a **1**. Ejecución **23:45:12–23:45:24 UTC**:
  mismos seis resultados/una auditoría, nunca iniciada aún scheduled. Health del contenedor
  **healthy** y `attendance:scheduler-status --max-age=180` salida 0. El worker se detuvo
  explícitamente antes de la última suite; no queda ejecutándose contra los fixtures.
- **Procedimiento exacto local:** `docker compose -f storage/app/private/a05-evidence/compose.scheduler.yaml stop scheduler`;
  `docker exec siaa-a05 php storage/app/private/a05-scheduler-probe.php --prepare`;
  `docker compose -f storage/app/private/a05-evidence/compose.scheduler.yaml start scheduler`;
  esperar el minuto real, ejecutar la sonda sin --prepare; `docker exec siaa-a05-scheduler php storage/app/private/a05-evidence/kill-worker.php`;
  observar reinicio/siguiente ejecución, repetir sonda y comando de salud; detener solo ese servicio.
  El Compose privado usa la misma orden/política/init/healthcheck de la definición pública y la red
  existente con conexión explícita a siaa_test. No inicia ni recrea los servicios habituales.

## Cuentas, años e importación

### V08 · años canónicos y Egresado (C)

- **Rol/datos:** Coordinación y estudiante con matrícula/historia previa.
- **Pasos/pruebas:** `test_graduation_is_explicit_filterable_and_preserves_existing_history`, `test_csv_supports_egresado_and_rejects_sixth_year_without_partial_accounts`, `test_study_level_rejects_numeric_coercions_and_boolean_values_in_csv_and_filters`.
- **Esperado/observado:** Egresado guarda graduated=true/año NULL, aparece en directorio/NFC y conserva acceso/matrícula. Nueva matrícula rechazada. CSV UTF-8/BOM admite egresado; sexto año, booleanos, 01/1.0/1e0 rechazados, sin altas parciales.
- **Estado/evidencia:** ejecutadas y aprobadas. Los legados NULL siguen Sin asignar; no se infiere un egreso.

### V09 · revisión por pestaña, propietario y vencimiento (D)

- **Rol/datos:** dos coordinadores; dos previews en una sesión y un nonce expirado.
- **Pasos/pruebas:** `test_two_tabs_confirm_their_own_reviews_and_retries_do_not_duplicate_accounts`, `test_review_cannot_be_confirmed_by_another_owner_or_at_its_expiry`, `test_preview_query_is_typed_and_expired_identity_has_a_clear_message`.
- **Esperado/observado:** cada URL muestra solo su CSV, confirmación independiente, reintento sin duplicado; propietario ajeno y minuto 15 rechazados. Query array da 422; identidad consumida/inexistente muestra mensaje explícito.
- **Estado/evidencia:** ejecutadas y aprobadas; V17 añade dos pestañas reales. Rutas POST conservan bloqueo de sesión y límite de solicitudes; máximo diez revisiones pendientes.

### V10 · cambio concurrente, rollback y contraseñas existentes (D, J)

- **Rol/datos:** dos estudiantes con años iniciales y un CSV de actualización; cupo/destino que cambia entre preview y confirmación.
- **Pasos/pruebas:** `test_year_review_detects_a_concurrent_edit_and_rolls_back_the_entire_batch`, `CsvImportTest`, `AnnualOrganizationTest`; modificar segunda cuenta/cupo/estado antes de confirmar.
- **Esperado/observado:** se rechaza el lote completo; primera cuenta intacta y edición ajena conservada, sin auditorías parciales. Correos/carnés duplicados, cabeceras y celdas inválidas no se convierten en escrituras; contraseñas existentes no se resetean.
- **Estado:** caso nuevo ejecutado y aprobado; casos heredados en regresión completa. NFC/CSV duplicados también se comprueban con procesos independientes V06.
- **Límite:** 500 filas/1 MB, lote manual 100; no se introduce un importador de archivos arbitrarios.

### V11 · acceso provisional y cambio de contraseña (D, K)

- **Rol/datos:** estudiante recién importado y sesión autenticada con must_change_password.
- **Pasos/prueba:** `test_imported_account_is_blocked_until_a_different_password_is_set`; abrir dashboard, POST JSON académico, perfil, cambiar contraseña y entrar de nuevo.
- **Esperado/observado:** redirect a perfil y JSON 403 con password_change_required; perfil disponible; hash cambiado, flag limpiado y sesión revocada, nuevo ingreso permitido. Altas CSV comparten el hash seguro elegido; auditoría no contiene texto plano.
- **Estado/evidencia:** ejecutada y aprobada, recorrido V01 y navegador V19. Invitaciones requieren abrir otra vez un acceso vigente: no se conserva token ni se prolonga la ventana.
- **Regresión adicional de invitación:** `test_provisional_account_cannot_bypass_password_change_through_an_attendance_invitation`
  comprueba GET con acción de cambiar contraseña y sin botón de registro, POST 403 aun con token
  válido, cambio/logout/login y rechazo 409 al volver a abrir un portal vencido; cero asistencias.
  Su estado se incluye en el resultado final PHP.

### V12 · bcrypt, sesiones y recursos privados (K)

- **Rol/datos:** Coordinación, usuarios activos/inactivos, contraseñas ASCII/UTF-8/NUL.
- **Pasos/prueba nueva:** `test_password_rules_reject_bcrypt_truncation_for_ascii_and_utf8`; enviar 73 bytes ASCII, 37 caracteres de dos bytes y un NUL.
- **Esperado/observado:** validación rechaza todos sin crear cuentas. Mínimo 12 caracteres y máximo 72 bytes evitan truncamiento silencioso.
- **Regresiones asociadas:** Authentication, AcademicAdministration, AbsencePermission y HttpsProxy verifican login/logout, rate limit, cuenta desactivada con sesión abierta, hash antiguo, CSRF/configuración proxy y evidencia privada por Policy. Estado final en listado PHP.
- **Límite:** cookies HTTPS/proxy verificadas por pruebas HTTP; no hay hosting/SMTP final validado.

## NFC y reasignación

### V13 · confirmación NFC segura (E)

- **Rol/datos:** Coordinación y estudiante con credencial activa, reemplazo pendiente y revocada.
- **Pasos/prueba:** `test_nfc_confirmation_retries_preserve_one_activation_and_never_reactivate_revoked` y `CredentialWorkflowTest`; emitir, confirmar dos veces, emitir reemplazo, confirmar y reintentar la anterior.
- **Esperado/observado:** activa anterior conservada hasta confirmar reemplazo; respuesta repetida coherente con una auditoría; revocada no reactivada. Pendiente vencida/token incorrecto/rol ajeno rechazados.
- **Estado/evidencia:** caso nuevo aprobado; nativo V06 y navegador V18 complementan. La prueba de servidor no acredita escritura física.

### V14 · etiqueta compartida y garantías A02 (F)

- **Rol/datos:** estudiante autenticado/matriculado, sesión presencial con referencia docente sintética precisa.
- **Pasos/prueba:** `test_shared_tag_is_self_registration_and_uses_a02_location_without_persisting_coordinates`; POST sin ubicación y después ubicación válida con student_id ajeno adulterado.
- **Esperado/observado:** primero 422 geo_unavailable; después 201 para la identidad autenticada, método nfc_tag separado y sin coordenadas en eventos/auditoría.
- **Estado/evidencia:** ejecutada y aprobada. GeographicAttendanceTest y 44 JS preservan radio 30 m, precisión >0/≤10 m, actualidad y cancelación de adquisición. Web NFC no ofrece HCE; enlace copiado recibe las mismas validaciones, no una certificación de lectura/presencia.
- **Límite:** NFC/GPS físicos pendientes V23/V24.

### V15 · cambio de docente y enlaces anteriores (G)

- **Rol/datos:** Coordinación, docente original/nuevo; sesiones futuras solapadas, clase iniciada, portal y auditoría de inicio.
- **Pasos/prueba:** `test_teacher_reassignment_rejects_future_conflicts_and_running_class_then_transfers_permissions`; intentar con conflicto, cancelar conflicto, intentar durante clase, finalizar justificadamente, reasignar y consultar ambas identidades.
- **Esperado/observado:** conflicto/clase en curso rechazan sin cambio; después nuevo docente consulta y anterior recibe 403; accesos anteriores cerrados y actor original conservado.
- **Estado/evidencia:** ejecutada y aprobada, más ambas carreras de programación/reasignación V06. El alcance histórico sigue las policies de asignación actual; no se agrega copropiedad histórica.

## Lectura, formularios y navegador

### V16 · diagnósticos, consultas y filtros (H, I, J)

- **Rol/datos:** Coordinación con grupo de 50 matrículas, revisión CSV de 1 y 500 filas, grupos actual/inactivo/cerrado y pendientes.
- **Pasos/pruebas:** `test_reading_group_does_not_acquire_exclusive_locks_or_recalculate_collective_deactivation`, `test_batch_review_lookup_cost_does_not_grow_per_row`, `test_sessions_and_permissions_share_catalog_filters_and_keep_suspended_pending_work_findable`, `test_reschedule_notifies_only_active_students_enrolled_on_the_session_date`.
- **Esperado/observado:** GET sin FOR UPDATE ni cálculo colectivo adicional; revisión usa igual número de consultas para 1/500 filas (≤2), sin búsqueda por fila. Sesiones/permiso clasifican por ciclo, pendiente de grupo suspendido visible; solo matrícula aplicable/activa recibe aviso.
- **Estado/evidencia:** cuatro pruebas ejecutadas y aprobadas. No se extrapola latencia/capacidad productiva a partir de consultas locales.

### V17 · formularios y dos pestañas reales (D, I)

- **Rol/datos:** Coordinación, dos CSV distintos y edición de estudiante con dos campos reason.
- **Pasos:** navegador Edge real, abrir dos pestañas autenticadas, previsualizar/confirmar cada CSV; enviar reset con confirmación distinta y motivo exclusivo.
- **Esperado:** identidades distintas y dos cuentas; solo reason del reset se repuebla, otro reason vacío y contraseñas vacías. `test_only_submitted_form_receives_old_reason_and_field_errors` aporta regresión de servidor aprobada.
- **Evidencia/observado:** resultados y PNG en índice visual final; script privado browser-a05.py. No se identifican IDs HTML distintos como sustituto del scope de old/errors.
- **Estado observado:** ejecutada y aprobada: dos cuentas creadas desde dos identidades independientes;
  motivo exclusivo aparece una vez, otro reason vacío y contraseña vacía tras el error de reset.

### V18 · lote NFC en navegador, NDEF emulado (E)

- **Rol/datos:** Coordinación, selección explícita de dos estudiantes; API NDEF sustituida deliberadamente, endpoints/BD reales.
- **Pasos:** preparar primer turno, escribir/leer, servidor confirma pero se pierde respuesta, repetir lectura; siguiente turno falla escritura, reintentar, leer/confirmar; preparar otra y cancelar.
- **Esperado:** no preparar el segundo token antes de confirmar primero; no avanzar por error; dos activaciones coherentes, cancelación conserva lo terminado y reemplazo anterior activo.
- **Evidencia/observado:** índice visual final y prueba nativa/funcional V13. NDEF emulado se etiqueta como tal; nunca se cuenta como NFC físico.
- **Estado observado:** ejecutada y aprobada: dos turnos completados, respuesta perdida recuperada,
  segundo turno detenido por escritura fallida y completado tras reintento; cancelación deshabilitó
  escritura/confirmación sin deshacer activaciones previas.

### V19 · escritorio, móvil y cuenta provisional (I, K)

- **Rol/datos:** Coordinación/docente/estudiante y cuenta provisional del seeder.
- **Pasos:** Edge a 1440, 390 y 320 px; imports/credenciales/egresados/sesiones/permisos/historial, dashboards/terminal/lector compartido por rol. Medir ancho de documento, etiquetas y errores JS; cuenta provisional intenta ruta académica.
- **Esperado:** sin desbordamiento global, controles utilizables y sin error JS; perfil obligatorio. Las tablas conservan scroll interno cuando corresponde.
- **Evidencia/observado:** medidas, capturas y hallazgos corregidos se consolidan en el índice visual. No se atribuye revisión manual a pantallas que solo tuvieron una aserción HTTP.
- **Estado observado:** ejecutada y aprobada en Edge 156.0.4314.42: **74 comprobaciones, 64 medidas,
  65 PNG**, cero errores JS; ningún ancho global excedió viewport. Se corrigieron filtros NFC
  apretados en móvil, dos opciones residuales 6–10 y texto CSV que aún decía que reemplazaba
  otras revisiones. Alta individual/lote mostró exactamente 1–5/Egresado en los tres tamaños.
  Índice y límites de inspección manual: [a05-evidencias-visuales.md](a05-evidencias-visuales.md).

### V20 · errores de red/HTTP y expiración (I)

- **Rol/datos:** cliente JavaScript con respuestas 401/403/419/422/429, HTML y confirmación ambigua.
- **Comando:** `npm test`, casos `a05-integration.test.js`, EventMemory, NFC, geolocalización y adquisición.
- **Esperado/observado:** 44 pruebas aprobadas; mensajes conservan estado/detalles, HTML 200 queda ambiguo y reintentable, expiración exacta no permite escritura nueva, URL compartida solo acepta origen/contexto propio. Red perdida conserva UUID/cuerpo, cambios deliberados reciben UUID nueva.
- **Corrección:** se evita descartar una confirmación ambigua o bloquear su replay por mera expiración visual. Límite: tests JS aislados; V18/V21 comprueban flujos de navegador con servidor real.
- **Extensión en navegador real:** el manual recibió confirmación del servidor con respuesta perdida;
  se esperó el minuto real de vencimiento, se recuperó foco y se repitió exactamente el mismo
  cuerpo/UUID con éxito. Otra entrada manual quedó deshabilitada y el panel compartido oculto.
  También se verificó manual deshabilitado sin ventana, con solo enlace y tras cierre, e invitación
  cerrada del estudiante con botón deshabilitado. Estado: ejecutadas y aprobadas.

### V21 · escrituras bloqueadas y reactivación (H)

- **Rol/datos:** todos los perfiles, grupos suspendidos, estados de ciclo/cómputo y dependencias históricas.
- **Pasos/pruebas:** matriz parametrizada `OfferingManagementTest` y `AcademicClosureTest`: invocar acciones/rutas directamente, repetir un evento ya confirmado, reactivar/reabrir y tratar de borrar con dependencias.
- **Esperado:** nuevas escrituras humanas bloqueadas bajo lock; replay conservado, sistema puede finalizar desactivados; activar no reabre recursos/períodos, borrar solo vacío/seguro conserva auditoría.
- **Defecto/corrección:** se añadió close_period a la matriz porque cierre manual omitía el guard de grupo activo. Evidencia funcional/nativa A04 en listado final.
- **Estado:** regresión completa al final; no se acepta ocultar botones como prueba de autorización.

## Instalación, limpieza y límites externos

### V22 · instalación, upgrade, dependencias y CI (C, K, L)

- **Rol/datos:** operador de testing; esquema vacío del arnés y cuenta sintética con año cinco/hash conocido solo por fixture.
- **Pasos:** suite con RefreshDatabase; `php scripts/verify-a05-upgrade.php --env=testing` en transacción rollback-only: bajar A05, año seis, intentar migrar y comprobar que no alteró parcialmente, clasificar en cinco, migrar y conservar cuenta/hash. Ejecutar Composer validate/platform/audit, Pint, build y JS.
- **Esperado/observado:** sonda incremental aprobada sin persistir cambios; instalación limpia por suite, build correcto y auditoría Composer sin avisos conocidos. Dos paquetes vulnerables del lock corregidos, sin paquetes nuevos; tres enums vacíos sin referencias eliminados.
- **Estado:** comandos y totales finales abajo. CI incluye upgrade/carreras A04/A05; CI remoto **no ejecutado** porque no hubo push/PR.

### V23 · NFC físico (E, F)

- **Estado:** requiere dispositivo; no ejecutada físicamente.
- **Impedimento:** no hay Android/NFC/etiquetas accesibles desde este entorno de trabajo.
- **Aceptación pendiente:** HTTPS real, registrar modelo/Chrome/tag, emitir al menos cinco etiquetas y 50 lecturas, repetir cancelación/escritura fallida/red perdida/reemplazo/revocación, probar etiqueta compartida y alternativa QR/enlace. Confirmar filas/auditorías en servidor; ninguna presencia antes de su respuesta.

### V24 · GPS/HTTPS del teléfono (A02, F)

- **Estado:** requiere dispositivo/lugar real; no aprobada por pruebas sintéticas.
- **Impedimento:** sin sensores del docente/estudiante en el sitio de uso.
- **Aceptación pendiente:** HTTPS, radio 30 m, precisión individual ≤10 m; medir éxito/fallos y tiempos en el aula/lugar, datos recientes y cancelaciones. No persistir coordenadas del estudiante y comprobar alternativas disponibles. Proximidad al docente no demuestra presencia dentro del aula.

### V25 · hosting definitivo y módulos de Persona 3 (B, K, L)

- **Estado:** hosting/SMTP/backups no ejecutados; módulos B pendientes fuera del incremento.
- **Impedimento:** no se proporcionó acceso a hosting final, servidor SMTP ni respaldo real.
- **Aceptación pendiente:** desplegar versión revisada con respaldo, migrate incremental, servidor web/HTTPS/proxy/cookies privados, iniciar único scheduler supervisado, detenerlo/recuperarlo, comprobar indicador y alertas, archivos privados, envío SMTP y restauración de respaldo. Ver pasos concretos en a05-entrega.md.
- **Alcance:** resultados/cierres existentes accesibles; PDF/CSV de resultados, ranking y noticias continúan pendientes, sin pantallas de guardado simulado.

## Resultados consolidados y evidencias

### Trazabilidad de la solicitud

| Requisito | Evidencia local | Límite separado |
|---|---|---|
| TXT 1: retirar demo/simulación residual | Fuentes login/layout/terminal/módulos en inventario; V19 y regresión de presentación | DEMO_SEED sigue protegido; fixtures no son datos reales |
| TXT 2: 1–5/Egresado | V08, V19, V22 | Legados NULL sin inferencia |
| TXT 3: clave común CSV | V09, V11, V12 | Comunicación de la clave por Coordinación fuera de registros/documentos |
| TXT 4: emisión por lote/año | V13, V18 | V23: etiqueta física |
| TXT 5: teléfono del estudiante | V14, V19, V20 | V23/V24: NFC/GPS físicos; no HCE |
| TXT 6: finalizar al vencer | V01, V04, V05, V07 | V25: operación del hosting |
| TXT 7: formularios aislados | V17, regresión del scope, inventario de Blade | Sin afirmar auditoría WCAG completa |
| A: fin/cierres/registro tardío | V01–V05 y V21 | Sin inventar inicio histórico |
| B: scheduler mantenible | V06/V07, Compose y estado de salud | V25 |
| C: años/egresados/migraciones | V08/V22, restricciones PostgreSQL y seeder | Clasificar años >5 antes de actualización |
| D: CSV/cuentas | V09–V12/V17 y carrera CSV de V06 | 500 filas/1 MB; no sobrescritura |
| E: credenciales | V13/V18 y carreras NFC de V06 | V23 |
| F: NFC viable | V14/V19/V20, documentación primaria | V23/V24; método declarado |
| G: reasignación | V15 y ambos órdenes de carrera V06 | Consulta según asignación actual |
| H: estados y autorizaciones | V03/V21, matrices HTTP/acciones y carreras | No reactivar recursos por reabrir/reactivar |
| I: UX e integración | V16–V20 y capturas/medidas | Scroll interno de tablas; accesibilidad básica |
| J: consultas/concurrencia | V06/V16 y paginación de resultados/sesiones | Sin extrapolar carga productiva |
| K: seguridad/operación | V11/V12/V20/V22 y HTTP real con CSRF | V25, secretos preservados |
| L: limpieza/CI/documentos | V22, inventario, cinco documentos A05 y propuesta de PR | CI remoto no ejecutado |

### Ejecución estable final

| Comprobación | Resultado observado | Estado |
|---|---|---|
| PHP completo, lock Symfony corregido | **268 pruebas / 1952 aserciones**, 244.14 s, cero fallos/errores/warnings/skips | Ejecutada y aprobada |
| A05IntegrationTest (incluida arriba) | 17 pruebas / 147 aserciones | Ejecutada y aprobada |
| A05JourneyTest (incluida arriba) | 5 pruebas / 61 aserciones | Ejecutada y aprobada |
| OfferingManagementTest A04 ampliada (incluida arriba) | 54 pruebas / 311 aserciones, incluido cierre manual suspendido | Ejecutada y aprobada |
| Sonda HTTP del entorno Docker (incluida arriba) | 1 prueba / 2 aserciones, configuración efectiva preservada | Ejecutada y aprobada |
| JavaScript | **44/44**, cero fallos/omisiones | Ejecutada y aprobada |
| Carreras PostgreSQL nativas | **37 PASS**, cinco scripts, conexiones independientes | Ejecutada y aprobada |
| Edge real | **74 comprobaciones, 64 medidas, 65 PNG**, cero errores JS | Ejecutada y aprobada; NFC emulado |
| Migración incremental A04 → A05 | Cuenta/hash/año conservados, año seis bloqueado antes de DDL; rollback de toda la sonda | Ejecutada y aprobada |
| Instalación limpia y seeders | Migraciones desde cero por RefreshDatabase y DemoSeederTest; A05AcceptanceSeeder real después de las suites | Ejecutada y aprobada |
| Worker real / supervisión | Recuperación, reinicio automático tras muerte del proceso y repetición sin duplicar; estado healthy | Ejecutada y aprobada |
| Pint | 190 archivos aprobados | Ejecutada y aprobada |
| Composer validate --strict / platform | JSON/lock válidos y extensiones PHP 8.4 satisfechas | Ejecutadas y aprobadas |
| Composer audit --locked | Sin avisos conocidos de vulnerabilidad tras corrección del lock | Ejecutada y aprobada |
| npm run build | 74 módulos, assets generados, sin aviso Sass obsoleto | Ejecutada y aprobada |
| compose config --quiet / git diff --check | Configuración válida, sin errores de whitespace | Ejecutadas y aprobadas |
| CI remoto, hosting, SMTP, backup real | Sin acceso/ejecución remota; código de CI actualizado | No ejecutadas, V25 |
| NFC/GPS físicos | Sin dispositivos/sensores accesibles | Requieren dispositivo/lugar, V23/V24 |

La suite final se ejecutó después de detener el scheduler, con código estático durante toda la
corrida y las conexiones efectivas comprobadas. Todos los escenarios PHP V01–V05, V08–V16 y V21
quedaron aprobados en esta corrida, incluidas las regresiones heredadas señaladas arriba.
Los 268 casos con nombre, archivo, aserciones, duración y estado están en
[a05-pruebas-php.csv](a05-pruebas-php.csv). Fuente: JUnit privado
`storage/logs/a05-final-stable.xml`; salida `a05-final-stable.txt` en el mismo directorio.
Los conteos de subclases ya están incluidos en 268: no se suman nuevamente.

Comando exacto de la suite final:

```powershell
docker exec siaa-a05 php scripts/check-test-database.php
docker exec siaa-a05 php artisan test --compact --display-warnings --fail-on-warning --log-junit=storage/logs/a05-final-stable.xml
```

Después, sin worker activo: `docker exec siaa-a05 php scripts/verify-a05-upgrade.php --env=testing`
y `docker exec siaa-a05 php artisan db:seed --class=A05AcceptanceSeeder --env=testing`.
Build/JS se ejecutaron con Node 24 en el montaje de este worktree; PHP/Composer/Pint con PHP 8.4
en siaa-a05. Las órdenes equivalentes completas figuran en [a05-entrega.md](a05-entrega.md).

### Intentos fallidos o descartados durante el trabajo

- Una primera corrida empezó antes de terminar el build: 18 fallos relacionados con manifest/infraestructura
  y 227 aprobados. Se completó el build; ese intento no se presenta como aprobación.
- Los primeros casos nuevos detectaron cuatro errores de fixture (sesión auth.session al cambiar de
  identidad, estado sintético sin motivo y binding de recurso inexistente). Se corrigieron los fixtures
  y se ejecutaron primero los escenarios específicos y después la suite completa.
- Una corrida intermedia aprobó 265/1909 antes de los últimos casos/lock. Otra terminó con
  siete fallos/258 aprobados: se había editado controlador/vista durante la ejecución y el proceso
  cargó una combinación de versiones (`previewMessage` indefinido). Se descartó como evidencia final
  y se repitió íntegramente con fuentes estables: **268/1952**. No se ocultaron warnings ni se
  debilitó el arnés/CI. Un intento interrumpido tampoco se contabiliza.
- La primera apertura de navegador precedió al arranque HTTP y recibió ERR_EMPTY_RESPONSE.
  Se comprobó HTTP 200 antes de repetir los recorridos; las 74 comprobaciones finales pasaron.
- Las comprobaciones de salud antes de la primera ejecución y a 181 s sin ejecución nueva
  devolvieron fallo **esperado**, demostrando detección; no son defectos pendientes.

No queda un fallo ejecutable pendiente ni un defecto crítico/alto conocido dentro del alcance
revisado. Esto expresa el resultado de esta revisión y sus pruebas, no una garantía absoluta.
Los riesgos restantes de hardware/hosting están delimitados con procedimientos V23–V25.

### Estado de entrega

Base/HEAD permanece `6273a307f3411a5e1ba40c427153cdf39e545a0e`; rama
`feature/p3a-a05-integracion-final`, árbol con cambios propios sin stage/commit. No se hizo
commit, push, PR, merge, release ni despliegue. Versión 0.3.2 conservada. Checkout habitual:
únicamente su modificación previa de `.env.example` y `Microsoft/` sin seguimiento;
`.env`/APP_KEY y datos de desarrollo preservados. La copia privada del entorno mantiene el
mismo hash que el original, sin publicar su contenido. Evidencias/secretos/dependencias no
aparecen en el inventario de cambios para Git. El worker A05 queda detenido y el servidor
HTTP local disponible con datos sintéticos para revisión.
