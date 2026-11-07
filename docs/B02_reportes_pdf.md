# B02.1 — Reportes PDF privados de versiones de cierre

## Alcance

- Usa únicamente `ReportVersion.snapshot` y verifica la firma académica con `ClosureSnapshot` (A03); nunca recalcula la asistencia.
- GET `/reports`: versiones que puede consultar Coordinación o el docente asignado, paginadas.
- POST `/reports/{reportVersion}/generate`: autoriza la versión y encola **después del commit**. El proceso HTTP no produce el PDF.
- GET `/reports/{reportVersion}/download`: vuelve a autorizar, verifica snapshot, ruta determinista y SHA-256 del archivo. El estudiante no puede descargar un reporte colectivo.
- Archivos PDF solo en disco `private` bajo `reports/period-N/version-V-report-ID.pdf`; sin `storage:link`. Registra ruta y hash sin modificar snapshot/versión.
- El servidor rechaza generación en `sync`, `null`, `deferred` o `background` (HTTP 503), para no bloquear peticiones. La conexión de base de datos y el worker dedicado deben estar realmente disponibles.
- Generación idempotente y serializada por versión, con tres intentos y timeout de 60 s. Los errores quedan en el sistema de jobs fallidos; un snapshot inválido nunca produce PDF.
- Las versiones antiguas y sus PDF se conservan tras una reapertura.

## Operación local (pendiente de servicio persistente)

Para ejecutar un trabajo de cola, con Docker y BD de desarrollo aislada:

```powershell
# Revisar estado antes de ejecutar cualquier worker; no usar la BD de testing con datos reales.
docker compose -p smartaulaues-b01-pruebas ps
# Procesar una sola tarea de la cola dedicada (solo después de verificar QUEUE_CONNECTION=database):
docker compose -p smartaulaues-b01-pruebas exec -T app php artisan queue:work database --queue=reports --once --tries=3 --timeout=60
```

Si la configuración efectiva todavía usa `QUEUE_CONNECTION=sync` (como los ejemplos del proyecto), una solicitud devolverá HTTP 503 hasta que el operador habilite `database` y un worker. No ejecutar jobs con datos reales antes de verificar credenciales y base de datos.

`DB_QUEUE_RETRY_AFTER` debe superar 60 s (config predeterminada: 90 s). **B02 aún no queda operativo permanentemente** hasta configurar un worker supervisado, la observabilidad de errores y las notificaciones. Ese trabajo queda para siguientes incrementos de B02/B07. No cambiar `.env`, `APP_KEY`, ni ejecutar migraciones destructivas.

## Límites conocidos

- No incluye todavía CSV, avisos internos, servicio permanente del worker ni reportes de ciclo. Forman parte de B02.2+.
- El snapshot del Integrante 2 congela los resultados y nombres de estudiantes, pero la identificación actual de ciclo/materia/grupo/docente se consulta desde sus modelos. Se advierte expresamente en el PDF; si se requiere congelar esos metadatos habrá que acordar un contrato nuevo con A03, sin mutar snapshots existentes.
- Los reportes pueden solicitarse durante un cómputo reabierto porque cada versión anterior sigue siendo histórica e inmutable.
- Un PDF ya registrado con hash inválido bloquea la descarga y requiere diagnóstico; no se sobrescribe silenciosamente.

## Verificación requerida en el equipo del usuario

1. `php artisan test --filter=ReportPdfVersionTest` en `siaa_test` de PostgreSQL 17.
2. `vendor/bin/pint --test`.
3. Suite integral `php artisan test`.
4. Pruebas visuales de PDF multipágina, acentos, datos nulos y versiones anteriores.
5. Comprobación real de worker y rechazos HTTP con cuentas distintas antes del PR.

**Estado:** implementado para pruebas; no declarar validación remota, CI, producción ni servicio worker persistente hasta contar con evidencia.
