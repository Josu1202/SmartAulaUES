# A03 · finalización automática, cierres y revisión administrativa

**Regla vigente A05 (24/10):** sustituye la espera diaria original por finalización al alcanzar
`ends_at` UTC en la siguiente ejecución, cada minuto. Inicio real fiable sigue siendo obligatorio;
programadas sin inicio no generan ausencias. [Decisiones A05](a05-decisiones.md) y
[operación de entrega](a05-entrega.md) prevalecen sobre los registros históricos de verificación A03.

Continuidad del 24/10: A03 está integrado por PR #5 (`5172b91`). [A04](a04-group-management.md)
clasifica Historial por ciclo cerrado y añade suspensión administrativa independiente. El scheduler
conserva grupos suspendidos; las correcciones humanas y reaperturas de cómputo requieren reactivarlos.
La descripción de implementación local inferior conserva el contexto anterior al merge.

Incremento local sobre `bf07b01` (merge de A02, PR #4). A01 está integrado por PR #3,
`18c7596`. Rama `feature/p3a-a03-cierres-reapertura`, release **0.3.2** conservada.
La integración de A02 no demuestra aceptación física de GPS/HTTPS; continúa pendiente.

## Estados y calendario

- **Sesión Finalizada** (`completed`): conserva presentes y resoluciones; la finalización genera
  ausente o justificada para inscripciones elegibles que carecen de resultado. Alta y retiro incluyen
  su fecha local. Los permisos pendientes no impiden finalizar, pero sí cerrar el cómputo.
- **Sin inicio registrado** (`unstarted`): se clasifica al intentar cerrar un cómputo vencido,
  nunca por vencer el horario individual. No genera resultados ni integra el denominador A01.
  Se conserva separada de finalizadas y canceladas en filtros, conteos y resultados.
- **Cómputo Cerrado**: snapshot válido de todo el grupo y bloqueo de escrituras que alteren
  sus resultados. Cierre y snapshot se confirman en una transacción.
- **Ciclo Cerrado**: fecha final local terminada, todos sus grupos con cómputos definidos y
  todos los cómputos cerrados con snapshot válido, sin sesiones o permisos pendientes.

Instantes en UTC; días académicos en `America/El_Salvador`. Se guarda `started_at` al iniciar
realmente una sesión y `finalized_at` al procesar su finalización, sin simular medianoche.
Una sesión en curso con inicio fiable es elegible desde `ends_at <= now()` en UTC.
No espera medianoche ni 24 horas desde su inicio. El registro usa intervalo final exclusivo;
una nueva escritura se rechaza al alcanzar `ends_at`, aunque el scheduler aún no la finalizó.

**Calendario que cruza medianoche:** SessionWorkflow conserva la fecha/hora completas de fin.
No se trunca la clase a medianoche: se procesa desde su `ends_at` real en una ejecución posterior.
La finalización manual con motivo anticipado mantiene las reglas actuales.

Las ventanas de QR/enlace/NFC/manual siguen limitadas por horario, portal, lector y autorización.
Un retraso del procesador no admite registros posteriores al horario autorizado.
A02 conserva radio 30 m, incertidumbre individual >0 y ≤10 m y fórmula
`distancia + precisión docente + precisión estudiante ≤30 m`. No se modifican geolocalización ni NFC.

## Migración incremental y registros anteriores

`2026_10_22_000100_add_academic_closures.php` agrega:

- `class_sessions.started_at`, `finalized_at`, índice por estado/inicio y estado `unstarted` en CHECK.
- `assessment_periods.current_report_id`, `reopened_at`, `closure_issues`, `closure_checked_at`.
- `academic_cycles.closed_at`, `reopened_at`, `closure_issues`, `closure_checked_at`.
- `report_versions.generated_at`, `origin`, `snapshot_hash`; `generated_by` nullable para Sistema.
- `attendances.recorded_by` nullable para resultados generados por Sistema, sin usuario ficticio.

No rellena fechas, estados, snapshots ni actores anteriores. `pdf_path` y `file_hash` ya eran
nullable: no es necesaria otra tabla ni un PDF para cerrar. Se conservan las migraciones compartidas.
El rollback se rechaza si ya existen versiones A03, sesiones `unstarted` o resultados de Sistema:
no se destruye historia para volver a un esquema incompatible. Usar respaldo/restauración compatible.

Una sesión antigua en curso sin `started_at` no se automatiza. Coordinación puede usar
**Recuperar inicio desde la auditoría** en su detalle: exige exactamente una acción `session.start`
con transición `scheduled → in_progress`, `after.started_at` explícito e instante dentro del horario,
no futuro. El proceso es explícito, con motivo y auditoría; no usa `starts_at`, `updated_at` ni la
hora actual como inicio supuesto. La auditoría anterior a A03 no guardaba ese campo: su `created_at`
usaba `CURRENT_TIMESTAMP` de PostgreSQL (inicio de transacción), que no acredita el instante real
de la transición. No se recupera automáticamente ni se admite ese timestamp como sustituto.
Las auditorías nuevas guardan además la hora real de escritura explícita, sin reescribir las anteriores.
Si falta evidencia inequívoca, se conserva el impedimento y sigue disponible finalizar manualmente.

## Cierre de cómputos y resolución de impedimentos

El cómputo permanece abierto durante toda su fecha final local. Después, el cierre:

1. Finaliza sesiones en curso elegibles usando la misma generación de resultados que el flujo manual.
2. Clasifica programadas sin evidencia de inicio ni permisos pendientes/aprobados.
3. Revisa calendario, vigencia, estados contradictorios, permisos pendientes, resultados faltantes o
   inconsistentes y disponibilidad del reglamento, incluso sin inscripciones.
4. Calcula con AttendanceCalculator, guarda todas las filas y publica la versión vigente.

Un permiso preventivo rechazado no prueba que hubo clase. Una presencia, portal o transición
auditada de inicio sí requiere revisión; un permiso aprobado también debe resolverse antes de
clasificar una programada. No se borran datos ni se resuelven permisos automáticamente.

Los impedimentos se guardan con su causa y recurso y se muestran a docente autorizado/Coordinación,
sin exponer incidencias ajenas al estudiante. Solo se audita un cambio en las causas: los reintentos
idénticos no producen avisos ni auditorías duplicadas. No hay opción de forzar cierre.
El estado sigue abierto cuando hay impedimentos; el scheduler reintenta los períodos iniciales.

Coordinación dispone de **Revisar finalización y resultados** en una sesión vencida programada
con evidencia o finalizada. Requiere motivo, conserva presentes y concilia ausencias/justificadas
con permisos aprobados, además de generar faltantes exclusivamente mediante el finalizador.
No registra presencias retrospectivas ni abre una ventana. Una programada sin evidencia no se convierte
en clase impartida por esta acción. Las correcciones ordinarias conservan sus validaciones.

Una resolución extraordinaria de permiso puede reabrirse por Coordinación con motivo en su detalle.
La decisión anterior se conserva en la bitácora; vuelve a pendiente y exige nueva resolución.
Si cambia una justificación existente, debe conciliarse explícitamente desde la sesión. El cálculo
señala la inconsistencia hasta resolverla. Una corrección ordinaria no sobreescribe justificaciones.

Sin sesiones evaluables, el cierre es válido con porcentaje y conclusión nulos:
**Sin sesiones evaluables**. Nunca se interpreta como 0 % ni como No alcanza.

## Reapertura y bloqueos

Administración corresponde al rol existente **Coordinación** (`Role::Coordinator`), activo.
No se concede al docente. Las rutas y acciones verifican autorización, además de los controles visuales.

Reabrir exige motivo y audita actor, instante y recurso. El cómputo vuelve a `open` y conserva
`reopened_at` como revisión en curso; `current_report_id` conserva la referencia histórica anterior.
Mientras está abierto y reabierto, la consulta por defecto es viva y se identifica **En revisión**.
El scheduler no lo cierra: requiere cierre manual administrativo. El siguiente cierre crea versión N+1,
conserva N, actualiza la vigente y vuelve a proteger escrituras. Una repetición del cierre no crea versión.

Si el ciclo está cerrado: reabrir ciclo → reabrir cómputo afectado → corregir → cerrar cómputo
manualmente → cerrar ciclo manualmente. Reabrir ciclo no reabre sus cómputos. Un ciclo reabierto
tampoco se recierra automáticamente. La edición general no permite cerrar ni eludir estas transiciones.
Cerrar un ciclo no elimina grupos. A04 los muestra en Historial por ese mismo estado del ciclo,
sin acción adicional de archivo ni copia de sus datos.

Los locks compartidos/exclusivos de A01 continúan en orden ciclo → grupo → cómputo/sesión → recurso.
RegisterAttendance conserva primero el advisory lock global por UUID y su replay antes de comprobar
caducidad/cierre. Registro nuevo, corrección, permisos, sesiones y vigencia de inscripciones se bloquean
en períodos cerrados. La vigencia compara la intersección anterior/nueva con cada período cerrado:
cambios independientes fuera de ese ámbito siguen admitidos, incluidos datos de contacto.
CSV y lote manual reutilizan AcademicRegistry y no evaden la protección.

## Contrato de snapshots para persona 3

`ReportVersion` es la única versión persistida. `AssessmentPeriod.current_report_id` selecciona
la vigente; número único `(period_id, version)`, política, `generated_at` UTC, `origin`
(`automatic`/`manual`), `generated_by` nullable y SHA-256 canónico de JSON (`snapshot_hash`).
La canonización ordena claves de objetos, preserva el orden de listas y tolera el orden de JSONB.
El modelo rechaza cambios de contenido/metadatos y borrado de versiones. Futuras exportaciones pueden
adjuntar `pdf_path`/`file_hash` sin cambiar el snapshot. SQL externo que ignore acciones/locks queda
fuera de la garantía de la aplicación; el hash detecta alteraciones accidentales, no sustituye permisos.

Contenido `schema_version=1`:

- Cómputo, grupo, número de versión, fechas del período, regla aplicada y definición versionada.
- Momento real de generación, origen y actor (null para Sistema).
- `rows`: todas las inscripciones, identificador de estudiante, nombre/carné/estado al cierre,
  vigencia y `calculation` con el contrato A01 completo, conteos, fracción, resultado e incidencias.
- `calculation.session_facts`: hechos por sesión (fecha académica, horario, estado, resultado y
  estado del permiso del titular). Permiten explicar el cálculo sin consultar registros vivos.
- `sessions`: identificadores, horarios, inicio real/finalización conocidos y estado al cierre.

No incluye motivos/documentos privados, contraseñas, tokens ni coordenadas. No genera PDF ni CSV.

Entrada autorizada reutilizable (no leer `ReportVersion::snapshot` directamente para el estudiante):

```php
$query = app(\App\Queries\AttendanceSummary::class);
$batch = $query->forOffering($actor, $offering, periodId: $period->id,
    page: 1, perPage: 100); // vigente cerrada o vivo abierto/reabierto
$historic = $query->forOffering($actor, $offering, periodId: $period->id,
    reportVersion: 1); // historial autorizado y paginado por estudiante
$own = $query->forEnrollment($actor, $enrollment, $period, reportVersion: 1);
```

El batch conserva `periods`, `period`, `rows`; añade `versions` (metadatos), `source`
(`live`, `review`, `snapshot`), `snapshotError` y `report` (solo versión/fecha/origen, nunca snapshot
colectivo sin filtrar). El estudiante recibe únicamente su fila y el total de su ámbito, incluso en
históricos. Docente: grupos asignados; Coordinación: ámbito académico completo del proyecto.
La entrada individual añade source y metadatos de versión. No modifica la fórmula A01.
`calculation.state` conserva los estados de contenido A01 (`provisional` o `no_sessions` en un cierre
válido); `period_state=closed` y `source=snapshot` identifican que ese resultado ya está congelado.

Un cierre sin snapshot válido devuelve inconsistencia explícita, sin recalcular en vivo. La entrada
individual lanza validación. No se reconstruye una certificación histórica inventada. Los filtros
de fecha `from`/`to` se rechazan al consultar un snapshot completo; siguen disponibles para cálculo vivo.
Las versiones pedidas que no pertenecen al cómputo autorizado devuelven 404.

## Aplicar, ejecutar y verificar

En PowerShell, desde el repositorio:

```powershell
docker compose up -d app
docker compose exec -T app php artisan migrate:status
docker compose exec -T app php artisan migrate --pretend
docker compose exec -T app php artisan migrate
docker compose exec -T app php artisan schedule:list
# Procesa datos reales de la instalación; ejecutar solo cuando se quiera aplicar los cierres vencidos:
docker compose exec -T app php artisan attendance:process-closures
```

La tarea se intenta cada minuto y recupera registros vencidos aunque se haya detenido el scheduler.
Cada sesión/cómputo/ciclo se procesa en su transacción, no en una transacción
global del lote. Un fallo operativo se registra y no impide procesar recursos independientes; el comando
devuelve fallo si hubo errores operativos. Los impedimentos académicos quedan visibles para resolver.
El advisory lock global PostgreSQL del comando evita procesadores solapados, incluidas invocaciones
manuales. A05 retira el lock de caché de treinta minutos que podía impedir recuperar un worker
terminado abruptamente; los locks de recursos y la versión única conservan su protección.
La limpieza geográfica diaria de A02 se conserva.

Hosting: habilitar cron una vez por minuto en la instalación correcta, usuario con permisos de
aplicación, PHP/extensiones del proyecto, configuración y BD correctas y caché compartida operativa:

```cron
* * * * * cd /ruta/SmartAulaUES && php artisan schedule:run >> /ruta/privada/scheduler.log 2>&1
```

Alternativa de proceso supervisado: `php artisan schedule:work`. No ejecutar ambos mecanismos a la vez.
Verificar hora UTC del servidor sin cambiarla, zona académica de la aplicación, `schedule:list`, ejecución
real, logs privados, instantes de auditoría Sistema y que el snapshot vigente permanezca único al repetir.
No se acredita hosting por tener esta configuración. Su operación y respaldos corresponden a B07.

Pruebas aisladas; **cada comando con datos comprueba primero lectura/escritura efectiva de siaa_test**.
No ejecutar baterías o fixtures visuales de BD simultáneamente. No modificar `.env` ni reconstruir `siaa`:

```powershell
docker compose exec -T -e APP_ENV=testing -e DB_DATABASE=siaa_test app sh -c 'php scripts/check-test-database.php && php artisan test --compact --display-warnings --fail-on-warning'
docker compose exec -T -e APP_ENV=testing -e DB_DATABASE=siaa_test app sh -c 'php scripts/check-test-database.php && php tests/Concurrency/attendance-races.php --env=testing && php tests/Concurrency/attendance-summary.php --env=testing && php tests/Concurrency/academic-closures.php --env=testing'
docker compose run --rm node npm test
docker compose run --rm node npm run build
docker compose exec -T -e GIT_CONFIG_COUNT=1 -e GIT_CONFIG_KEY_0=safe.directory -e GIT_CONFIG_VALUE_0=/var/www/html app composer validate --strict
docker compose exec -T app composer check-platform-reqs
docker compose exec -T -e GIT_CONFIG_COUNT=1 -e GIT_CONFIG_KEY_0=safe.directory -e GIT_CONFIG_VALUE_0=/var/www/html app vendor/bin/pint --test
git diff --check
```

Si el entorno no dispone de `.env.testing`, preparar uno privado con las credenciales/APP_KEY
existentes, sin regenerar claves. Los comandos usan la base aislada y no imprimen credenciales.
RefreshDatabase está autorizado exclusivamente para `siaa_test` desechable tras el control previo.

## Revisión manual breve

1. Docente inicia una clase en horario: detalle muestra inicio real. Tras el horario ya no admite
   registros; la finalización manual conserva reglas y el procesador finaliza desde ends_at.
2. En datos de prueba de un cómputo vencido, ejecutar el comando: programadas sin contradicción
   pasan a Sin inicio registrado; permisos pendientes o resultados incompletos bloquean el cierre.
3. Resolver permisos o revisar finalización con Coordinación y motivo. Reintentar: cierre con
   fecha/versión y resultados congelados de todos los estudiantes, incluidas páginas posteriores.
4. Estudiante ve solo su resultado, incluso seleccionando una versión anterior. Docente ajeno
   y acceso directo a acciones administrativas se rechazan.
5. Coordinación reabre con motivo: En revisión, histórico conservado, sin reactivar portales;
   repetir comando no cierra. Corregir y cerrar manualmente genera versión nueva.
6. Cerrar ciclo exige todos los cómputos. Para corregir dentro de uno cerrado, reabrir primero
   ciclo y luego cómputo. Ambos requieren cierre manual después de la revisión.

Evidencia final por escenario y límites reales en [a03-verificacion-final.md](a03-verificacion-final.md).
La ejecución anterior del 22/10 se conserva en [a03-verification.md](a03-verification.md).
