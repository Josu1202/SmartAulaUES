# Actualizar a SmartAulaUES v0.1.2

Esta versión incorpora el nombre SmartAulaUES y su icono propio. Conserva la corrección Docker v0.1.1.
No requiere migraciones SQL ni volver a sembrar o restablecer cuentas.

## Si ya tienes el repositorio local o un remoto GitHub

Extrae `SmartAulaUES_v0.1.2_base.zip` en una carpeta separada de tu instalación.
El directorio `SmartAulaUES` extraído incluye Git y las tres etiquetas. Importa la entrega desde
tu repositorio existente para conservar su remoto y tu configuración local. En PowerShell:

```powershell
git status
git fetch "C:/RUTA/DE/LA/ENTREGA/SmartAulaUES" tag v0.1.2
git merge --ff-only v0.1.2
```

Reemplaza la ruta por la carpeta extraída. El estado debe estar limpio antes de integrar.
Si hay cambios propios o Git no puede avanzar directamente, integra esos cambios en una rama;
no uses `reset --hard` ni sustituyas el directorio `.git` de tu repositorio existente.
La entrega comparte el historial de v0.1.0/v0.1.1 y no añade un repositorio remoto por su cuenta.

Si es tu primera instalación, usa directamente la carpeta de la entrega y el procedimiento README.

## Configuración y recursos

En tu `.env`, edita únicamente estos nombres:

```dotenv
APP_NAME=SmartAulaUES
MAIL_FROM_NAME=SmartAulaUES
VITE_APP_NAME=SmartAulaUES
```

Conserva tu APP_KEY, credenciales de PostgreSQL y DEMO_PASSWORD.
La zona horaria puede usar `SMARTAULAUES_DISPLAY_TIMEZONE`; se acepta la variable anterior.
El módulo de configuración anterior se reemplazó por `config/smartaulaues.php`.

Con Docker Desktop abierto, dentro de tu proyecto:

```powershell
docker compose build app
docker compose run --rm app composer install
docker compose run --rm node npm ci
docker compose run --rm node npm run build
docker compose run --rm app php artisan config:clear
docker compose run --rm app php artisan view:clear
docker compose up -d --force-recreate app
```

La aplicación conserva el proyecto Compose y el volumen PostgreSQL originales para reutilizar sus datos.
Se siguen utilizando los correos demo ya documentados en README, con sus contraseñas actuales.
Abre http://localhost:8080 y vuelve a iniciar sesión si cambió el nombre de tu cookie.

Sin Docker: `composer install`, `npm ci`, `npm run build`, `php artisan config:clear`,
`php artisan view:clear`; reinicia tu servidor PHP.

## GitHub

El nombre sugerido del repositorio nuevo es `smartaulaues`. Si ya tienes un remoto, conserva su URL
hasta que decidas renombrarlo en GitHub. Después de verificar la actualización, sube el incremento:

```powershell
git push origin main
git push origin v0.1.2
```

El ZIP es una entrega descargable. El repositorio contiene los archivos y su historial, sin `.env`,
dependencias descargadas, claves privadas ni datos reales.
