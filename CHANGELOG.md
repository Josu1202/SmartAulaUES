# Historial de versiones SIAA

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
