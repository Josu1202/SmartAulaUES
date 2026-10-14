# SmartAulaUES · Asistencia académica universitaria

**v0.3.1 · Fase 2: estudiantes por año, calendarios reutilizables, clases, asistencia y permisos.**
Aplicación Laravel/Blade sobre la base v0.2.0, commit `47f48ca4c37efb33809bd8cdf29bc349b0bc5d65`.
Repositorio del equipo: [Josu1202/SmartAulaUES](https://github.com/Josu1202/SmartAulaUES).
La marca, el icono propio y el diseño Tabler negro, rojo y blanco se conservan.

**Parche v0.3.1:** corrige el error 500 de la bitácora detectado en GitHub Actions cuando
no había configuración de idioma español. Los filtros usan códigos estables y la bitácora
mantiene etiquetas en español, incluso con locale/fallback en inglés. CI prepara su propio
`.env.testing` desde el ejemplo para evitar advertencias de archivo ausente; no usa tus claves.
No hay migraciones nuevas. Actualización y subida: [docs/update-v0.3.1.md](docs/update-v0.3.1.md).

## Tres fases, un responsable por fase

El usuario es el miembro 1. Los nombres de los miembros 2 y 3 siguen pendientes de asignar.
Cada fase incluye reglas del servidor, interfaz, autorización, documentación y pruebas de sus módulos.
Esta organización sustituye el antiguo reparto backend/frontend/integración de la guía.

| Fase | Responsable humano | Alcance | Estado |
| --- | --- | --- | --- |
| 1. Organización académica | Usuario, miembro 1 | Cuentas/acceso, ciclos, materias, grupos/docentes/cupos, cómputos, inscripciones/retiros, CSV y auditoría | Implementada en v0.2.0; conservada y ampliada |
| 2. Clases, asistencia y permisos | Miembro 2 | Directorios separados, años de estudiantes, formatos de ciclos, sesiones, credenciales y lector NFC, QR/enlace/manual, ausencias, permisos/evidencias, correcciones y avisos internos | Implementada en v0.3.0; aceptación con NFC físico y PostgreSQL/Compose nativo pendiente |
| 3. Seguimiento, cierre y entrega | Miembro 3 | Política aprobada, porcentaje e historial, cierre/snapshot, PDF privado versionado, noticias e integración/HTTPS/respaldo/manuales | Pendiente; recibe conteos y avisos operativos de fase 2 |

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

El 75 %, denominador, tardanzas, plazos y efecto de justificadas siguen pendientes de validación
institucional. `config/attendance.php` contiene una propuesta demo; fase 2 no calcula el porcentaje oficial.

## Tecnologías y actualización

Laravel 13/PHP 8.4, PostgreSQL 17, Fortify, Blade/Tabler 1.6.1, Vite/Node 24. Sin otro frontend.
Versiones exactas en ambos archivos lock. pgAdmin 9.17 es opcional para inspeccionar la BD.
Los identificadores técnicos `siaa` de base, volumen y cuentas demo se conservan para reutilizar datos;
el nombre visible del sistema es SmartAulaUES. Dompdf está preparado para fase 3; QR ya está integrado.

Para actualizar v0.2.0 o v0.1.2, consulta [docs/update-v0.3.0.md](docs/update-v0.3.0.md).
Extrae el ZIP aparte, copia sus fuentes a una rama de tu checkout y conserva `.git`, `.env`, APP_KEY,
almacenamiento privado y volumen. Instala dependencias, compila, limpia caché y ejecuta `php artisan migrate`.
**No uses migrate:fresh, key:generate ni down -v para actualizar.** No necesitas repetir el seeder.

## Instalación nueva con Docker: Windows, Linux o macOS

Requiere Docker Desktop/Engine con Compose. Comandos desde la carpeta `SmartAulaUES` extraída.

1. Copia `.env.example` a `.env`. PowerShell: `Copy-Item .env.example .env`.
2. Define contraseñas nuevas en `DB_PASSWORD` y `DEMO_PASSWORD`. La segunda requiere al menos
   12 caracteres. Conserva `APP_ENV=local`, `DB_CONNECTION=pgsql` y `DEMO_SEED=true` para la demo.
3. Ejecuta:

```bash
docker compose up -d db
docker compose build app
docker compose run --rm app composer install
docker compose run --rm app php artisan key:generate
docker compose run --rm app php artisan migrate --seed
docker compose run --rm node npm ci
docker compose run --rm node npm run build
docker compose up -d app
```

Abre **http://localhost:8080**. PostgreSQL publica **55432** en tu equipo. Dentro de Docker la aplicación
usa **db:5432**. `--no-reload` en Dockerfile conserva esa dirección en el proceso HTTP; no lo elimines.
Si 8080 está ocupado, añade `APP_PORT=8090` y usa `APP_URL=http://localhost:8090` en `.env`.
Los puertos se publican únicamente en el equipo local. Este Compose es de desarrollo.

```bash
docker compose run --rm app php artisan test --compact
docker compose run --rm node npm test
docker compose stop
```

El volumen nuevo crea `siaa_test`. Si tu volumen ya existía y falta esa base:
`docker compose exec db createdb -U siaa siaa_test` (adapta el usuario si lo cambiaste).
La base de pruebas debe usarse exclusivamente para pruebas, que recrean sus tablas.
`docker compose stop` conserva datos. **`docker compose down -v` elimina los volúmenes** y no es parte del arranque.

### pgAdmin opcional

La aplicación administra usuarios/grupos desde sus formularios. pgAdmin sirve para inspeccionar la BD;
PostgreSQL y Laravel gestionan los datos y migraciones aunque pgAdmin no se inicie.

Añade a tu `.env` un `PGADMIN_PASSWORD` nuevo, `PGADMIN_EMAIL=admin@example.com` y `PGADMIN_PORT=5050`.
Si copiaste `.env.example` estos campos ya existen; solo falta la contraseña.

```bash
docker compose --profile tools up -d pgadmin
```

Abre **http://localhost:5050**, entra con `PGADMIN_EMAIL`/`PGADMIN_PASSWORD` y abre el servidor
**SmartAulaUES · desarrollo**. Para conectar a PostgreSQL introduce **DB_PASSWORD**, que es una contraseña
diferente de la del portal pgAdmin. El servidor precargado usa host `db`, puerto `5432`, base/usuario `siaa`.
Si personalizaste DB_DATABASE/DB_USERNAME, edita la conexión en pgAdmin. No guardes contraseñas en servers.json.
No cambies tablas manualmente como sustituto de una migración versionada.

`docker compose stop pgadmin` detiene la herramienta. Su volumen independiente conserva preferencias;
no modifica el volumen de PostgreSQL. Instalar la aplicación no obliga a iniciar pgAdmin.

## Instalación local sin Docker

PHP 8.4, Composer 2, PostgreSQL 17, Node 24/npm. Extensiones: pdo_pgsql, mbstring, XML/DOM, ctype,
curl, fileinfo, intl, gd, zip y bcmath. `composer check-platform-reqs` verifica requisitos reales.

```bash
php scripts/configure.php
```

Crea `.env` con contraseñas aleatorias y muestra la contraseña de las cuentas ficticias. Conserva
un `.env` existente. Adapta DB_HOST/DB_PORT/DB_USERNAME/DB_PASSWORD a tu PostgreSQL y crea las bases
**siaa** y **siaa_test** con tu usuario.

```bash
composer install
npm ci
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve --port=8080
```

Estos comandos corresponden a instalación nueva; una actualización conserva APP_KEY.
Para editar recursos con recarga ejecuta `npm run dev` en otra terminal.
`composer run setup` automatiza una instalación con `.env`/PostgreSQL previamente configurados;
conserva una APP_KEY existente y no elimina la BD.

Para pruebas locales, copia `.env.testing.example` a `.env.testing`, configura credenciales de
`siaa_test` y ejecuta `php artisan key:generate --env=testing`. Después:

```bash
composer validate --strict
composer check-platform-reqs
vendor/bin/pint --test
php artisan test --compact
npm test
npm run build
```

## Cuentas ficticias y diagnóstico de acceso

| Perfil | Correo |
| --- | --- |
| Coordinación | coordinacion@siaa.test |
| Docente 1 / 2 | docente1@siaa.test / docente2@siaa.test |
| Estudiante 01 | estudiante01@siaa.test |
| Otros estudiantes | estudiante02@siaa.test … estudiante20@siaa.test |

Las cuentas nuevas del seed usan **DEMO_PASSWORD de `.env`**. El ZIP no contiene contraseñas, APP_KEY ni
una BD prellenada. Seed idempotente: 23 usuarios, 2 materias/grupos, 40 inscripciones y 8 sesiones ficticias.
Solo crea cuentas en local/testing con `DEMO_SEED=true`; no restablece contraseñas ya existentes.
Coordinación puede recuperar el acceso de otras cuentas desde **Estudiantes / Docentes / Coordinación → Editar → Restablecer acceso**.

Si cambiaste DEMO_PASSWORD después del primer seed, las cuentas anteriores mantienen su contraseña inicial.
Si aparece `Connection refused` comprueba `docker compose ps` y `docker compose logs --tail=80 app db`.
Si PostgreSQL dice `password authentication failed`, cambiar DB_PASSWORD en `.env` **no cambia** la
contraseña del usuario de un volumen existente. Para sincronizarla sin borrar datos:

```bash
docker compose exec db psql -U siaa -d postgres
```

Dentro de psql ejecuta `\password siaa`, escribe dos veces el mismo valor de DB_PASSWORD y sal con `\q`.
Después ejecuta `docker compose run --rm app php artisan config:clear` y `docker compose restart app`.
Adapta `siaa` al usuario real de tu base. No publiques tu `.env` ni pegues sus contraseñas en un issue.

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
   Una sesión finalizada o ventana vencida rechaza registros nuevos. Los reportes/cierre siguen pendientes.

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

Rama de esta entrega: `feature/phase-2-attendance`. Base local v0.2.0: `47f48ca4c37efb33809bd8cdf29bc349b0bc5d65`.
Consulta del 14/10/2026: main sigue en v0.1.2 (`446396cb61353fbd391faab0078babe908e43980`);
las ramas de fases 1 y 2 están subidas. Fase 2 está en `9a8361d7d8a45a73407fad02279d65695a67cb92`
y su fuente coincide con v0.3.0. El CI reportado por el usuario falló; este parche corrige su causa.
V0.3.1 aún debe subirse y verificarse en GitHub. Este ZIP incluye ambas fases y no hace push ni despliega.
[docs/update-v0.3.1.md](docs/update-v0.3.1.md) explica el parche de CI y su subida a la misma rama.
[docs/update-v0.3.0.md](docs/update-v0.3.0.md) conserva los pasos de instalación completa de fase 2.

Actualiza VERSION, config/smartaulaues.php, package.json/package-lock.json y CHANGELOG al liberar.
Con checkout limpio y commit registrado: `npm run build` y `composer run package` crean en `dist/`
`SmartAulaUES_v0.3.1_fase2.zip`, manifiesto RELEASE.json con commit/hashes y SHA-256 del ZIP.
Incluye fuentes y assets compilados; excluye `.git`, `.env`, dependencias descargadas y datos privados.

[docs/test-results.md](docs/test-results.md) y [docs/verification.json](docs/verification.json) registran
lo comprobado y conservan evidencia anterior. CI incluye la batería y un script de procesos concurrentes
contra PostgreSQL nativo: `php tests/Concurrency/attendance-races.php --env=testing`, exclusivamente en BD
con sufijo `_test` y esquema migrado. Las pruebas de este entorno usan PGlite/PDO; no prueban concurrencia nativa.
Docker/pgAdmin, GitHub Actions, Android/etiquetas/HTTPS real y respaldo/restauración requieren aceptación
en el equipo correspondiente. Los porcentajes, cierre y PDF oficial corresponden a fase 3.

## Referencias

- [Guía entregada](docs/reference/Guia_de_desarrollo_SIAA_v2.pdf).
- [Contrato NFC y registro](docs/nfc-contract.md), [modelo de datos](docs/database.md).
- [Versionado](docs/versioning.md), [avisos de terceros](THIRD_PARTY_NOTICES.md).
