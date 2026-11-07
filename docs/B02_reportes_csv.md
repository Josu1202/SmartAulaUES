# B02.2 — Exportación CSV privada de versiones certificadas

## Contrato de datos

- Requiere B02.1, A03 y `ReportVersion.snapshot` **verificado** con `ClosureSnapshot`. El archivo CSV contiene un registro por estudiante del snapshot original. **No recalcula resultados** ni consulta asistencia en vivo.
- Un CSV tiene 16 columnas: ID y número del cómputo, versión, fechas, fecha de corte UTC, política y umbral registrados, nombre y carné del estudiante, conteos P/A/J/elegibles, porcentaje y conclusión. El porcentaje **sin sesiones** se deja vacío, nunca 0 inventado. Las justificaciones se mantienen separadas.
- El archivo incorpora BOM UTF-8 y delimitador coma (`fputcsv` con escape vacío), compatible con herramientas comunes. La parte decimal utiliza punto, como en el snapshot, y los conteos son numéricos.
- En campos **textuales** se antepone `'` a valores que, incluso tras espacios, controles o caracteres invisibles iniciales, empiecen por `=`, `+`, `-` o `@`. Esto evita fórmulas al abrir el CSV en una hoja de cálculo. El prefijo de protección es parte del formato exportado, no del snapshot.
- Los datos de ciclo/materia/docente **no** se vuelven a consultar para crear el CSV; sus identificadores descriptivos no están congelados dentro del snapshot actual de A03. Esto evita presentar identificadores actuales como históricos. El PDF B02.1 ya advierte esa limitación.

## Flujo y acceso

- `POST /reports/{reportVersion}/csv/generate` revalida `ReportVersionPolicy::view`, la integridad de snapshot y encola `GenerateReportCsv` **después del commit** en `reports`. Exige `QUEUE_CONNECTION=database` y worker; rechaza cola síncrona con HTTP 503.
- El worker vuelve a validar actor y política antes de generar, dentro de una transacción con bloqueo de fila; tres intentos con timeout 60 s, igual que B02.1.
- `GET /reports/{reportVersion}/csv/download` vuelve a autorizar, verifica snapshot, ruta y SHA-256; si falta o fue alterado devuelve 404. No se permite descarga colectiva a estudiantes ni docentes de otros grupos.
- Archivo en disco privado: `reports/period-N/version-V-report-ID.csv` bajo `storage/app/private`. `csv_path` y `csv_hash` son independientes de `pdf_path` y `file_hash`. Se conservan versiones anteriores y se rechaza sobrescribir un CSV ya registrado cuyo hash no coincide.
- La nueva migración `2026_11_06_000100_add_csv_exports_to_report_versions.php` es aditiva, con columnas anulables. **Nunca ejecutar `migrate:fresh` ni hacer rollback destructivo de reportes históricos.**
- En grupos sin estudiantes se entrega solo encabezado, sin filas de datos; versión y cómputo siguen identificados en el nombre del archivo.

## Verificación local requerida

1. Crear respaldo y verificar BD de **desarrollo**, luego ejecutar `php artisan migrate --force` solo si la BD es la prevista y se entiende el efecto de la migración no destructiva.
2. `php artisan test --filter=ReportCsvVersionTest` sobre PostgreSQL 17 aislado (`siaa_test`).
3. `vendor/bin/pint --test`, `php artisan test` y CI completo antes de PR.
4. Desde cuenta docente del grupo: solicitar CSV, revisar logs de `worker` y descargarlo. Contrastar el SHA-256 descargado con `csv_hash`, como se hizo con PDF B02.1.
5. Probar rol estudiante/otro docente, snapshot adulterado, archivo adulterado, reintento, importación en Excel/LibreOffice y caracteres `= + - @` con espacios precedentes.

**Estado:** preparado para pruebas. No se afirma que se haya ejecutado la suite en el equipo del usuario ni que esté instalado en producción.
