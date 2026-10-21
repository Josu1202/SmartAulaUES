# A01 · Reglamento y cálculo · contrato 1

Base 4873cbf, rama feature/p3a-a01-calculo-asistencia, release 0.3.2 conservada.
La revisión actual sustituye la pantalla independiente y la conservación de políticas demo de los
cómputos de desarrollo. Estado de implementación, pruebas y revisión visual en a01-table-verification.md.

## Reglamento vigente

project-2026-v1: mínimo 75 %, numerador presentes + justificadas respaldadas por permiso aprobado,
denominador clases completed elegibles. Presente y justificada son estados separados.
Comparación exacta: 4 × numerador >= 3 × denominador. La presentación usa dos decimales.
8 presentes + 1 justificada + 1 ausente de 10 clases produce 90.00 %.
Conclusiones: «Alcanza el mínimo de asistencia» y «No alcanza el mínimo de asistencia».
Son reglas vigentes del sistema. No se atribuye aprobación formal a la universidad o a una persona.
Resultado vivo y reglamento vigente son conceptos distintos: la certificación pertenece a A03.

AttendancePolicy conserva la definición interna inmutable. AcademicRegistry, copias de formatos,
DemoSeeder (solo al crear) y factories seleccionan la versión vigente desde config/attendance.php.
El calculador no convierte versiones demo/desconocidas en alias: policy_unavailable permanece explícito.
Cambiar reglas exige una nueva versión, no cambiar silenciosamente las anteriores.

## Normalización explícita de desarrollo

Comando sin efectos automáticos al consultar. Solo admite local/siaa o testing/siaa_test,
DB PostgreSQL y coincidencia entre base configurada, argumento y current_database() real.
La ejecución sin --apply informa candidatos y protegidos. --apply muestra otra revisión y revalida
bajo locks exclusivos ciclo → grupo → cómputo, colecciones por ID.

```powershell
docker compose exec -T app php artisan attendance:normalize-development-policy --database=siaa
docker compose exec -T app php artisan attendance:normalize-development-policy --database=siaa --apply
```

Solo cambia policy_version y updated_at. Conserva cuentas, inscripciones, sesiones, resultados,
permisos y fechas académicas. Excluye períodos en closing/closed, closed_at no nulo, ciclos cerrados
 y cualquier período con ReportVersion. Informa el número protegido y no sobrescribe snapshots.
Es repetible y audita antes/después con actor_id null, mostrado como Sistema; no inventa un actor.
No ejecuta seeder, migraciones ni regeneración de claves. En esta instalación se actualizaron 2;
la revisión siguiente informó 0 candidatos y 0 protegidos.

## Consulta individual

```php
$summary = app(\App\Queries\AttendanceSummary::class);
$result = $summary->forEnrollment($actor, $enrollment, $period,
    from: '2026-06-01', to: '2026-06-30'); // filtros opcionales, inclusivos
```

Relee modelos y autoriza EnrollmentPolicy: coordinación, docente asignado o estudiante titular,
siempre actor activo. Período de otro grupo: 404. Actor ajeno: 403. Fechas inválidas: validación.
La fecha académica es starts_at en America/El_Salvador. Se intersectan cómputo, inscripción y filtro.
Alta y retiro incluyen su día. Una cuenta inactiva conserva historia para docente/coordinación.
Sin intersección: no_sessions, incluso si el grupo sí tiene clases.

## Consulta colectiva por página

```php
$batch = $summary->forOffering($actor, $offering,
    periodId: $period->id, page: 1, perPage: 20,
    from: '2026-06-01', to: '2026-06-30'); // fechas opcionales
$selected = $batch['period'];       // AssessmentPeriod o null si no hay cómputos
$options = $batch['periods'];       // únicamente cómputos del grupo autorizado
$rows = $batch['rows'];             // LengthAwarePaginator, orden estable por enrollment.id
foreach ($rows as $row) {
    $name = $row['student_name'];
    $code = $row['student_code'];
    $active = $row['student_active'];
    $result = $row['calculation'];  // mismo contrato individual
}
```

La consulta autoriza OfferingPolicy usando actor y grupo releídos. El estudiante obtiene solo su
inscripción, incluyendo el total de paginación; un docente ajeno no obtiene filas ni incidencias.
perPage admite 1–100 (interfaz: 20); page debe ser positivo. El valor por defecto de periodId selecciona
el menor number y luego id del grupo. Un ID solicitado de otro grupo devuelve 404.
Sin cómputos: period null, filas sin cálculo; la interfaz muestra el vacío y no dibuja la tabla.

Paginar estudiantes no pagina sesiones. Se leen todas las sesiones del cómputo y solo los resultados
 y permisos de las inscripciones visibles en esa página. Las consultas SQL no aumentan por estudiante.
rows.total() es el total de inscripciones del ámbito autorizado, no una estadística académica.
No se ofrecen totales de asistencia del grupo ni rankings. El paginador usa results_page; la lista
existente de inscripciones usa page. Los enlaces conservan period_id. Cambiar el selector reinicia páginas.

## Consistencia

Ambas entradas utilizan la misma carga por lote y un único AttendanceCalculator puro.
Transacción con locks compartidos ciclo → grupo → cómputo. Las acciones de escritura existentes
(finalización, permisos, retiro, etc.) toman primero el lock exclusivo del ciclo.
Todas las filas de una página comparten as_of y permanecen protegidas hasta leer sus sesiones,
resultados y permisos. Evita mezclar el estado previo del permiso con el resultado posterior, o viceversa.
No hay cadena de consultas individuales ni escrituras al consultar.

A03 puede invocar las entradas dentro de su transacción, adquiriendo antes los locks parentales.
Una página no constituye un snapshot certificado ni garantiza que otra página consultada después tenga
el mismo corte. Para un lote de varias páginas, el consumidor debe mantener su transacción/lock de ciclo
antes del recorrido, o usar el snapshot de A03. El cierre no se implementa aquí.
No se garantiza coherencia de SQL externo que ignore los locks. El lock del ciclo puede esperar
escrituras de otros grupos; no se añade caché ni se relajan autorizaciones.

## Respuesta individual y por fila

Campos: schema_version=1; policy_version y policy internos; enrollment_id, offering_id, period_id,
period_state; range inclusivo; as_of UTC ISO con microsegundos; state; counts; fraction;
percentage; meets_minimum; conclusion; incidents y pending_request_session_ids.
No incluye motivos, evidencias, credenciales o datos de otros estudiantes.

```json
{
  "state": "provisional",
  "fraction": {"numerator": 9, "denominator": 10},
  "percentage": "90.00",
  "meets_minimum": true,
  "conclusion": "Alcanza el mínimo de asistencia",
  "incidents": [],
  "pending_request_session_ids": []
}
```

counts contiene eligible, present, justified, absent, missing, inconsistent, cancelled, future,
in_progress, awaiting_start, overdue_unfinished y pending_requests. Los tres conteos de resultados
son coherentes: justificada sin aprobación o ausente con aprobación pasan a inconsistent, sin reparar
su fila original. eligible = present + justified + absent + missing + inconsistent.
Presente con permiso aprobado cuenta una sola vez como presente.

| state | Tratamiento |
| --- | --- |
| provisional | Datos completos; percentage string decimal y conclusión disponibles. Pendientes visibles; pueden cambiar el resultado. Incluso sin pendientes sigue sin certificar. |
| incomplete | Faltantes o inconsistencias; fracción observada y conteos disponibles, percentage/meets_minimum/conclusion null. No transformar null en cero ni inferir ausencia. |
| no_sessions | Sin elegibles o sin intersección; percentage/meets_minimum/conclusion null. Mostrar Sin clases evaluables. |
| policy_unavailable | Versión no calculable; policy y numerador null, sin porcentaje/conclusión. No aplicar fórmula de otra versión. |

Prioridad: policy_unavailable, incomplete, no_sessions, provisional.
Incidencias: missing_result, justified_without_approval, approved_request_with_absent_result,
invalid_result y completed_before_start. Solo pertenecen al titular de la fila.
Pendientes/rechazados no suman como justificados. Una aprobación sin resultado no inventa esa fila.

Sesiones no realizadas: future si inicio > as_of; overdue_unfinished si fin <= as_of; dentro del horario,
in_progress si así está almacenado y awaiting_start si sigue programada. Canceladas se cuentan aparte.
No suman al denominador. La interfaz distingue cada categoría y explica incidencias sin mostrar códigos.

Consolidación futura de B: sumar fracciones compatibles sin duplicar ámbitos; no promediar porcentajes.
Propagar incompletitud y versiones no disponibles. Nunca omitirlas silenciosamente del resultado global.

## Ejemplo ejecutable con datos ficticios

Preparar .env.testing privado con conexión a siaa_test (db:5432 dentro de Compose), reutilizando la clave
existente sin regenerarla. No incluir credenciales en Git. El siguiente ejemplo valida la base efectiva,
crea 10 sesiones y datos ficticios en transacción, usa finalización y permiso reales, consulta ambas entradas
 y hace rollback al terminar, incluso ante error. No modifica siaa ni requiere cuentas reales.

```powershell
docker compose exec -T -e APP_ENV=testing -e DB_DATABASE=siaa_test app php scripts/attendance-calculation-examples.php --env=testing
```

La salida individual y la primera fila colectiva deben tener 8 presentes, 1 justificada, 1 ausente,
fracción 9/10, porcentaje "90.00" y la conclusión positiva. Sus IDs/as_of son variables.
Fixtures adicionales: tests/Fixtures/attendance-calculation.php. Tests de tabla prueban el cambio
80.00 → 90.00 tras aprobar el permiso real y recargar, sin duplicar asistencia.

## Pantalla y verificación

GET /offerings/{offering}?period_id={period}&results_page=1#attendance-results.
Desde Materias y grupos/Mis materias, abrir el grupo y Resultados de asistencia. Seleccionar cómputo.
No hay menú, controlador ni pantalla de política independientes; GET /attendance-policy devuelve 404.
Versionado interno y metadatos de aprobación no se exponen en la tabla.

```powershell
docker compose exec -T -e APP_ENV=testing -e DB_DATABASE=siaa_test app sh -c 'php scripts/check-test-database.php && php artisan test --compact --display-warnings --fail-on-warning'
docker compose exec -T -e APP_ENV=testing -e DB_DATABASE=siaa_test app sh -c 'php scripts/check-test-database.php && php tests/Concurrency/attendance-races.php --env=testing && php tests/Concurrency/attendance-summary.php --env=testing'
```

RefreshDatabase reconstruye exclusivamente siaa_test. El script concurrente de lectura observa el bloqueo
real de una página colectiva de dos filas frente a finalización y resolución, y comprueba as_of común.
La evidencia anterior a esta revisión queda en a01-verification.md; no sustituye las pruebas nuevas.
La lista de otras notas y su asignación está en change-notes-status.md. A02–A05/B01–B07 no bloquean A01.