# B02.4 — Avisos internos de generación de reportes

- Los jobs `GenerateReportPdf` y `GenerateReportCsv` siguen validando `ReportVersionPolicy` al ejecutarse.
- Solo después de generar y volver a comprobar la integridad del archivo se emite un aviso **disponible** al actor que lo solicitó. El enlace es una ruta de descarga controlada por Policy, nunca una ruta de almacenamiento privado.
- Si el job agota los **3 intentos**, `failed()` registra un aviso **fallido** para un actor que todavía esté autorizado. No se publica el mensaje de la excepción ni información académica de otros estudiantes. La vista de reportes conserva el botón de solicitar si aún no existe archivo válido: el usuario puede reintentar la **misma versión**, sin crear un cierre nuevo.
- Se reutilizan `AcademicNotice`, la tabla `notifications` y la interfaz `/notifications`. No se envían correos ni mensajes externos.
- Cada aviso se identifica de manera determinista por actor, formato, versión (`report_versions.id`) y evento (`ready` o `failed`), mediante un UUID v5. `insertOrIgnore` sobre la clave primaria deduplica tanto reintentos como concurrencia. Dos actores autorizados reciben avisos independientes; PDF y CSV también.
- El estado de lectura sigue perteneciendo al destinatario. El aviso queda conservado incluso si cambia posteriormente la asignación docente, pero la ruta de descarga verifica los permisos vigentes y puede devolver 403.
- Un aviso de fallo indica reintentar o pedir ayuda. Para diagnóstico técnico revisar `failed_jobs`, el worker y los logs protegidos; no introducir mensajes de error sin depurar en el aviso.
- **No se cambian** la migración original de notificaciones, el cálculo de asistencia, los snapshots certificados ni las rutas de descarga.

## Verificación

1. `php artisan test --filter=ReportGenerationNotificationTest` en PostgreSQL `_test`.
2. `vendor/bin/pint --test` y `php artisan test` en Docker.
3. En entorno local con `QUEUE_CONNECTION=database` y worker actualizado: solicitar PDF y CSV de **versiones que aún no tengan archivo generado**. Confirmar un aviso por actor/formato, enlaces autorizados y marcado de lectura. Para versiones generadas con anterioridad, la descarga sigue disponible; la nueva notificación no es retroactiva.
4. Comprobar reintentos, error definitivo y permisos; no alterar snapshots o archivos certificados reales para forzar un fallo. Usar pruebas aisladas.

No modificar la base de datos de producción, ejecutar migraciones destructivas ni hacer merge sin verificación de CI.
