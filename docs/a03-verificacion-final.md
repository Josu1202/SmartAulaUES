# Verificación final de A03 · 23/10/2026

## Veredicto

**Listo con pendientes externos explícitos.** El incremento A03 local cumple los escenarios
esenciales de integridad, cálculo, autorización, reapertura y automatización verificados aquí.
Se corrigió D01, que mostraba controles de registro después del horario, y se repitieron la batería
completa y la revisión visual afectada: **192 pruebas / 1433 aserciones sin warnings**, 39 JS,
16 comprobaciones concurrentes nativas, dos ejecuciones reales del scheduler y 20 capturas/medidas.
No quedan defectos conocidos de A03 ni requisitos funcionales locales esenciales sin verificar.

La operación del scheduler/respaldos en hosting (B07) y la aceptación física de GPS/HTTPS/NFC
de A02 siguen **no verificadas en ese entorno**, con acciones concretas al final. Este veredicto
habilita revisar/cerrar el incremento local; no acredita producción ni cierra la fase 3.
Versión 0.3.2 conservada; sin commit, push, merge ni despliegue.

## Preparación y matriz previa a la ejecución

Esta matriz se creó antes de ejecutar la nueva batería. Se conserva su estado inicial como registro
de planificación; su resolución está en «Cobertura final». Los resultados del 22/10 son antecedentes,
no resultados de esta revisión.
Se leyeron AGENTS.md, a03-closures.md, a03-verification.md y los contratos de A01/A02.
Rama `feature/p3a-a03-cierres-reapertura`, base `bf07b01`, versión 0.3.2, cambios locales sin publicar.
Solo `siaa_test`, previa comprobación efectiva de lectura/escritura. Relojes de prueba, sin cambiar el equipo.

| ID | Requisito/riesgo | Cobertura que se ejecutará | Límite/dato esperado | Estado inicial |
| --- | --- | --- | --- | --- |
| M01 | Inicio real, medianoche y fin programado | AcademicClosureTest: actual_start, cross_midnight; añadir inicio 23:59 | UTC real; antes de 00 no finaliza; después sí, salvo horario vigente | Pendiente |
| M02 | Finalización manual, elegibilidad y recursos | daily_finalization, still_in_progress + SessionWorkflowTest/A02 | Presente intacto; retiro inclusivo; fuera de vigencia sin fila; lectores cerrados | Pendiente |
| M03 | Canceladas/finalizadas y legacy | explicit_start_recovery + casos adicionales | Sin reproceso; sin inferir inicio; reparación con motivo | Pendiente |
| M04 | Clasificación al cierre y exclusión A01 | period_remains_open + ejemplo obligatorio nuevo | 10 programadas → 8 finalizadas + 2 sin inicio; 8/8 = 100 % | Pendiente |
| M05 | Contradicciones y vía administrativa | scheduled_with_attendance, preventive_approved, pending_preventive | Bloquea sin forzar; acciones reales reparan o rechazan permiso sin inventar clase | Pendiente |
| M06 | Fecha final y orden de cierre | period_remains_open, pending_permission, missing_and_inconsistent | Finalizar → clasificar → validar → snapshot; causas concretas | Pendiente |
| M07 | Independencia, reintentos y recuperación | scheduler_recovers, operational_failure + scheduler real | Otro cómputo progresa; fallo revierte el afectado; siguiente ejecución recupera sin duplicar | Pendiente |
| M08 | A01 exacto y justificaciones | AttendanceCalculationTest + cierres al 74/75/76 % | Comparación exacta; aprobación suma; nulos sin evaluables | Pendiente |
| M09 | Snapshot completo, privacidad e historia | snapshot_covers_all_students + auditoría de contenido | 22 filas, segunda página; hechos congelados; sin secretos/documentos/coordenadas | Pendiente |
| M10 | Atomicidad, corrupción e inmutabilidad | snapshot_and_closure_rollback, closed_period_without_valid, snapshot_corruption | Ningún cierre parcial; error explícito; versiones no reescritas | Pendiente |
| M11 | Roles, motivo y endpoints | administrative_endpoints + ampliar matriz de rutas | Docente/estudiante/ajeno/inactivo/anónimo rechazados; motivo obligatorio | Pendiente |
| M12 | Bloqueo de todas las escrituras | closed_period_blocks, csv_confirmation, independent_contact + inspección rutas | Registro/corrección/permisos/sesiones/vigencias/lotes/CSV bloqueados; cambios independientes permitidos | Pendiente |
| M13 | Reapertura, marcador y recierre | reopening_requires, cycle_order + navegador | En revisión; portal no reactivado; scheduler omite reabiertos; exactamente N+1 | Pendiente |
| M14 | Ciclos coherentes y orden | cycle_order, empty_period + casos adicionales | Fecha final completa; grupos identificados; reabrir ciclo primero; no borrar/archivar | Pendiente |
| M15 | Comando/scheduler reales y Sistema | comando en tests + schedule:run dos veces aislado | Hora efectiva; actor null; una versión; prune geográfico conservado | Pendiente |
| M16 | Carreras reales PostgreSQL | attendance-races, attendance-summary, academic-closures | Espera Lock observada entre procesos; datos finales y versión única | Pendiente |
| M17 | Flujos web y móvil | Edge/CDP aislado: tres perfiles, 1440/390/320 | Estados, historial, formularios/motivo, bloqueo, privacidad; capturas inspeccionadas | Pendiente |
| M18 | Regresión y controles del repositorio | Suite PHP, JS, build, Composer, Pint, diff | Sin warnings PHP ni fallos; avisos heredados declarados | Pendiente |
| M19 | Preservación y límites externos | Huellas env/desarrollo antes/después; revisión del diff | Sin cambios de desarrollo/secretos; hosting/GPS físico no acreditados | Pendiente |

## Tabla de escenarios: ejecución de acciones y endpoints

Evidencia `T`: [AcademicClosureTest.php](../tests/Feature/AcademicClosureTest.php),
33 casos ejecutados con PostgreSQL nativo. Cada sufijo entre comillas identifica un método de esa clase.
`AC`: [AttendanceCalculationTest.php](../tests/Feature/AttendanceCalculationTest.php).
Las fechas indicadas son relojes controlados de proceso; ninguna modifica el reloj del equipo.
La alteración SQL deliberada de las pruebas de corrupción simula una escritura externa indebida;
las reparaciones funcionales se ejecutan mediante acciones o formularios de la aplicación.

| ID | Funcionalidad/regla | Datos iniciales | Pasos ejecutados | Resultado esperado | Resultado observado | Estado | Evidencia |
| --- | --- | --- | --- | --- | --- | --- | --- |
| S01 | Inicio real y fin del día | Horario 22/10 16–17 UTC; inicio a las 16:07 | Iniciar; respaldo 23/10 05:59:59 UTC y 06:00:00 UTC; repetir | Guardar 16:07; finalizar al cambiar el día local, no esperar 24 h | Inicio 16:07; antes no genera filas; a 06:00 finaliza, crea 1 ausencia Sistema; repetir devuelve false | Verificado mediante ejecución | T `actual_start_is_saved` |
| S02 | Inicio próximo a medianoche | Horario local 23:50–23:59:59; inicio real 23:59:58 | Iniciar; intentar fin anticipado sin motivo; respaldo 23:59:59 y 00:00:00 | Motivo anticipado obligatorio; respaldo tras terminar día | Sin motivo permanece en curso; antes de 00 devuelve false; a 00 crea una ausencia, dos segundos después del inicio | Verificado mediante ejecución | T `start_two_seconds_before_midnight` |
| S03 | Horario atravesando medianoche | Inicio 22/10 23:40 local; fin 23/10 01:00 | Intentar respaldo 00:00 y 01:00 | Conservar horario válido | A 00:00 sigue en curso; a 01:00 finaliza. No se trunca la clase | Verificado mediante ejecución | T `cross_midnight_schedule` |
| S04 | Finalización manual | Sesión antigua sin started_at; otra aún dentro de horario | Finalizar antigua al vencer; otra anticipadamente con motivo | Vía manual real sin inventar fecha antigua | Ambas finalizan; antigua conserva started_at null; anticipada exige motivo | Verificado mediante ejecución | T `cross_midnight_schedule`, `start_two_seconds_before_midnight`; SessionWorkflowTest |
| S05 | Preservación y retiro inclusivo | Un presente, un retirado el mismo día con aprobación, un alta al día siguiente | Ejecutar respaldo con portal vencido y lector marcado activo | Presente intacto; justificada para retiro inclusivo; sin fila futura; cerrar recursos | Presente conserva todos sus atributos; genera 1 justificada; alta futura sin fila; portal cerrado y lector closed | Verificado mediante ejecución | T `daily_finalization_preserves_presence` |
| S06 | Las cuatro ventanas | Sesión en curso cuyo horario terminó a 17 UTC; portal hasta 19 UTC; reloj 18 UTC | Enviar QR, enlace, NFC y manual; intentar respaldo | Rechazar registros pese a estado en curso; ends_at no dispara respaldo | Cuatro respuestas 409 «La sesión ya no admite registros»; cero asistencias; respaldo false antes de terminar el día | Verificado mediante ejecución | T `still_in_progress_after_schedule` |
| S07 | Legacy fiable/no fiable | Auditoría única con after.started_at 16:03 y created_at 15:50; otra sin ese campo | Recuperar con motivo; intentar recuperación antigua | Usar timestamp explícito, jamás created_at | Primera guarda 16:03; segunda devuelve validación y mantiene inicio null | Verificado mediante ejecución | T `explicit_start_recovery` |
| S08 | No reproceso ni reactivación | Sesión finalizada, cancelada, portal y lector cerrados; un cierre previo | Reabrir cómputo; ejecutar comando y finishOverdue de ambas | Conservar datos y recursos; no recierre | Atributos de ambas sesiones/portal/lector idénticos; ambas llamadas false; cómputo open, una versión | Verificado mediante ejecución | T `reopening_never_reactivates_portals` |
| U01 | Clasificación solo al cierre | Programada vencida, cómputo termina 22/10 | Comando 22/10 23:59:59 local y 23/10 00:00 | Antes sigue programada; después sin inicio y sin ausencias | Antes scheduled/open; después unstarted/closed, cero asistencias; porcentaje y conclusión null; etiqueta visible | Verificado mediante ejecución | T `period_remains_open_until` |
| U02 | Programada con presencia contradictoria | Presencia existente en sesión scheduled | Intentar cierre; conciliar como Administración con motivo; cerrar | No clasificar ni perder presencia; reparación funcional | Primer intento mantiene scheduled/open; conciliación la finaliza, conserva presente; cierre posterior válido | Verificado mediante ejecución | T `scheduled_with_attendance` |
| U03 | Permiso preventivo aprobado | Scheduled sin evidencia de inicio con permiso approved | Intentar cierre; reabrir decisión y rechazarla con motivo; cerrar | No inventar clase; resolver contradicción administrativa | Antes no hay reporte; después unstarted, cero asistencias y auditoría request.reopened | Verificado mediante ejecución | T `preventive_approved_permission` |
| U04 | Permiso preventivo pendiente | Scheduled sin inicio con permiso pending | Intentar cierre; rechazar mediante AbsenceWorkflow; reintentar | Pending impide clasificación; rechazo permite clasificar | Inicial scheduled sin asistencias; después unstarted y closed | Verificado mediante ejecución | T `pending_preventive_permission` |
| P01 | Orden y permisos pendientes | In_progress vencida, inicio fiable y permiso pendiente | Cierre, segundo intento, aprobar permiso, reintentar | Finalizar antes de validar; bloquear pendiente; evitar auditoría repetida | Sesión completed pero período open con motivos; misma cantidad de auditorías al repetir; aprobación permite snapshot 100 % | Verificado mediante ejecución | T `pending_permission_blocks_close` |
| P02 | Faltantes/inconsistencias no se reparan al consultar/cerrar | Completed sin resultado; después ausencia con permiso approved | Intentar cierre, conciliar finalización, aprobar y conciliar de nuevo, cerrar | Bloquear hasta reparación explícita | Primer intento deja cero filas; finalizador crea absent; aprobación contradictoria bloquea; conciliación genera justified y permite cierre | Verificado mediante ejecución | T `missing_and_inconsistent_results` |
| P03 | Administración no fuerza política desconocida | Sin sesiones/inscripciones, versión de reglamento inexistente | Cierre administrativo | No usar otra fórmula ni certificar vacío inválido | Cómputo open; impedimento «Reglamento no disponible»; cero reportes | Verificado mediante ejecución | T `missing_policy_blocks_even` |
| P04 | Independencia y reintento sin ruido | Un período con legacy sin inicio y otro con cancelada | Saltar tres días; ejecutar comando dos veces | Recuperar vencidos independientes sin cerrar el bloqueado | Bloqueado open, independiente closed; un reporte total; segunda ejecución no cambia cantidad de auditorías | Verificado mediante ejecución | T `scheduler_recovers_missed_runs` |
| P05 | Recuperación tras fallo operativo | Dos períodos independientes; fallo inyectado al crear ReportVersion de uno | Ejecutar comando, retirar fallo y repetir | Revertir solo afectado; continuar lote; recuperar después | Primer comando falla, período afectado open/scheduled, otro closed; siguiente comando cierra el afectado sin duplicar reporte del otro | Verificado mediante ejecución | T `operational_failure_rolls_back_only` |
| C01 | Ejemplo obligatorio 10/8/2 | 10 programadas; ocho con inicio fiable y presente del titular; dos nunca iniciadas | Ejecutar comando y repetir | Ocho evaluables, no diez | Ocho completed, dos unstarted; titular 8/8 = 100.00 %, alcanza; segunda ejecución no cambia asistencias/reportes/auditorías | Verificado mediante ejecución | T `ten_scheduled_eight_finished` |
| C02 | Comparación exacta y permiso favorable | Mismas ocho evaluables; otros tres alumnos con 5, 5+1 aprobada y 7 favorables | Finalizar y generar snapshot | Debajo falla; igualdad y superior alcanzan | 5/8=62.50 % no alcanza; 6/8=75.00 % alcanza (5 presentes+1 justificada+2 ausentes); 7/8=87.50 % alcanza; 32 asistencias para cuatro alumnos | Verificado mediante ejecución | T `ten_scheduled_eight_finished` |
| C03 | Precisión de A01 | 30000 presentes de 40001 hechos evaluables | Calcular mediante A01 puro | Redondeo visual no debe aprobar por error | Se presenta 75.00 %, pero meets_minimum=false por comparación exacta 120000 < 120003 | Verificado mediante ejecución | AC `rounding_cannot_change_the_conclusion` en batería completa |
| C04 | Todos los estudiantes y lectura congelada | 22 inscripciones; presente titular; segunda página de 20 | Cerrar; simular cambio externo del presente a absent; consultar vigente e histórico | Snapshot completo, independiente del vivo y paginado por alumno | 22 filas congeladas; página 2 tiene dos filas y total 22; titular conserva 100 % pese al cambio externo | Verificado mediante ejecución | T `snapshot_covers_all_students` |
| C05 | Explicación y minimización | Diez sesiones, cuatro filas y permiso con motivo/documento marcador PRIVADO | Inspeccionar recursivamente claves y valores del snapshot | Hechos suficientes sin documentos/coordenadas/secretos | Cada cálculo conserva diez session_facts y fracción/conteos; no aparecen claves geográficas, credenciales, evidence_path, reason ni resolution_note; sin marcador privado | Verificado mediante ejecución | T `ten_scheduled_eight_finished` |
| C06 | Fallo antes de guardar snapshot | Scheduled sin inicio; evento ReportVersion.creating falla | Cerrar, verificar rollback; retirar fallo y cerrar | Ninguna clasificación/cierre parcial | Tras fallo: open, scheduled, cero reportes; reintento crea una versión | Verificado mediante ejecución | T `snapshot_and_closure_rollback` |
| C07 | Fallo después de crear snapshot | In_progress vencida sin resultado; falla guardado del estado closed | Comprobar reporte y resultado existen dentro de transacción, lanzar fallo; reintentar | Revertir reporte, resultado, auditoría y estado juntos | Tras fallo: open, current_report_id null, in_progress, cero reportes/asistencias/auditorías; reintento cierra con una versión y una asistencia | Verificado mediante ejecución | T `failure_saving_closed_state` |
| C08 | Cierre inválido e historia inmutable | Closed sin referencia válida; otro reporte con hash alterado | Consultar y pedir cierre; intentar modificar snapshot por modelo | Error explícito sin cálculo vivo; rechazo de sobrescritura | Interfaz «Cierre inconsistente», sin 100 % vivo; cierre manual rechazado; actualización de snapshot lanza LogicException | Verificado mediante ejecución | T `closed_period_without_valid_snapshot`, `snapshot_corruption` |
| C09 | Sin evaluables y vacío total | Una unstarted con alumno; otro cómputo sin inscripciones ni sesiones | Cerrar y consultar | Nulos, no 0 % ni conclusión negativa; vacío legítimo aceptable | Primer resultado no_sessions y nulos; segundo snapshot rows=[] válido | Verificado mediante ejecución | T `period_remains_open_until`, `empty_period_can_close` |
| R01 | Roles y acceso directo | Siete acciones administrativas; anónimo, estudiante, docentes propio/ajeno y coordinator inactivo | POST directo a cierre/reapertura de período/ciclo, ambas revisiones de sesión y reapertura de permiso | Rechazar sin mutaciones | Anónimo redirige login; roles activos no administrativos 403; inactivo JSON 403; estado y cero auditorías/reportes conservados | Verificado mediante ejecución | T `all_administrative_routes_reject` |
| R02 | Privacidad actual/histórica y ámbito | Cierre de un grupo y actores ajenos; 22 filas para personal | GET actual/histórico ajeno; pedir versión/cómputo incorrecto; consultar como titular | 403/404 o una fila propia | Ajeno 403 actual/histórico; versión 99 y período extranjero 404; titular total 1, sin nombre ni modelo colectivo del otro; closure_issues globales vacías para alumno | Verificado mediante ejecución | T `administrative_endpoints`, `reopening_both_resources`, `snapshot_covers_all_students`, `pending_permission_blocks_close` |
| R03 | Bloqueo de escrituras cerradas | Completed/absent, permiso rechazado, cómputo cerrado y otro abierto; lector propio y credencial NFC activa | Cambiar resultado, permiso, sesión, calendario, vigencia; intentar trasladar sesión al abierto; enviar las cuatro vías de registro y abrir portal | Error del cierre antes de mutar datos | Todas las rutas rechazan con error session/period_id/number según recurso; las cuatro vías de registro devuelven 422 con error session, cero eventos; permiso sigue rejected, fecha intacta y snapshot 0 % | Verificado mediante ejecución | T `closed_period_blocks_attendance`, `closed_period_blocks_permission_resolution`; ampliación final: 1 caso/37 aserciones, closed-routes-final.log |
| R04 | Lotes y CSV | Preview CSV anterior al cierre; un alumno nuevo para lote | Cerrar entre preview/confirmación; confirmar CSV y alta masiva | Revalidar en escritura, no confiar en preview | Ambos rechazan active_from; se conserva una inscripción y una versión | Verificado mediante ejecución | T `csv_confirmation_cannot_change`, `closed_period_blocks_attendance` |
| R05 | Cambios independientes | Cómputo cerrado y ciclo aún abierto | Ajustar retiro después del período y correo por acciones académicas | Permitir sin alterar cierre | Retiro queda 23/10, correo actualizado; período sigue closed | Verificado mediante ejecución | T `independent_contact_and_enrollment_changes` |
| R06 | Motivo, revisión e historial | Snapshot v1=100 %, ciclo/cómputo cerrados | Rechazar motivos vacíos; reabrir con motivo, corregir a absent, ejecutar automático, cerrar manual y repetir | Motivo obligatorio, automático omite, N+1 único | En revisión muestra 0 % vivo; cierre manual v2=0 %, v1=100 % intacta; dos versiones, sin duplicar al repetir | Verificado mediante ejecución | T `reopening_requires_admin_reason`, `reopening_both_resources` |
| Y01 | Orden ciclo/cómputo y marcador | Ciclo vencido con cómputo sin cerrar | Intentar ciclo; cerrar cómputo/ciclo; intentar reabrir cómputo; reabrir ciclo y cómputo; comando; recierre manual | Primero ciclo; scheduler omite reaperturas | Bloqueo identifica cómputo; reapertura de cómputo rechazada hasta reabrir ciclo; comando los deja active/open; ambos cierran manualmente | Verificado mediante ejecución | T `cycle_order_and_reopened_cycle` |
| Y02 | Fecha final, grupos afectados y conservación | Dos grupos del mismo ciclo, uno abierto | Intentar cerrar ciclo; completar segundo grupo; intentar 23:59:59; cerrar 00:00; reabrir solo primero | Identificar grupo; respetar día completo; conservar otros cierres/grupos | Motivo incluye ID de grupo/cómputo; antes de 00 validación, después closed; segundo cómputo sigue closed con un reporte, ambos grupos existen | Verificado mediante ejecución | T `cycle_deadline_and_other_groups` |
| Y03 | No eludir por edición general | Cómputo/ciclo válidos, después ciclo reabierto | PUT general status=closed; después status=planned | Transiciones exclusivas, marcador conservado | Ambos PUT tienen error status; no permiten saltar cierre ni borrar condición de revisión | Verificado mediante ejecución | T `empty_period_can_close` |

## Fallos encontrados y cambios de esta revisión

La primera ejecución de la ampliación pasó 28 de 31 casos y falló en tres **expectativas del ensayo**:
se enviaron `status/resolution_note` en vez de `decision/note`, se esperaba 403 web de una cuenta
inactiva aunque el middleware cierra su sesión y redirige a login, y no se capturaba la validación
del cierre manual prematuro del ciclo. Se corrigieron payload/expectativas, sin relajar autorización
ni cambiar reglas; la repetición pasó 33/33 (324 aserciones, 83.86 s).

Se ampliaron ocho pruebas de riesgos que faltaban como evidencia explícita: 10/8/2 y umbral,
inicio próximo a medianoche/manual, recursos tras reapertura, rutas cerradas, matriz de roles,
ciclos con varios grupos/medianoche, fallo posterior a crear snapshot y privacidad/motivos de ambos recursos.
Además se reforzaron las pruebas existentes de ventanas y carreras: vigencia de una inscripción real
en vez de usar una cuenta con rol inválido; snapshot final comprobado en ambos órdenes de escritura.

**D01 · controles de registro visibles después del horario.** La captura
`coordinator-legacy-session-390.png` reproduce una sesión en curso del 21/10, revisada el 23/10:
ofrecía «Abrir registro», «Iniciar lector» y registro manual aunque su horario había vencido.
El impacto era confusión operativa: las solicitudes al servidor se rechazaban, pero la pantalla
invitaba a realizarlas mientras esperaba la finalización diaria/manual.

Se corrigió `resources/views/academic/sessions/show.blade.php`: esos controles solo se muestran
en una sesión en curso, escribible y dentro de su horario. Fuera de él se explica el cierre de
registro y se conservan finalización, revisión y medición A02 sin registrar. No se cambian
los límites ni el registrador. Se extendió `still_in_progress_after_schedule` para comprobar
controles presentes a 16:20, ausentes a 17:00 exactas y a 18:00, con finalización y diagnóstico
todavía disponibles. Después de corregirlo pasaron las 192 pruebas (1433 aserciones, 207.48 s,
sin warnings). Las dos nuevas capturas y las comprobaciones CDP de Administración/Docente
confirmaron ausencia de apertura/lector/manual y conservación de finalizar/medir, sin desbordamiento
ni excepciones JS. Evidencia: regression-after-ui-fix.log, late-window-browser.log,
coordinator-late-fixed-320.png y teacher-late-fixed-390.png.

## Entorno, comandos y controles

- Windows/PowerShell; aplicación Laravel 13 en Compose con PHP 8.4.26 y PostgreSQL **17.11 nativo**.
  No se ejecutó Laravel con el PHP 8.2 del host ni con PGlite. Node del host 24.14.1 para CDP;
  el servicio Node 24 de Compose ejecutó las pruebas JS y Vite.
- Rama/base anteriores; se evalúa el árbol de trabajo A03 sin commit. `.env.example` y `Microsoft/`
  eran cambios previos y no se modifican en esta verificación. No hubo staging, push, merge ni despliegue.
- Zona académica America/El_Salvador; persistencia UTC. La fecha local de revisión es 23/10;
  durante la noche los instantes UTC corresponden al 24/10. Sin cambiar el reloj del sistema.
- Antes de pruebas destructivas: PDO de escritura **y** lectura confirmaron `siaa_test` mediante
  `scripts/check-test-database.php`. Baterías, carreras, fixtures web y limpieza se ejecutan secuencialmente.
  RefreshDatabase opera solo en esa base. No se aplicó ninguna migración nueva a desarrollo en esta revisión.
- Navegador aislado en `127.0.0.1:8091`, APP_ENV=testing, APP_DEBUG=false, DB_DATABASE=siaa_test;
  sesiones/incógnito separados por perfil y usuarios ficticios. Es HTTP local, no validación GPS/HTTPS físico.

Cambios evaluados: ClosureWorkflow/ReviewSession y el comando de cierre, SessionWorkflow,
AcademicRegistry/AuditRecorder/AbsenceWorkflow, AttendanceSummary/calculador A01,
ClosureSnapshot/modelos, migración incremental A03, controladores/rutas/Blade y sus pruebas/CI locales.
En esta revisión se conservaron esos avances y se ampliaron pruebas/carreras/documentación;
la única corrección funcional nueva fue D01 en el detalle Blade de sesión.

Comandos ejecutados desde la raíz (los auxiliares temporales tienen su propio control de ambas conexiones):

```powershell
docker compose up -d app
docker compose exec -T -e APP_ENV=testing -e DB_DATABASE=siaa_test app php scripts/check-test-database.php
docker compose exec -T -e APP_ENV=testing -e DB_DATABASE=siaa_test app sh -c 'php scripts/check-test-database.php && php artisan test --filter=AcademicClosureTest --display-warnings --fail-on-warning --colors=never'
docker compose exec -T -e APP_ENV=testing -e DB_DATABASE=siaa_test app sh -c 'php scripts/check-test-database.php && php artisan test --compact --display-warnings --fail-on-warning --colors=never && php tests/Concurrency/attendance-races.php --env=testing && php tests/Concurrency/attendance-summary.php --env=testing && php tests/Concurrency/academic-closures.php --env=testing'
# Repetición completa posterior a D01:
docker compose exec -T -e APP_ENV=testing -e DB_DATABASE=siaa_test app sh -c 'php scripts/check-test-database.php && php artisan test --compact --display-warnings --fail-on-warning --colors=never'
docker compose run --rm node npm test
docker compose run --rm node npm run build
docker compose exec -T -e GIT_CONFIG_COUNT=1 -e GIT_CONFIG_KEY_0=safe.directory -e GIT_CONFIG_VALUE_0=/var/www/html app composer validate --strict
docker compose exec -T -e GIT_CONFIG_COUNT=1 -e GIT_CONFIG_KEY_0=safe.directory -e GIT_CONFIG_VALUE_0=/var/www/html app composer check-platform-reqs
docker compose exec -T -e GIT_CONFIG_COUNT=1 -e GIT_CONFIG_KEY_0=safe.directory -e GIT_CONFIG_VALUE_0=/var/www/html app vendor/bin/pint --test
docker compose exec -T -e APP_ENV=testing -e DB_DATABASE=siaa_test app php artisan schedule:list
git diff --check
```

`safe.directory` se limitó al proceso: el bind mount de Windows produjo un aviso de propiedad de Git
en la primera consulta de requisitos de Composer; se repitió con esas variables y todos los requisitos
pasaron. No se cambió la configuración global de Git ni el arnés/CI para evitar comprobaciones.

Los logs de esta revisión y los auxiliares web se guardan fuera de Git en
`%TEMP%\smartaulaues-a03-final-20261023`. Son distintos de la evidencia histórica
`smartaulaues-a03-review` del 22/10. No se atribuye ninguna ejecución a CI remoto.

| Control | Resultado de esta revisión | Evidencia local |
| --- | --- | --- |
| Batería PHP en el estado final, posterior a D01 (incluye A01/A02) | 192/192, 1433 aserciones, 207.48 s, sin warnings ni omisiones | regression-after-ui-fix.log |
| A03 dirigido, anterior a D01 | 33/33, 324 aserciones, 83.86 s, sin warnings | targeted-final.log |
| Batería PHP anterior a D01 | 192/192, 1401 aserciones, 294.01 s, sin warnings | regression-native.log |
| Rutas cerradas, ampliación final de una prueba | 1/1, 37 aserciones, 62.92 s; sin warnings. Ejecutada después de la batería completa; no se suma como prueba distinta | closed-routes-final.log |
| JavaScript | 39/39, sin omisiones ni fallos | javascript.log |
| Vite | 72 módulos; build correcto en 58.44 s | build.log |
| Composer validate/platform | composer.json válido; PHP/extensiones cumplen | composer.log y salida repetida con safe.directory |
| Pint completo posterior a D01 | PASS, 177 archivos | pint-after-ui.log |
| Concurrencia nativa | 16 comprobaciones: seis registrador, dos lectura A01 y ocho cierre A03; todas correctas | regression-native.log |
| Navegador | 20 capturas/medidas; tres perfiles y repetición específica posterior a D01; cero excepciones JS | measurements/supplement/late-window JSON y PNG |
| Difusión de cambios | diff --check correcto; sin cambios de release | Salida Git |

El build emitió el aviso heredado de Sass sobre `mixed-decls` obsoleto y una observación de tiempos
de plugins; no falló. No se modificaron dependencias ni se ocultaron warnings PHP.

## Automatización, carreras y navegador

Carreras ejecutadas en PostgreSQL 17.11, con procesos PHP distintos. Los ocho casos A03
de [academic-closures.php](../tests/Concurrency/academic-closures.php) esperan observar en
`pg_stat_activity` el PID del hijo con `wait_event_type='Lock'` **antes** de confirmar el padre.
Se verifican filas/snapshot después de ambos procesos, no solo respuestas HTTP.

| ID | Funcionalidad/regla | Datos iniciales | Pasos ejecutados | Resultado esperado | Resultado observado | Estado | Evidencia |
| --- | --- | --- | --- | --- | --- | --- | --- |
| N01 | Cierre contra corrección | Completed/absent, período vencido | Padre cierra; hijo intenta present mientras espera lock | Corrección bloqueada; no divergencia | Error session; asistencia absent; un reporte con 0 % | Verificado mediante ejecución | regression-native.log, academic-closures.php |
| N02 | Corrección contra cierre | Misma situación | Padre corrige a present; hijo cierra tras esperar | Capturar corrección confirmada | Asistencia present; un reporte con 100 % | Verificado mediante ejecución | Mismo script |
| N03 | Aprobación de permiso contra cierre | Ausencia y permiso pending | Padre aprueba; hijo espera y cierra | Aprobación y justificación atómicas | Cómputo closed; snapshot 100 %, posterior a la aprobación | Verificado mediante ejecución | Mismo script |
| N04 | Dos cierres simultáneos | Un período abierto/vencido | Dos procesos solicitan cierre | Una versión, ambos idempotentes | Hijo termina sin error y reportes=1 | Verificado mediante ejecución | Mismo script |
| N05 | Registro retrasado contra cierre | Sesión completed, absent y portal vencido | Padre cierra; hijo solicita registro manual nuevo con advisory lock | Bloquear por cierre antes de crear evento | Error session; sigue absent; cero eventos; snapshot conserva 0 % | Verificado mediante ejecución | Mismo script |
| N06 | Cierre contra cambio de vigencia | Inscripción real; cambio válido de inicio de anteayer a ayer | Padre cierra; hijo cambia vigencia tras esperar | Conservar rango histórico | Error active_from; vigencia anterior intacta en inscripción y snapshot | Verificado mediante ejecución | Mismo script |
| N07 | Cambio de vigencia contra cierre | Mismo rango y cambio válidos | Padre cambia vigencia; hijo cierra tras esperar | Capturar nuevo rango completo | Una versión; active_from del snapshot y de inscripción coinciden con fecha nueva | Verificado mediante ejecución | Mismo script |
| N08 | Cierre de ciclo contra reapertura | Cómputo ya cerrado y coherente | Padre cierra ciclo; hijo intenta reabrir cómputo | Exigir reapertura previa del ciclo | Reapertura bloqueada; cómputo sigue closed | Verificado mediante ejecución | Mismo script |
| N09 | Regresión de registro y recursos | UUID/cuerpo iguales y distintos; NFC/enlace/manual; cinco alumnos; QR geográfico | Ejecutar seis carreras del registrador | Unicidad/idempotencia y ventanas coherentes | Mismo UUID: una fila/replay; cuerpo distinto: conflicto; tres métodos: una presencia; finalización: cinco resultados/portales cerrados; QR/cierre serializable y bloqueo observado | Verificado mediante ejecución | attendance-races.php, seis PASS en regression-native.log |
| N10 | Lectura coherente A01 | Dos alumnos, finalización o permiso concurrente | Consultar página mientras escritor tiene lock | No mezclar estados de una página | Dos esperas nativas correctas; resultados y aprobación coherentes después de commit | Verificado mediante ejecución | attendance-summary.php, dos PASS |

N05 representa una solicitud retrasada: un registro nuevo legal ya no está disponible cuando
ha terminado todo el día del cómputo. La carrera durante una ventana válida se cubre por N09
(registro frente a finalización). No se amplían horarios para fabricar una carrera que acepte
registros después del cierre.

Scheduler real en testing:

| ID | Funcionalidad/regla | Datos iniciales | Pasos ejecutados | Resultado esperado | Resultado observado | Estado | Evidencia |
| --- | --- | --- | --- | --- | --- | --- | --- |
| A01 | Ejecutar, no solo configurar | Sesión del día anterior, alumno sin resultado, período/ciclo vencidos; otro período bloqueado | schedule:run dos veces con mutex en caché database; evento everyFiveMinutes elegible mediante reloj del padre | Hijo ejecuta comando real con reloj real; finaliza/cierra sin duplicar | Dos eventos DONE (13 s/12 s). Sesión completed, una ausencia de Sistema, un reporte automático y ciclo cerrado; el otro período continúa bloqueado | Verificado mediante ejecución | scheduler.log, fixtures.php operación scheduler |
| A02 | Instante de procesamiento y actor | Inicio real del fixture anterior a la ejecución | Comparar timestamps persistidos con reloj real antes/después; actor nullable | No fingir ejecución a medianoche ni usar hora simulada del padre | finalized_at=2026-10-24T02:11:18Z; generated_at=02:11:19Z; comprobación real a 02:11:32Z; origin=automatic y actor_id=null | Verificado mediante ejecución | JSON en scheduler.log |
| A03 | Idempotencia del scheduler | Dos snapshots manuales de preparación y un reporte automático nuevo | Comparar conteos globales tras ambas ejecuciones | Segunda ejecución sin asistencias/reportes/auditorías nuevas | Ambas mantienen [9 asistencias, 3 reportes, 9 auditorías]; el período cron tiene exactamente un reporte | Verificado mediante ejecución | scheduler.log |
| A04 | Conservar prune de A02 | Plan de tareas y registros geográficos de pruebas | schedule:list y regresión GeographicAttendanceTest | Diario geográfico y cierres cada cinco minutos, ambos sin solapamientos | schedule:list conserva 0 0 * * * attendance:prune-location y */5 attendance:process-closures; prueba de retención ejecutada en batería completa | Verificado mediante ejecución | routes/console.php; GeographicAttendanceTest `location_retention_keeps_recent_portals` |

El padre usa 10:00 UTC solo para hacer elegible el evento sin esperar cinco minutos; el proceso
hijo ejecutó `artisan attendance:process-closures` con reloj real, como acreditan los timestamps.
No se simuló el comando del scheduler ni se cambió la hora de Windows/PostgreSQL.
La interrupción se probó como fallo operativo controlado y recuperación de vencidos (P04/P05/C06/C07),
no como apagado físico del servidor. No se atribuye evidencia de tolerancia a un corte eléctrico real.

Recorrido principal de navegador: **Edge 155.0.4283.62 headless/CDP**, formularios web y
cookies/CSRF reales, contextos aislados. Se generaron 16 capturas en el recorrido principal y dos
suplementarias; están en measurements.json/supplement-measurements.json y sus PNG.

| ID | Funcionalidad/regla | Datos iniciales | Pasos ejecutados | Resultado esperado | Resultado observado | Estado | Evidencia |
| --- | --- | --- | --- | --- | --- | --- | --- |
| V01 | Motivo, corrección, recierre e historial | Fixture v1=75 %, v2=100 % | Rechazar reapertura sin motivo; reabrir con formulario; corregir una presencia a ausencia; cerrar; consultar v2 | Validación, En revisión, v3 único e historia conservada | Motivo vacío: 422 y HTML inválido; En revisión a 100 %, corrección a 75 %, recierre v3=75 %; v2 sigue 100 % | Verificado mediante ejecución | browser.log, coordinator-closed/history/review PNG; browser-data-final.log |
| V02 | Docente y estudiante | Dos alumnos del grupo; cuentas propias | Login por perfil; consultar actual/histórico; POST de estudiante con CSRF válido | Docente sin controles administrativos; estudiante solo su fila | Docente ve dos filas y no formulario admin; estudiante una fila, sin nombre ajeno incluso en v2; POST directo devuelve 403 | Verificado mediante ejecución | teacher/student PNG, browser.log |
| V03 | Resolver impedimento funcionalmente | Legacy sin inicio y permiso pendiente | Ver impedimento; finalizar con formulario; aprobar permiso; cerrar cómputo | Resolver sin editar BD ni inventar inicio | Cómputo cierra con versión 1 y 100 %; sesión completed con started_at null; permiso approved | Verificado mediante ejecución | coordinator-blocked/legacy-session/permission/resolved PNG; browser-data-final.log |
| V04 | Presentación móvil y acciones de ciclo | Tres perfiles; 1440/390/320 px; ciclo cerrado | Capturar nueve combinaciones; históricos/revisión/bloqueos; desplazar tablas y abrir motivo del ciclo | Sin desbordar página; controles alcanzables; no errores JS | Ancho documento 1425/390/320 px; fila de estudiante única; formulario de ciclo y motivo visibles al desplazar tabla; cero excepciones JS en ambos recorridos | Verificado mediante ejecución | measurements.json y supplement-measurements.json, 18 PNG |

La consulta de BD posterior al recorrido principal confirmó exactamente `[[1,"75.00"],
[2,"100.00"],[3,"75.00"]]`, un reporte del período resuelto a 100 %, su permiso aprobado y
el inicio antiguo todavía null. Son resultados persistidos de los formularios, no estados simulados del DOM.

Se inspeccionaron visualmente las capturas de coordinación/escritorio y revisión/móvil,
docente/móvil, estudiante/móvil, impedimentos, legacy, resolución y acciones de ciclo; se observó
D01 en esa inspección y se corrigió. La tabla usa desplazamiento horizontal dentro de su contenedor.
La captura suplementaria del estudiante muestra el extremo derecho y la del ciclo el motivo desplegado.
Después de D01 se preparó otro fixture ficticio equivalente, se volvió a recorrer la sesión antigua
como Administración a 320 px y Docente a 390 px y se inspeccionaron ambas capturas corregidas.
CDP confirmó cero controles de apertura/lector/registro manual, un formulario de finalización y un
panel de medición A02 por perfil. Anchos de página 320/390, sin excepciones JS. Son **20 capturas/medidas
en total**, incluidas las 18 anteriores, conservadas para comparar el defecto con su corrección.

## Cobertura final de los requisitos acordados

La columna «Estado inicial» de la matriz previa conserva el registro de planificación. Esta tabla
indica su resolución. Para el umbral se eligieron fracciones pequeñas 5/8, 6/8 y 7/8
(62.50/75.00/87.50), complementadas con 30000/40001, que redondea a 75 % pero no alcanza;
se cubre debajo/igual/encima sin crear cientos de filas artificiales.

| Matriz previa | Escenarios y evidencia que satisfacen el requisito | Estado final |
| --- | --- | --- |
| M01 · inicio/medianoche/horario | S01–S03, S06; relojes controlados antes/después y calendario cruzando medianoche | Verificado mediante ejecución |
| M02 · manual/elegibilidad/recursos | S04–S06, SessionWorkflowTest y AttendanceRegistrationTest completos | Verificado mediante ejecución |
| M03 · no reproceso y legacy | S07–S08, V03 | Verificado mediante ejecución |
| M04 · sin inicio/denominador | U01, C01, C09; ejemplo obligatorio 8/8 = 100 % | Verificado mediante ejecución |
| M05 · contradicciones/vía administrativa | U02–U04, P02, V03; acciones y formularios reales | Verificado mediante ejecución |
| M06 · fecha/orden/causas/no forzar | U01, P01–P03, R03 | Verificado mediante ejecución |
| M07 · independencia/idempotencia/recuperación | P04–P05, C06–C07, A01–A03 | Verificado mediante ejecución |
| M08 · A01 exacto/justificada/nulos | C01–C03 y C09; AttendanceCalculationTest completo | Verificado mediante ejecución |
| M09 · snapshot completo/explicación/privacidad | C04–C05, R02, V01–V03 | Verificado mediante ejecución |
| M10 · atomicidad/corrupción/inmutabilidad | C06–C08, R06 y versiones persistidas V01 | Verificado mediante ejecución |
| M11 · roles/motivos/endpoints | R01–R02, R06, V01–V02; siete rutas por cada perfil de riesgo | Verificado mediante ejecución |
| M12 · escrituras cerradas/cambios independientes | R03–R05, N01–N07; corrección/permisos/estado/fechas/traslado/registro/lote/CSV/vigencia | Verificado mediante ejecución |
| M13 · reapertura/recierre/recursos | S08, R06, Y01–Y03, V01 | Verificado mediante ejecución |
| M14 · ciclos/grupos/orden/conservación | Y01–Y03, N08; motivos con grupo/cómputo y dos grupos conservados | Verificado mediante ejecución |
| M15 · automatización/Sistema/prune | P04–P05, A01–A04; comando y dos schedule:run reales | Verificado mediante ejecución |
| M16 · carreras/integridad final | N01–N10; 16 comprobaciones nativas, ocho A03 con Lock observado | Verificado mediante ejecución |
| M17 · tres perfiles/móvil/flujos | V01–V04; D01 corregido y revalidado en dos perfiles; 20 capturas/medidas | Verificado mediante ejecución |
| M18 · regresión/controles | PHP final 192/1433, JS 39, build, Composer, Pint y diff correctos | Verificado mediante ejecución |
| M19 · preservación/entorno/externos | Guardas siaa_test, huellas env y desarrollo antes/después; límites externos declarados | Verificado mediante ejecución local; hosting/hardware no verificados |

Inspección de código complementaria, sin presentarla como ejecución específica: estructura e índices
de la migración incremental; rechazo de rollback destructivo cuando hay historia A03; protección
`ReportVersion.deleting`; ausencia de rutas de borrado/archivo de grupos; middleware y CSRF de rutas;
orden de locks y reutilización de finalizador/calculador. La migración incremental en desarrollo y sus
datos anteriores tienen evidencia histórica separada del 22/10; no se volvió a aplicar aquí.

## Preservación y cierre del entorno de revisión

El control de desarrollo fue **solo de lectura**: entorno local y PDO de lectura/escritura
efectivos a `siaa`, conteos y SHA-256 de las columnas académicas anteriores. La comparación
se repitió después de las dos revisiones visuales y la limpieza; todas las huellas coincidieron.
El auxiliar conservaba en su mensaje el texto «migración incremental» de la auditoría anterior;
en esta ejecución únicamente comparó datos, sin ejecutar migraciones ni escrituras académicas.

| Tabla de desarrollo | Antes | Después |
| --- | --- | --- |
| users | 23 | 23 |
| academic_cycles | 1 | 1 |
| assessment_periods | 2 | 2 |
| class_sessions | 12 | 12 |
| enrollments | 40 | 40 |
| attendances | 83 | 83 |
| absence_requests | 0 | 0 |
| report_versions | 0 | 0 |

`.env` y `.env.example` conservan exactamente sus SHA-256 iniciales; APP_KEY no se cambió ni
regeneró. Microsoft/ y el cambio previo de .env.example siguen fuera del incremento. HEAD continúa
en bf07b01, índice sin archivos preparados, sin diferencias de versión/dependencias; diff --check correcto.

Los dos juegos de fixtures ficticios y sus sesiones HTTP se retiraron por sus IDs, previa guarda
de siaa_test. Los contextos web y Edge se cerraron; el contenedor temporal se detuvo y eliminó
mediante su opción --rm. Se retiraron los auxiliares/manifiestos de /tmp del contenedor principal.
Las 20 capturas, métricas, logs y copias de auxiliares permanecen en el directorio temporal del host
para revisión; no se incorporan al repositorio. La aplicación y PostgreSQL de desarrollo permanecen disponibles.
Evidencia: development-preservation.log, cleanup-final.log y comprobaciones finales de Git/huellas.

Los auxiliares se ejecutaron tras copiarlos temporalmente al contenedor, siempre en testing:

```powershell
$qaPath = Join-Path $env:TEMP 'smartaulaues-a03-final-20261023'
docker cp "$qaPath\fixtures.php" siaa-app-1:/tmp/a03-final-fixtures.php
docker compose exec -T -e APP_ENV=testing -e DB_DATABASE=siaa_test app sh -c 'php scripts/check-test-database.php && php /tmp/a03-final-fixtures.php setup && php /tmp/a03-final-fixtures.php scheduler'
docker cp siaa-app-1:/tmp/smartaulaues-a03-fixtures.json "$qaPath\fixtures.json"
node "$qaPath\browser.mjs"
node "$qaPath\supplement.mjs"
# Nuevo fixture equivalente tras corregir D01 y pasar nuevamente la batería:
node "$qaPath\late-window.mjs"
docker compose exec -T -e APP_ENV=testing -e DB_DATABASE=siaa_test app sh -c 'php scripts/check-test-database.php && php /tmp/a03-final-fixtures.php cleanup'
```

Entre scripts CDP se inició Edge headless con perfil temporal dedicado y puerto local 9226;
cada script cerró sus contextos y el navegador al terminar. Los comandos anteriores registran
las acciones ejecutadas; para repetirlas se deben preparar nuevamente contenedor/fixtures, pues se limpiaron.

## Pendientes reales y límites

| Tipo | Pendiente y motivo | Riesgo/acción exacta |
| --- | --- | --- |
| Código A03 | Ningún defecto conocido pendiente. D01 corregido y revalidado | Revisión del incremento local y su informe; no se requiere otra batería manual para suplir cobertura faltante |
| Entorno externo · B07 | Scheduler/respaldos en hosting no verificados: no se proporcionó una instalación remota autorizada; esta tarea prohíbe despliegue | Configurar un solo cron cada minuto o schedule:work supervisado en el hosting correcto; verificar APP_ENV/BD/hora/caché, procesar fixture autorizado, repetir y comprobar auditoría Sistema/versión única; acreditar respaldo y restauración |
| Entorno físico · A02 | GPS/HTTPS/NFC físico no ensayados con dispositivos aquí | Ejecutar el ensayo autorizado de a02-location-measurement.md, 30 m/10 m sin cambiar fórmula; comprobar permisos/precisión/HTTPS y NFC en hardware compatible |
| Otros módulos | PDF/CSV de reportes, ranking, archivo/eliminación de grupos y A04–A05/B01–B07 no forman parte de este incremento | Mantenerlos identificados como pendientes; no inferir cierre de fase 3 |

No hubo una prueba local esencial omitida por falta de navegador o PostgreSQL: ambos estuvieron
disponibles y se utilizaron. Hosting/hardware se clasifican como **no verificados en este entorno**;
no cuentan como aprobados. No se probó un apagado físico ni una restauración de producción.

## Revisión humana mínima

No se delega al usuario la repetición de los escenarios funcionales automatizados. Una lectura de
3–5 minutos del veredicto, D01 y dos capturas posteriores basta para revisar este incremento local.
La aceptación operativa del hosting corresponde a su instalación autorizada; la aceptación física
de A02 requiere los dispositivos y no se sustituye por este recorrido de Edge.
