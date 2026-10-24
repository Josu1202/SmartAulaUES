# Estado de continuidad · SmartAulaUES

## Estado vigente: A04 local sobre A01, A02 y A03 integrados

23/10/2026: historial local confirma `5172b91` (merge PR #5 de A03), `bf07b01` (PR #4)
y `18c7596` (PR #3). Rama `feature/p3a-a04-gestion-grupos`, versión 0.3.2 conservada.
A04 organiza grupos por estado real del ciclo, suspende/reactiva administrativamente y elimina solo
grupos demostrablemente vacíos, con confirmación, motivo, auditoría y locks existentes.
Actuales incluye ciclos vencidos abiertos; Historial conserva acceso y snapshots de A03. Desactivados
siguen consultables; reabrir ciclos conserva su suspensión. Métricas académicas del inicio filtradas;
resúmenes históricos operativos por ciclo, separados de las versiones de resultados.
[Contrato y comandos](a04-group-management.md), [verificación y límites](a04-verification.md).
Verificación del 24/10: A04 53/308, 39 JS, 31 comprobaciones concurrentes nativas y 23 capturas/medidas.
Regresión completa: 242 funcionales aprobadas; sonda HTTP transitoria aprobada al repetir sin cambios.
Scheduler real de testing dos veces, sin duplicar cierres ni resultados. No equivale a CI remoto o hosting.
No commit/push/PR/merge/despliegue. No se migró ni cambió desarrollo. GPS/HTTPS/NFC físico y hosting
siguen pendientes; A05/B01–B07 no se declaran entregados. Lo siguiente conserva evidencia histórica.

## Estado histórico de implementación A03, anterior al merge PR #5

22/10/2026: historial local confirma bf07b01 (PR #4, A02) y 18c7596 (PR #3, A01) en main.
Rama feature/p3a-a03-cierres-reapertura, release 0.3.2 conservada. A03 implementa respaldo diario de
sesiones, clasificación Sin inicio registrado al cierre, cierres atómicos/snapshots de todo el cómputo,
consulta vigente/histórica autorizada y reapertura administrativa con recierre manual. Ciclos protegidos.
[Contrato y operación](a03-closures.md), [verificación final por escenario](a03-verificacion-final.md).
Verificación del 23/10: 192 pruebas/1433 aserciones sin warnings, 39 JS, 16 comprobaciones concurrentes
nativas y scheduler real repetido sin duplicaciones. Corregidos controles de registro fuera de horario;
finalización/revisión y diagnóstico A02 se conservan. Evidencia anterior del 22/10 en a03-verification.md:
184 pruebas/1247 aserciones sin warnings, 39 JS, 14 comprobaciones concurrentes
nativas, scheduler ejecutado dos veces en testing y 16 medidas/capturas de interfaz. No es evidencia de CI remoto.
No commit/push/merge/despliegue. GPS/HTTPS físico de A02 y hosting del scheduler siguen pendientes;
A04–A05/B01–B07 no se declaran entregados. Las secciones inferiores conservan continuidad histórica.

22/10/2026: ante el bloqueo físico por precisión insuficiente reportado por el usuario, se añade adquisición
acotada a 15 s, mejor lectura reciente, cancelación/liberación y diagnóstico en las pantallas existentes.
Docente y estudiante pueden medir sin registrar, incluso sin portal abierto. En una instrucción posterior,
el usuario acuerda como configuración final radio 30 m e incertidumbre individual >0 y ≤10 m, conservando
`distancia + ambas incertidumbres ≤30 m`. Servidor e interfaz comparten config/geolocation.php.
Solo QR/enlace presenciales: proximidad aproximada al docente, no presencia dentro del aula. [Medición real y alcance](a02-location-measurement.md).
Implementación A02 preparada para revisión/commit del usuario; aceptación física 30 m/10 m pendiente.
Los portales no congelan límites: nueva solicitud usa configuración actual; referencia >10 m exige
cerrar/reabrir. Respuestas/evidencias históricas se conservan. Sin commit/push/merge/release.

## Estado vigente: A02 local para revisión sobre A01 integrado

21/10/2026: historial local confirma 18c7596, merge de PR #3 de A01 en main.
Rama feature/p3a-a02-qr-geolocalizacion, 0.3.2 conservada. A02 añade QR/enlace presenciales con
referencia fija, incertidumbre combinada, reintentos e idempotencia en el registrador único existente.
Evento requiere ubicación explícita, sin inferir valores previos. Migración incremental nullable.
[Contrato y ensayo físico](a02-geographic-access.md), [evidencia nueva](a02-verification.md).
GPS/HTTPS con dispositivos reales pendiente. Sin commit, push, merge, despliegue ni release.
A03–A05/B01–B07 siguen pendientes. El estado «sin PR» de A01 que sigue es histórico.

## Estado actual: A01 local para revisión

La revisión vigente añade tabla de Resultados de asistencia en el grupo, con period_id y results_page;
retira la pantalla independiente y normaliza explícitamente los 2 cómputos de desarrollo en siaa.
Una segunda revisión del comando encontró 0 candidatos/0 protegidos. El usuario declaró datos de desarrollo,
sin uso académico real; no se inventa aprobación formal universitaria. El cálculo sigue actualizable.
Consulta colectiva por lote, privacidad por perfil, toda la historia académica y sesiones completas por fila.
Prueba real de permiso: finalizar → solicitar → aprobar → recargar produce 80.00 % → 90.00 %, sin duplicados.
Evidencia vigente: docs/a01-table-verification.md; notas externas: docs/change-notes-status.md.
Implementación terminada y validación local completada: 134 pruebas/831 aserciones sin warnings,
seis comprobaciones concurrentes nativas, ejemplo ejecutable 90.00 % y nueve medidas visuales por perfil/ancho.
Sin integración mediante PR, commit, push ni merge; versión 0.3.2 conservada. Sin errores funcionales conocidos en A01.
La sección siguiente conserva el registro anterior de A01 y sus decisiones sustituidas sobre pantalla/políticas.

## Registro anterior de A01 (sustituido por la revisión de tabla)

Base comprobada 4873cbf, feature/p3a-a01-calculo-asistencia; release 0.3.2 sin cambios.
Fase 3A corresponde al integrante 2, fase 3B al integrante 3 (reparto F3 r1).
A01 añade política project-2026-v1, calculador, consulta autorizada consistente y pantalla de política.
Solo nuevos cómputos reciben la política. Los existentes conservan demo/desconocidas sin calcularlas.
Confirmación del proyecto: 75 %, presentes + justificadas aprobadas / realizadas elegibles;
no aprobación institucional. Faltantes/inconsistencias anulan porcentaje/conclusión, no la fracción.
No se implementan QR geográfico, cierre, PDF ni dashboards nuevos. Sin migraciones/dependencias nuevas.
Contrato definitivo: [attendance-calculation-contract.md](attendance-calculation-contract.md).
Evidencia de esta ejecución y pendientes: [a01-verification.md](a01-verification.md).
Verificación final 18/10: 124 pruebas/728 aserciones correctas sin warnings, exclusivamente en siaa_test;
cuatro carreras de registro y dos de lectura consistente nativas comprobadas en esta tarea.
Composer, Pint, 14 pruebas JavaScript y build correctos; queda advertencia Sass heredada documentada.
Sin commit/push/merge. Se conserva el cambio previo en .env.example y no se incorpora Microsoft/.

## Registro histórico de la entrega 0.3.2

Fecha de trabajo del equipo: 14/10/2026, America/El_Salvador. Entrega **v0.3.2, fase 2**.
Rama: `feature/mobile-https`. Base pública main/v0.3.1:
`7b9b11826b0488cac26cb9febfa7f2741f186c45`.
Remoto: https://github.com/Josu1202/SmartAulaUES.git. Consulta del 14/10/2026 para esta entrega:
main tiene VERSION=0.3.1; fase 2 está en `a697e84589cb0c3a7c65a2520d12644a93c3b711`,
también VERSION=0.3.1. Ambos árboles son iguales al código local v0.3.1 previamente entregado.
Fases 1 y 2, incluida la corrección de bitácora/entorno CI, ya están integradas en main.
El usuario pide HTTPS para probar desde el celular: se prepara Cloudflare Quick Tunnel hacia su Docker,
con sus mismas cuentas/BD, sin dominio/cuenta de Cloudflare ni despliegue independiente.
El enlace lo generará el contenedor tunnel en su computadora. No se ha iniciado aquí ni hecho push de v0.3.2.
Docker/Cloudflare reales, NFC físico y nueva ejecución CI siguen pendientes; no afirmar esos resultados.

## Instrucciones vigentes del usuario

Tres miembros humanos, módulos completos: usuario fase 1, miembro 2 asistencia/permisos, miembro 3
seguimiento/cierre/entrega. Nombres 2–3 pendientes. Solicitud actual: desarrollar fase 2 con docentes
separados de estudiantes, estudiantes masivos/por año, calendarios de cómputos reutilizables, docentes
limitados a sus sesiones y gráficas reales. Ahora se habilitan pruebas móviles por HTTPS manteniendo fase 2.
README contiene el contexto completo para otras IAs.

Se eligieron dos campos: entry_year (ingreso) y study_year (carrera), porque “año” admitía ambos usos.
Sin promoción automática ni inferencia para usuarios antiguos. Ambos se filtran al inscribir grupos.
Formato de ciclo: fechas absolutas por ciclo, copia por grupo; utilizado/heredado inmutable. Variante
nueva requiere otro nombre/formato. El campo version existe, pero no hay un editor automático de versiones.

## Base técnica conservada

Laravel 13/PHP 8.4/PostgreSQL 17; Blade/Tabler 1.6.1 adjunto; negro/rojo/blanco, nombre e icono SmartAulaUES.
No React/Next. `siaa` en base/volumen/correos demo y prefijo NDEF mantiene compatibilidad técnica.
Instantes UTC, visualización/fecha académica America/El_Salvador. Sin datos institucionales reales.
pgAdmin opcional en perfil tools; no reemplaza las migraciones/formularios de Laravel.

## Operativo desde v0.3.0; conservado en v0.3.2

Fase 1 completa conservada: acceso/contraseñas/revocación, cuentas/perfiles/estado/carné/correo,
ciclos/materias/grupos/cupos, cómputos individuales, inscripción/retiro/fechas históricas, CSV y bitácora.

- Directorios Estudiantes, Docentes y Coordinación; altas individuales con perfil fijo.
- Estudiantes con ingreso/carrera; lote manual 100, CSV 500/1 MB y CSV que cambia únicamente años de
  cuentas existentes. Preview 15 min, nonce/propietario/CSRF, revalidación y lote atómico.
- Inscripción de estudiantes activos por filtro/selección, vigencia común y cupo, páginas de 25.
- CycleFormat/CycleFormatPeriod, calendario copiado al crear grupo, fechas/rango/no solapamiento,
  formato utilizado y cómputos heredados protegidos. Grupos previos no se migran a otro calendario.
- Sesiones de docente asignado/coordinación: programación, reprogramación/cancelación con motivo,
  estados, fin con ausentes/justificadas y preservación de presencia; horario propio no solapado.
- Portales temporales, QR PNG, enlace por fragmento, login de estudiante conservando token en memoria;
  lector NFC propio con expiración/cierre. NFC/manual/QR/enlace convergen en RegisterAttendance.
- UUID ligado a actor/cuerpo, advisory lock global y una respuesta almacenada; replay y conflicto 409;
  resultado único inscripción/sesión. UI no confirma lecturas sin respuesta y reutiliza UUID al reintentar.
- Credenciales pendiente/escritura/lectura/activación/reemplazo/revocación; una activa por usuario,
  hashes en BD y tokens temporales fuera de logs. Alta pendiente 10 min.
- Correcciones presente/ausente con motivo; no sobrescribir justificación resuelta. Calendario cerrado bloquea.
- Permisos preventivos/posteriores, evidencia PDF/JPG/PNG privada 5 MB, decisión del docente/coordinación,
  justificación y avisos internos. Pendientes visibles tras finalizar; resolución no se reabre todavía.
- Gráficas accesibles con conteos autorizados y vacíos honestos; no porcentaje institucional.
- Auditoría y traducciones/filtros para entidades de ambas fases; no secretos ni rutas privadas en snapshots.
- Dos migraciones de v0.3.0; Docker permite cargas 6M/8M; script de carreras independiente en CI.
- v0.3.2: perfil mobile/tunnel, proxy HTTPS configurable, guía con URL/cookies/caché/reinicio y vuelta a HTTP local. No migraciones nuevas.

## Verificación y límites

Consultar docs/test-results.md y verification.json para cantidades/evidencia final; no reutilizar cifras
antiguas como pruebas de esta versión. Runtime PHP 8.4.25/Node 24.19.0, PostgreSQL 17.5 PGlite/PDO,
casos aislados y aplicación real por HTTP. Navegador con NDEF simulado, sin teléfono físico. En v0.3.2 se repitió el recorrido por un proxy TLS local
(HTTPS): contexto seguro, cookies Secure, login, URLs/recursos y formularios comprobados; no túnel público real.
Upgrade de esquema v0.2.0 conserva 23 cuentas/contraseñas, 40 inscripciones y 8 sesiones del seed.
Script concurrente tiene smoke previo con conexiones serializadas; concurrencia real y batería conjunta
quedan para PostgreSQL nativo/CI. El CI de v0.3.0 reportado falló antes de ejecutar ese script; no se consultaron resultados CI de la integración posterior.
Docker/pgAdmin y NFC Android/HTTPS físicos no ejecutados aquí.

## Próximo trabajo autorizado: estabilizar fase 2

1. Copiar v0.3.2 sobre un checkout de main/v0.3.1 en feature/mobile-https; conservar .git/.env/APP_KEY/storage/volúmenes.
2. Seguir docs/mobile-https.md: crear el túnel en Docker, configurar la URL/cookies/proxy y reiniciar app.
3. Ejecutar batería y carreras con PostgreSQL 17 nativo/Compose; revisar el PR con otro miembro.
4. Probar Android/Chrome real por HTTPS, cinco etiquetas y fallos/reintentos descritos en nfc-contract.md.
5. Validar con coordinación calendarios, años y escenarios de permisos de la institución.
6. Corregir hallazgos en ramas pequeñas y registrar versiones/evidencia. No tratar un mock NFC como
   prueba física ni un ZIP como publicación GitHub. El usuario pidió permanecer en fase 2.

Fase 3 sigue pendiente: política aprobada (75 %, denominador, tardanzas, plazos/justificadas), porcentaje
provisional, historial/calendario, cierre/snapshot/PDF privado/versionado, reapertura controlada, noticias,
respaldo/restauración, HTTPS definitivo y manuales. Avisos internos y conteos básicos ya existen en fase 2.
ESP32/PN532, biometría y APIs de hardware son ampliaciones posteriores.

Otra IA debe leer README, AGENTS, CHANGELOG, development-phases, decisions, nfc-contract y test-results.
Si scratch ya no está, recuperar SmartAulaUES_v0.3.2_fase2.zip; preservar el checkout Git del equipo.
No leer/editar materiales PLM externos. No editar migraciones compartidas ni publicar sin instrucción.
