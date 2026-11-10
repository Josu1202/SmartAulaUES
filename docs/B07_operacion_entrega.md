# B07 — Operación, continuidad y entrega de SmartAulaUES

Estado: **código preparado para revisión**. Esta rama NO representa un despliegue, HTTPS definitivo ni una restauración ensayada. Las comprobaciones de hosting y hardware requieren la participación del equipo.

## Contratos y alcance

B07 no cambia cálculos, snapshots, acciones de cierre, roles, rutas de evidencias ni contratos del Integrante 2. Consume `attendance:process-closures`, `attendance:scheduler-status`, `queue:work`, `jobs`, `failed_jobs`, `operation_runs` y el almacenamiento privado existentes. Ninguna migración nueva es necesaria. El `VERSION` histórico no cambia por el PR.

## Arquitectura de producción

`compose.production.yaml` utiliza PostgreSQL 17 persistente y servicios independientes: `app` (Apache/Blade), `db`, `scheduler` (`schedule:work`) y `worker` (cola PostgreSQL). Todos comparten las variables de aplicación, APP_KEY, acceso a BD y el volumen **privado** `storage/app`. `worker` escucha las colas `reports,default`: PDFs/CSV de B02 van a `reports`; noticias y cierres de B03 a `default`.

- `QUEUE_CONNECTION=database` y `DB_QUEUE_RETRY_AFTER=180` en producción. El timeout del worker es 90 segundos (reintento 180 > 90), tres intentos y reciclaje controlado tras 3600 s. Los jobs pueden imponer límites más cortos, como PDF.
- `restart: unless-stopped` para worker y scheduler. El healthcheck del worker comprueba proceso vivo; **no** certifica que todos los jobs hayan terminado. El scheduler conserva su healthcheck de ejecución reciente.
- Web escucha exclusivamente en `127.0.0.1:${PRODUCTION_HTTP_PORT}`; PostgreSQL no publica puerto al host y no existe pgAdmin en producción. Un reverse proxy real termina TLS. `PRODUCTION_APP_URL` debe ser HTTPS real, con IP/CIDR del proxy de confianza explícitas (nunca `*` en producción).
- Datos privados, reportes, adjuntos y APP_KEY deben conservarse al actualizar; no usar `storage:link` ni copias públicas. `SESSION_SECURE_COOKIE=true`, `APP_DEBUG=false`, `DEMO_SEED=false`.
- Los certificados, DNS, alojamiento, monitoreo y destinatarios de alarmas son **decisiones externas del equipo**; la configuración no acredita su puesta en servicio.

## Instalación nueva (servidor autorizado, sin datos previos)

1. Seleccionar hosting, dominio HTTPS y proxy reversible. Crear almacenamiento persistente cifrado, accesos mínimos, un mecanismo de secretos y un usuario operador. Mantener fuera de Git el archivo privado de variables de producción; partir de `docker/production.env.example` y **sustituir valores REQUIRED**.
2. `docker compose --env-file /ruta/privada/prod.env -f compose.production.yaml config --quiet` verifica configuración y sustituciones. NO muestra secretos; evite `config` sin `--quiet` en capturas.
3. `docker compose --env-file /ruta/privada/prod.env -f compose.production.yaml build app scheduler worker`. Imagen Apache con JS precompilado. Comprobar etiquetas e imagen de PHP 8.4 y PostgreSQL 17.
4. `docker compose --env-file /ruta/privada/prod.env -f compose.production.yaml up -d db`; esperar `healthy`. Ejecutar **una sola vez** las migraciones incrementales: `docker compose --env-file /ruta/privada/prod.env -f compose.production.yaml run --rm --no-deps app php artisan migrate --force`. No usar `migrate:fresh`, `down -v` o regenerar `APP_KEY` en actualizaciones.
5. Crear la primera coordinación por el procedimiento existente (`php artisan app:create-coordinator`; utilice sus opciones/entrada privada según `php artisan help app:create-coordinator`). Arrancar `up -d app scheduler worker` y verificar healthchecks y URL HTTPS desde el navegador.
6. `docker compose --env-file /ruta/privada/prod.env -f compose.production.yaml exec -T app php artisan b07:status --require-scheduler`. La prueba exigirá scheduler reciente y colas sin fallos. Inspeccionar `docker compose ... ps` y logs limitados, sin publicarlos con datos privados.

## Actualización de instalación existente

- Aprobar commit y respaldar **BD, storage privado y secretos** antes de una actualización; usar la APP_KEY anterior. Preparar ventana, avisar usuarios, detener nuevas escrituras y conservar el rollback de **código**. Un rollback de esquema no está garantizado; si una migración modifica datos, la restauración verificada es el plan de recuperación.
- Detener scheduler y worker y colocar la app en mantenimiento con `php artisan down`; evitar nuevos despachos. Crear copia cifrada coherente usando `operations/backup.sh` (Linux del servidor). **No** borrar volúmenes.
- Construir nuevas imágenes; ejecutar `php artisan migrate --force` una sola vez con la app en mantenimiento; luego `php artisan optimize:clear`, `php artisan up`, reiniciar app, scheduler y worker. Verificar `/health/ready`, `attendance:scheduler-status`, `b07:status --require-scheduler`, generar y descargar un PDF/CSV privado; vigilar `failed_jobs`.
- Reiniciar workers después del despliegue: trabajadores de larga vida no cargan nuevas clases hasta reiniciarse. En este Compose, la recreación del servicio `worker` evita código PHP obsoleto.
- No entregar ni cambiar credenciales por mensajes de chat; responsable y ventana de mantenimiento se acuerdan antes de producción.

## Verificaciones operativas

```bash
# Ejecutar dentro del contenedor app, con PostgreSQL de la instancia correcta
php artisan schedule:list
php artisan attendance:scheduler-status --max-age=180
php artisan b07:status --require-scheduler --max-pending=250 --max-oldest-seconds=900 --max-failed=0
php artisan queue:failed
```

Los dos últimos comandos no muestran datos personales de los mensajes. `b07:status` devuelve código distinto de cero cuando hay trabajos demorados/fallidos, cola sin recursos suficientes o scheduler sin pase reciente. Diseñar una alerta externa que ejecute el comando y avise a un operador; no se configura un servicio de correo ni webhooks arbitrarios desde el repositorio. Consultar logs privados para diagnóstico de jobs, no reintentar todos a ciegas: revisar si son idempotentes, permisos y la vigencia de las versiones. Comprobar recuperación de un cierre vencido y de PDF fallido en un **ambiente aislado**.

## Respaldo cifrado y restauración

En un host Linux con Docker Compose, `age`, bash y sha256sum, el programa `operations/backup.sh` produce tres archivos cifrados con clave pública `age`: dump `pg_dump -Fc` de PostgreSQL, archivo de `storage/app` y copia privada de variables (`APP_KEY`, contraseñas) más manifiesto y hashes. No escribe dump en claro sobre el host. Debe almacenarse y replicarse **fuera del servidor**, con control de accesos y llave privada separada.

**Preparación manual obligatoria del respaldo coherente:** detener entradas por proxy, ejecutar `php artisan down`, detener `worker` y `scheduler`, comprobar que no haya operaciones abiertas, después ejecutar con confirmación exacta de BD:

```bash
operations/backup.sh /ruta/privada/prod.env /respaldo/fuera-del-repo age1CLAVE_PUBLICA_REAL smartaulaues --maintenance-confirmed
```

El script comprueba mantenimiento, servicios detenidos y nombre efectivo de la base. Si falla, no debe marcarse como backup válido. Finalmente recuperar la app con `artisan up` y `up -d scheduler worker`, y verificar salud. Respaldar con frecuencia de al menos una vez al día como propuesta sujeta a aceptación; definir retención (por ejemplo 7 diarios y 4 semanales) según política institucional y capacidad real. Ni el RPO de 24 h ni el RTO de 4 h se consideran cumplidos sin ensayo cronometrado.

**Restauración — únicamente entorno vacío aislado:** preparar en otro proyecto Compose `COMPOSE_PROJECT_NAME=b07-restore-...` con BD `b07_restore_...`, su propio almacenamiento y configuración privada cuya APP_KEY coincida con el respaldo, sin exposición pública. Recuperar archivos `.age` desde copia **fuera del host** y mantener la identidad privada fuera del repo. Construir la imagen `app` antes de restaurar. Ejecutar:

```bash
operations/restore.sh /ruta/privada/restore.env /ruta/backup-cifrado /ruta/privada/age-identity.txt b07_restore_verificacion
```

El script valida `SHA256SUMS`, exige nombre de proyecto de restauración y BD vacía, descifra hacia PostgreSQL y volumen privado sin generar archivos secretos intermedios. **No** inicia automáticamente la app ni prueba login. Validar manualmente login, historial, permisos y evidencia privada, PDF de versión cerrada y estado de scheduler/worker; medir tiempos, hash de muestra y diferencias observadas. Nunca ejecutar el restaurador contra la base habitual o producción.

## Requisitos externos que continúan abiertos

- URL HTTPS estable, certificado válido y proxy confiable del hosting real, con cookies Secure y acceso móvil desde red distinta a la del servidor.
- Prueba Android/Chrome y etiquetas NFC reales; ensayos GPS con precisión y límites documentados, junto al Integrante 2. No confundir NDEF simulado con hardware certificado.
- Simular caída del worker, reintento y recuperación de cierre vencido, con efectos observados y evidencia.
- Restaurar una copia cifrada en entorno limpio y documentar tiempos; verificar acceso a datos y archivos privados. Definir custodio de llaves, frecuencia de backups y responsable operativo.

Consulte `docs/B07_manual_usuarios.md` y `docs/B07_aceptacion_final.md` para los recorridos funcionales y el registro de evidencia. **Una rama validada por CI no equivale a una instalación de producción verificada.**
