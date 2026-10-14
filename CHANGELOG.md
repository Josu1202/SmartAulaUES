# Historial de versiones SmartAulaUES

## [0.2.0] — 2026-10-13 · Fase 1

### Implementado
- Administración de usuarios, perfiles/estado/carné, restablecimiento por coordinación y contraseña propia.
- Normalización de correo/carné, índice único lower(email) y revocación de sesiones/remember_token.
- Alta/edición de ciclos, catálogo, grupos/docentes/cupos, cómputos e inscripciones con vigencia/retiro.
- Fechas sin superposición, ciclos/cómputos cerrados de lectura, motivos y preservación de historial.
- Cupos transaccionales que reservan inscripciones futuras; retiro inclusivo, sin eliminar filas.
- CSV de usuarios e inscripciones: plantillas, preview 15 min/500 filas/1 MB, revalidación y lote completo.
- Bitácora con actor/antes/después/motivo y campos permitidos, sin contraseñas/tokens.
- pgAdmin 9.17 opcional para desarrollo; volumen independiente y conexión sin contraseña en JSON.
- Formularios/navegación Blade y diseño negro/rojo/blanco conservando marca e icono.

### Documentación y versiones
- Tres fases por módulos completos, usuario responsable de fase 1; contratos y pendientes de miembros 2–3.
- README ampliado para el equipo y otras IAs; continuidad, CSV, instalación y actualización compatible.
- Base pública verificada en Josu1202/SmartAulaUES; desarrollo en feature/phase-1-academic-administration.
- ZIP sin .git/secretos/dependencias; script de empaquetado con commit/hashes. Sin push ni merge remotos.
- Setup conserva APP_KEY existente; requisitos PHP explícitos comprobables por Composer.

### Verificado y límites
- 66 casos PHPUnit/313 aserciones correctas, motores PostgreSQL 17.5 PGlite aislados por caso.
- 8 pruebas NDEF; Composer, Pint, Vite, Blade y estructura Compose correctos.
- Flujo HTTP real con tres perfiles, altas/retiro/CSV/bitácora/contraseña; CSRF 419, 0 errores JS.
- Nueve pantallas en 1440/390/320 px, sin desbordamiento horizontal de página.
- Batería conjunta en PostgreSQL nativo, Docker/pgAdmin, CI y NFC físico aún pendientes. Fases 2–3 sin implementar.

## [0.1.2] — 2026-10-12

### Actualizado
- Nombre oficial SmartAulaUES en acceso, menú, títulos, errores, pie, NFC y documentación.
- Configuración central en `config/smartaulaues.php`, paquetes Composer/npm, prefijos CSS y workflow.
- Icono propio con birrete y marca de asistencia, en SVG/PNG y favicon SVG/ICO; logotipo adaptado
  al nombre largo para el menú y el acceso.
- Guía de actualización para conservar el remoto Git, los datos y las contraseñas ya configurados.

### Compatibilidad
- Se mantienen el proyecto/volumen Docker, las bases y los correos de prueba de versiones anteriores.
- Se acepta la variable de zona horaria anterior y el formato NDEF técnico de la base.
- No se cambian las migraciones SQL ni se agrega un restablecimiento de contraseñas al seed.

### Verificado
- Nombre e icono en acceso HTTP y estructura de aplicación en escritorio y móvil (1440, 390 y 320 px),
  sin desbordamientos ni errores JavaScript; SVG/ICO accesibles.
- 1 prueba de infraestructura con 2 aserciones y 8 pruebas NDEF correctas; Composer, Pint, Vite y Blade correctos.
- Las pruebas de dominio/roles anteriores conservan su versión de evidencia; no se repitieron con PostgreSQL aquí.

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
