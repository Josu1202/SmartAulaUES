# SmartAulaUES · Asistencia académica universitaria

**Versión 0.1.2 — cimientos de la aplicación.** Base Laravel real para desarrollar función por función.
Utiliza la guía de desarrollo v2.0 del 11/10/2026, entregada con el nombre anterior SIAA, y las fuentes
de Tabler adjuntas. Diseño negro, rojo y blanco. SmartAulaUES es el nombre oficial desde v0.1.2.

## Qué puedes usar ahora

- Iniciar/cerrar sesión con Fortify. Registro público deshabilitado y cuentas inactivas bloqueadas.
- Entrar como coordinación, docente o estudiante. Menú y consultas restringidos al perfil.
- Consultar grupos, docentes, sesiones e inscripciones. El estudiante ve solo su propia inscripción.
- Consultar usuarios y ciclos como coordinación; buscar usuarios y filtrar perfiles.
- Abrir la cuenta personal y una página de **prueba de lectura NFC** para un dispositivo compatible.
- Instalar migraciones y un escenario ficticio: 23 usuarios, 2 materias/grupos, 40 inscripciones y 8 sesiones.

**Aún no registra asistencias.** CRUD académico, asignación de etiquetas, NFC conectado al servidor,
QR/enlace/manual, permisos, CSV, noticias y cierre PDF son los próximos módulos. Las pantallas pendientes
lo indican expresamente. Las tablas/modelos ya existen como base, sin simular funciones terminadas.

## Tecnologías y dependencias

| Parte | Base |
| --- | --- |
| Servidor | PHP 8.4; Laravel 13 (parche exacto en composer.lock) |
| Datos | PostgreSQL 17; instantes UTC, visualización America/El_Salvador |
| Vistas | Blade; fuentes SCSS Tabler 1.6.1 del ZIP proporcionado |
| Recursos | Node 24 LTS; npm y Vite; Bootstrap JS 5.3.8 |
| Acceso | Laravel Fortify y Policies propias |
| Integraciones preparadas | Dompdf y endroid/qr-code instalados; sin endpoints de reporte/QR todavía |
| Calidad | PHPUnit, pruebas NDEF con node:test, Pint y workflow de GitHub Actions |

No hay una segunda aplicación frontend ni un servidor Node permanente. No se usa CDN para la interfaz.
Se conservan composer.lock y package-lock.json para reproducir las dependencias.

## Instalación con Docker (Windows, Linux o macOS)

Requiere Docker con Compose. Ejecuta los comandos dentro de esta carpeta.

1. Copia `.env.example` a `.env`. En PowerShell: `Copy-Item .env.example .env`.
2. Define `DB_PASSWORD` y `DEMO_PASSWORD` con contraseñas nuevas. `DEMO_PASSWORD` debe tener al menos
   12 caracteres. Mantén `APP_ENV=local`, `DB_CONNECTION=pgsql` y `DEMO_SEED=true`.
3. Ejecuta, en este orden:

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

Abre **http://localhost:8080**. PostgreSQL usa el puerto del host **55432**, para convivir con otra
instancia en 5432. Dentro de Docker la aplicación usa `db:5432` automáticamente.
Si 8080 está ocupado, añade `APP_PORT=8090` y cambia `APP_URL=http://localhost:8090` en `.env`.
Los recursos compilados se incluyen en el ZIP como comodidad; vuelve a compilarlos al editar el diseño.

```bash
docker compose run --rm app php artisan test --compact
docker compose run --rm node npm test
docker compose stop
```

La base `siaa_test` se crea al inicializar un volumen nuevo. Si ya existía el volumen:
`docker compose exec db createdb -U siaa siaa_test` (ajusta el usuario si lo cambiaste).
Conservar el volumen mantiene los datos. `docker compose down -v` los elimina: no forma parte del arranque.
La configuración Docker es de **desarrollo**, no un despliegue de producción.

### Actualización a SmartAulaUES v0.1.2

Sigue [docs/update-v0.1.2.md](docs/update-v0.1.2.md) para incorporar el cambio de nombre y el icono
a una instalación o repositorio existente. La configuración del sistema ahora está en
`config/smartaulaues.php`. Actualiza en tu `.env`:

```dotenv
APP_NAME=SmartAulaUES
MAIL_FROM_NAME=SmartAulaUES
VITE_APP_NAME=SmartAulaUES
```

La marca usa una configuración única en las vistas. `SMARTAULAUES_DISPLAY_TIMEZONE` sustituye
al nombre de variable anterior, que se sigue aceptando si ya estaba en tu `.env`.
La base, el proyecto/volumen de Docker y los correos de prueba mantienen sus identificadores técnicos
`siaa` para que la actualización reutilice los datos y las contraseñas existentes.
No se modifican las relaciones, el esquema ni la contraseña de las cuentas.

El icono propio (birrete y marca de asistencia) está en `public/brand/smartaulaues-icon.svg`,
con versiones PNG de 192/512 px y favicon SVG/ICO. Se integra en el logotipo del menú y del acceso.

### Corrección de conexión Docker desde v0.1.0

Si el navegador muestra `Connection refused` contra `127.0.0.1:55432` aunque instalaste con Docker,
actualiza el último comando de tu `Dockerfile`:

```dockerfile
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8080", "--no-reload"]
```

Laravel descarta variables externas al crear el proceso HTTP con recarga automática de `.env`.
`--no-reload` conserva `DB_HOST=db` y `DB_PORT=5432`, definidos por Compose. El puerto 55432
de `.env` sigue sirviendo para conexiones desde tu equipo; no lo cambies para aplicar este parche.

Desde la carpeta donde está `compose.yaml`, con Docker Desktop abierto:

```bash
docker compose up -d db
docker compose build app
docker compose run --rm app php artisan config:clear
docker compose up -d --force-recreate app
docker compose ps
```

Recarga http://localhost:8080. Si persiste, consulta `docker compose logs --tail=80 app db`.
Si faltan tablas porque las migraciones no terminaron, ejecuta
`docker compose run --rm app php artisan migrate --seed`.
Esta actualización no requiere copiar de nuevo `.env` ni generar otra `APP_KEY`.
Al cambiar `.env` con este arranque, usa `docker compose restart app`.

## Instalación local, sin Docker

Requisitos: PHP 8.4, Composer 2, PostgreSQL 17, Node 24 y npm.
Extensiones: pdo_pgsql, mbstring, XML/DOM, ctype, curl, fileinfo, intl, gd, zip y bcmath.
`composer check-platform-reqs` comprueba los requisitos del entorno real.

```bash
php scripts/configure.php
```

Este comando crea `.env` con contraseñas locales aleatorias y muestra la contraseña ficticia.
No sobrescribe un `.env` existente. Adapta DB_HOST, DB_PORT, DB_USERNAME y DB_PASSWORD a tu PostgreSQL.
Crea dos bases con tu usuario: **siaa** y **siaa_test**; las pruebas solo aceptan nombres con sufijo `_test`.

```bash
composer install
npm ci
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve --port=8080
```

Abre **http://localhost:8080**. Para editar recursos con recarga: `npm run dev` en otra terminal.
`composer run setup` automatiza instalación, migraciones y compilación **después** de configurar `.env`
y arrancar PostgreSQL. No restaura ni elimina tu base.

Para probar localmente, copia `.env.testing.example` a `.env.testing`, define sus credenciales de `siaa_test`
y ejecuta `php artisan key:generate --env=testing`. Luego:

```bash
composer validate --strict
composer check-platform-reqs
php artisan test --compact
npm test
npm run build
```

Las pruebas recrean las tablas de `siaa_test`; usa una base exclusivamente de pruebas.

## Cuentas ficticias

| Perfil | Correo |
| --- | --- |
| Coordinación | coordinacion@siaa.test |
| Docente del grupo 1 | docente1@siaa.test |
| Docente del grupo 2 | docente2@siaa.test |
| Estudiante 01 | estudiante01@siaa.test |
| Otros estudiantes | estudiante02@siaa.test ... estudiante20@siaa.test |

Todas usan inicialmente **el valor de DEMO_PASSWORD de tu .env**. No se incluye una contraseña real
ni APP_KEY en el ZIP. Repetir el seeder no duplica cuentas ni cambia contraseñas ya existentes.
El seeder de demostración se bloquea fuera de local/testing. Con DEMO_SEED=false no crea cuentas.
La recuperación de acceso controlada por coordinación se desarrollará en otra versión.

## Recorrido para revisar la base

1. Entra como coordinación y revisa Inicio, Usuarios, Ciclos y Materias y grupos.
2. Entra como docente1: solo aparecerá su grupo de Análisis de sistemas.
3. Entra como estudiante01: dentro de un grupo solo verás su inscripción.
4. Abre Asistencias o Permisos para ver su estado de próxima versión.
5. Desde una URL HTTPS y un Android compatible, abre Prueba del lector NFC.

La detección de NDEFReader no garantiza hardware NFC. La prueba lee contenido NDEF sin mostrar el token,
no escribe etiquetas y no confirma asistencia. Ver [docs/nfc-contract.md](docs/nfc-contract.md).

## Organización del código

| Carpeta | Responsabilidad |
| --- | --- |
| app/Models, Enums | Entidades, relaciones y estados del esquema |
| app/Http/Controllers | Consultas y vistas; sin reglas de asistencia duplicadas |
| app/Policies, Http/Middleware | Recursos autorizados, roles y usuarios activos |
| app/Actions/Attendance, Reports | Espacio reservado para acciones transaccionales de próximas versiones |
| app/Http/Requests | Validadores específicos que se incorporarán con cada formulario |
| resources/views/layouts, components | Diseño compartido y componentes reutilizables |
| resources/views/admin, academic, terminal, profile | Pantallas de la versión base |
| resources/vendor/tabler/scss | Fuentes originales seleccionadas del ZIP; mantener sus avisos de licencia |
| resources/scss/tabler.scss, css/app.css | Configuración de Tabler y diseño SmartAulaUES |
| resources/js/nfc-reader.js | Prueba real de lectura, todavía sin registrar en servidor |
| database/migrations, seeders, factories | Esquema, datos ficticios y escenarios de prueba |
| tests, .github | Pruebas y preparación de integración continua |
| docs | Alcance, decisiones, modelo de datos, contratos y versiones |

## Git y conexión posterior con GitHub

El ZIP contiene `.git`, con **v0.1.0**, **v0.1.1** y **v0.1.2** en **main**.
No hay remoto conectado. Después de crear un repositorio **vacío** en GitHub:

```bash
git status
git remote add origin https://github.com/TU_USUARIO/smartaulaues.git
git push -u origin main
git push origin v0.1.0 v0.1.1 v0.1.2
```

Configura tu identidad personal para los commits siguientes con `git config user.name` y `git config user.email`.
Si GitHub creó un README inicial, usa un repositorio vacío o integra ese historial sin un push forzado.
No subas .env, vendor, node_modules, claves ni evidencias privadas. Evita subir el ZIP completo al repositorio.

Cada función nueva se trabaja en una rama, por ejemplo `feature/academic-cycles`, con revisión y pruebas.
[docs/versioning.md](docs/versioning.md) describe cómo actualizar y registrar la versión.
[docs/project-state.md](docs/project-state.md) es el punto de continuidad de este entorno.

## Validación y siguiente etapa

Los resultados y límites reales están en [docs/test-results.md](docs/test-results.md).
El workflow se podrá ejecutar en GitHub cuando conectes el repositorio; no se presenta como ya ejecutado.
No se ha probado un teléfono físico, HTTPS externo, Docker ni el prototipo ESP32/PN532.

Siguiente incremento recomendado: CRUD de ciclos, catálogo, grupos e inscripciones con control de cupos;
comprobar NFC/HTTPS en el dispositivo real desde la primera semana.

## Referencias y licencias

- Guía adjunta: [docs/reference/Guia_de_desarrollo_SIAA_v2.pdf](docs/reference/Guia_de_desarrollo_SIAA_v2.pdf).
- [Laravel 13](https://laravel.com/docs/13.x/installation), [Fortify](https://laravel.com/docs/13.x/fortify).
- [Tabler con Laravel](https://docs.tabler.io/ui/getting-started/frameworks/laravel).
- [Web NFC](https://developer.chrome.com/docs/capabilities/nfc).
- [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md) conserva los créditos de las dependencias.
