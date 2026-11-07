# B02.3 — Exportación CSV del seguimiento docente filtrado

## Objetivo

La ruta `GET /dashboard/seguimiento.csv` permite a un **docente activo** exportar el ranking completo de un grupo y cómputo autorizados. Reutiliza `DashboardStatistics::teacherAbsenceRanking` y `AttendanceSummary`; el comportamiento habitual del dashboard (10 filas) permanece intacto. Requiere los parámetros `ranking_group_id` y `ranking_period_id`; admite `ranking_cycle_id`, `ranking_sort`, `ranking_date_from`, `ranking_date_to`.

## Contratos académicos y seguridad

- Cómputo abierto: consulta exploratoria, fechas inclusivas conforme al dashboard; sus números son provisionales, no una conclusión oficial.
- Cómputo cerrado: el ranking proviene del snapshot A03 validado. El filtro solicitado de fechas **no recorta el ranking ni los denominadores certificados**, como en B01. Incluye indicador de si el filtro fue efectivo y versión certificada vigente.
- No se implementan cálculos ni políticas académicas nuevos. Se rechazan grupos/ciclos/cómputos ajenos, docentes inactivos y otros roles. Un snapshot inconsistente responde 409, nunca un CSV aparentemente vacío.
- CSV con BOM UTF-8, coma, encabezados descriptivos y celda vacía para porcentajes no calculables (distinta de `0.00`). Las justificaciones y los permisos pendientes se muestran separados.
- Neutralización de fórmulas en **campos textuales** que empiezan por `=`, `+`, `-` o `@`, incluso precedidos de espacios o controles, sin cambiar los datos académicos persistidos.
- Exportación bajo demanda mediante `streamDownload`, sin persistencia ni versión histórica adicional. Para el **reporte certificado persistente** y su hash SHA-256 se conserva B02.2 en `/reports`. La ruta envía encabezados para evitar cacheo; no utiliza `storage:link`.

## Verificación requerida

1. Ejecutar `php artisan test --filter=DashboardTrackingCsvTest` con PostgreSQL `_test`, después toda la suite y `vendor/bin/pint --test`.
2. Iniciar sesión como docente y descargar CSV de grupo propio: comparar posiciones, ausencias, denominadores, porcentaje y filtros con el dashboard. Repetir para cómputos abiertos/cerrados.
3. Intentar exportar un grupo ajeno y desde un estudiante o coordinación; verificar denegación.
4. Abrir el archivo mediante la importación de Excel **UTF-8 y delimitador coma**, conservando el carné como texto.
5. Registrar el resultado de la revisión; no asumir rendimiento, integración CI ni producción antes de verificarlos.
