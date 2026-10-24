# A04 · verificación por escenario

Implementación local iniciada el 23/10 y continuada el 24/10/2026. Rama
`feature/p3a-a04-gestion-grupos`, HEAD `5172b91` (merge A03 PR #5). Versión 0.3.2.
No commit/push/PR/merge/despliegue. [Contrato y comandos](a04-group-management.md).

## Entorno y evidencia

PHP 8.4.26 y PostgreSQL 17 nativo mediante Docker Compose. Antes de cada batería con datos se
ejecutó `scripts/check-test-database.php`, comprobando entorno testing, configuración y conexión
efectiva de lectura/escritura a **siaa_test**. RefreshDatabase permanece intacto y autorizado solo
en esa base desechable; no se debilitó el arnés ni CI.

Evidencias y auxiliares fuera del repositorio:
`C:\Users\jozzz\AppData\Local\Temp\smartaulaues-a04-20261023\`.
El nombre del directorio conserva el inicio del trabajo el 23/10; los registros `*-resumed` corresponden
a la continuación del 24/10. Son pruebas locales, no ejecuciones de CI remoto.

| Control | Resultado registrado | Evidencia |
| --- | --- | --- |
| Primera batería A04 | 47 pruebas / 268 aserciones aprobadas | targeted-final.log |
| Batería completa al reanudar | 242 pruebas aprobadas; falla de la sonda HTTP de infraestructura, 1733 aserciones en total | regression-resumed.log |
| Repetición aislada de infraestructura, sin cambiar código/plazo | 1 prueba / 2 aserciones aprobadas, 69.04 s | infrastructure-repeat.log |
| A04 final, incluidos dos escenarios nuevos de migración | 53 pruebas / 308 aserciones aprobadas, 92.79 s, sin warnings | a04-final-resumed.log |
| Revisión final de la leyenda del resumen histórico | Escenario afectado repetido: 1/1, 11 aserciones, sin warnings | history-wording-final.log |
| JavaScript | 39/39 aprobadas | frontend-resumed.log |
| Build Vite | Aprobado, 72 módulos, 43.04 s; aviso Sass mixed-decls heredado | frontend-resumed.log |
| Composer / plataforma | composer.json válido y todos los requisitos satisfechos | quality-resumed.log |
| Pint completo | 182 archivos correctos | quality-resumed.log |
| Carreras A04 | 15 comprobaciones con espera real de procesos PostgreSQL independientes | concurrency-a04.log |
| Regresiones concurrentes A01/A02/A03 | 16 comprobaciones nativas aprobadas; 31 junto con A04 | concurrency-regression.log |
| Navegador Edge/CDP | 17 capturas principales + 6 suplementarias; tres perfiles, 1440/390/320 px; cero excepciones JS y sin desbordamiento de página | browser-repeat.log, browser-supplement-resumed.log, measurements.json, supplement-measurements.json |
| Estado persistido después del navegador | Snapshot y ciclo cerrado conservados; sesión futura scheduled, permiso pending, una auditoría por transición y grupo vacío eliminado | browser-data-final.log |
| Scheduler real en testing | schedule:run dos veces; grupo sigue desactivado, sesión unstarted, una versión automática y cero asistencias nuevas | scheduler.log |

Los conteos anteriores se solapan: no se suman las 53 pruebas de A04 a las 242 funcionales como si
fueran conjuntos independientes. La primera regresión completa quedó interrumpida antes de producir
un resultado final (`regression-final.log`); no se acredita como aprobada. La repetición completa
informó conexión rechazada al servidor temporal de DockerEnvironmentTest. Se repitió únicamente la
sonda sin cambiar su plazo ni su código y pasó; la causa exacta del fallo transitorio no quedó determinada.

## Escenarios funcionales ejecutados

`OM` identifica métodos/variantes de `tests/Feature/OfferingManagementTest.php`.
Todos los estados «Ejecutado y aprobado» de esta tabla se refieren a la batería PHP final, salvo que
se indique explícitamente otro tipo de evidencia. No sustituyen validación de hosting o hardware.

| ID | Regla | Datos iniciales | Pasos ejecutados | Esperado | Observado | Estado | Evidencia |
| --- | --- | --- | --- | --- | --- | --- | --- |
| OM01 | Inicio y clasificación dependen del ciclo | Grupo activo, ciclo vencido abierto, cerrado y desactivado | GET dashboard, Actuales, Historial y Desactivados | Abiertos habilitados en inicio; cerrado histórico; desactivado con acceso propio | Actual y vencido abierto visibles; cerrado/desactivado excluidos del inicio; etiquetas correctas en sus vistas | Ejecutado y aprobado | test_dashboard_current_expired_open_disabled_and_closed_are_classified_by_cycle |
| OM02 | Filtros y paginación conservados | 14 grupos del mismo ciclo | Filtrar, consultar página 2, entrar/volver y solicitar vista vacía | 12 + 2 grupos y contexto intacto | Totales 14; páginas 12/2; cycle_id/availability/catalog_page conservados; vacío comprensible y filtro inválido 422 | Ejecutado y aprobado | test_filters_pagination_empty_state_and_navigation_keep_context |
| OM03 | Perfil y acceso histórico | Inscripción retirada, grupo cerrado/desactivado, grupo ajeno y otro estudiante | Consultar con docente/estudiante y acceder a URLs ajenas/administrativas | Historial propio visible; sin personas ajenas ni acciones administrativas | Inscripción retirada conservada; datos propios; grupo ajeno 403 y acciones administrativas 403 | Ejecutado y aprobado | test_teacher_student_historical_enrollments_and_direct_urls_preserve_scope |
| OM04 | Métricas actuales e históricas | Presencia NFC actual, sesión histórica y sesión de grupo desactivado | Consultar DashboardStatistics, inicio e Historial por ciclo | No mezclar ciclos ni perfiles; cuentas institucionales independientes | Actual: una finalizada/presencia/NFC y cero en curso; histórico filtrado; Builder sin ámbito para docente ajeno devuelve cero sesiones | Ejecutado y aprobado | test_dashboard_and_historical_operational_statistics_use_the_same_group_selection |
| OM05 | Motivo y transiciones auditadas | Grupo con cómputo abierto y sesión futura | Rechazar motivo blanco, desactivar/reactivar y repetir cada solicitud | Sin perder sesión, sin cerrar cómputo y sin duplicar auditoría | active false/true; sesión idéntica; cómputo open; actor/motivo/fecha y before/after correctos; una auditoría por transición | Ejecutado y aprobado | test_administrative_transitions_require_reason_and_audit_only_real_changes |
| OM06 | Impedimentos reales de suspensión | Nueve variantes: en curso, portal, lector, permiso pendiente, resultado faltante, justificación inválida, evidencia contradictoria, snapshot inválido y horario fuera del cómputo | Intentar desactivar cada variante | Motivo concreto; ninguna reparación silenciosa | Rechazos con causa; grupo habilitado, estado de sesión conservado y sin offering.deactivated | Ejecutado y aprobado | test_deactivation_explains_real_blockers_without_changing_data, proveedor blockers |
| OM07 | Vencidos requieren cierre explícito | Portal y lector vencidos aún abiertos | Intentar suspender, cerrar recursos con motivo dos veces, suspender y reactivar | Retirar impedimento sin cambiar resultados ni reactivar recursos | Primer intento bloqueado; portal/lector cerrados y auditados una vez; sin asistencia nueva ni cambio de sesión; siguen cerrados tras reactivar | Ejecutado y aprobado | test_expired_resources_are_closed_explicitly_without_closing_live_resources_or_rewriting_sessions |
| OM08 | Mantenimiento no cierra recursos vigentes | Portal/lector NFC vigentes y sesión en curso | Cerrar recursos vencidos | Conservar la operación vigente | Portal abierto, lector active y sesión in_progress | Ejecutado y aprobado | test_expiration_maintenance_never_closes_a_live_portal_or_reader |
| OM09 | Todas las escrituras humanas se revalidan bajo lock | Caller con grupo antiguo habilitado y estado persistido desactivado | Dieciséis vías: sesiones, portal/lector, inscripción/vigencia, cómputo, grupo, permiso/resolución/revisión, corrección y CSV | Suspensión efectiva desde las acciones, no solo en botones | Todas rechazan por grupo desactivado; permiso pendiente y resultado ausente conservados | Ejecutado y aprobado | test_all_human_academic_writers_recheck_inactive_group_in_the_transaction, proveedor writes |
| OM10 | Replay conserva contrato | Registro manual confirmado; finalización y desactivación posteriores | Repetir misma UUID/cuerpo y probar cuatro métodos con UUID nuevas | Recuperar operación confirmada; rechazar escrituras nuevas | Replay 200 con mismo attendance_id; QR/enlace/NFC/manual nuevos bloqueados; una asistencia y un evento | Ejecutado y aprobado | test_registration_replay_survives_deactivation_but_every_new_method_is_blocked |
| OM11 | Endpoints directos y cargas masivas | Grupo suspendido, inscripción existente y estudiante candidato | POST start/portal/lote/permiso y GET formularios | Rechazos también por URL y formularios sin operación | 422 con offering_id; fieldsets deshabilitados; sin permiso nuevo ni inscripción adicional | Ejecutado y aprobado | test_direct_http_writes_batch_and_forms_cannot_operate_on_disabled_groups |
| OM12 | Cuenta independiente y corrección académica | Grupo suspendido y cómputo cerrado | Reabrir cómputo, actualizar contacto, reactivar y reabrir | Contacto permitido; revisión requiere reactivación | Primera reapertura bloqueada; correo actualizado; segunda reapertura deja reopened_at y conserva versión 1 | Ejecutado y aprobado | test_independent_account_contact_changes_remain_available_and_period_reopening_requires_reactivation |
| OM13 | Planificación futura no es inconsistencia | Sesión futura con permiso preventivo aprobado | Desactivar | Conservar planificación y resolución | Sesión scheduled, permiso approved, cero asistencias; grupo desactivado | Ejecutado y aprobado | test_future_session_with_approved_preventive_permission_is_preserved_and_does_not_block_deactivation |
| OM14 | A03 procesa desactivados | Ciclo/cómputo vencidos, sesión nunca iniciada y grupo suspendido | Ejecutar attendance:process-closures dos veces | Sin inicio registrado y snapshot único sin ausencia inventada | unstarted, ciclo closed, una versión, cero asistencias; porcentaje/conclusión null y consulta snapshot | Ejecutado y aprobado | test_scheduler_processes_disabled_groups_preserving_unstarted_rules_and_snapshots |
| OM15 | Reactivar/reabrir conserva estados independientes | Ciclo y cómputo cerrados, grupo habilitable y otro suspendido | Suspender/reactivar, reabrir ciclo y procesar scheduler | Habilitados actuales En revisión; suspendidos siguen fuera; cómputos cerrados conservados | Estado/filtros correctos; snapshot idéntico; vigencia no elude cierre; reopened_at conservado y sin recierre automático | Ejecutado y aprobado | test_reactivation_of_closed_cycle_and_reopening_keep_independent_states_and_snapshot |
| OM16 | Borrado vacío, confirmación y auditoría | Grupo sin dependencias | Código erróneo, DELETE correcto y repetición | Rechazar confirmación; eliminar una vez y conservar identificación | 422 inicial; grupo eliminado; auditoría con ID/materia/código/motivo y after=null; repetición 404 | Ejecutado y aprobado | test_empty_deletion_requires_confirmation_preserves_audit_and_repeated_request_is_404 |
| OM17 | Calendario inicial vacío demostrable | Grupo creado mediante AcademicRegistry con formato | Verificar dependencias y eliminar | Retirar únicamente copia inicial vacía | Sin impedimentos; grupo/cómputo retirados y period.empty_removed conservado | Ejecutado y aprobado | test_only_untouched_generated_empty_periods_can_be_removed |
| OM18 | Dependencias impiden borrar | Once variantes: inscripción, sesión, asistencia, permiso, portal, evento, cómputo manual, ReportVersion, reapertura, ciclo cerrado y noticia | DELETE con motivo/confirmación válidos | Conservar información e indicar dependencias | Todas 422; grupo conservado; sin offering.deleted | Ejecutado y aprobado | test_deletion_is_blocked_by_academic_and_historical_dependencies, proveedor dependencies |
| OM19 | Migración incremental | Grupo existente con esquema anterior a active | Retirar columna A04 aún sin uso en siaa_test, aplicar up y comparar todos los campos previos | Solo active=true e índice nuevos | Campos e instantes anteriores idénticos; grupo habilitado | Ejecutado y aprobado | test_incremental_migration_preserves_existing_group_data_and_enables_existing_groups |
| OM20 | Rollback conserva historia de uso | Grupo desactivado y reactivado | Intentar down | Rechazar retiro del estado administrativo | Excepción explicativa; active y auditoría conservados | Ejecutado y aprobado | test_schema_rollback_cannot_erase_an_administrative_state_already_used |

## Concurrencia nativa A04

No se usó PGlite ni conexiones serializadas como sustituto. Cada caso crea un proceso PHP independiente,
publica su `pg_backend_pid`, observa `wait_event_type='Lock'` desde PostgreSQL y solo entonces confirma
la transacción ganadora. Los fixtures se retiran por sus IDs al terminar.

| ID | Regla | Datos iniciales | Pasos ejecutados | Esperado | Observado | Estado | Evidencia |
| --- | --- | --- | --- | --- | --- | --- | --- |
| C01–C07 | Desactivar gana frente a siete escritores/repeticiones | Grupo coherente, sesión programada y datos de cada operación | Retener transacción de desactivación; iniciar start/portal/register/permission/schedule/enroll/disable; observar espera y commit | Rechazar nueva actividad y no duplicar suspensión | Seis escritores bloqueados; repetición de disable sin cambio; una auditoría por grupo | Ejecutado y aprobado | concurrency-a04.log, offering-management.php |
| C08 | Inicio gana frente a suspensión | Sesión dentro de horario | Iniciar manteniendo lock; intentar desactivar desde otro proceso | Conservar inicio y bloquear suspensión | in_progress y grupo habilitado | Ejecutado y aprobado | concurrency-a04.log |
| C09 | Permiso gana frente a suspensión | Estudiante inscrito y sesión programada | Crear permiso; intentar suspender esperando su commit | Conservar pendiente y bloquear suspensión | Grupo habilitado y permiso conservado | Ejecutado y aprobado | concurrency-a04.log |
| C10 | Borrado gana frente a inscripción | Grupo realmente vacío | DELETE retenido; otro proceso intenta inscribir | Sin grupo ni dependencia huérfana | Recurso ausente tras espera; cero inscripción | Ejecutado y aprobado | concurrency-a04.log |
| C11 | Inscripción gana frente a borrado | Grupo vacío al comienzo | Inscribir manteniendo lock; intentar eliminar | Grupo e inscripción conservados | Borrado bloqueado; una inscripción | Ejecutado y aprobado | concurrency-a04.log |
| C12–C15 | Reactivar frente a cierre/reapertura, ambos órdenes | Cómputo cerrado con snapshot; grupo suspendido | Intercalar enable/close-cycle/reopen-cycle con espera nativa | Clasificación coherente sin cambiar versiones | Ciclo cerrado permanece histórico; reabierto actual; cómputo cerrado y una versión en los cuatro casos | Ejecutado y aprobado | concurrency-a04.log |

## Revisión visual y scheduler real

Edge **156.0.4314.42**, controlado por CDP con perfil temporal y contextos separados por cuenta.
Aplicación temporal `siaa-a04-review`, `http://127.0.0.1:8092`, APP_ENV=testing, DB_DATABASE=siaa_test,
sesiones de BD y cookies/CSRF reales. La aplicación de desarrollo no se utilizó para estos recorridos.
Fixtures íntegramente ficticios: CURRENT, EXPIRED, HISTORY, DISABLED, BLOCKED y EMPTY.

| ID | Regla | Datos iniciales | Pasos ejecutados | Esperado | Observado | Estado | Evidencia |
| --- | --- | --- | --- | --- | --- | --- | --- |
| V01 | Inicio y vistas reales | Actual y vencido abierto; histórico y suspendido | Login de Administración; consultar inicio, catálogo y filtros | Mantener abiertos actuales; separar cerrado/suspendido | Materia actual y Ciclo vencido todavía abierto visibles en inicio; histórica/desactivada excluidas; Historial y Desactivados permiten su consulta | Ejecutado y aprobado | browser-repeat.log; admin-dashboard-1440.png, admin-current-1440.png, admin-history-390.png, admin-inactive-320.png |
| V02 | Bloqueo con causa y sin resolver datos | BLOCKED con un permiso pending | Ver motivo y enviar POST de desactivación con CSRF | Botón deshabilitado, servidor rechaza y conserva pendiente | Mensaje Hay permisos pendientes; botón disabled y POST 422; permiso sigue pending y grupo activo | Ejecutado y aprobado | admin-blockers-390.png, browser-data-final.log |
| V03 | Motivo y cambios reales por formulario | CURRENT con una sesión futura | Motivo vacío; abrir formulario; desactivar, POST start directo, reactivar | Validar motivo, suspender operación, conservar planificación | Motivo 422 y HTML inválido; controles operativos ocultos; start 422 por offering_id; active vuelve true; sesión sigue scheduled sin started_at y una auditoría de cada transición | Ejecutado y aprobado | admin-deactivate-form-390.png, admin-disabled-390.png, browser-repeat.log, browser-data-final.log |
| V04 | Confirmación de borrado | EMPTY sin dependencias | DELETE con código erróneo; envío del formulario con EMPTY y motivo | Primer rechazo; eliminación final identificada | 422 al confirmar mal; grupo vacío eliminado y aviso de bitácora; estado final confirma ausencia | Ejecutado y aprobado | admin-delete-confirmation-390.png, browser-data-final.log |
| V05 | Docente sin administración | Grupos asignados, incluido DISABLED | Login propio; filtro/URL de suspendido; POST de reactivación | Consulta propia sin controles ni permiso administrativo | Grupo consultable, sin formulario de administración y POST 403 | Ejecutado y aprobado | teacher-inactive-390.png, supplement-teacher-disabled-320.png, browser-repeat.log |
| V06 | Estudiante e integridad del snapshot | HISTORY cerrado, snapshot colectivo de dos filas; DISABLED inscrito | Consultar catálogo, cierre vigente y versión 1; intentar reactivar | Solo fila propia y snapshot intacto | Una fila con 100.00 %, sin OTRA_PERSONA_PRIVADA_A04; POST 403; hash original idéntico, dos filas siguen almacenadas en el reporte colectivo | Ejecutado y aprobado | supplement-student-snapshot-1440.png, student-history-catalog-390.png, student-disabled-320.png, browser-data-final.log |
| V07 | Teclado, etiquetas y validación nativa | Filtros etiquetados y details de desactivación cerrado | Tab desde ciclo; Space sobre summary; comprobar formulario sin motivo | Foco y apertura accesibles; motivo obligatorio | Tab alcanza estado del grupo; Space abre details; checkValidity=false y etiquetas explícitas | Ejecutado y aprobado | supplement-deactivate-keyboard-390.png, browser-supplement-resumed.log |
| V08 | Escritorio/móvil y estados vacíos | Tres perfiles, anchos 1440/390/320 | Capturar 23 vistas, medir documento y recorrer horizontalmente la tabla | Sin scroll horizontal de página; tablas con su región propia | Documento 1425 en viewport 1440 y 390/320 en sus viewports; estados vacíos legibles, tabla desplazable y cero excepciones JS | Ejecutado y aprobado | measurements.json, supplement-measurements.json; supplement-current-320.png, admin-empty-state-320.png, supplement-student-snapshot-right-320.png |
| R01 | Scheduler no excluye grupos desactivados | Grupo CRON suspendido, ciclo/cómputo vencidos, sesión scheduled sin evidencia | schedule:run real dos veces, caché/mutex de BD; comprobar filas y timestamps | Unstarted, cierre único, actor Sistema y cero ausencia inventada | Ambas ejecuciones DONE en 17 s; active=false, sesión unstarted, una versión automática, generated_by=null, cero asistencias del grupo; conteos globales estables en la repetición | Ejecutado y aprobado | scheduler.log, fixtures.php operación scheduler |

Para hacer elegible el evento cada cinco minutos se simuló únicamente el reloj del proceso padre de
`schedule:run` a las 10:00 UTC. El hijo PHP ejecutó el comando real con la hora real del equipo, que no se
modificó: `generated_at=2026-10-24T17:46:16Z`; comprobación a `17:46:35Z`. La validación compara el instante
persistido con el intervalo real de ejecución y exige origen automático/actor null. No se presenta
la hora simulada del padre como momento real de procesamiento.

El primer arranque HTTP de revisión tardó 2 min 26 s y agotó el plazo del auxiliar CDP; no llegó a
producir capturas ni cambios académicos. Se conservó el error en browser.log y se corrigió el auxiliar
para esperar ese arranque y preservar el error principal durante la limpieza. Una primera llamada del
suplemento encontró Edge todavía sin puerto CDP; se añadió espera de disponibilidad. Ninguno de estos
ajustes cambia aplicación, CI o límites de registro. Los recorridos posteriores terminaron aprobados.

Las seis capturas suplementarias corrigen el encuadre mediante scroll real del navegador para no
colocar el título bajo la barra fija; no se editaron las imágenes. Se inspeccionaron visualmente
el catálogo a 320, la reactivación a 390 y el snapshot propio en escritorio/móvil. Se conservan los
PNG originales y las medidas, con datos ficticios y sin credenciales.
Después se simplificó únicamente la leyenda del resumen histórico para explicar los conteos y
dirigir a los resultados de cierre sin terminología interna. Se repitió su escenario PHP: 1/1 y
11 aserciones. Las capturas conservan la redacción anterior de esa leyenda; la funcionalidad es idéntica.

Capturas representativas dentro del directorio de evidencia:

- `supplement-current-320.png`: filtros Actuales/ciclo/estado y grupo habilitado.
- `supplement-admin-disabled-390.png`: suspensión, consulta conservada y reactivación con motivo.
- `supplement-deactivate-keyboard-390.png`: formulario abierto desde teclado.
- `admin-delete-confirmation-390.png`: identidad del grupo y confirmación de eliminación.
- `supplement-teacher-disabled-320.png`: consulta sin administración.
- `supplement-student-snapshot-1440.png`: única fila propia, 100.00 % y versión de cierre.
- `supplement-student-snapshot-right-320.png`: región horizontal de la tabla dentro del móvil.

## Defectos y ajustes de verificación

- Una primera condición de desactivación confundía el permiso preventivo aprobado de una sesión futura
  con una contradicción de cierre. Se limitó esa comprobación al escenario vencido, conservando A03 y
  añadiendo OM13. Sesiones futuras y cómputos abiertos no bloquean por sí solos.
- La paginación del catálogo usa `catalog_page` al entrar al grupo para no cambiar la página de
  inscripciones. Los formularios de resultados/versiones conservan el contexto de consulta.
- DashboardStatistics reaplica siempre el ámbito del actor, incluso al recibir un Builder externo;
  OM04 prueba explícitamente el docente ajeno con consulta sin filtrar.
- En grupos suspendidos, los enlaces de edición se identifican como consulta y un cómputo ya cerrado
  indica reactivar antes de reabrir, en lugar de mostrar una futura disponibilidad de cierre.
- El auxiliar de pruebas se renombró makeSession para no colisionar con TestCase::session. Las dos
  fallas iniciales de la batería correspondían al montaje de tests: cambiar de perfil sin reinicializar
  auth.session y comparar atributos recién creados contra su representación PostgreSQL sin refrescar.
  Se corrigió el montaje, se mantuvieron las reglas y se aprobaron las baterías posteriores.
- Falla HTTP transitoria de infraestructura y arranque lento de los auxiliares web: registradas y
  repetidas sin debilitar pruebas ni CI. No se conoce un defecto funcional de A04 pendiente.

## Conservación, límites y pendientes

- `.env` y el cambio previo de `.env.example`: huellas SHA-256 idénticas a las tomadas antes de A04.
  APP_KEY queda preservada por la identidad del archivo completo. `Microsoft/` no se leyó ni modificó.
- La sonda de desarrollo comprueba lectura/escritura efectiva en `siaa` y compara huellas de columnas
  preexistentes en ocho tablas: cuentas, ciclos, cómputos, sesiones, inscripciones, asistencias, permisos
  y reportes. No se ejecutaron migraciones, seeders, cierres ni escrituras de prueba sobre desarrollo.
  La comprobación final pasó y `migrate:status` confirma A04 Pending en desarrollo. Evidencias:
  development-preservation.log y development-migrations-status.log.
- Los siete grupos ficticios de navegador/scheduler y sus dependencias, sesiones HTTP, cuentas y
  auditorías de prueba se retiraron por sus IDs de siaa_test. Contextos Edge cerrados y contenedor
  temporal siaa-a04-review detenido/eliminado; aplicación y PostgreSQL de desarrollo conservados.
  Las capturas, medidas, logs y auxiliares permanecen en el directorio temporal del host.
  Evidencia: cleanup-final.log y verificaciones de procesos/contenedores.
- Versión, composer/package y lockfiles sin diferencias. La migración de desarrollo queda para el
  usuario, con comandos en el contrato. No se cambió la hora del equipo.
- Hosting/cron remoto, restauración y GPS/HTTPS/NFC físicos no verificados. Requieren entorno o
  dispositivos reales y no se acreditan con pruebas locales. A05/B01–B07 siguen pendientes.
- Revisión humana mínima: contrastar filtro por ciclo/estado, motivo de suspensión/reactivación,
  conservación de una sesión futura y auditoría de borrado vacío; revisar las capturas móviles y el PR
  propuesto. No integrar ni publicar desde este incremento.

| Clasificación | Situación final |
| --- | --- |
| Ejecutado y aprobado | Escenarios PHP, pruebas de migración, JavaScript/build, calidad, 31 comprobaciones nativas, 23 capturas/medidas y scheduler local real repetido |
| Inspeccionado sin ejecución en desarrollo | Migración/rollback de la instalación real y configuración diaria de prune geográfico; no se modificó siaa |
| Bloqueado por el entorno | Ninguna comprobación local queda bloqueada; los fallos transitorios de arranque se registraron y sus repeticiones pasaron |
| No verificado | CI remoto, hosting/cron y restauración remota, GPS/HTTPS/NFC físicos y aceptación institucional |

Inventario del incremento en [a04-files.txt](a04-files.txt); excluye expresamente el cambio previo de
`.env.example` y el directorio `Microsoft/`. Propuesta de revisión en [a04-pr.md](a04-pr.md).
