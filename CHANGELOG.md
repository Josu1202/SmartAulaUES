# Historial de versiones SmartAulaUES

## A02 · parámetros finales para revisión · 22/10/2026 · sin release

- Configuración del servidor: radio 30 m e incertidumbre >0 y ≤10 m por docente y estudiante;
  aceptación distancia + ambas incertidumbres ≤30 m, sin redondear antes de decidir.
- Solo QR/enlace presenciales. Conserva referencia fija, adquisición ≤15 s, selección de mejor lectura
  reciente, liberación, reintentos y NFC/manual tras tres fallos. Mensajes sin promesa de presencia en aula.
- Portales abiertos leen los límites actuales; una referencia previa >10 m requiere cerrar/reabrir.
  No se reescriben evidencias ni respuestas de operaciones ya resueltas.
- Pruebas de límites inclusivos, rechazo y transición entre configuraciones; documentación vigente
  actualizada. Evidencia 5 m/2,5 m y 50 m/15 m conservada como historial, no valida estos parámetros.
- Implementación lista para revisión local; proximidad aproximada al docente. Validación física de
  30 m/10 m pendiente. Sin dependencias, migración adicional, cambios env, commit/push/merge.

## Registro anterior A02 · configuración de ensayo físico · 22/10/2026 · sin release

- Por instrucción posterior del usuario, radio 50 m e incertidumbre máxima 15 m por docente/estudiante;
  conserva distancia + ambas incertidumbres ≤50 m. Sustituye los límites iniciales descritos abajo.
- Radio y precisión centralizados en config/geolocation.php, mensajes y pruebas actualizados.
  Solo QR/enlace presenciales; adquisición ≤15 s, reintentos y NFC/manual tras tres fallos conservados.
- Valida proximidad aproximada al docente, no presencia dentro del aula. Sin nueva migración ni env.
  A02 sigue abierto: prueba en dispositivos reales con esta configuración pendiente. Sin commit/push.

## Registro anterior A02 · adquisición y diagnóstico acotados · 22/10/2026 · sin release

- Sustituye la lectura única por una ventana de hasta 15 s que conserva la candidata reciente de mejor
  precisión, sin promedios, coordenadas en diagnóstico ni historial de movimientos.
- Libera watch/temporizador/listeners al terminar, cancelar, ocultar o abandonar la página; callbacks
  tardíos no registran. Cancelar no suma fallos. Reintentos e idempotencia conservados.
- Muestra precisión obtenida, requisito individual y errores; permite medir sin registrar en las pantallas
  existentes de docente/estudiante, también sin portal abierto. Recomendación NFC/manual tras tres fallos.
- Mantiene 5 m, precisión >0 y <=2.5 m, fórmula d+aD+aE<=5 m y actualidad del servidor.
- Sin migración/dependencia/env/release nuevos. [Guía de medición](docs/a02-location-measurement.md).
  El bloqueo físico reportado requiere nuevas mediciones; A02 sigue abierto.

## Registro inicial A02 local · QR y enlace geográficos · sin release

- Base 18c7596 con A01 integrado; rama feature/p3a-a02-qr-geolocalizacion, 0.3.2 conservada.
- Referencia fija por portal presencial, validación conservadora de 5 m con incertidumbre de ambos
  dispositivos y rechazo en servidor de ubicación inválida/caducada. NFC/manual conservados.
- Modalidad efectiva protegida; Evento exige ubicación explícita nullable sin clasificar filas previas.
  Portales presenciales antiguos exigen cierre/reapertura. Una migración incremental, sin dependencias nuevas.
- Registro único con locks/idempotencia existentes, lectura nueva con UUID nuevo y reenvío exacto tras
  fallo de red. Mensajes específicos, botón protegido y recomendación tras tres fallos geográficos.
- Geolocation mismo origen solo en consola/check-in, sin coordenadas del estudiante persistidas,
  sin ubicación en auditoría/old_input y limpieza geográfica a los 30 días conservando idempotencia.
- [Contrato y pasos de actualización](docs/a02-geographic-access.md), [evidencia](docs/a02-verification.md).
  Aceptación física GPS/HTTPS pendiente; no cierra A02 completo ni el resto de fase 3.

## Revisión local A01 · tabla y reglamento vigente · sin release

- Retira la pantalla de política y el enlace independiente; incorpora explicación y tabla por cómputo
  en el detalle del grupo, selector validado, paginación independiente y privacidad por perfil.
- Reutiliza el calculador único con lectura colectiva consistente bajo locks compartidos y cargas por lote;
  paginar estudiantes no recorta sesiones ni publica totales de página como estadísticas de grupo.
- Operación explícita de normalización local/testing, con base efectiva, revisión previa, protección de
  cierres/reportes y auditoría de Sistema. Actualiza 2 cómputos de desarrollo; repetición con 0 candidatos.
- Fixtures/factories vigentes, ejemplo ejecutable 9/10 = 90.00 % y flujo real de permiso con recarga de tabla.
- Pruebas de historial, privacidad, paginación, no escrituras y concurrencia colectiva; evidencia actual
  en docs/a01-table-verification.md. Notas externas clasificadas en docs/change-notes-status.md.
- Sin dependencias/migraciones nuevas, cambios de release, commit, push ni merge.
- Validación local final: 134 pruebas/831 aserciones sin warnings, seis comprobaciones concurrentes
  nativas, 14 pruebas JavaScript y build correcto; revisión visual de tres perfiles en 1440/390/320 px.

## Registro anterior A01 · decisiones sustituidas por la revisión de tabla

- Política versionada project-2026-v1, confirmada para el proyecto: 75 % con presentes + justificadas
  respaldadas por permisos aprobados. No se atribuye aprobación institucional.
- Calculador puro y consulta autorizada con lectura consistente bajo locks compartidos parentales.
- Resultados incompletos sin porcentaje/conclusión; pendientes visibles y conclusiones provisionales.
- Cómputos nuevos reciben la política; existentes/demo/desconocidos no se reinterpretan ni reasignan.
- Pantalla Blade de política, fixtures comunes, pruebas de reglas/privacidad y script concurrente nativo.
- Auditoría de creación de cómputos incluye versión de política; preflight de conexión exacta siaa_test.
- Sin migraciones/dependencias nuevas, sin cambios de versión, cierres, PDF, QR geográfico ni ranking.
- Contrato y resultados de comprobación en docs/attendance-calculation-contract.md y docs/a01-verification.md.
- Sonda de infraestructura HTTP: timeout de petición compatible con arranque Docker/Windows y
  reintentos explícitos de conexión; mismas aserciones, sin cambiar RefreshDatabase ni CI.
- Verificación final: 124 pruebas/728 aserciones sin warnings en PostgreSQL nativo siaa_test,
  seis comprobaciones concurrentes, 14 pruebas JavaScript, Composer/Pint y build correctos.

## [0.3.2] — 2026-10-14 · HTTPS móvil de pruebas

### Implementado
- Perfil Docker mobile con cloudflared oficial: enlace HTTPS temporal a la aplicación/BD actuales.
- Middleware TrustDeploymentProxies configurable por SMARTAULAUES_TRUSTED_PROXIES, vacío por defecto.
- Configuración resuelta tras bootstrap; X-Forwarded-Proto/For para esquema/IP, sin Host/Port reenviados.
- Guía mobile-https.md: rama, copia, arranque/URL, .env/cookies, recursos compilados, cachés/reinicio,
  prueba física NFC, detención y regreso a localhost; README/AGENTS/estado/decisiones/contrato actualizados.
- Base pública main/v0.3.1 comprobada y reflejada en empaquetado. Rama feature/mobile-https.
- Sin migraciones/dependencias nuevas; preserva el parche de CI v0.3.1 y ambas fases.

### Verificado y límites
- Reproducidos recursos/formularios HTTP detrás de un proxy HTTPS antes del arreglo.
- 111 casos / 623 aserciones correctas, casos aislados con PostgreSQL 17.5 PGlite/PDO cuando requieren BD;
  cero advertencias PHPUnit. Tres pruebas nuevas de proxy/cookies/URLs/IP y encabezados no confiables.
- 14 pruebas JavaScript; Composer, Pint, Vite, Blade y YAML de Compose correctos.
- Recorrido por proxy TLS local: 30 comprobaciones de tres perfiles y 39 mediciones en 1440/390/320 px,
  contexto seguro/cookies Secure/URLs HTTPS, CSRF 419, NDEF simulado/reintento y 0 errores JavaScript.
- Upgrade conserva 23 cuentas/contraseña, 40 inscripciones y 8 sesiones.
- Docker/cloudflared reales, URL del equipo, chips físicos y nueva ejecución CI/carreras nativas pendientes.
- El soporte temporal no se presenta como hosting definitivo ni fase 3 completada.

## [0.3.1] — 2026-10-14 · Parche de fase 2

### Corregido
- Error 500 de la bitácora en GitHub Actions: los filtros ya no toman array_keys de una traducción ausente.
- Códigos de entidades definidos en AuditLog y etiquetas de bitácora españolas con locale/fallback externos.
- Idioma/fallback por defecto es, acorde con la interfaz española.
- CI prepara .env.testing desde su ejemplo; evita warnings por .env ausente y falla ante nuevas advertencias.
- Sin cambios de dependencias, migraciones, cuentas, datos académicos ni tokens NFC.

### Verificado y contexto
- Reproducción del fallo de v0.3.0 con locale en y del warning por archivo de entorno ausente.
- Dos regresiones nuevas: render con datos/filtros en es/en/zz y códigos válidos/inválidos con fallback en.
- 108 casos / 610 aserciones correctas mediante PostgreSQL 17.5 PGlite/PDO aislado, sin .env, con el
  entorno de pruebas preparado como CI y locale/fallback en; cero warnings con --fail-on-warning.
- 14 pruebas JavaScript; Composer, Pint, Vite y Blade correctos.
- Rama de fase 2 del usuario comprobada en remoto; parche preparado para actualizar el mismo PR.
- README/AGENTS/estado/evidencia y guía update-v0.3.1.md actualizados para el equipo y otras IAs.
- CI nativo posterior al push pendiente; no se afirma ejecución verde de GitHub ni prueba NFC física.

## [0.3.0] — 2026-10-13 · Fase 2

### Implementado
- Directorios independientes de docentes, estudiantes y coordinación, con perfil fijo en formularios.
- Año de ingreso/carrera y filtros; alta masiva manual/CSV y actualización solo de años de cuentas existentes.
- Inscripción de cohortes por filtro/selección, vigencia común, revalidación y cupo transaccional.
- Nueva pestaña Formatos de ciclos: cómputos/fechas una vez, copia al crear grupos; utilizado/heredado inmutable.
- Sesiones propias del docente: programación/reprogramación/estado/cancelación/finalización y horarios sin solapar.
- Portales temporales, QR PNG/enlace, lector NFC autenticado y asistencia unificada NFC/QR/enlace/manual.
- Credenciales pendientes/escritura/lectura/activación/reemplazo/revocación, hash y una activa por estudiante.
- Registro idempotente por UUID/cuerpo normalizado, advisory lock global, respuesta guardada y resultado único.
- UI sin falsa confirmación por fallo de red; reintento reutiliza evento. Login del enlace recarga CSRF/sesión nueva.
- Ausencias/justificadas al finalizar, preservación de presencias, correcciones con motivo y auditoría.
- Permisos preventivos/posteriores, evidencia privada hasta 5 MB, descarga/resolución autorizada y avisos internos.
- Gráficas/contador de permisos con datos autorizados reales y estados vacíos, sin porcentaje no aprobado.
- Auditoría/traducciones/filtros de fase 2 y navegación/formularios adaptados a móviles.
- Dos migraciones nuevas, límites PHP Docker compatibles y script de carreras incorporado a CI.

### Contexto y versiones
- Las anotaciones del usuario están en README, AGENTS, fases, decisiones, estado, CSV, esquema y contrato NFC.
- Guía de actualización v0.3.0: conservar .git/.env/APP_KEY/volumen/evidencias, aplicar migraciones sin reiniciar.
- Desarrollo sobre v0.2.0 en feature/phase-2-attendance; ZIP limpio con commit/hashes, sin publicación automática.
- La propuesta anterior de incrementos 0.3/0.4/0.5 se reúne en esta entrega; fase 3 sigue pendiente.

### Verificado y límites
- 106 casos PHPUnit/566 aserciones correctas con PostgreSQL 17.5 PGlite/PDO aislado; 14 pruebas JavaScript.
- Composer, Pint, Vite, Blade y diferencia Git correctos. Upgrade conserva datos/contraseñas de fase 1.
- 29 comprobaciones HTTP/navegador de tres perfiles; 39 mediciones en 1440/390/320 px, sin desbordamiento/errores JS.
- NFC simulado contra servidor real, respuesta perdida/replay sin duplicar; CSRF 419 y evidencia privada comprobados.
- Harness de cuatro carreras pasó smoke serializado; ejecución PostgreSQL nativo/CI pendiente.
- Docker/pgAdmin y Android/etiquetas/HTTPS reales pendientes; sin cierre/PDF/política institucional de fase 3.

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
