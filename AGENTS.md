# Entorno de trabajo SmartAulaUES

## Continuidad vigente: A03 local, sin release

22/10: rama feature/p3a-a03-cierres-reapertura sobre bf07b01, merge de A02 por PR #4;
A01 integrado por PR #3/18c7596. Las referencias inferiores a A01/A02 sin integración son históricas.
Leer docs/a03-closures.md y docs/a03-verificacion-final.md; evidencia anterior en docs/a03-verification.md.
Versión 0.3.2 conservada. Sin commit/push/merge/despliegue.
A03 añade inicio real UTC, respaldo tras terminar el día local America/El_Salvador, Sin inicio registrado
al procesar el cierre del cómputo, snapshot completo/inmutable en ReportVersion y reapertura con motivo.
Calendario permite cruzar medianoche: no truncar horario válido; respaldo espera ends_at cuando siga vigente.
No inventar inicio antiguo: recuperación explícita solo de after.started_at en una única transición auditada;
created_at antiguo usa inicio de transacción y no acredita inicio real. Si falta, finalización manual.
Cierre solo después de toda la fecha final local, sin permisos pendientes ni inconsistencias; no forzar cierre.
Coordinación (Role::Coordinator) es Administración. Ciclo cerrado: reabrir ciclo antes del cómputo.
reopened_at exige recierre manual; no borrar el marcador ni recerrar automáticamente. Conservar versiones.
Abierto usa cálculo vivo, cerrado snapshot vigente, reabierto En revisión; histórico mediante AttendanceSummary
con reportVersion. Estudiante solo su fila; no entregar el ReportVersion colectivo sin filtrar.
Sin sesiones evaluables: porcentaje/conclusión nulos. Resultados faltantes solo los genera el finalizador.
Conservar locks ciclo → grupo → cómputo/sesión → recurso y advisory lock global/replay del registrador.
No ampliar ventanas QR/enlace/NFC/manual ni cambiar A01/A02. Scheduler attendance:process-closures cada 5 min;
mantener prune geográfico diario. Hosting real, GPS/HTTPS físico y resto de fase 3 siguen pendientes.
Migración incremental 2026_10_22_000100; no migrate:fresh en desarrollo. Pruebas solo siaa_test tras verificar
lectura/escritura con scripts/check-test-database.php. Preservar .env/APP_KEY/.env.example y Microsoft/.
PDF/CSV/ranking/archivo de grupos fuera del incremento. Evidencia real y pendientes en a03-verification.md.
Validación local del 22/10: 184 pruebas/1247 aserciones sin warnings, 39 pruebas JS, 14 comprobaciones
concurrentes nativas, scheduler real en testing dos veces y 16 capturas/medidas; no atribuirlo a CI remoto.
23/10: verificación final por escenarios en docs/a03-verificacion-final.md; 192 pruebas/1433 aserciones,
39 JS, 16 comprobaciones concurrentes nativas, scheduler real repetido sin duplicar y 20 capturas/medidas. Se corrigieron
controles de registro visibles fuera de horario: ocultar apertura/lector/manual; conservar finalización,
revisión y diagnóstico A02 sin registrar. Sin alterar ventanas del servidor ni datos de desarrollo.

## Continuidad anterior: A02 integrado, sin release

Revisión 22/10: adquisición <=15 s con watchPosition y deadline propio; clearWatch al terminar,
cancelar, ocultar o salir. Una sola mejor candidata reciente en memoria. Diagnóstico sin coordenadas,
medición sin registrar para docente y estudiante (también en sesión sin portal). Para el ensayo físico
posterior autorizado: radio 30 m e incertidumbre máxima 10 m por dispositivo; fórmula intacta.
Valida proximidad aproximada al docente, no presencia dentro del aula. Leer docs/a02-location-measurement.md. Usuario reportó bloqueo físico por precisión;
la mejora busca medirlo, no declara aceptación ni cierre de A02.

Base 18c7596: A01 integrado en main por PR #3, confirmado en el historial local.
Rama feature/p3a-a02-qr-geolocalizacion, versión 0.3.2. Leer docs/a02-geographic-access.md
y docs/a02-verification.md. QR/enlace presenciales: referencia fija del abridor autorizado;
d + precisión docente + precisión estudiante <=30 m; cada precisión >0 y <=10 m;
evidencia nueva <=30 s y futuro <=5 s; parámetros en config/geolocation.php.
Evento requiere event_attendance_mode explícito: guía p15 no define que todos sean no presenciales.
Existentes quedan NULL sin reclasificar; error claro en QR/enlace, NFC/manual siguen autorizados.
Migración incremental nullable; nunca migrate:fresh en desarrollo. Sin coordenadas del estudiante
persistidas ni ubicación en auditoría/HTML/URLs/old_input. Retención geográfica 30 días mediante
attendance:prune-location y scheduler diario; verificar operación real del scheduler en el hosting.
Conservar registro único, locks e idempotencia de RegisterAttendance; replay antes de caducidad.
No commit/push/merge/despliegue; no modificar .env, APP_KEY ni cambio previo de .env.example;
Microsoft/ y artefactos privados quedan fuera. GPS/HTTPS físico pendiente; no cerrar fase 3.
El estado histórico de A01 «sin PR» de abajo queda sustituido por la integración confirmada arriba.

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
