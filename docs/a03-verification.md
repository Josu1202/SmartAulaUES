# A03 · evidencia local y revisión · 22/10/2026

Antecedente histórico. La nueva auditoría del 23/10, su corrección de controles fuera de horario,
resultados por escenario y veredicto están en [a03-verificacion-final.md](a03-verificacion-final.md).
Las cifras de este documento corresponden al 22/10 y no sustituyen esa ejecución posterior.

Rama `feature/p3a-a03-cierres-reapertura`, base local `bf07b01`, merge PR #4 de A02.
A01 integrado por PR #3/`18c7596`. Release 0.3.2 conservada. Sin commit, push, merge ni despliegue.
Las referencias anteriores a A01/A02 sin integración quedan como historia de sus incrementos,
sin convertir esa integración en aceptación física de GPS/HTTPS ni cierre de fase 3.

## Alcance implementado

Inicio real UTC y respaldo por fin de día local; clasificación Sin inicio registrado al procesar cierre;
validaciones de cómputos/ciclos; snapshot completo y versiones inmutables; consulta cerrada/histórica
autorizada; En revisión y recierre administrativo; protección de escrituras y elegibilidad en CSV/lotes.
Se reutilizan calculador A01, finalizador, AssessmentPeriod/ReportVersion, auditoría y locks existentes.
Reglas, entrada reutilizable para persona 3, comandos y revisión manual en [a03-closures.md](a03-closures.md).

La revisión del esquema confirmó que el `created_at` antiguo de auditoría usa `CURRENT_TIMESTAMP`
de PostgreSQL, fecha de inicio de transacción. No acredita un inicio real inequívoco. Solo puede
recuperarse un `after.started_at` absoluto explícito de una única transición fiable, dentro del horario
y no futuro; de otro modo la sesión antigua debe finalizarse manualmente. No se infieren fechas.
Los eventos nuevos de AuditRecorder guardan además su hora real de escritura, sin modificar los previos.

## Base de datos de desarrollo

Se comprobó entorno local y conexiones efectivas de lectura/escritura a `siaa`. Se revisaron
`migrate:status` y `migrate --pretend` antes de aplicar exclusivamente la migración incremental
`2026_10_22_000100_add_academic_closures`. Aplicada como batch 3, sin seeders, fresh ni reconstrucción.

| Contenido anterior | Filas antes/después |
| --- | --- |
| users | 23 / 23 |
| academic_cycles | 1 / 1 |
| assessment_periods | 2 / 2 |
| class_sessions | 12 / 12 |
| enrollments | 40 / 40 |
| attendances | 83 / 83 |
| absence_requests | 0 / 0 |
| report_versions | 0 / 0 |

Se conservaron también las huellas de las columnas académicas anteriores de esas tablas,
incluidos estados, vigencias, resultados y timestamps. La comprobación se repitió después de
la revisión visual. No se ejecutó el procesador de cierres sobre desarrollo; las acciones del
scheduler y los fixtures se ejecutaron exclusivamente en `siaa_test`.
No se rellenaron inicios, cierres históricos, actores o snapshots anteriores.

## Pruebas y herramientas

Todos los comandos PHP de aplicación usan Compose/PHP **8.4.26** y PostgreSQL nativo.
El PHP 8.2 del host no se empleó para ejecutar Laravel. Antes de cada batería o script con datos
se verificaron lectura y escritura efectivas de `siaa_test` mediante el control existente, encadenado
con `&&`. RefreshDatabase reconstruye exclusivamente esa BD desechable. No se modificó el arnés.

| Comprobación | Resultado observado |
| --- | --- |
| Batería PHP sobre el estado final | 184/184 pruebas, 1247 aserciones, sin warnings, 185.84 s |
| npm test | 39/39 correctas |
| npm run build | Correcto, 72 módulos, 33.96 s; aviso Sass mixed-decls heredado |
| composer validate --strict | Correcto |
| composer check-platform-reqs | Todos correctos; PHP 8.4.26 |
| Pint completo final | PASS, 177 archivos PHP |
| attendance-races.php sobre el estado final | 6 carreras nativas correctas |
| attendance-summary.php sobre el estado final | 2 esperas nativas correctas; páginas coherentes tras commits |
| academic-closures.php sobre el estado final | 6 esperas nativas correctas de A03 |
| schedule:list | Limpieza geográfica diaria y procesador cada 5 minutos, ambos sin solapamientos |
| schedule:run real en entorno testing | Dos ejecuciones, una versión automática y ciclo cerrado |
| git diff --check | Correcto |

La cobertura A03 consta de **25 pruebas**: medianoche local antes/después, inicio real,
horarios cruzando medianoche, ausencia de evidencia fiable, recuperación explícita, ventanas intactas,
presencias conservadas, permisos aprobados/pendientes, retiro inclusivo, canceladas/finalizadas,
clasificación de programadas, contradicciones, resultados faltantes/inconsistentes y reglamento ausente.
También comprueba cierre vacío, snapshot completo de 22 estudiantes y paginación, vigencia/histórico,
privacidad de filas y metadatos, corrupción detectada e inmutabilidad, rollback de generación,
roles/URLs directas, bloqueo de corrección/permiso/sesión/inscripción/lote/CSV, cambios independientes,
reapertura con motivo, orden ciclo/cómputo y prohibición de recierre automático de reabiertos.

El fallo operativo inyectado en la generación revierte solo el cómputo afectado, permite cerrar otro
independiente y se recupera en la siguiente ejecución sin duplicar versiones. El calculador nunca
crea ausencias. La prueba de recuperación distingue el timestamp explícito del evento del timestamp
de transacción y rechaza la auditoría antigua sin ese campo.

Las seis carreras nuevas observan `pg_stat_activity.wait_event_type = Lock` en un proceso PHP
independiente antes de confirmar la transacción escritora. Comprueban cierre → corrección,
permiso → cierre, cierre → cierre, cierre → registro, cierre → inscripción y cierre de ciclo →
reapertura de cómputo. No son conexiones embebidas serializadas ni una simulación de PostgreSQL.
CI añade el control efectivo previo y las carreras de consulta/cierre, sin relajar warnings ni pruebas.
No se consultaron ni se atribuyen resultados a una ejecución remota de GitHub Actions.

## Scheduler realmente ejecutado

Se crearon fixtures ficticios ya vencidos en `siaa_test`, con una sesión iniciada el día anterior.
El proceso padre usó un reloj controlado solo para hacer elegible el evento de cinco minutos,
sin esperar ni modificar el reloj del equipo. `schedule:run` lanzó el comando real en un proceso
hijo; este usó su hora real de procesamiento. Se empleó caché de base de datos para el mutex.

Primera ejecución: finalización de la sesión, resultados de Sistema, snapshot automático único y
cierre del ciclo aplicable. Segunda ejecución: misma versión vigente, sin duplicación.
Se conservaron pendientes de otro cómputo, sin impedir procesar el independiente.
Esto verifica ejecución local del scheduler; no acredita cron, supervisor o hosting real.

## Navegador y privacidad

Edge **155.0.4283.62** headless mediante CDP; contenedor temporal de aplicación en
`127.0.0.1:8091`, `APP_ENV=testing`, base `siaa_test`, cuentas ficticias y sesión web real.
No se modificó `.env` ni se abrió un túnel público. Se verificó:

- Snapshot vigente versión 2 con 100.00 %, e histórico versión 1 con 75.00 %.
- Formulario administrativo real: reapertura con motivo → corrección → cierre versión 3 con 75.00 %.
  La versión 2 sigue mostrando 100.00 %; no se sobrescribe historia.
- Docente propio consulta; no obtiene controles administrativos de reapertura.
- Estudiante obtiene una sola fila, vigente e histórica, sin nombre de otro estudiante.
  POST directo a reapertura con CSRF válido devuelve 403.
- Impedimentos concretos en cómputo bloqueado e inicio antiguo desconocido visibles en las pantallas.
- Nueve medidas de perfiles × 1440/390/320 px, más históricos/revisión/bloqueo/sesión/ciclos:
  16 capturas/medidas en total. Anchos de documento 1425/390/320 px, sin desbordamiento de página.
  Tabla con desplazamiento horizontal; controles de reapertura de ciclo inspeccionados en móvil.
- Cero excepciones JavaScript en la aplicación. El agente inspeccionó capturas de coordinación/escritorio,
  revisión/móvil, estudiante/móvil, bloqueo y formulario administrativo del ciclo.

Capturas, mediciones y scripts auxiliares permanecen fuera de Git en `smartaulaues-a03-review`
dentro del directorio temporal del equipo. Los fixtures, sus sesiones HTTP y registros se retiraron
de `siaa_test` por sus IDs; el navegador y el contenedor temporal se cerraron.
La revisión automática rechazó borrar recursivamente el perfil temporal de Edge con motivo
«blocked by policy», sin más detalle. Ese perfil permanece fuera del repositorio; no es parte de la entrega.

## Archivos principales

- Migración: `database/migrations/2026_10_22_000100_add_academic_closures.php`.
- Nuevos: ClosureWorkflow, ReviewSession, ClosureSnapshot, ProcessAcademicClosures y AcademicClosureController.
- Integración: SessionWorkflow, AcademicRegistry, AuditRecorder, AbsenceWorkflow, AttendanceSummary,
  AttendanceCalculator, DashboardStatistics, enums/modelos, controladores de consulta y rutas.
- Blade: panel de cierre/histórico, revisión de sesión, resultados, ciclos y revisión de resoluciones;
  etiquetas de auditoría españolas. Se conserva diseño/estilos y JavaScript A02.
- Pruebas: AcademicClosureTest y Concurrency/academic-closures.php; CI incorpora carreras y control previo.
- Contexto: AGENTS, README, CHANGELOG, contratos, estado, decisiones, esquema, reparto y notas de cambio;
  [título y descripción de PR](a03-pr.md) según la plantilla del repositorio.

`.env` y el cambio previo de `.env.example` conservan sus huellas. APP_KEY no se modificó ni se regeneró.
No se incorporan Microsoft/, dependencias, logs, perfiles o datos privados. Sin cambios de versión ni
dependencias nuevas, sin staging y sin publicar el incremento.

## Pendientes reales

- Configurar/verificar el scheduler y respaldos en hosting (B07), con su evidencia operativa propia.
- Aceptación física GPS/HTTPS de A02; los límites 30 m/10 m no se modificaron.
- Revisión humana del incremento local. PDF/CSV, ranking/gráficas, archivo/eliminación de grupos y
  demás módulos A04–A05/B01–B07 continúan fuera del alcance de A03. No se cierra fase 3.
