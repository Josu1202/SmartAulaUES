# B06 · Rendimiento, consultas y evidencia reproducible

**Alcance:** Integrante 3, SmartAulaUES. Base esperada: `main` después de B05 (merge `5fc36ed`). Este documento describe el código propuesto; no afirma un resultado que aún no se haya medido en el Docker del integrante.

## Cambios controlados

1. **Calendario (B03):** `CalendarSessions::forStudent()` sustituye el `Enrollment::get()` de todas las matrículas más un `OR` por matrícula, por un `EXISTS` de PostgreSQL. El filtro inicial de `Offering::visibleTo` y las fechas de inicio y finalización se conservan. La fecha académica usa `AT TIME ZONE 'America/El_Salvador'`; inscripción y retiro son inclusivos. Coordinación y docentes no cambian su consulta ni permisos.
2. **Índices:** migración `2026_11_09_000600_add_b06_calendar_read_indexes`, índices `b06_enrollment_calendar_idx` sobre `(student_id, offering_id, active_from, active_to)` y `b06_session_calendar_idx` sobre `(starts_at, id, offering_id)`. No borra ni recrea datos. Evaluar tamaño/plan `EXPLAIN (ANALYZE, BUFFERS)` únicamente sobre SQL de prueba y con permiso; en producción una creación normal de índice puede bloquear escrituras brevemente.
3. **Jobs de noticias (B03):** la lista de destinatarios ya no se recopila como arrays PHP mediante `pluck`. Un SQL de subconsultas identifica coordinación, docentes y alumnos con matrícula vigente al publicar; se itera por `chunkById(100)`. **Se mantiene** la comprobación `Gate::forUser(...)->allows('view', $news)` por cada destinatario, la huella del contenido, y el ID UUID determinista con `insertOrIgnore` para reintentos. No se envía correo ni notificación externa.
4. **Paginación:** calendario conserva `paginate(15)`; las respuestas y los detalles nunca se precargan de forma masiva. La medición compara los primeros 15 identificadores con el mismo orden y mismos filtros.
5. **Caché:** no se introduce una caché persistente de resultados, noticias o grupos. Los roles, matrículas, cierres y reasignaciones pueden cambiar después de cualquier solicitud; sin invalidación fiable **tras commit** desde *todos* los escritores (incluido el Integrante 2), una caché por ID/rol/filtros puede mostrar información obsoleta o ajena. El caché de vistas de Blade y de recursos estáticos sigue siendo independiente. Esto es una decisión explícita, no una optimización afirmada.

## Medición antes/después

El comando `b06:benchmark` calcula **las dos rutas de lectura sobre la misma BD y ventana**, una por la consulta anterior (baseline) y otra por `EXISTS` (optimized). Antes de medir compara los IDs visibles y falla si son distintos. Usa calentamiento inicial; alterna 3 a 30 muestras; calcula p50 y p95 de pared con `hrtime()` y mínimo/máximo de consultas SQL por lectura. Los conteos corresponden al acceso al calendario limitado a 15 filas, **no a toda la solicitud HTTP**. No cambia datos, credenciales ni cookies; la muestra se restringe a una cuenta de estudiante activa.

En PowerShell y **solo en la BD local**:

```powershell
# Obtener un ID de estudiante de prueba sin mostrar datos personales:
docker compose -p smartaulaues-b01-pruebas -f compose.yaml exec -T app php artisan tinker --execute="echo App\\Models\\User::where('role', 'student')->where('active', true)->value('id');"

# Sustituir ID por el número obtenido y ajustar fechas a un ciclo con sesiones reales:
docker compose -p smartaulaues-b01-pruebas -f compose.yaml exec -T app php artisan b06:benchmark --student=ID --confirm-database=smartaulaues_local --from=2026-11-01 --to=2026-11-30 --iterations=9
```

Si el estudiante no tiene sesiones en esa ventana, cambiar fechas. Registrar **no solo** los p50/p95 y consultas, sino también número de matrículas, sesiones, PHP, PostgreSQL, CPU/RAM, Docker, estado de caché, tamaño de BD, fecha y si la web/worker ejecutaban carga. El comando devuelve el número de jobs pendientes como contexto, pero **no** mide duración, reintentos ni p95 del worker. Sin carga representativa, la medida no demuestra rendimiento en producción.

| Evidencia | Antes | Después | Observación |
| --- | --- | --- | --- |
| Base / commit | B05 (`5fc36ed`) | B06 (PR pendiente) | Registrar SHA aplicado |
| Dataset: matrículas/sesiones | Por medir | Igual | Misma BD, fecha y estudiante de prueba |
| Consulta calendario SQL | `OR` por matrícula + hidratación | `EXISTS` parametrizado | Misma autorización y resultados |
| Consulta `queries_min/max` | Por ejecutar | Por ejecutar | Medición de calendario (15 resultados) |
| `p50_ms` | Por ejecutar | Por ejecutar | Calentamiento y 9 muestras |
| `p95_ms` | Por ejecutar | Por ejecutar | No confundir con p95 HTTP/cliente |
| Jobs pendientes | Por ejecutar | Por ejecutar | Cola, no tiempo de ejecución |
| Hardware, PostgreSQL, Docker | Por registrar | Mismo equipo | No inferir RPO/RTO ni capacidad |

## Validaciones y operación

1. `docker compose -p smartaulaues-b01-tests -f compose.testing.yaml up -d`
2. `docker compose -p smartaulaues-b01-tests -f compose.testing.yaml exec -T tests php artisan test --filter='B06PerformanceTest|B03TimelineTest|B03NewsTest'`
3. `docker compose -p smartaulaues-b01-tests -f compose.testing.yaml exec -T tests vendor/bin/pint --test`
4. Ejecutar `php artisan test` completo y, si se modificó JavaScript, `npm test` y `npm run build` (B06 no cambia JS).
5. **Solo después**, en Docker de desarrollo, aplicar `php artisan migrate --force` (nunca `migrate:fresh`). No crear datos sintéticos automáticamente. Revisar calendario de Estudiante/Docente y noticias por perfil, y ejecutar `b06:benchmark` en la BD local de prueba.
6. Documentar datos observados y corregir antes del commit; verificar CI y mantener B06 separado de B07.

## Garantías y límites

- El cálculo de asistencia, cierres/snapshots, políticas, bloqueos y versiones de reporte del Integrante 2 **no se tocan**.
- Las rutas del calendario y los jobs siguen autorizando con scopes/Policies existentes, los reintentos mantienen deduplicación y los resultados cerrados se leen por contrato de A03.
- PostgreSQL 17 y zona académica `America/El_Salvador` son parte de este contrato; no se afirma compatibilidad SQLite.
- No se afirma ninguna mejora porcentual sin medir ni se atribuye al servidor latencia de GPS, NFC o red móvil.
