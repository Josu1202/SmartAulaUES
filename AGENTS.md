# Entorno de trabajo SmartAulaUES

## Prioridad de continuidad: A01, fase 3A (trabajo local, sin release)

Base 4873cbf, rama feature/p3a-a01-calculo-asistencia, versión 0.3.2 conservada.
El reparto phase3-member2.md/F3 r1 sustituye el antiguo: integrante 2 A01–A05;
integrante 3 B01–B07. A01 está implementado localmente para revisión; no implica cierre de fase 3.
Leer docs/attendance-calculation-contract.md y docs/a01-verification.md.
Reglamento project-2026-v1 vigente: 75 %, presentes + justificadas con permiso aprobado.
El usuario autoriza normalizar explícitamente cómputos de desarrollo en local/siaa y testing/siaa_test,
con preview, base efectiva, protección de cierres/reportes y auditoría de Sistema. No se hace al consultar.
La decisión anterior de conservar políticas demo de desarrollo queda sustituida. No crear alias en el calculador.
La pantalla independiente de política se retiró: resultados y explicación en /offerings/{offering},
selector period_id y paginación results_page. No exponer versiones internas en la interfaz.
AttendanceSummary::forOffering reutiliza el calculador único, carga por lote y lectura coherente por página;
paginar estudiantes jamás pagina sesiones. Estudiante solo su fila y sus incidencias.
Leer docs/a01-table-verification.md y docs/change-notes-status.md; a01-verification.md es histórico.
Estado de A01: implementación terminada y validación local completada; no integrado mediante PR.
Evidencia final: 134 pruebas/831 aserciones sin warnings, seis comprobaciones concurrentes y revisión visual
en tres perfiles × 1440/390/320 px. No atribuir estos resultados a CI remoto o al resto de fase 3.
Faltantes/inconsistencias: fracción observada e incidencias propias, porcentaje/conclusión nulos.
Permisos pendientes con datos completos: resultado provisional. No afirmar aprobación institucional.
AttendanceSummary usa locks compartidos ciclo → grupo → cómputo; conservar ese protocolo.
El usuario autoriza RefreshDatabase exclusivamente en siaa_test desechable, previa comprobación
de conexión efectiva con scripts/check-test-database.php. No modificar arnés/CI para evitarlo.
No modificar .env, regenerar claves, incluir cachés PowerShell, publicar ni cambiar release.

## Contexto histórico de fases 1–2 (subordinado a lo anterior)

Este repositorio es SmartAulaUES, el sistema de asistencia académica de una materia universitaria.
El usuario autorizó crear los cimientos y continuar por funciones. Leer README.md, CHANGELOG.md,
docs/project-state.md y la guía en docs/reference antes de cambiar el alcance.

Desde v0.2.0 el trabajo se divide en tres fases por módulo completo. El usuario desarrolla fase 1
(cuentas/organización académica/CSV); miembro 2, clases/asistencia/permisos; miembro 3,
seguimiento/cierre/entrega. Leer docs/development-phases.md y docs/decisions.md.
Esta división reemplaza el reparto backend/frontend/integración antiguo de la guía.
Repositorio del usuario: https://github.com/Josu1202/SmartAulaUES.git. No afirmar que una entrega
ZIP está publicada en GitHub. Los ZIP no contienen .git; conservar el checkout del usuario.

v0.3.2 conserva fase 2 de v0.3.0 y el parche v0.3.1. Base pública main/v0.3.1
(7b9b11826b0488cac26cb9febfa7f2741f186c45), rama feature/mobile-https.
Fases 1 y 2 ya están integradas en main; árbol verificado igual a la entrega v0.3.1. Las anotaciones del usuario agregan directorios docentes/estudiantes,
estudiantes por entry_year y study_year (ambas interpretaciones de “año”), alta manual/CSV y
actualización de años de cuentas existentes, inscripción por filtro y formatos de ciclo reutilizables.
Formato usado y cómputos heredados son inmutables; no asignar fechas inventadas a registros anteriores.
Docente administra sesiones/resultados/permisos de grupos propios; no estudiantes/inscripciones/calendarios.
Conteos y avisos internos operativos; política/porcentaje/cierre/PDF/noticias siguen en fase 3.
Leer docs/mobile-https.md, docs/update-v0.3.1.md, docs/update-v0.3.0.md, nfc-contract.md y project-state.md antes de continuar.
Parche 0.3.1: AuditLog::ENTITY_TYPES valida códigos independientes del idioma; la bitácora usa
etiquetas es explícitas porque la interfaz es española. No volver a validar con array_keys(__()).
Defaults de locale/fallback es. CI copia .env.testing.example a .env.testing y falla ante warnings;
regresión prueba locale es/en/zz con fallback en, tanto render como filtros válidos e inválidos.
v0.3.2 agrega túnel Docker opcional (perfil mobile), sin dependencias/migraciones nuevas.
TrustDeploymentProxies obtiene SMARTAULAUES_TRUSTED_PROXIES desde config en cada request tras el bootstrap;
no leer config prematuramente al resolver HttpKernel. Default vacío; solo X-Forwarded-For/Proto confiables.
Comodín * solo para pruebas con app en 127.0.0.1; servidor definitivo usa IP/CIDR reales del proxy.
APP_URL real del túnel, APP_DEBUG=false y SESSION_SECURE_COOKIE=true; limpiar cachés y reiniciar app.
No modificar .env/APP_KEY del usuario al copiar la entrega; URL real se obtiene en los logs de su equipo.
TLS local y NDEF simulado no demuestran ejecución cloudflared ni lectura física. No afirmar URL pública
sin haber iniciado el túnel real. Este soporte no sustituye HTTPS/hosting definitivo de fase 3.

- Laravel 13, PHP 8.4, PostgreSQL 17, Blade y fuentes Tabler 1.6.1 adjuntas.
- Negro, rojo y blanco. Reutilizar layouts/componentes; estilos SmartAulaUES en resources/css/app.css.
- No convertir a React/Next ni reemplazar Laravel. No introducir otra aplicación frontend.
- Módulos aún pendientes deben seguir identificados como pendientes; no simular guardados.
- Preservar nombres y relaciones de la guía. Policies por recurso, auth/active por ruta y CSRF.
- La aplicación guarda instantes en UTC y muestra America/El_Salvador.
- No subir .env, claves, vendor, node_modules, evidencias privadas ni datos reales a Git.
- No leer o editar materiales PLM que existen fuera de este repositorio.
- Aplicar migraciones nuevas al ampliar un esquema que ya tenga datos/versiones compartidas.
- Una función incluye pruebas útiles de sus reglas, autorización y fallos relevantes.
- Reutilizar app/Actions/Academic para escrituras/CSV. Motivos y auditoría en la misma transacción.
- Cupos reservan inscripciones futuras; retiro inclusivo y sin borrado. No excluir historial al editar fechas.
- Mantener orden de locks ciclo → grupo → recurso para nuevas operaciones de asistencia/cierre.
- RegisterAttendance toma primero advisory lock global por event_id; misma UUID/cuerpo recupera
  respuesta, otra solicitud con esa UUID devuelve 409. No confirmar presencia antes del servidor.
- QR/enlace registran solo al estudiante autenticado; tokens en fragmento de URL y memoria temporal.
- Evidencias privadas: descarga por Policy, sin storage:link. Resoluciones y justificaciones auditadas
  no se sobrescriben desde una corrección ordinaria; reapertura controlada es tarea de fase 3.
- Contraseñas/tokens/hash no se añaden a bitácoras ni al input recuperado. auth.session es obligatorio.
- Diferenciar pruebas PGlite/PDO de PostgreSQL/Compose nativo y NFC físico; documentar evidencia real.
- Verificar composer validate, npm run build, npm test y php artisan test en una BD con sufijo _test.
- Ejecutar tests/Concurrency/attendance-races.php --env=testing contra PostgreSQL nativo migrado.
  Un smoke de conexiones serializadas PGlite no demuestra carreras nativas ni sustituye CI.
- Actualizar VERSION, config/smartaulaues.php, package.json, CHANGELOG.md y docs/project-state.md al liberar.
- Usar ramas feature/... y revisión antes de integrar. No hacer push/publicar sin instrucción.
- NFC del docente desde celular es prioridad. ESP32/PN532 y biometría son una ampliación posterior.
- Cuando se solicite un ZIP, excluir secretos/dependencias y registrar versión y estado verificable.
