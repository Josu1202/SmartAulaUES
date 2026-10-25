# Operación de SmartAulaUES

Guía vigente para los cambios locales de `feature/p3a-a05-integracion-final`, sobre
`6273a307f3411a5e1ba40c427153cdf39e545a0e`. Versión 0.3.2 conservada. No se hizo commit,
push, PR, merge ni despliegue remoto. Es la misma aplicación Laravel/Blade; el nombre del
worktree y de la rama sirve para aislar cambios, no identifica una edición del producto.

## 1. Instalaciones que coexisten en esta máquina

Ejecutar las órdenes de desarrollo desde PowerShell en:

```powershell
Set-Location C:\Users\jozzz\Downloads\SmartAulaUES-A05
```

| Uso | Proyecto Compose | Dirección | Base efectiva dentro de Docker |
| --- | --- | --- | --- |
| Revisión actual, datos ficticios nuevos | `smartaulaues-local` | http://127.0.0.1:8085 | `db:5432/smartaulaues_local`; desde Windows `127.0.0.1:55433` |
| Checkout habitual conservado | `siaa` | http://127.0.0.1:8080 | `db:5432/siaa`; desde Windows `127.0.0.1:55432` |
| Suites desechables, sin servidor público | `smartaulaues-tests` | Sin puerto publicado | `db:5432/siaa_test`, otra red y otro volumen |

Los antiguos auxiliares `siaa-a05` y `siaa-a05-scheduler` permanecen detenidos. No hay que
iniciarlos: usaban otra configuración y no forman parte del arranque vigente. No se borraron
la BD original, sus volúmenes ni las evidencias privadas anteriores. La aplicación habitual
no recibió las migraciones de esta rama. No ejecutes comandos de reconstrucción desde ella.

Requisitos locales: Docker Desktop con motor Linux y Compose v2, Git y PowerShell. Las imágenes
aportan PHP 8.4, Composer, PostgreSQL 17 y Node 24. No se necesita cambiar el PHP de Windows.
El seeder toma fechas de America/El_Salvador; los instantes se guardan en UTC. Mantener el reloj
del equipo sincronizado. Los servicios locales solo publican en `127.0.0.1`.

## 2. Arranque cotidiano de la instalación ya preparada

El Compose local usa la cookie `smartaulaues-local-session`, distinta de la instalación habitual:
los puertos por sí solos no separan cookies de un mismo host. Así se puede alternar entre
ambas instalaciones sin sustituir su sesión de acceso.

```powershell
Set-Location C:\Users\jozzz\Downloads\SmartAulaUES-A05
docker compose up -d
docker compose ps
Invoke-RestMethod http://127.0.0.1:8085/health/ready
docker compose exec -T scheduler php artisan attendance:scheduler-status
```

Abre **http://127.0.0.1:8085/login**. El primer comando inicia BD, web y scheduler. El proceso web
es el CMD de la imagen; no requiere `docker exec ... artisan serve`. `restart: unless-stopped`
recupera servicios que estaban activos al reiniciar Docker. Un servicio detenido intencionalmente
con `stop` se inicia mediante `up -d`. La BD conserva su volumen. El scheduler tiene un único
servicio por instalación y procesa cada minuto; puede tardar hasta el siguiente minuto en actualizar
su indicador. El pruning geográfico diario sigue programado.

La salud web verifica HTTP real, conexión de lectura y escritura, esquema requerido y manifest
de assets. `/up` solo confirma el arranque de Laravel; usa `/health/ready` para disponibilidad.
La salud del scheduler exige una ejecución completa sin errores en los últimos 180 segundos.
Un impedimento académico visible, como un permiso pendiente, no equivale a fallo del servicio.
Docker informa un servicio `unhealthy`; supervisa ese estado: el healthcheck no reinicia por sí
solo un proceso vivo. La política de reinicio sí recupera un proceso que termina.

```powershell
docker compose logs --tail 80 app scheduler db
docker compose exec -T app php artisan migrate:status
docker compose exec -T app php artisan schedule:list
```

Los errores de aplicación del entorno local se registran en `storage/logs/laravel.log`, según
el `.env` privado conservado. Los logs no contienen contraseñas, parámetros SQL ni cuerpos de
solicitudes. No publiques `.env`, datos privados, cookies, backups ni capturas con credenciales.

## 3. Primera instalación local o dependencias nuevas

En esta máquina ya está completada. Para un checkout nuevo de esta misma configuración:

```powershell
Set-Location C:\Users\jozzz\Downloads\SmartAulaUES-A05
docker build -t smartaulaues-development:local .
docker run --rm -v "${PWD}:/var/www/html" smartaulaues-development:local php scripts/configure-local.php
docker compose up -d --wait db
docker compose run --rm --no-deps app composer install --no-interaction --prefer-dist
docker compose run --rm node npm ci
docker compose run --rm node npm run build
docker compose run --rm --no-deps app php artisan migrate --force
docker compose up -d
```

`configure-local.php` crea `.env` **solo si no existe**: genera APP_KEY y contraseña PostgreSQL
privadas, deshabilita el seeder antiguo y configura los puertos locales. Si ya existe, no cambia
ningún byte. No copiar encima otro `.env` ni ejecutar `key:generate` en una instalación existente.
El `.env.example` previo del usuario se conserva; el script adapta únicamente el archivo nuevo.
Compose fija la BD local explícita y no depende del DB_HOST/PORT del archivo original.

Si falta DB_PASSWORD en un `.env` anterior, asigna una clave privada coherente con el usuario
PostgreSQL existente antes de arrancar. Cambiar la variable no cambia la contraseña de una BD
ya inicializada; no borres el volumen para resolver una diferencia de credenciales.

El código se monta desde Windows, mientras `vendor`, `node_modules` y `storage/framework`
usan volúmenes Linux del proyecto para reducir E/S. Cambiar la imagen **no actualiza un volumen
vendor existente**: ejecutar `composer install` como arriba al cambiar `composer.lock`.
No se ejecutan migraciones, reconstrucciones ni seeders al arrancar web o scheduler.

## 4. Cuentas y escenarios locales

```powershell
docker compose exec -T app php artisan local:scenario --generate-password
docker compose exec -T app php artisan local:access
```

El primer comando ya fue ejecutado. Su repetición conserva cuentas, fechas, contraseñas y cambios
posteriores. Para elegir una contraseña en la preparación inicial, ejecutar
`docker compose exec app php artisan local:scenario` y responder al campo oculto. No pasarla
como argumento ni escribirla en documentación. `--password-stdin` admite una entrada privada
desde una herramienta de secretos; no escribir el valor literal en el historial del shell.

`local:access` muestra la contraseña elegida/generada únicamente al administrador que ejecuta
el comando local. Se guarda cifrada con APP_KEY en `storage/app/private/exploration-access.enc`,
nunca en Git ni en una URL pública. Si un usuario cambia su contraseña, su cuenta utiliza la nueva;
la recuperación local sigue mostrando la contraseña inicial de las demás cuentas. Restaurar
APP_KEY y el archivo privado juntos si se traslada este escenario. No habilitar estos comandos en
producción: verifican entorno, host y conexiones efectivas y rechazan una base distinta.

Hay 52 cuentas: una coordinación, siete docentes y 44 estudiantes; tres egresados, dos cuentas
inactivas y una provisional. Los nombres y datos visibles son naturales, con correos `example.test`.
La lista completa y los recorridos están en [escenarios-y-cuentas-locales.md](escenarios-y-cuentas-locales.md).
Accesos principales: `lucia.herrera@example.test`, `ana.martinez@example.test`,
`camila.morales@example.test`; provisional `ines.mendoza@example.test`.

El seeder `ExplorationSeeder` usa acciones de dominio, conserva resultados válidos al cerrar y
limita al propio seeder el reloj histórico y la confirmación NFC sintética. Nunca se invoca al
arrancar. No usar `A05AcceptanceSeeder` para esta revisión: pertenece a la evidencia técnica
anterior, que permanece conservada.

## 5. Detener, reiniciar y actualizar sin perder datos

```powershell
docker compose stop
docker compose start
docker compose restart app scheduler
docker compose ps
```

`stop/start` conserva contenedores y volúmenes. `down` sin `-v`, seguido de `up -d`, conserva los
volúmenes, aunque recrea contenedores. No usar `down -v`, `volume prune` ni eliminación masiva.

Después de una actualización autorizada del código, con respaldo si quieres conservar los datos:

```powershell
docker compose stop scheduler
docker compose build app scheduler
docker compose run --rm --no-deps app composer install --no-interaction --prefer-dist
docker compose run --rm node npm ci
docker compose run --rm node npm run build
docker compose run --rm --no-deps app php artisan migrate --force
docker compose run --rm --no-deps app php artisan optimize:clear
docker compose up -d
Invoke-RestMethod http://127.0.0.1:8085/health/ready
docker compose exec -T scheduler php artisan attendance:scheduler-status
```

Estas órdenes no obtienen ni integran cambios Git. Esta entrega no ejecutó commit, push, PR o merge.
La versión 0.3.2 sigue vigente; la integración/release se decide por separado.

## 6. Reconstruir exclusivamente la base local desechable

**Borra los cambios y datos ficticios de `smartaulaues_local`.** No ejecutar sobre el checkout
habitual ni cambiar el nombre permitido. El comando verifica PostgreSQL, APP_ENV local, host
reconocido, nombre configurado y servidor/base efectivos de lectura y escritura antes de borrar.
Sin la opción explícita no modifica tablas. No elimina volúmenes ni otras bases.

```powershell
Set-Location C:\Users\jozzz\Downloads\SmartAulaUES-A05
docker compose stop scheduler app
docker compose run --rm --no-deps app php artisan local:rebuild
# La orden anterior debe terminar indicando que no borró nada y mostrando el destino verificado.
docker compose run --rm --no-deps app php artisan local:rebuild --confirm-database=smartaulaues_local
docker compose run --rm --no-deps app php artisan local:scenario --generate-password
docker compose up -d
docker compose exec -T app php artisan local:access
```

Detener web evita solicitudes de una revisión simultánea. El archivo cifrado se sustituye al crear
el nuevo escenario. Las evidencias privadas anteriores y `.env`/APP_KEY se conservan. Si necesitas
preservar una exploración, respáldala antes. El procedimiento es deliberadamente explícito.

## 7. Pruebas aisladas

La aplicación de revisión permanece en su base local mientras estas órdenes usan otro proyecto,
sin web pública ni scheduler. No ejecutar dos suites simultáneas sobre `siaa_test`.

```powershell
docker compose -f compose.testing.yaml up -d
docker compose -f compose.testing.yaml exec -T tests composer install --no-interaction --prefer-dist
docker compose -f compose.testing.yaml exec -T tests php scripts/check-test-database.php
# Continuar solo si confirma testing y siaa_test efectivos en lectura/escritura.
docker compose -f compose.testing.yaml exec -T tests php artisan test --compact --display-warnings --fail-on-warning
docker compose -f compose.testing.yaml exec -T tests php scripts/verify-a05-upgrade.php --env=testing
docker compose -f compose.testing.yaml exec -T tests php tests/Concurrency/attendance-races.php --env=testing
docker compose -f compose.testing.yaml exec -T tests php tests/Concurrency/attendance-summary.php --env=testing
docker compose -f compose.testing.yaml exec -T tests php tests/Concurrency/academic-closures.php --env=testing
docker compose -f compose.testing.yaml exec -T tests php tests/Concurrency/offering-management.php --env=testing
docker compose -f compose.testing.yaml exec -T tests php tests/Concurrency/a05-integration.php --env=testing
docker compose run --rm node npm test
docker compose -f compose.testing.yaml stop
```

Los comandos nativos son secuenciales y preparan sus propios datos. `RefreshDatabase` puede
reconstruir **solo** esa BD verificada. La sonda de migración también exige testing/siaa_test.
No cambiar el arnés para apuntarlo a desarrollo.

## 8. Diagnóstico local

| Síntoma | Comprobación y solución |
| --- | --- |
| Puerto ocupado | `docker ps --format "table {{.Names}}\t{{.Ports}}"` y `Get-NetTCPConnection -LocalPort 8085`. Los auxiliares antiguos deben estar detenidos. Cambiar `SMARTAULAUES_HTTP_PORT` conscientemente afecta a URL y publicación; no detener servicios ajenos. |
| Respuesta vacía | `docker compose ps`, `docker compose logs --tail 80 app`, `docker compose restart app`. El CMD debe ser `php artisan serve ... --no-reload`, no `sleep infinity`. Recrear con `up -d --force-recreate app` tras corregir configuración. No iniciar otro servidor dentro del contenedor. |
| Página sin estilos o manifest ausente | Ejecutar `docker compose run --rm node npm ci` y `docker compose run --rm node npm run build`; eliminar únicamente un `public/hot` residual de Vite si no hay servidor Vite activo. Comprobar health y solicitudes `/build/assets/` en el navegador. |
| Error de BD | `docker compose logs --tail 80 db`; verificar DB_PASSWORD privada, estado/migraciones y destino efectivo. Dentro de Docker es `db:5432`, no el puerto 55433 del host. No mostrar `docker compose config` completo en tickets: expande secretos. |
| Scheduler sin ejecuciones recientes | `docker compose logs --tail 100 scheduler`, `docker compose exec -T scheduler php artisan schedule:list`, `docker compose exec -T scheduler php artisan attendance:scheduler-status`. `up -d scheduler` y esperar el siguiente minuto. Buscar errores operativos privados y resolverlos. |
| Cómputo vencido abierto con worker sano | Revisar motivos de cierre y permisos pendientes desde Coordinación. Los reabiertos requieren recierre manual; reiniciar el worker no elimina esta protección. |
| Cambios PHP no visibles en producción | Recompilar la imagen y recrear app/scheduler. OPcache de producción no revisa archivos modificados; no editar el código dentro del contenedor. |

Un segundo procesador accidental termina mediante advisory try-lock PostgreSQL y no duplica
transiciones. Esto protege concurrencia, pero no sustituye mantener un solo scheduler por instalación.
En local no están disponibles NFC del teléfono ni ubicación física desde HTTP de escritorio.
Para el túnel opcional se conserva `docker compose --profile mobile up -d tunnel`; la URL real
sale de sus logs. Requiere configurar URL/cookies/proxy de forma explícita; no se inició un túnel
ni se atribuye aquí ninguna aceptación física. Véase [mobile-https.md](mobile-https.md).

En el Compose actual, usar el override versionado `compose.mobile.yaml` sin editar `.env`:

```powershell
docker compose --profile mobile up -d tunnel
docker compose logs --tail 80 tunnel
# Sustituir por la URL HTTPS real de los logs anteriores; aquí no se generó una URL pública.
$env:SMARTAULAUES_MOBILE_URL = "https://URL-REAL-DE-LOS-LOGS.trycloudflare.com"
docker compose -f compose.yaml -f compose.mobile.yaml --profile mobile up -d --force-recreate app scheduler tunnel
docker compose -f compose.yaml -f compose.mobile.yaml exec -T app php artisan optimize:clear
docker compose -f compose.yaml -f compose.mobile.yaml restart app scheduler
```

El comodín de confianza del override solo corresponde a este túnel local, con web publicada
en loopback; producción exige IP/CIDR reales. Al terminar:

```powershell
docker compose -f compose.yaml -f compose.mobile.yaml --profile mobile stop tunnel
docker compose up -d --force-recreate app scheduler
Remove-Item Env:SMARTAULAUES_MOBILE_URL
```

Volver a http://127.0.0.1:8085. Si se detiene/reinicia el túnel, la URL puede cambiar; repetir
la lectura de logs y el override. Estos pasos preparan el ensayo físico, no lo sustituyen.

## 9. Ejecución con servidor de producción

`Dockerfile.production` construye assets y dependencias sin desarrollo y ejecuta Apache con
document root `public`, reglas de Laravel, OPcache y logs estándar. `compose.production.yaml`
usa la misma imagen para web y scheduler; PostgreSQL no se publica y archivos privados tienen
un volumen persistente. El proceso inicial compila configuración/rutas/vistas **desde las variables
privadas del contenedor**. No migra ni siembra. `artisan serve` se reserva al Compose local.

Pasos para un servidor Linux con Docker Compose y checkout revisado, desde `/srv/smartaulaues`.
La ruta es una elección de instalación; el dominio, las IP de proxy y el proveedor de correo
deben sustituirse por los valores reales del hosting. Nunca reutilizar las credenciales ficticias.

```sh
cd /srv/smartaulaues
install -m 600 docker/production.env.example /srv/smartaulaues-production.env
# Editar /srv/smartaulaues-production.env de forma privada:
# PRODUCTION_APP_URL: dominio HTTPS real; DB_PASSWORD: clave aleatoria propia.
# APP_KEY: conservar la existente al actualizar/restaurar.
# Solo en una instalación NUEVA, obtener una clave con:
docker run --rm php:8.4-cli php -r 'echo "base64:".base64_encode(random_bytes(32)).PHP_EOL;'
# Guardar esa salida en APP_KEY del archivo privado; no incluirla en tickets o Git.
docker compose --env-file /srv/smartaulaues-production.env -f compose.production.yaml build
docker compose --env-file /srv/smartaulaues-production.env -f compose.production.yaml up -d --wait db
docker compose --env-file /srv/smartaulaues-production.env -f compose.production.yaml run --rm --no-deps app php artisan migrate --force
# Solo si no hay cuentas de Coordinación en la instalación nueva:
docker compose --env-file /srv/smartaulaues-production.env -f compose.production.yaml run --rm --no-deps app php artisan app:create-coordinator
docker compose --env-file /srv/smartaulaues-production.env -f compose.production.yaml up -d
curl --fail http://127.0.0.1:8086/health/ready
docker compose --env-file /srv/smartaulaues-production.env -f compose.production.yaml exec -T scheduler php artisan attendance:scheduler-status
docker compose --env-file /srv/smartaulaues-production.env -f compose.production.yaml logs --tail 100 app scheduler
```

La primera cuenta se crea con nombre, correo y contraseña ocultos elegidos por el administrador,
sin clave universal, y se audita. El comando se niega a sustituir Coordinación existente. No ejecutar
el seeder local en producción. El env de ejemplo versionado no contiene secretos válidos.

El puerto 8086 solo escucha en loopback. Un proxy del servidor termina HTTPS y reenvía a
`http://127.0.0.1:8086`, preservando Host, X-Forwarded-Proto y X-Forwarded-For. Configurar
`SMARTAULAUES_TRUSTED_PROXIES` con las IP/CIDR reales del proxy visto por Docker; vacío significa
que no se confía en cabeceras reenviadas. No usar `*` en el hosting definitivo. Instalar y renovar
un certificado válido del dominio; comprobar redirección HTTP→HTTPS, cookies Secure/HttpOnly,
CSRF, sesión, ubicación y NFC desde el dispositivo real. El proxy concreto depende del hosting.
No publicar directamente el puerto de PostgreSQL ni abrir la carpeta del proyecto como raíz web.

Se incluye [un vhost Nginx para el host](../docker/nginx/smartaulaues.conf.example). Si el servidor
usa Nginx con `sites-available/sites-enabled`, instalarlo después de disponer del certificado real:

```sh
cd /srv/smartaulaues
sudo install -m 644 docker/nginx/smartaulaues.conf.example /etc/nginx/sites-available/smartaulaues
sudoedit /etc/nginx/sites-available/smartaulaues
# Sustituir REQUIRED_DOMAIN y las rutas del certificado; conservar las variables propias de Nginx.
# Crear el enlace solo si aún no existe:
sudo ln -s /etc/nginx/sites-available/smartaulaues /etc/nginx/sites-enabled/smartaulaues
sudo nginx -t
# Continuar solo si la comprobación anterior termina correctamente:
sudo systemctl reload nginx
```

No se instala otro proxy dentro de Compose. En hosting administrado, configurar los mismos
destino y encabezados en su proxy existente. La emisión/renovación del certificado y las
rutas concretas dependen del dominio contratado; no se ejecutaron en un servidor externo.

`SESSION_SECURE_COOKIE=true`, APP_DEBUG=false y DEMO_SEED=false están fijados en Compose de
producción. No hacer `storage:link`: las evidencias se descargan por Policy, desde almacenamiento
privado. APP_KEY es necesaria para sesiones cifradas y archivos locales cifrados; respaldarla de
forma separada y segura. El volumen de producción no monta las evidencias ni el `.env` del worktree.

Correo: hoy las notificaciones académicas son internas. `MAIL_MAILER=log` no entrega mensajes.
Si se habilita SMTP, completar MAIL_HOST, MAIL_PORT, MAIL_SCHEME (`smtp` con STARTTLS o `smtps`
según proveedor), MAIL_USERNAME/PASSWORD/FROM_ADDRESS en el archivo privado, configurar SPF/DKIM
y comprobar entrega real. Esta entrega no valida un proveedor externo.

Supervisión: habilitar Docker al iniciar el servidor, revisar logs/rotación, espacio, certificados,
backups y la salud de ambos servicios. Alertar si `/health/ready` falla o scheduler supera 180 s;
un indicador académico bloqueado necesita intervención humana, no borrado de resultados.

## 10. Respaldo, restauración y actualización del servidor

Ejemplo Linux, desde la instalación de producción, usando el archivo privado anterior. Definir
`BACKUP_DIR` en una ruta privada del servidor con espacio y permisos para el operador.

```sh
cd /srv/smartaulaues
BACKUP_DIR=/srv/backups/smartaulaues
install -d -m 700 "$BACKUP_DIR"
docker compose --env-file /srv/smartaulaues-production.env -f compose.production.yaml stop scheduler app
docker compose --env-file /srv/smartaulaues-production.env -f compose.production.yaml exec -T db sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc' > "$BACKUP_DIR/database.dump"
docker compose --env-file /srv/smartaulaues-production.env -f compose.production.yaml run --rm --no-deps --entrypoint tar app -C /var/www/html/storage/app -czf - . > "$BACKUP_DIR/private-files.tar.gz"
chmod 600 "$BACKUP_DIR/database.dump" "$BACKUP_DIR/private-files.tar.gz"
docker compose --env-file /srv/smartaulaues-production.env -f compose.production.yaml start app scheduler
```

Conservar además el archivo de entorno/APP_KEY en almacenamiento protegido y una copia externa
cifrada. Verificar códigos de salida y tamaños; programar retención. La pausa evita desfase entre
BD y evidencias. Los nombres del ejemplo deben versionarse por fecha en el procedimiento real.

Para restaurar, ensayar primero en **otra instalación verificada**, con el mismo código y APP_KEY.
Las siguientes órdenes sustituyen datos de la instalación de destino: detener web/scheduler,
confirmar host/base efectivos y respaldo antes de ejecutarlas. No aplicarlas al entorno del usuario
por rutina ni eliminar volúmenes. La BD de destino debe existir y no tener otras conexiones activas.

```sh
docker compose --env-file /srv/smartaulaues-production.env -f compose.production.yaml stop scheduler app
docker compose --env-file /srv/smartaulaues-production.env -f compose.production.yaml exec -T db sh -c 'pg_restore --clean --if-exists --no-owner -U "$POSTGRES_USER" -d "$POSTGRES_DB"' < "$BACKUP_DIR/database.dump"
docker compose --env-file /srv/smartaulaues-production.env -f compose.production.yaml run --rm --no-deps --entrypoint tar app -C /var/www/html/storage/app -xzf - < "$BACKUP_DIR/private-files.tar.gz"
docker compose --env-file /srv/smartaulaues-production.env -f compose.production.yaml up -d
```

Verificar permisos `www-data` de los archivos restaurados, salud, acceso por rol, evidencias y
resultados cerrados antes de aceptar la recuperación. La restauración del hosting real sigue
pendiente; no se declara probada por tener estas órdenes preparadas.

Actualizar servidor: respaldo coherente; detener app/scheduler; disponer del código revisado;
construir imagen; `run --rm --no-deps app php artisan migrate --force`; `up -d --force-recreate`;
comprobar HTTP/rol/worker/privacidad. Mantener APP_KEY, BD y archivos. No ejecutar `migrate:fresh`,
seeders o rollback que elimine historia. Una imagen anterior puede requerir un esquema anterior:
planificar recuperación compatible, no asumir que cambiar la etiqueta de imagen revierte datos.

## 11. Regreso a la carpeta habitual después de integrar la rama

**No realizar ahora:** ambos worktrees tienen cambios que deben conservarse. Esta entrega no
integra Git ni mueve carpetas. Después de una integración revisada y autorizada:

1. Conservar el cambio previo `.env.example`, Microsoft/ y toda evidencia privada fuera de Git.
   El inventario de esta entrega identifica los archivos de código/documentación para el commit.
2. Confirmar que los cambios están integrados y que cada worktree está limpio de cambios pendientes.
   `git worktree list` y `git status --short` en ambas carpetas; no usar reset/clean para lograrlo.
3. En la carpeta habitual, cambiar/actualizar a la rama ya integrada mediante el flujo Git acordado.
   No copiar carpetas enteras desde el worktree ni reemplazar su `.env`/APP_KEY.
4. Detener el Compose nuevo **desde el worktree**: `docker compose stop`. Preservar su volumen
   `smartaulaues-local_database`, su archivo cifrado y evidencia. Detener la web original únicamente
   cuando se decida sustituirla; no destruir su BD `siaa`.
5. Ejecutar los comandos de instalación/actualización de esta guía desde
   `C:\Users\jozzz\Downloads\SmartAulaUES`. El mismo proyecto Compose `smartaulaues-local` puede
   recrearse desde allí con `up -d --force-recreate`; apuntará al código de la carpeta habitual y
   conservará los volúmenes. Verificar `docker inspect`/salud antes de abrir la aplicación.
6. Para conservar las cuentas locales cifradas, trasladar **solo** los archivos privados necesarios
   y APP_KEY correspondiente mediante un respaldo controlado, sin sobrescribir el entorno habitual.
   Si se desea comenzar con datos ficticios nuevos, usar la reconstrucción explícita de la sección 6.
7. Retirar el worktree únicamente después de preservar evidencias y confirmar que no quedan cambios
   ni archivos privados pendientes. No se ejecutó `git worktree remove` en esta entrega.

## 12. Evidencia y aceptaciones externas

Los resultados nuevos, mediciones comparables y reinicios realmente ejecutados están en
[operacion-verificacion.md](operacion-verificacion.md). Los informes A05 anteriores se conservan
como evidencia de aquella revisión, no como instrucciones de arranque vigentes.

La configuración local no es una afirmación de capacidad productiva. La imagen Apache se verifica
localmente por separado; dominio/TLS/proxy final, SMTP, copias/restauración externas, disponibilidad
del hosting y NFC/GPS físicos requieren aceptación en sus destinos reales. PDF/CSV de resultados,
ranking y noticias mantienen el alcance de Persona 3; no se presentan como funciones completadas.

Referencias de operación: [despliegue Laravel 13](https://laravel.com/docs/13.x/deployment),
[configuración OPcache](https://www.php.net/manual/en/opcache.configuration.php) e
[imagen oficial PHP](https://hub.docker.com/_/php). La evidencia local distingue estas recomendaciones
del comportamiento medido en esta máquina.
