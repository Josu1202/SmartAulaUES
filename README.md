# SmartAulaUES · Asistencia académica universitaria

**v0.2.0 · Fase 1: cuentas y organización académica.** Desarrollo real a partir de la primera
versión publicada en [Josu1202/SmartAulaUES](https://github.com/Josu1202/SmartAulaUES).
La base revisada fue v0.1.2, commit `446396cb61353fbd391faab0078babe908e43980`.
La guía original conserva SIAA en su nombre; el nombre oficial del sistema es **SmartAulaUES**.
Se mantiene el icono propio, Tabler y la paleta negra, roja y blanca.

## División del trabajo: tres fases, tres responsables

El usuario solicitó el 13/10/2026 dividir el desarrollo por módulos y asumir personalmente la fase 1.
Cada responsable entrega sus formularios, reglas, autorización y pruebas. Esta división sustituye
el reparto por especialidades A/backend, B/frontend y C/integración de la guía; mantiene sus funciones.
Los nombres de los miembros 2 y 3 todavía están por asignar: no se inventan responsables.

| Fase | Responsable | Alcance y entrega | Estado en este ZIP |
| --- | --- | --- | --- |
| **1. Organización académica** | **Tú, miembro 1** | Cuentas/perfiles/estado/acceso; ciclos; catálogo; grupos/docentes/cupos; cómputos; inscripciones y retiros; CSV con revisión; bitácora; instalación y contratos para continuar | Implementada en v0.2.0; comprobación final en Docker/CI pendiente |
| **2. Clases, asistencia y permisos** | Miembro 2 | Programar/reprogramar/cancelar/finalizar clases; alta y revocación NFC; lector autenticado; registro unificado NFC/QR/enlace/manual; justificaciones, evidencias privadas y correcciones | Pendiente; tablas/modelos y diagnóstico NFC disponibles |
| **3. Seguimiento, cierre y entrega** | Miembro 3 | Porcentajes provisionales, historial, noticias/notificaciones; cierre por cómputo y PDF privado versionado; integración, HTTPS, respaldo/restauración y manuales | Pendiente; dependencias PDF y estructura de datos disponibles |

La guía propone seis semanas y unas 12 h por persona/semana. Organización orientativa:
semanas 1–2, fase 1; semanas 3–4, fase 2; semanas 5–6, fase 3 e integración. Los responsables
2 y 3 pueden preparar contratos, criterios, pruebas y diseños mientras se construye la fase 1.
**La viabilidad NFC en el Android real y HTTPS debe comprobarse desde la primera semana**, sin esperar
al final. El prototipo ESP32/PN532 y la biometría son ampliaciones posteriores.

Consulta [docs/development-phases.md](docs/development-phases.md) para límites, dependencias,
criterios de aceptación y tareas de cada responsable. Cada función pequeña tiene issue, rama y PR;
una fase puede tener varios incrementos. Una tabla existente no significa que el módulo ya funcione.

## Funciones operativas de la fase 1

- Acceso Fortify, salida, usuarios activos, límite de intentos, sin registro público. Tres perfiles:
  coordinación, docente y estudiante. El estudiante ve únicamente su inscripción en cada grupo.
- Coordinación crea/edita cuentas y las desactiva conservando su historial. Carné obligatorio para
  estudiantes; correo único sin distinguir mayúsculas. No se cambia el perfil de cuentas con historial
  ni se permite quitar a la coordinación su propio acceso.
- Restablecimiento de contraseña por coordinación con motivo; cambio personal desde **Mi cuenta**,
  con verificación de contraseña actual. Contraseñas de al menos 12 caracteres y revocación de sesiones.
- Crear/editar ciclos, materias y grupos, asignar docentes activos y validar el cupo.
- Crear/editar cómputos dentro del ciclo, sin fechas superpuestas. Los cómputos cerrados son de lectura.
- Inscribir estudiantes activos y ajustar vigencia/retiro sin borrar filas ni excluir registros históricos.
- CSV separado de usuarios e inscripciones: plantilla, revisión de errores, confirmación transaccional,
  caducidad de 15 minutos, control de duplicados y comprobación final de cupo/datos. Máximo 500 filas/1 MB.
- Bitácora de altas, cambios, restablecimientos e importaciones. Actor, antes/después y motivo;
  sin contraseñas, hashes de acceso, tokens ni archivo CSV completo en la bitácora.
- pgAdmin **opcional** en Docker para consultar PostgreSQL durante el desarrollo.

**Todavía no se registran asistencias ni se producen reportes.** Programación de clases desde formularios,
credenciales NFC, QR/enlace/manual, permisos, cierre, seguimiento y noticias corresponden a fases 2–3.
El lector actual es un diagnóstico que lee NDEF; no guarda asistencias. Los módulos pendientes lo indican.

## Reglas que el equipo debe conservar

Las escrituras académicas comparten `app/Actions/Academic/AcademicRegistry.php`. Se bloquea el ciclo
antes del grupo en las transacciones; el CSV utiliza las mismas acciones que los formularios.
No escribir directamente en las tablas desde nuevos controladores/importadores para saltarse reglas.

| Regla | Decisión actual |
| --- | --- |
| Historial | Sin endpoints DELETE. Desactivar cuenta/materia, registrar retiro o cerrar ciclo |
| Identidad del grupo | Ciclo, materia y código no cambian si existen cómputos, sesiones o inscripciones |
| Cupo | Reserva una plaza toda inscripción cuyo fin sea vacío o >= `max(hoy en El Salvador, inicio del ciclo)`, incluso si empieza en el futuro. Las cuentas inactivas conservan la plaza hasta registrar retiro |
| Retiro | `active_to` es inclusivo; la plaza se libera al día siguiente. Volver a inscribir al mismo estudiante exige editar la vigencia existente |
| Fechas | Ciclo contiene cómputos, sesiones e inscripciones. Cómputos del mismo grupo sin superposición, incluso en los límites |
| Cambios sensibles | Motivo obligatorio al cambiar fechas, estado, vigencia, cupo o docente; se auditan en la misma transacción |
| Cierre | Ciclo cerrado y cómputo cerrado/en cierre son de lectura. No cerrar ciclo con cómputos abiertos o sesiones pendientes. El cierre oficial de cómputos pertenece a fase 3 |
| Consulta tras retiro | Se conserva el acceso del estudiante a su grupo histórico. Esto **no** autoriza registrar asistencia fuera de vigencia |
| Tiempo | Instantes UTC; presentación `America/El_Salvador`. Fechas académicas se almacenan como DATE |
| CSV | Solo altas de docente/estudiante e inscripciones nuevas. No sobrescribe cuentas, no importa coordinación, no confirma filas parcialmente |

El 75 %, tardanzas, justificadas, plazos y denominador aún necesitan validación institucional antes
de implementarse. `config/attendance.php` contiene una **propuesta de demostración**, no una regla aprobada.
Los cómputos nuevos conservan su `policy_version` propuesta; fase 3 deberá fijar la política aprobada y la instantánea.

## Tecnologías

| Parte | Base |
| --- | --- |
| Servidor | PHP 8.4, Laravel 13, Fortify; versiones exactas en composer.lock |
| Datos | PostgreSQL 17; Policies, restricciones SQL y acciones transaccionales |
| Vistas | Blade, fuentes Tabler 1.6.1 adjuntas; recursos locales, sin CDN |
| Recursos | Node 24 LTS, npm, Vite; Bootstrap JS 5.3.8 |
| Herramientas | Compose de desarrollo; pgAdmin 9.17 opcional |
| Calidad | PHPUnit, pruebas NDEF con node:test, Pint y GitHub Actions con PostgreSQL 17 |
| Preparación futura | Dompdf y endroid/qr-code instalados; sin flujos de reporte/QR todavía |

No hay otro frontend ni servidor Node permanente. Se conservan ambos archivos lock.
Los nombres técnicos `siaa` de base, volumen/proyecto Docker y cuentas demo permanecen para reutilizar
los datos de la primera versión. No cambies esos nombres para actualizar la marca.

## Actualizar tu primera versión ya instalada

Lee [docs/update-v0.2.0.md](docs/update-v0.2.0.md). Extrae el ZIP aparte y copia sus archivos sobre
una **rama nueva de tu repositorio**, conservando `.git`, `.env`, APP_KEY y los datos/volúmenes.
Este ZIP contiene el código y los recursos compilados; **no incluye `.git` ni dependencias descargadas**.
No sustituye el historial de GitHub. Después de copiar los archivos, desde tu proyecto:

```bash
docker compose up -d db
docker compose build app
docker compose run --rm app composer install
docker compose run --rm node npm ci
docker compose run --rm node npm run build
docker compose run --rm app php artisan optimize:clear
docker compose run --rm app php artisan migrate
docker compose up -d --force-recreate app
```

Abre http://localhost:8080 y entra con tu cuenta existente. **No regeneres APP_KEY, no reemplaces `.env`
ni ejecutes migrate:fresh para actualizar.** El seeder sigue sin cambiar contraseñas existentes.
La migración nueva agrega unicidad del correo sin distinguir mayúsculas y conserva los datos.
El documento de actualización explica qué hacer si había correos duplicados por mayúsculas.

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
Coordinación puede recuperar el acceso de otras cuentas desde **Usuarios → Editar → Restablecer acceso**.

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

## Recorrido para probar la fase 1

1. Entra como coordinación. Crea docente y estudiante (con carné), ciclo y materia.
2. Crea un grupo con docente/cupo y abre su detalle. Define cómputos sin superponer fechas.
3. Inscribe al estudiante. Edita vigencia/retiro con motivo; comprueba que la fila se conserva.
4. En Importaciones descarga una plantilla. Carga un CSV con duplicados: muestra errores y bloquea
   confirmar. Corrígelo y confirma; comprueba usuarios/inscripciones y bitácora.
5. En Usuarios restablece una contraseña; entra con ella y cámbiala desde Mi cuenta.
6. Entra como docente: solo consulta sus grupos. Entra como estudiante: solo ve su inscripción;
   URLs directas de administración dan 403.
7. Abre Asistencias/Permisos/Reportes: continúan marcados como pendientes. El diagnóstico NFC
   no guarda asistencia; requiere HTTPS y un dispositivo compatible para lectura física.

El seed ya incluye cómputos y sesiones. Para recorrer altas sin alterar ese escenario crea otro ciclo/grupo.
[docs/csv-imports.md](docs/csv-imports.md) contiene formatos y ejemplos ficticios.

## Mapa del código y contexto para otras IAs

| Ruta | Responsabilidad |
| --- | --- |
| app/Actions/Academic | Reglas transaccionales, gestión de cuentas, auditoría e importación |
| app/Http/Requests | Autorización de coordinación, normalización y validación de formularios |
| app/Http/Controllers/Admin | Operaciones administrativas; delegan reglas a acciones |
| app/Policies, Http/Middleware | Acceso por perfil/recurso, estado y sesión vigente |
| app/Models, Enums | Entidades, relaciones y estados del esquema |
| resources/views/layouts, components | Diseño compartido; x-form-field muestra errores y etiquetas |
| resources/views/admin, academic, profile | Gestión académica y consultas restringidas |
| resources/vendor/tabler/scss, resources/scss, resources/css | Plantilla proporcionada y diseño SmartAulaUES |
| resources/js/nfc-reader.js | Diagnóstico real NDEF; conexión al servidor pendiente |
| database/migrations, seeders, factories | Esquema incremental, demo y escenarios de pruebas |
| tests, .github/workflows/ci.yml | Pruebas e integración con PostgreSQL 17 nativo |
| docker/pgadmin, compose.yaml | Entorno local y consulta opcional de PostgreSQL |
| docs, AGENTS.md, CHANGELOG.md | Contexto, decisiones, límites, contratos, criterios y versiones |

Antes de modificar: leer **este README**, [AGENTS.md](AGENTS.md), [docs/project-state.md](docs/project-state.md),
[docs/development-phases.md](docs/development-phases.md), [docs/decisions.md](docs/decisions.md) y CHANGELOG.
La guía está en `docs/reference/Guia_de_desarrollo_SIAA_v2.pdf`; la marca y los contratos actuales prevalecen
sobre nombres o reparto de trabajo antiguos. No cambiar Laravel/Blade por React, no simular módulos pendientes,
no duplicar reglas ni editar migraciones compartidas. Mantener UTC y privacidad por recurso.

Para cada cambio: definir fase/criterio de aceptación, trabajar en rama, añadir pruebas útiles, ejecutar
comprobaciones y actualizar README/estado/changelog. Registrar resultados **reales**, distinguiendo motor
embebido, PostgreSQL nativo, Docker y teléfono físico. La siguiente IA debe conocer qué falta verificar.

## GitHub y versiones

Remoto del equipo: **https://github.com/Josu1202/SmartAulaUES.git**. Primera base pública verificada:
commit `446396cb61353fbd391faab0078babe908e43980`. La entrega se prepara en
`feature/phase-1-academic-administration`; no se publica automáticamente en el remoto.
No subas el ZIP como código del repositorio: copia los archivos fuente en tu checkout y conserva `.git`.
[docs/update-v0.2.0.md](docs/update-v0.2.0.md) incluye los comandos para rama, commit, push y PR.

[docs/versioning.md](docs/versioning.md) registra incrementos propuestos. Actualiza VERSION,
config/smartaulaues.php, package.json/package-lock.json y CHANGELOG al liberar. Cada PR requiere revisión
de otro miembro y CI antes de integrar. No usar push forzado ni publicar secretos, evidencias privadas o datos reales.

## Evidencia y límites

Para preparar otra entrega desde un checkout limpio y con los cambios registrados:
`npm run build` y `composer run package`. Crea el ZIP en `dist/`, con fuentes versionadas,
assets compilados, `RELEASE.json` (commit y hashes por archivo) y checksum SHA-256. Excluye secretos,
dependencias, datos y `.git`; no publica nada en GitHub. Para fases posteriores adapta el nombre del
paquete y los campos de fase en scripts/package.php.

[docs/test-results.md](docs/test-results.md) y [docs/verification.json](docs/verification.json) registran la
verificación de v0.2.0 y conservan resultados anteriores por versión. La ejecución de Docker/pgAdmin,
CI en GitHub y NFC físico se confirma en el equipo real; no se presenta como realizada en este entorno.
La fase 1 proporciona código operativo; una liberación institucional requiere las fases restantes y sus pruebas.

## Referencias

- [Guía proporcionada](docs/reference/Guia_de_desarrollo_SIAA_v2.pdf).
- [Laravel 13](https://laravel.com/docs/13.x/installation), [Fortify](https://laravel.com/docs/13.x/fortify).
- [pgAdmin en contenedor](https://www.pgadmin.org/docs/pgadmin4/9.17/container_deployment.html).
- [Web NFC](https://developer.chrome.com/docs/capabilities/nfc), [contrato NFC](docs/nfc-contract.md).
- [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md) conserva los avisos de las dependencias.
