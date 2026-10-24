# A04 · organización y administración de grupos

Trabajo local en `feature/p3a-a04-gestion-grupos`, base real `5172b91` (merge A03, PR #5).
A01/A02 ya integrados por PR #3/#4. Implementación iniciada el 23/10 y verificación completada
en la continuación del 24/10/2026. Release **0.3.2** conservada. Sin commit, push, PR, merge ni despliegue.
Evidencia por escenario en [a04-verification.md](a04-verification.md).

## Consulta y clasificación

| Vista | Criterio | Predeterminado |
| --- | --- | --- |
| Actuales | Ciclo distinto de `closed` | Grupos habilitados |
| Historial | Ciclo `closed` | Todos los estados de grupo |
| Desactivados | Filtro `availability=inactive` dentro de cualquiera de las vistas | Nunca cambia la clasificación del ciclo |

Las fechas no deciden si un grupo es actual: un ciclo vencido abierto conserva sus pendientes accesibles.
No hay bandera ni acción de archivo manual, copia de resultados o cierre adicional. Reabrir un ciclo
devuelve sus habilitados a Actuales y muestra **Ciclo en revisión** por su `reopened_at` real;
los desactivados mantienen su suspensión y los cómputos conservan sus cierres/versiones.

`Offering::visibleTo($actor)` conserva el ámbito existente: Coordinación, docente asignado o
estudiante con inscripción, incluso retirada. La vigencia se aplica en las operaciones que la requieren;
no se usa para ocultar una inscripción histórica. Las Policies siguen protegiendo URLs directas.
El estudiante recibe únicamente sus filas y solicitudes, también en snapshots/versiones anteriores.

`Offering::catalog($view, $availability, $cycleId)` centraliza la selección; `current()` equivale a
`catalog('current', 'active')`. `OfferingCatalog` valida los filtros HTTP y limita los ciclos a aquellos
con grupos visibles al actor. No utilizar un filtro de fecha ni un global scope de activos: ambos
romperían acceso histórico, replay o scheduler. Los consumidores externos deben validar los filtros
igual que la entrada HTTP; no pasar valores arbitrarios a los scopes.

Rutas de consulta:

```text
/offerings
/offerings?view=history&cycle_id=ID
/offerings?view=current&availability=inactive&cycle_id=ID
/offerings?view=history&availability=all&cycle_id=ID&page=2
```

Paginación de catálogo: 12 grupos. Filtros persistentes al paginar y entrar/salir del grupo.
`catalog_page` conserva la página del catálogo sin interferir con `page` de inscripciones ni
`results_page` de A01. Los selectores de cómputo/versión conservan ese contexto.

## Inicio y resúmenes históricos

Inicio selecciona exclusivamente `visibleTo()->current()` para grupos, próximas sesiones y métricas
académicas. `DashboardStatistics::forUser($actor)` aplica el mismo criterio a sesiones, asistencias,
métodos y solicitudes pendientes. El total institucional de estudiantes activos y su distribución
por año siguen siendo métricas independientes de los ciclos.

Historial permite seleccionar un ciclo y reutiliza `DashboardStatistics::forUser($actor, $groups)` y
`dashboard.operational-charts`. Muestra además conteos de grupos/inscripciones dentro del filtro.
La consulta estadística reaplica el ámbito del actor incluso si recibe un Builder de otro consumidor.
Los gráficos son **resúmenes operativos de registros conservados**, no versiones congeladas ni un
nuevo informe institucional. Se etiquetan expresamente de esa forma; no se generan nuevos gráficos.
Las asistencias agregadas de un estudiante siguen limitadas por `Enrollment::visibleTo`.

Los resultados cerrados se leen mediante `AttendanceSummary`, desde el `ReportVersion` vigente o
la versión autorizada solicitada. A04 no cambia ni recalcula esos snapshots para construir Historial.

## Suspender y reactivar

Administración es **Coordinación**, `Role::Coordinator`, activo. Docentes y estudiantes solo consultan.
Acciones administrativas en el detalle del grupo; validación tanto en ruta/controlador como en acción:

```text
POST /admin/offerings/{offering}/state/deactivate
POST /admin/offerings/{offering}/state/reactivate
POST /admin/offerings/{offering}/state/expire-resources
DELETE /admin/offerings/{offering}
```

Cada acción exige motivo entre 5 y 1000 caracteres, validado nuevamente en el servicio. Los cambios
releen actor y recurso dentro de la transacción. Estado anterior/nuevo, actor, motivo e instante UTC
quedan en la bitácora. Repetir desactivar/reactivar sin transición no genera otra auditoría.

Para desactivar, `OfferingLifecycle` comprueba sesiones en curso, portales sin cerrar, lectores activos,
permisos pendientes e integridad académica. Comparte `ClosureWorkflow::periodIssues` y el calculador
A01 a través de `AttendanceSummary`; no existe una segunda fórmula ni una reparación al consultar.
Los cómputos cerrados solo validan su snapshot, sin recálculo vivo. Cómputos abiertos y sesiones
futuras, incluso con permiso preventivo ya aprobado, no son impedimentos por sí solos. Una programada
vencida con permiso aprobado o evidencia de inicio sí exige revisión según A03.

La interfaz identifica los recursos y cómo resolverlos: finalizar la sesión, cerrar el registro/lector,
resolver permisos o usar la revisión administrativa existente. Ninguna desactivación finaliza,
cancela, elimina ni resuelve datos para poder continuar.

Un portal o lector vencido todavía abierto requiere tratamiento explícito: **Cerrar recursos vencidos**,
con motivo y auditoría, cierra portales vencidos y lectores vencidos o pertenecientes a portales ya
cerrados. Conserva ventanas/lectores vigentes, sesiones, permisos y resultados. Funciona también con
ciclos/cómputos cerrados y evita exigir una reapertura académica para retirar recursos operativos caducos.

Desactivar conserva sesiones futuras, inscripciones e historial. `AcademicRegistry::lockOffering`
comprueba `active` bajo locks; `AcademicSessionLock` extiende esa comprobación a toda operación humana:
programar/reprogramar/iniciar/finalizar/cancelar, abrir portal/lector, registrar/corregir asistencia,
solicitar/resolver/revisar permisos, crear/editar cómputos y grupos, e inscribir individualmente o por lote/CSV.
Las correcciones requieren reactivación y las reaperturas que correspondan. Datos de contacto y otras
operaciones independientes sobre cuentas mantienen sus reglas anteriores.

Reactivar no reabre ciclo/cómputos, no restaura portales/lectores y no reinicia sesiones ni genera
asistencia retroactiva. En ciclo cerrado continúa en Historial y protegido; en ciclo abierto vuelve a
Actuales. Las reglas de horario/elegibilidad/geolocalización A01–A03 permanecen intactas.

## Scheduler, replay y bloqueos

Orden: ciclo → grupo → cómputo/sesión → recurso. Las acciones de ciclo existentes toman primero el
lock del ciclo, por lo que reactivación, cierre y reapertura se serializan. El borrado y la creación
de dependencias usan el mismo protocolo. Las validaciones de mutación se repiten después de adquirir locks.

`RegisterAttendance` conserva el advisory lock global por UUID y el replay autorizado antes del guard
académico. Una operación ya confirmada devuelve su respuesta aunque después se haya suspendido el grupo;
otra UUID es escritura nueva y se rechaza. No se amplían ventanas ni se reactiva un recurso para reintentar.

La excepción `allowInactive: true` se utiliza exclusivamente desde el respaldo automático
`SessionWorkflow::finishOverdue`. `ClosureWorkflow` sigue recorriendo todos los grupos, sin global scope
de activos. Puede clasificar Sin inicio registrado y cerrar válidamente grupos desactivados; las
contradicciones y permisos pendientes siguen bloqueando A03. Los marcadores `reopened_at` se conservan
y continúan exigiendo recierre manual. Se mantiene `attendance:process-closures` cada cinco minutos y
`attendance:prune-location` diario. Esta revisión no acredita operación en hosting.

## Eliminación restringida y esquema

La migración incremental `2026_10_23_000100_add_offering_administrative_state.php` agrega
`offerings.active` booleano obligatorio, default `true`, e índice `(cycle_id, active)`.
No añade dependencias ni fechas inventadas. El rollback se rechaza si ya se usó la suspensión/reactivación
o hay grupos desactivados. La migración se comprueba en `siaa_test`; desarrollo se deja sin modificar.

Eliminar exige Coordinación, ciclo abierto, código exacto del grupo en `confirmation` y motivo.
Se comprueban inscripciones, sesiones, asistencias, permisos/evidencias, noticias, cómputos y reportes.
Portales/lectores/eventos dependen de sesiones y sus FKs RESTRICT impiden perderlos. Todas las FKs
académicas conservan RESTRICT; no hay borrado forzado ni cascada sobre historia.

Solo pueden retirarse cómputos copiados inicialmente de un formato del grupo si están abiertos, sin
sesiones, reportes, cierre, reapertura ni intentos de cierre almacenados; tienen exactamente su auditoría
de creación y su contenido coincide con ella. Se retiran explícitamente en la misma transacción y se
audita cada estructura retirada. Un cómputo manual o legado no demostrable bloquea la eliminación;
se informa la causa. Si contiene historia, conservar el grupo y usar la suspensión cuando sea válida.

La auditoría polimórfica no tiene FK al grupo y sobrevive: `offering.deleted`, ID, materia, ciclo, código,
estado/cupo/docente anteriores, actor, fecha y motivo; `after=null`. Un DELETE repetido responde 404 y no
duplica la auditoría. Se conservan también las referencias de auditoría anteriores.

## Coordinación con persona 3

- Para B01/B03 usar `visibleTo()->catalog(...)` y `OfferingCatalog`; no inferir Historial por fecha.
- `DashboardStatistics` con filtro explícito produce agregados operativos. No presentarlos como snapshot.
- Resultados de personas/exportaciones futuras: consumir `AttendanceSummary::forOffering/forEnrollment`
  y el contrato A03, nunca entregar el snapshot colectivo al estudiante.
- Nuevos escritores de noticias/reportes/dependencias deben respetar locks y FKs. La suspensión requiere
  validación dentro de su transacción; evitar filtros globales que oculten los grupos al scheduler.
- A05, ranking, PDF/CSV, nuevas gráficas y nuevos contratos de reportes permanecen fuera de A04.

## Aplicación y comprobación desde PowerShell

Desde la raíz del checkout. Los primeros comandos son para cuando el usuario decida aplicar A04
a su instalación; esta ejecución no migró desarrollo ni ejecutó cierres sobre `siaa`.

```powershell
docker compose up -d app
docker compose exec -T app php artisan migrate:status
docker compose exec -T app php artisan migrate --pretend
docker compose exec -T app php artisan migrate
docker compose run --rm -e NPM_CONFIG_UPDATE_NOTIFIER=false node npm run build
docker compose exec -T app php artisan schedule:list
```

Pruebas aisladas: ejecutar secuencialmente los comandos con BD. Cada entrada comprueba conexiones
efectivas de lectura y escritura a `siaa_test`, sin tocar `.env`, claves ni la base de desarrollo.

```powershell
docker compose exec -T -e APP_ENV=testing -e DB_DATABASE=siaa_test app sh -c 'php scripts/check-test-database.php && php artisan test --compact --display-warnings --fail-on-warning'
docker compose exec -T -e APP_ENV=testing -e DB_DATABASE=siaa_test app sh -c 'php scripts/check-test-database.php && php tests/Concurrency/attendance-races.php --env=testing && php tests/Concurrency/attendance-summary.php --env=testing && php tests/Concurrency/academic-closures.php --env=testing && php tests/Concurrency/offering-management.php --env=testing'
docker compose run --rm -e NPM_CONFIG_UPDATE_NOTIFIER=false node npm test
docker compose run --rm -e NPM_CONFIG_UPDATE_NOTIFIER=false node npm run build
docker compose exec -T -e GIT_CONFIG_COUNT=1 -e GIT_CONFIG_KEY_0=safe.directory -e GIT_CONFIG_VALUE_0=/var/www/html app composer validate --strict
docker compose exec -T -e GIT_CONFIG_COUNT=1 -e GIT_CONFIG_KEY_0=safe.directory -e GIT_CONFIG_VALUE_0=/var/www/html app composer check-platform-reqs
docker compose exec -T -e GIT_CONFIG_COUNT=1 -e GIT_CONFIG_KEY_0=safe.directory -e GIT_CONFIG_VALUE_0=/var/www/html app vendor/bin/pint --test
git diff --check
```

## Revisión humana mínima

1. En Materias y grupos, recorrer Actuales/Historial y el filtro Desactivados. Un ciclo vencido abierto
   sigue actual; historial conserva resultados cerrados y su versión.
2. Coordinación: observar motivos de bloqueo, desactivar un grupo sin impedimentos y reactivarlo con
   motivo; comprobar sesión futura e inscripciones conservadas. Docente/estudiante solo consultan.
3. Revisar una eliminación vacía y su auditoría, y una eliminación bloqueada por historia.
4. Revisar las capturas móviles y el informe por escenario. No hace falta repetir manualmente toda
   la batería técnica para revisar el incremento; la integración y migración quedan en manos del usuario.
