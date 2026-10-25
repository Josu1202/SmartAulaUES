# SmartAulaUES desde el celular por HTTPS · v0.3.2

**Compose actual:** utilizar el override versionado `compose.mobile.yaml` y los comandos de
[la guía de operación](operacion-local-y-produccion.md#8-diagnóstico-local). Editar solo `.env`
no sustituye las variables fijadas por el Compose normalizado. Las instrucciones inferiores
conservan la explicación anterior; no se generó una URL pública en esta revisión.

Para la rama local A02, aplicar antes su migración incremental y recompilar recursos:
[guía A02 y casos físicos](a02-geographic-access.md). Los pasos de HTTPS de este documento se
reutilizan; las instrucciones históricas de commit/push de abajo no forman parte de la entrega A02.

Para probar las etiquetas NFC se usa un Quick Tunnel de Cloudflare hacia la aplicación Laravel
que ya corre en tu computadora. El celular usa las mismas cuentas, PostgreSQL y datos locales.
No se crea otra aplicación ni otra base. El enlace temporal no requiere cuenta ni dominio.
Tu computadora, Docker, la aplicación y el túnel deben permanecer encendidos y con Internet.

El servicio tunnel está en el perfil mobile; no se inicia en el arranque normal del proyecto.
Su imagen oficial cloudflare/cloudflared:latest se usa únicamente para estas pruebas. Un servidor
definitivo requiere otro proceso de despliegue y una imagen/versión fijada; sigue pendiente en fase 3.

## 1. Copiar la actualización

main ya contiene v0.3.1 (comprobado el 14/10/2026). Guarda tus cambios propios antes de actualizar
y crea una rama para esta mejora:

```powershell
git status
git switch main
git pull --ff-only origin main
git switch -c feature/mobile-https
```

Si ya existe esa rama, usa git switch feature/mobile-https. No sobrescribas trabajo posterior del equipo
al copiar el ZIP: compara los archivos si tu checkout ha recibido otros cambios.

Si tienes v0.3.1, descarga SmartAulaUES_v0.3.2_fase2.zip y copia el contenido de su carpeta
SmartAulaUES sobre la raíz de tu repositorio. Conserva .git, .env, APP_KEY, evidencias privadas
y los volúmenes. No hay migraciones ni dependencias nuevas desde v0.3.1.
La entrega conserva el arreglo de la bitácora y CI de v0.3.1.

Los archivos de funcionamiento añadidos/cambiados son:

- compose.yaml: servicio tunnel opcional conectado a http://app:8080.
- app/Http/Middleware/TrustDeploymentProxies.php, bootstrap/app.php y config/smartaulaues.php:
  reconocimiento configurable del proxy y de HTTPS, resuelto cuando la configuración ya está cargada.
- .env.example: nueva variable SMARTAULAUES_TRUSTED_PROXIES; tu .env existente se conserva.
- tests/Feature/HttpsProxyTest.php: URLs/redirects/cookie, proxies/IP y rechazo de encabezados no confiables.

VERSION, paquetes npm, README, AGENTS, CHANGELOG y documentos de estado/evidencia registran v0.3.2.
scripts/package.php registra la base pública main/v0.3.1 en el manifiesto del ZIP.
Desde v0.3.0 también recibes el parche de CI. Desde v0.2.0 o anterior, aplica primero los pasos de
docs/update-v0.3.0.md para las migraciones incrementales; no recrees la base.

## 2. Arrancar la aplicación y obtener el enlace

En PowerShell, desde la carpeta que contiene compose.yaml, deja de ejecutar npm run dev si estaba
abierto: el teléfono usará los recursos compilados. Ejecuta:

```powershell
Remove-Item public/hot -ErrorAction SilentlyContinue
docker compose up -d app
docker compose run --rm node npm run build
docker compose --profile mobile up -d tunnel
docker compose --profile mobile logs --tail 60 tunnel
```

En la salida busca el enlace https://...trycloudflare.com dentro del aviso del Quick Tunnel.
Es una URL real distinta para cada arranque nuevo. Si todavía no aparece, repite el comando de logs.
El túnel se conecta al puerto 8080 interno de app, así que funciona aunque APP_PORT local sea 8090 u otro.
En el celular no se usa localhost: se usa ese enlace completo.

## 3. Ajustar tu .env a la URL real

Edita únicamente estos valores existentes, sin duplicarlos; añade la variable nueva:

```dotenv
APP_URL=https://EL_SUBDOMINIO_QUE_APARECIO.trycloudflare.com
APP_DEBUG=false
SESSION_SECURE_COOKIE=true
SMARTAULAUES_TRUSTED_PROXIES=*
```

Reemplaza APP_URL por la URL exacta del paso anterior, sin /login al final. Conserva tu APP_KEY,
DB_PASSWORD, demás configuración y SESSION_DOMAIN vacío/sin definir. Un dominio de cookie antiguo
como localhost impediría iniciar sesión en la URL del túnel.

El comodín de proxies es para este perfil de pruebas: compose.yaml conserva app en 127.0.0.1 y el
túnel en su red Docker. No publicar app directamente en 0.0.0.0 mientras uses ese comodín.
Para un servidor posterior, configura las IP/CIDR reales de tus proxies separados por comas.
Laravel reconoce X-Forwarded-Proto y X-Forwarded-For; no usa X-Forwarded-Host/Port para cambiar el dominio.

Aplica la configuración y reinicia solo la aplicación:

```powershell
docker compose exec app php artisan optimize:clear
docker compose restart app
```

El reinicio es necesario porque artisan serve usa --no-reload para preservar la dirección de PostgreSQL.
El túnel permanece activo. Desde ahora entra por la URL HTTPS también en la computadora: con cookies
Secure, el login por http://localhost deja de ser la ruta adecuada durante estas pruebas.

## 4. Probar el NFC desde el celular

Abre la URL directamente en Chrome para Android, activa NFC y mantén el celular desbloqueado.

1. Inicia sesión como coordinación con tu cuenta existente.
2. Credenciales NFC: elige un estudiante activo, emite el alta pendiente, pulsa Escribir etiqueta y
   acerca el chip a la zona NFC del teléfono. Acepta el permiso del navegador.
3. Lee la misma etiqueta para activar la credencial antes de que venza el alta de 10 minutos.
4. Entra como docente; usa un grupo propio con estudiante inscrito y calendario/cómputo vigente.
5. Programa una clase que incluya la hora actual, iníciala, abre el registro con NFC habilitado y pulsa
   Iniciar lector. Acerca la etiqueta; espera la confirmación del servidor y el cambio en la lista.

El lector operativo está dentro de la clase; la terminal de diagnóstico es solo diagnóstico.
Las etiquetas deben admitir NDEF y escritura. HTTPS no añade compatibilidad a un chip que no la tenga.
QR/enlace/manual también pueden probarse desde esta URL. El teléfono necesita conexión para confirmar.
Detalles del registro, reintentos y plan de aceptación física: docs/nfc-contract.md.

## 5. Cerrar las pruebas y volver a localhost

```powershell
docker compose --profile mobile stop tunnel
```

Conserva tus valores anteriores de .env y restáuralos al terminar. En una instalación local típica:

```dotenv
APP_URL=http://localhost:8080
SESSION_SECURE_COOKIE=false
SMARTAULAUES_TRUSTED_PROXIES=
```

Si usabas otro APP_PORT, utiliza ese puerto. Restaura también tu valor previo de APP_DEBUG.
Ejecuta php artisan optimize:clear dentro del contenedor y docker compose restart app como en el paso 3.
El enlace público deja de funcionar cuando detienes el túnel. Al iniciarlo de nuevo se obtiene otra URL:
actualiza APP_URL y reinicia app antes de abrir nuevas ventanas QR/enlace.

## Si aparece un problema

| Resultado | Comprobar |
| --- | --- |
| 502 / aplicación inaccesible | docker compose ps; app y db deben estar funcionando. Revisa docker compose logs --tail 60 app |
| Página sin diseño o recursos HTTP | Detener Vite, quitar public/hot, recompilar; copiar todos los archivos nuevos, configurar proxy, limpiar cachés y reiniciar app |
| Login que regresa al formulario / 419 | Usar la misma URL HTTPS, cookies habilitadas, SESSION_DOMAIN sin localhost; limpiar cachés/reiniciar y recargar /login |
| NFC no disponible | Chrome Android con NFC activo, enlace HTTPS directo, permiso concedido y pantalla desbloqueada |
| Credencial/registro rechazados | Alta confirmada, estudiante inscrito, docente asignado, clase en curso y portal/lector vigentes |
| Sin enlace en logs | Verificar Internet; el proceso cloudflared debe conseguir una conexión. Revisar docker compose --profile mobile logs tunnel |

Comparte el enlace con los miembros que harán las pruebas. La aplicación conserva sus permisos y login,
pero la URL del túnel es accesible desde Internet. No se publica PostgreSQL ni pgAdmin.

## Versionar la configuración de soporte

Los cambios personales de .env y la URL temporal no se suben. Las fuentes del perfil, middleware,
documentación y pruebas sí quedan en la rama:

```powershell
git add .
git diff --cached --stat
git commit -m "feat: HTTPS movil de pruebas v0.3.2"
git push -u origin feature/mobile-https
```

Abre PR hacia main; integra tras revisión/CI y crea la etiqueta v0.3.2 sobre el commit integrado.
El túnel funciona con tu checkout local antes de integrar el PR. No reemplaza el servidor definitivo.

## Evidencia y referencias

Pruebas de proxy, batería y recorrido por TLS local constan en docs/test-results.md/verification.json.
No se afirma que se haya iniciado el túnel de tu computadora ni probado chips físicos desde este entorno.

- Cloudflare, Quick Tunnels: https://developers.cloudflare.com/tunnel/get-started/quick-tunnels/
- Laravel 13, proxies: https://laravel.com/docs/13.x/requests#configuring-trusted-proxies
- Chrome, Web NFC: https://developer.chrome.com/docs/capabilities/nfc
# Operación vigente del túnel con el Compose normalizado

La configuración actual conserva el perfil `mobile` y añade el override versionado
`compose.mobile.yaml`. Los pasos vigentes de PowerShell, URL real, cookies, proxy y regreso
a localhost están en [la guía única de operación](operacion-local-y-produccion.md#8-diagnóstico-local).
Desde esta revisión las variables fijas del Compose base se cambian mediante ese override;
editar solo `.env` ya no sustituye APP_URL/SESSION_SECURE_COOKIE de esos servicios.
El resto de este documento conserva la explicación y los ensayos anteriores de v0.3.2.
