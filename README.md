# SmartAulaUES · Asistencia académica universitaria

**Estado local para revisión:** actualización del mismo SmartAulaUES, versión 0.3.2,
rama `feature/p3a-a05-integracion-final` sobre A01–A04 integrados. Sin commit, push, PR o release.
El checkout habitual y su entorno se conservan; los cambios siguen aislados en `../SmartAulaUES-A05`.

**Acceso:** http://127.0.0.1:8085/login. Web y scheduler arrancan mediante el Compose versionado.
[Guía única de operación local y producción](docs/operacion-local-y-produccion.md),
[52 cuentas y recorridos](docs/escenarios-y-cuentas-locales.md),
[verificación y rendimiento](docs/operacion-verificacion.md),
[inventario para commit futuro](docs/operacion-inventario.csv).
Las pruebas tienen su propia BD; la imagen Apache se distingue del servidor de desarrollo.
Se conservan la integración académica, snapshots y geolocalización, con textos y errores completos
en español, datos relativos y preparación repetible. NFC/GPS físicos y hosting definitivo
requieren aceptación externa. PDF/CSV de resultados, ranking y noticias siguen pendientes.
Las secciones históricas inferiores describen entregas anteriores; para comandos y cuentas
actuales utiliza la guía de operación.

El bloque siguiente conserva el registro histórico de A04 antes de su integración mediante PR #6.

**A04 local para revisión, sin release:** rama `feature/p3a-a04-gestion-grupos`, base `5172b91`
(A03 integrado mediante PR #5; A01/A02 por PR #3/#4). Materias y grupos distingue Actuales e Historial
por estado del ciclo, conserva ciclos vencidos abiertos y permite consultar desactivados con filtro.
Coordinación desactiva/reactiva con motivo y auditoría; solo elimina grupos demostrablemente vacíos.
Los grupos suspendidos conservan consultas y sesiones futuras; las escrituras humanas quedan bloqueadas,
el replay y el scheduler A03 se conservan. Una migración incremental, versión **0.3.2** intacta.
[Contrato, operación y comandos PowerShell](docs/a04-group-management.md),
[escenarios y evidencia real](docs/a04-verification.md), [propuesta de PR](docs/a04-pr.md).
Sin commit, push, PR, merge ni despliegue. La migración de desarrollo queda para el usuario.

**A03 integrado por PR #5 (`5172b91`), sin release:** implementación sobre `bf07b01`,
merge de A02 (PR #4); A01 integrado por PR #3. Finalización diaria de respaldo, cierre de cómputos/ciclos,
snapshots versionados y reapertura de Coordinación con motivo. Los resultados cerrados se consultan desde
su snapshot; los reabiertos aparecen En revisión y requieren cierre manual. Una migración incremental,
sin reiniciar datos ni cambiar la versión 0.3.2. [Reglas, actualización, comandos y contrato para persona 3](docs/a03-closures.md),
[verificación final por escenario y límites](docs/a03-verificacion-final.md). Sin commit, push, merge ni despliegue.

**Revisión A02 del 22/10:** adquisición de hasta 15 s y diagnóstico con **Medir precisión sin registrar**
para docente/estudiante, incluso sin portal abierto. Ensayo físico autorizado: radio **30 m**,
incertidumbre **>0 y ≤10 m por dispositivo**, fórmula **distancia + ambas incertidumbres ≤30 m**.
Valida proximidad aproximada al docente; no acredita presencia dentro del aula.
[Pasos sencillos para medir en dispositivos reales](docs/a02-location-measurement.md).
El usuario reportó precisión insuficiente en los equipos probados; A02 no se considera aceptado físicamente.

**A02 integrado en main por PR #4, sin release nueva:** QR/enlace presenciales con referencia fija al abrir
el portal y radio de 30 m considerando incertidumbre de ambos dispositivos. NFC/manual conservados;
en línea no solicita ubicación. Evento requiere indicar Presencial o En línea, sin reclasificar datos antiguos.
A01 integrado en main, base `18c7596`; la rama de A02 fue `feature/p3a-a02-qr-geolocalizacion`, versión 0.3.2.
Hay una migración nueva. [Uso, actualización y ensayo físico HTTPS](docs/a02-geographic-access.md),
[resultados y pendientes](docs/a02-verification.md). Prueba física pendiente; no se certifica GPS.

**v0.3.2 · Fase 2: estudiantes por año, calendarios reutilizables, clases, asistencia y permisos.**
Aplicación Laravel/Blade. Base local de A01: v0.3.2, commit
`4873cbf5c9a792cc3e3e986a05849e39433e1dae`, conservando las fases 1 y 2.
Repositorio del equipo: [Josu1202/SmartAulaUES](https://github.com/Josu1202/SmartAulaUES).
La marca, el icono propio y el diseño Tabler negro, rojo y blanco se conservan.

**Pruebas desde el celular en v0.3.2:** perfil Docker `mobile` con Cloudflare Quick Tunnel y
reconocimiento configurable del proxy HTTPS en Laravel. Usa tu instalación, cuentas y PostgreSQL
existentes. El enlace lo genera Docker en tu computadora; es temporal y requiere mantenerla encendida.
Pasos completos, configuración, prueba NFC y regreso a localhost: [docs/mobile-https.md](docs/mobile-https.md).

**Parche v0.3.1:** corrige el error 500 de la bitácora detectado en GitHub Actions cuando
no había configuración de idioma español. Los filtros usan códigos estables y la bitácora
mantiene etiquetas en español, incluso con locale/fallback en inglés. CI prepara su propio
`.env.testing` desde el ejemplo para evitar advertencias de archivo ausente; no usa tus claves.
No hay migraciones nuevas. Actualización y subida: [docs/update-v0.3.1.md](docs/update-v0.3.1.md).

## Fase 3 repartida entre integrantes 2 y 3

El reparto F3 r1 sustituye la asignación antigua de toda fase 3 al miembro 3.
Integrante 2 (3A): A01 política/cálculo, A02 QR geográfico, A03 cierre/snapshot/reapertura,
A04 estado de grupos y A05 CSV. Integrante 3 (3B): dashboards, PDF/CSV, historia/noticias,
experiencia, demo, rendimiento y operación. Cada módulo incluye reglas, autorización y pruebas.

**A01 integrado en main, sin nueva release:** reglamento vigente `project-2026-v1`,
75 % de (presentes + justificadas aprobadas) / clases realizadas elegibles. No se atribuye aprobación
formal de la universidad. En **Materias y grupos → detalle → Resultados de asistencia** se selecciona
el cómputo y se consulta una tabla por perfil, con denominador, pendientes e incidencias propias.
En períodos abiertos el cálculo sigue siendo actualizable; A03 conserva snapshots al cerrar. Ranking y PDF pertenecen a B01/B02.
La pantalla independiente de política se retiró. Los 2 cómputos de desarrollo se normalizaron mediante
operación explícita y auditada, conservando datos y fechas. Versiones desconocidas no son alias del reglamento.
[Contrato y ejemplos ejecutables](docs/attendance-calculation-contract.md),
[verificación actual](docs/a01-table-verification.md) y [notas de cambio](docs/change-notes-status.md).

| Fase | Responsable humano | Alcance | Estado |
| --- | --- | --- | --- |
| 1. Organización académica | Usuario, miembro 1 | Cuentas/acceso, ciclos, materias, grupos/docentes/cupos, cómputos, inscripciones/retiros, CSV y auditoría | Implementada en v0.2.0; conservada y ampliada |
| 2. Clases, asistencia y permisos | Miembro 2 | Directorios separados, años de estudiantes, formatos de ciclos, sesiones, credenciales y lector NFC, QR/enlace/manual, ausencias, permisos/evidencias, correcciones y avisos internos | Implementada en v0.3.0; aceptación NFC física pendiente; evidencia Compose/PostgreSQL en docs/a01-verification.md |
| 3A. Integridad y registro | Integrante 2 | A01–A05 | A01–A04 integrados; A05 local verificado; aceptación física A02 pendiente |
| 3B. Seguimiento y entrega | Integrante 3 | B01–B07, consumiendo cálculo/snapshot de A | Pendiente |

El alcance de v0.3.0 reúne funciones de fase 2 que antes se sugerían como varios incrementos.
Las versiones 0.4/0.5 no se consideran entregadas por aquella propuesta. Consulta
[docs/development-phases.md](docs/development-phases.md) para criterios y pendientes.

## Cambios solicitados e implementados en fase 2

### Coordinación: docentes y estudiantes separados

El menú incluye **Estudiantes**, **Docentes** y **Coordinación** como directorios independientes.
Cada formulario mantiene el perfil de su directorio. Las cuentas con historial no se convierten a otro perfil.

Cada estudiante tiene **año de ingreso** (ej. 2025) y **año de carrera** (ej. 2º). Se conservan ambos
porque “por año” puede referirse a cohorte o avance académico. Son datos administrativos actuales;
no se incrementan automáticamente ni alteran las fechas de sus inscripciones históricas.
Los registros anteriores muestran **Sin asignar** hasta que coordinación los clasifique.

- Alta individual desde Estudiantes, con carné, ambos años y contraseña inicial.
- **Alta masiva manual:** hasta 100 cuentas del mismo año, filas agregables, revisión y confirmación.
- **CSV de estudiantes:** hasta 500 cuentas/1 MB con años en cada fila; docentes tienen su propia plantilla.
- **Actualizar años por CSV:** clasifica cuentas existentes por carné y modifica únicamente sus dos años.
- **Cargar estudiantes al grupo:** filtra por ingreso, carrera, nombre/carné; selecciona varias filas de
  la página y establece vigencia común. La página muestra 25 candidatos; confirmar y continuar con la
  siguiente página. CSV de inscripciones admite lotes mayores. Se comprueba el cupo dentro de la transacción.

La revisión dura 15 minutos. Un lote se confirma completo o se rechaza completo. Las altas no sobrescriben
cuentas; la actualización de años conserva contraseña, perfil, estado e inscripciones.
Formatos exactos y ejemplos: [docs/csv-imports.md](docs/csv-imports.md).

### Formatos de ciclos: configurar una vez, reutilizar por grupo

Nueva pestaña **Formatos de ciclos** en la navegación. Coordinación selecciona un ciclo académico,
define un nombre y de 1 a 12 cómputos con número/fechas. Deben quedar dentro del ciclo y sin superposición.
Al crear cada grupo, selecciona el formato de ese ciclo: todos los cómputos se cargan automáticamente.

Cada grupo recibe una **copia identificada** del calendario. Un formato utilizado y los cómputos heredados
son de lectura; modificar fechas exige otro formato para grupos nuevos. Un formato sin uso puede editarse.
Los formatos contienen fechas absolutas: para otro ciclo/año se define su calendario una vez y se reutiliza
entre sus grupos. Los grupos anteriores conservan sus cómputos; la actualización no les asigna fechas nuevas.
La opción sin formato se conserva para calendarios individuales de coordinación y compatibilidad.

### Docente: sus sesiones y resultados

El docente consulta sus grupos/estudiantes y administra **solo sesiones de grupos asignados**:
programar, reprogramar con motivo, iniciar durante el horario, abrir/cerrar registro, finalizar o cancelar.
Elige un cómputo existente; no puede editar estudiantes, inscripciones, grupo ni calendario.
No puede programar horarios superpuestos con otra clase propia. Horarios se introducen en El Salvador.

La cancelación/reprogramación se admite sin asistencias ni permisos asociados; con historial se conserva
la sesión. La finalización conserva presencias y genera ausentes/justificadas para inscripciones vigentes
**en la fecha de la clase**, con retiro inclusivo. Las cuentas inactivas conservan su condición académica
hasta registrar retiro; desactivar acceso no las elimina de la lista. Finalizar antes de hora requiere motivo.
No hay finalización automática ni presencias inventadas por abrir la pantalla.

### Registro de asistencia y credenciales

**Sesiones y asistencia** integra NFC, QR, enlace y manual en una misma acción transaccional.
La sesión debe estar en curso, dentro de horario y con ventana vigente. Se verifica el método habilitado,
la inscripción vigente, el recurso autorizado y, en NFC, lector propio y credencial activa.
La ventana dura 15 minutos por defecto, configurable al abrirla (1–60), sin superar el fin de la clase.
Una ventana nueva cierra la anterior. Modalidad sugiere métodos; el docente puede ajustarlos.

| Método | Flujo operativo |
| --- | --- |
| NFC móvil | Coordinación emite alta pendiente, escribe y lee la misma etiqueta para activarla. Docente inicia lector desde su clase y recibe confirmación del servidor |
| QR / enlace temporal | Estudiante abre el enlace, inicia sesión allí y confirma su propia presencia. Token en fragmento de URL, hash en BD; no acepta un estudiante elegido por el cliente |
| Manual | Docente asignado/coordinación elige un inscrito de la lista e indica motivo, con ventana manual habilitada |

Coordinación puede revocar credenciales con motivo. Solo queda una activa por estudiante; altas pendientes
vencen en 10 minutos y no registran asistencia. El token solo se conserva en memoria durante el alta y en
la etiqueta; la BD guarda SHA-256. Una etiqueta legible puede copiarse: esta versión requiere supervisión.

Un UUID queda ligado al actor y cuerpo del evento. Repetir el mismo envío recupera su resultado; cambiar
el cuerpo con ese UUID devuelve 409. Dos métodos para la misma inscripción/clase conservan una sola fila.
Ante fallo de red la interfaz muestra **Sin confirmar** y permite reintentar el mismo evento. No hay cola
offline ni confirmación optimista. NFC físico requiere Android/Chrome compatible y HTTPS accesible desde
el celular; `localhost` del teléfono no es el servidor del equipo. El diagnóstico separado sigue disponible.

El QR/enlace se entrega al abrir una ventana. Si el docente recarga la pantalla, el secreto no se recupera:
puede abrir otra ventana y compartir el nuevo QR/enlace. El estudiante debe abrir el enlace completo;
se retira el fragmento de la dirección después de leerlo. QR/enlace no prueban presencia física por sí solos.
Contrato, errores y prueba física pendiente: [docs/nfc-contract.md](docs/nfc-contract.md).

### Permisos, evidencias, avisos y gráficas

El estudiante solicita permiso preventivo o posterior para una sesión de su inscripción vigente.
Puede adjuntar PDF/JPG/PNG de hasta 5 MB. Docente asignado/coordinación resuelve con explicación;
una aprobación convierte la ausencia en justificada o se aplica al finalizar la clase. Una presencia
existente no se convierte automáticamente en justificada. Finalizar conserva solicitudes pendientes.
Una resolución es inmutable en esta fase; una reapertura controlada se diseñará con el cierre en fase 3.

Las evidencias se guardan en `storage/app/private`, con descarga autorizada al titular/docente/coordinación;
no se sirven con `storage:link`. Docker incluye límites de carga compatibles; una instalación PHP local debe
admitir archivo de 5 MB y cuerpo de al menos 8 MB. Las correcciones presente/ausente requieren motivo,
respetan ciclo/cómputo abierto y conservan las justificaciones resueltas.

**Avisos** guarda notificaciones internas por reprogramación/cancelación, solicitud y resolución.
Cada titular marca sus avisos como leídos. No se envían mensajes externos ni correo.
El inicio muestra un contador de permisos pendientes y gráficas de barras con conteos reales por estado de sesión, resultado y método;
coordinación además ve estudiantes por año. Docentes ven sus grupos; estudiantes, sus propios resultados.
Gráficas con etiquetas, tabla accesible y estado vacío; no se muestran porcentajes sin política aprobada.

## Reglas que se conservan

Las escrituras reutilizan `app/Actions/Academic` y `app/Actions/Attendance`, Policies y CSRF.
Ciclo → grupo → cómputo/sesión → recurso es el orden de locks; registro toma antes un advisory lock global
por UUID. No saltar estas acciones desde controladores, importaciones o la futura fase 3.

| Regla | Decisión vigente |
| --- | --- |
| Historial | Sin borrado académico en la aplicación; desactivar, registrar retiro o cerrar |
| Cupo | Reservan plaza las inscripciones futuras y sin retiro, según `max(hoy local, inicio del ciclo)` |
| Retiro | `active_to` inclusivo; ajustar inscripción existente, conservar resultados históricos |
| Calendario | Formato utilizado y cómputos heredados inmutables; cómputos individuales solo coordinación |
| Recursos | El menú no sustituye la Policy; docente asignado, estudiante titular, cuenta activa y sesión vigente |
| Estado cerrado | Ciclo cerrado/cómputo en cierre o cerrado son de lectura; cierre oficial pendiente de fase 3 |
| Tiempo | Instantes UTC, presentación y fecha académica en `America/El_Salvador`; fechas puras DATE |
| Secretos | No contraseñas/tokens/huellas ni rutas de evidencia en la bitácora; snapshots con campos permitidos |
| Actualización | Nuevas migraciones, misma APP_KEY/BD/volumen; no reiniciar datos |

El proyecto confirma 75 % y justificadas aprobadas en el numerador para `project-2026-v1`.
No consta aprobación institucional. Tardanzas, plazos y certificación no se implementan en A01.
La política inmutable está en `AttendancePolicy`; `config/attendance.php` la selecciona para cómputos nuevos.
La actualización de cómputos de desarrollo es una operación de mantenimiento explícita; no se ejecuta al abrir páginas.

## Tecnologías y actualización

Laravel 13/PHP 8.4, PostgreSQL 17, Fortify, Blade/Tabler 1.6.1, Vite/Node 24. Sin otro frontend.
Versiones exactas en ambos archivos lock. pgAdmin 9.17 es opcional para inspeccionar la BD.
Los identificadores técnicos `siaa` de base, volumen y cuentas demo se conservan para reutilizar datos;
el nombre visible del sistema es SmartAulaUES. Dompdf está preparado para fase 3; QR ya está integrado.

Para actualizar v0.2.0 o v0.1.2, consulta [docs/update-v0.3.0.md](docs/update-v0.3.0.md).
Extrae el ZIP aparte, copia sus fuentes a una rama de tu checkout y conserva `.git`, `.env`, APP_KEY,
almacenamiento privado y volumen. Instala dependencias, compila, limpia caché y ejecuta `php artisan migrate`.
**No uses migrate:fresh, key:generate ni down -v para actualizar.** No necesitas repetir el seeder.

## Instalación y uso cotidiano

Todos los comandos vigentes, requisitos, preparación privada, dependencias, assets, migraciones,
seeder, arranque y reinicios están en [la guía única](docs/operacion-local-y-produccion.md).
Incluye reconstrucción protegida de la BD local, suites aisladas, diagnóstico, Apache/HTTPS/proxy,
respaldo/restauración y regreso a la carpeta habitual después de integrar la rama.
No hay que iniciar un servidor manual dentro de un contenedor encendido.

[Cuentas ficticias y escenarios actuales](docs/escenarios-y-cuentas-locales.md).
La contraseña local se obtiene con el comando privado indicado allí; no se publica en README.
## Recorrido de aceptación de fase 2

1. Coordinación: entra en Estudiantes, crea un lote manual/CSV y revisa antes de confirmar.
   Usa la plantilla de actualización de años para las cuentas anteriores. Filtra ingreso y carrera.
2. Crea/elige ciclo activo y materia. En Formatos de ciclos define cómputos y fechas. Crea dos grupos
   con el mismo formato y comprueba el calendario copiado. Elige docentes activos y cupos.
3. Desde cada grupo, Cargar estudiantes: filtra por años, selecciona inscritos y establece vigencia.
   Revisa duplicados/cupo; ninguna falla guarda medio lote.
4. Docente asignado: programa una sesión dentro de un cómputo, con hora local actual para probarla.
   Iníciala y abre registro con los métodos deseados. Comprueba que las URLs de estudiantes/formatos dan 403.
5. Prueba manual con motivo y QR/enlace con una cuenta de estudiante inscrita. Repetir no duplica.
   Para NFC, usa HTTPS/Android y coordinación → Credenciales NFC: emitir, escribir, leer/activar;
   luego docente → clase → iniciar lector. La respuesta del servidor es la confirmación.
6. Otro estudiante solicita permiso con evidencia. Finaliza la clase, verifica ausentes y resuelve el
   permiso. Revisa descarga privada, aviso al titular, bitácora y gráficas actualizadas.
7. Entra como otro docente/estudiante y comprueba que no accede a esos permisos/evidencias/resultados.
   Una sesión finalizada o ventana vencida rechaza registros nuevos. Cierres según A03; exportaciones PDF/CSV pendientes de B02.

El seed conserva su escenario ficticio inicial: no añade años, portales, credenciales ni presencias
artificiales. Sus clases tienen fechas fijas; crea tu propio ciclo/grupo/sesión para una prueba de hoy.

## Contexto del código para el equipo y otras IAs

| Ruta | Responsabilidad |
| --- | --- |
| app/Actions/Academic | Cuentas, años, ciclo/formato, grupos, cupos, inscripciones, CSV y auditoría |
| app/Actions/Attendance | Sesiones, locks, portales/lectores, credenciales, registro, correcciones y permisos |
| app/Http/Requests, Controllers | Validación/autorización de entrada y coordinación de acciones |
| app/Policies, Http/Middleware | Perfil y recurso, cuenta activa y contraseña/sesión vigente |
| app/Queries/DashboardStatistics.php | Conteos reales limitados al usuario autorizado |
| app/Notifications | Avisos internos en base de datos |
| resources/views | Blade y componentes reutilizados para los tres perfiles |
| resources/js/attendance-live.js | Formularios masivos, NFC operativo, reintentos, alta de etiquetas y QR/enlace |
| resources/js/nfc-reader.js | Validación NDEF y diagnóstico separado |
| resources/vendor/tabler, resources/scss, resources/css | Fuentes de la plantilla y diseño SmartAulaUES |
| database/migrations, seeders, factories | Migraciones incrementales, demo y escenarios de prueba |
| tests/Feature, JavaScript, Infrastructure, Concurrency | Reglas/permisos/interfaz/arranque y carreras de registro |
| .github/workflows, compose.yaml, docker | CI PostgreSQL 17, desarrollo y pgAdmin opcional |
| docs, AGENTS.md, CHANGELOG.md | Decisiones, límites, contratos, versiones y continuidad |

Antes de modificar: leer este README, [AGENTS.md](AGENTS.md), [docs/project-state.md](docs/project-state.md),
[docs/development-phases.md](docs/development-phases.md), [docs/decisions.md](docs/decisions.md) y CHANGELOG.
Guía original: `docs/reference/Guia_de_desarrollo_SIAA_v2.pdf`. Las anotaciones actuales del usuario
prevalecen sobre el reparto y nombres antiguos. No reemplazar Laravel ni modificar migraciones compartidas.
Cada incremento debe actualizar contexto y pruebas reales, distinguiendo motor embebido, nativo y NFC físico.

## GitHub, empaquetado y evidencia

Rama de esta entrega: `feature/mobile-https`. Base: main/v0.3.1,
`7b9b11826b0488cac26cb9febfa7f2741f186c45`, consultado el 14/10/2026.
La rama anterior feature/phase-2-attendance está en `a697e84589cb0c3a7c65a2520d12644a93c3b711`.
Ambos árboles coinciden con la entrega v0.3.1: las fases 1 y 2 y el arreglo de CI ya están en main.
No se ha publicado v0.3.2 ni abierto un túnel en tu equipo. Este ZIP no hace push ni despliega.
[docs/mobile-https.md](docs/mobile-https.md) explica la actualización, la URL temporal y el contexto del proxy.
[docs/update-v0.3.1.md](docs/update-v0.3.1.md) explica el parche de CI y su subida a la misma rama.
[docs/update-v0.3.0.md](docs/update-v0.3.0.md) conserva los pasos de instalación completa de fase 2.

Actualiza VERSION, config/smartaulaues.php, package.json/package-lock.json y CHANGELOG al liberar.
Con checkout limpio y commit registrado: `npm run build` y `composer run package` crean en `dist/`
`SmartAulaUES_v0.3.2_fase2.zip`, manifiesto RELEASE.json con commit/hashes y SHA-256 del ZIP.
Incluye fuentes y assets compilados; excluye `.git`, `.env`, dependencias descargadas y datos privados.

[docs/test-results.md](docs/test-results.md) y [docs/verification.json](docs/verification.json) registran
lo comprobado y conservan evidencia anterior. CI incluye la batería y un script de procesos concurrentes
contra PostgreSQL nativo: `php tests/Concurrency/attendance-races.php --env=testing`, exclusivamente en BD
con sufijo `_test` y esquema migrado. La evidencia histórica usa PGlite/PDO; la ejecución local A01
usa Compose/PostgreSQL nativo y se registra en [docs/a01-verification.md](docs/a01-verification.md),
con conexión exclusiva a `siaa_test` y dos pruebas concurrentes nuevas de lectura consistente.
pgAdmin, GitHub Actions, Android/etiquetas/HTTPS real y respaldo/restauración requieren aceptación
en el equipo correspondiente. A01 calcula resultados vivos de períodos abiertos; A03 conserva el cierre y sus versiones. PDF oficial pendiente de B02.

## Referencias

- [Guía entregada](docs/reference/Guia_de_desarrollo_SIAA_v2.pdf).
- [Contrato NFC y registro](docs/nfc-contract.md), [modelo de datos](docs/database.md).
- [Versionado](docs/versioning.md), [avisos de terceros](THIRD_PARTY_NOTICES.md).
