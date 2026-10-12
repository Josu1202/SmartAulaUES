# Historial de versiones SIAA

## [0.1.1] — 2026-10-12

### Corregido
- Arranque Docker con `artisan serve --no-reload` para conservar `DB_HOST=db` y `DB_PORT=5432`
  en las peticiones HTTP. Evita la conexión rechazada contra `127.0.0.1:55432` dentro del contenedor.
- Instrucciones de actualización desde v0.1.0 y diagnóstico de contenedores, sin reinicializar la base.
- Versiones del proyecto y documentación de continuidad actualizadas.

### Verificado
- Prueba HTTP con el mismo Laravel: sin el parche usa `127.0.0.1:55432`; con el parche usa `db:5432`.
- Regresión automatizada del proceso HTTP: 1 caso y 2 aserciones. Composer, Pint, 8 pruebas NDEF,
  compilación Vite y caché de vistas correctos; resultados anteriores identificados como evidencia de v0.1.0.
- Docker completo sigue pendiente de ejecución en un equipo con Docker; no se afirma haberlo probado aquí.

## [0.1.0] — 2026-10-12

### Construido
- Base Laravel 13, PHP 8.4 y PostgreSQL con dependencias bloqueadas.
- Diseño Tabler del ZIP adjunto, Blade y paleta negra/roja/blanca.
- Acceso Fortify, cierre de sesión, usuarios activos, limitación de login y registro público deshabilitado.
- Consultas por rol, grupos propios, inscripción privada del estudiante y directorio de coordinación.
- Cuatro migraciones de dominio con unicidad, checks y relaciones compuestas.
- Escenario ficticio reproducible e idempotente, protegido fuera de local/testing.
- Diagnóstico Web NFC que lee NDEF sin escribir ni guardar asistencias.
- Configuración local, Docker de desarrollo, pruebas, CI y documentación de continuidad.
- Primer commit main y etiqueta v0.1.0; sin remoto ni despliegue.

### Pendiente
CRUD académico, NFC autenticado, CSV, registro de asistencia, permisos, seguimiento, PDF y operación HTTPS.
Hardware y biometría se mantienen como ampliación posterior.
