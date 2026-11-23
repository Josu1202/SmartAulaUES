# Despliegue público gratuito de SmartAulaUES

Base confirmada en GitHub: `main` en `11df0c01305bcaabe7a75e6232d98116d996ea42`, versión 0.3.2, con ambos QR y terminal física. Usa el SHA exacto que contenga también estos archivos de operación al instalar. La preparación local no asigna un dominio ni crea una cuenta en la nube.

## Destino y coste

La ruta completa usa OCI Always Free en la región principal, una VM A1 ARM64 con Ubuntu LTS, Docker Compose y Caddy en el host. Punto de partida: 2 OCPU, 8 GiB y aproximadamente 50 GB de arranque. Confirma capacidad y coste total en la consola de la cuenta antes de crear red, IP, VM o almacenamiento. OCI publica 1.500 OCPU-h y 9.000 GB-h mensuales para A1, con hasta 200 GB combinados de volúmenes de arranque y bloque. OCI puede recuperar VMs inactivas. Si falta capacidad, intenta otro dominio de disponibilidad permitido en la región principal o espera una plaza gratuita. [OCI Always Free](https://docs.oracle.com/en-us/iaas/Content/FreeTier/freetier_topic-Always_Free_Resources.htm)

Render Free suspende la web tras 15 minutos sin tráfico y Neon Free incluye 100 CU-h mensuales por proyecto. Una base activa de 0,25 CU las 24 horas acumula unas 180 CU-h en 30 días. El worker y el scheduler hacen consultas continuas. Conserva cualquier proyecto Render/Neon existente y revisa sus datos y plan con los accesos del titular. [Render Free](https://render.com/docs/free) · [Neon](https://neon.com/docs/introduction/plans)

## Preparar el host

1. Instala [Docker y Compose](https://docs.docker.com/engine/install/ubuntu/), [Caddy](https://caddyserver.com/docs/install), Git, curl, openssl, age, rsync, SSH y util-linux. Habilita Docker y Caddy al arrancar. Configura rotación de logs, sincronización de reloj y monitoreo de disco.
2. Limita SSH al origen autorizado. Abre TCP 80/443 al público. PostgreSQL queda sin publicación y Apache escucha en loopback. Verifica las reglas efectivas de firewall y los puertos publicados por Docker.
3. Reserva el dominio y apunta su registro A a la VM. Publica AAAA solo si IPv6 funciona. Mantén los tokens DNS fuera del repositorio. Caddy usa sus directorios persistentes para renovación automática. [DuckDNS](https://www.duckdns.org/spec.jsp) · [HTTPS de Caddy](https://caddyserver.com/docs/automatic-https)
4. Prepara `/var/lib/smartaulaues` con permiso 0755 y propietario operador. Desde esa cuenta crea primero el marcador `touch /var/lib/smartaulaues/maintenance && chmod 644 /var/lib/smartaulaues/maintenance`. Adapta `docker/caddy/Caddyfile.example` al dominio y puerto, valida con `sudo caddy validate --config /etc/caddy/Caddyfile` y recarga Caddy. Comprueba que HTTPS devuelve 503 con `X-SmartAulaUES-Maintenance: 1`.
5. Instala el código revisado y con árbol limpio, por ejemplo en `/srv/smartaulaues`. Crea `/srv/smartaulaues-production.env` con modo 0600 desde `docker/production.env.example` solo en una instalación nueva. Define el SHA como etiqueta, la URL HTTPS y la base de producción. Genera en privado una contraseña con `openssl rand -hex 32` y una APP_KEY con el prefijo `base64:` más `openssl rand -base64 32`. Guarda ambas directamente en el archivo privado. Conserva APP_KEY para actualizaciones y restauraciones.
6. Identifica la IP que el proxy del host presenta realmente a Apache, normalmente la puerta de enlace de la red Docker. Inicia solo la BD dentro de ese proyecto con `docker compose --env-file /srv/smartaulaues-production.env -f compose.production.yaml up -d --wait db` y consulta la puerta de enlace con `docker network inspect smartaulaues-production_default --format '{{range .IPAM.Config}}{{.Gateway}}{{end}}'`. Usa la IP comprobada en `SMARTAULAUES_TRUSTED_PROXIES`. Verifica en aceptación que Laravel percibe HTTPS y que la API de dispositivo devuelve 401 al pedir estado sin Bearer; 403 puede indicar un proxy sin confianza. Conserva la red o vuelve a comprobar su IP si se recrea.

## Primera instalación

Desde el checkout en el host, ejecuta en una sesión SSH interactiva, reemplazando cada argumento de ejemplo:

```sh
bash operations/deploy.sh initial /srv/smartaulaues-production.env SHA_COMPLETO smartaulaues https://DOMINIO_REAL
```

El script valida archivo y SHA, fija la imagen compartida, exige la barrera HTTPS 503, confirma la base efectiva y un esquema vacío, ejecuta una vez la migración y llama a `app:create-coordinator`. Introduce correo y contraseña definitiva en el prompt privado. Comprueba exactamente una Coordinación activa y arranca los servicios. No ejecuta seeders. Si se interrumpe tras migrar, conserva la barrera. Verifica migraciones y cuenta existente antes de completar manualmente los pasos que falten bajo el mismo mantenimiento. No borres usuarios ni repitas el bootstrap sobre una base poblada.

Antes de abrir el proxy, toma y replica el primer respaldo con el procedimiento siguiente. Para publicar, verifica los servicios con `bash operations/check-production.sh ENV_FILE https://DOMINIO_REAL private`, retira el marcador y repite la comprobación con `public`. Si falla, repón el marcador de inmediato. Comprueba desde una red externa DNS, HTTP → HTTPS, certificado, login/logout, cookies Secure/HttpOnly y una API sin Bearer con 401. Registra solo conteos y estados de la base. La URL de entrega termina en `/login`.

## Actualización y respaldo

```sh
bash operations/deploy.sh update /srv/smartaulaues-production.env SHA_NUEVO smartaulaues https://DOMINIO_REAL \
  /srv/smartaulaues-backups age1RECEPTOR operador@host-respaldo /ruta/privada
```

El destino SSH de respaldo debe estar fuera de la VM, con acceso por llave y host previamente verificado. En actualización, el script comprueba acceso, construye la nueva imagen, activa la barrera, pone Laravel en mantenimiento, detiene worker y scheduler, cifra con el script B07, replica por SSH y comprueba los hashes remotos antes de migrar. Tras verificar la revisión nueva, actualiza la etiqueta en el entorno privado y sigue el mismo procedimiento de apertura. La barrera de Caddy permanece si falla la migración o se recrea Apache.

Para el respaldo inicial y diario, usa la misma barrera y coordínalo con despliegues usando `flock` sobre `/var/lib/smartaulaues/deploy.lock`. Activa `php artisan down` en app, deja drenar las solicitudes en vuelo y detén worker y scheduler. Ejecuta `bash operations/backup.sh ENV_FILE DESTINO_LOCAL age1RECEPTOR BASE --maintenance-confirmed`. Copia la carpeta completa fuera de la VM con rsync/SSH, verifica allí `sha256sum -c SHA256SUMS`, levanta Laravel, arranca worker y scheduler y verifica salud antes de reabrir. Programa una ventana diaria fuera de clase, supervisa la copia externa y define retención según consumo. El operador debe custodiar la identidad age fuera de la VM. Si falla una copia, conserva la anterior y la barrera hasta validar una recuperación segura.

Ensaya `operations/restore.sh` según [B07](B07_operacion_entrega.md) con proyecto `b07-restore-*`, base vacía `b07_restore_*`, puerto alternativo y APP_KEY del respaldo. Mide el tiempo y comprueba login, adjuntos, snapshots, reportes y terminal. En un incidente vuelve a la imagen anterior solo si el esquema lo permite. Si no, recupera primero en el destino aislado.

## Evidencia pendiente del destino

Verificación local de la preparación: imagen de producción Linux amd64 construida con
los assets Vite, Composer y requisitos de plataforma aprobados, y configuración Apache,
Compose y Caddy válidas. Pasaron las 48 pruebas JavaScript y la sintaxis Bash de los cuatro
scripts operativos. Las imágenes base publican manifiestos Linux ARM64. La compilación
y el arranque en la arquitectura de la VM se comprueban al disponer del destino.

Completa el [protocolo B07](B07_aceptacion_final.md) en un entorno de aceptación con datos ficticios. Comprueba que los reportes se generan y descargan, que los cierres respetan los permisos pendientes, que los contenedores y los datos sobreviven a reinicios, y que el respaldo externo se restaura. El estado healthy y `b07:status` son sondas operativas. Prueba el QR proyectado, el QR corto autenticado, cámara, GPS y relectura NFC con hardware real según la [guía de terminal](terminal-fisica.md). Registra región, arquitectura, modalidad gratuita de la cuenta, consumo y SHA.

Acceso externo pendiente: una VM gratuita en la cuenta OCI, acceso SSH por llave, dominio real y destino externo de copias. La inspección de los proyectos Render y Neon existentes requiere acceso de lectura a esas cuentas.
