# Incorporar v0.2.0 a tu repositorio e instalación

Base pública revisada: https://github.com/Josu1202/SmartAulaUES, commit
`446396cb61353fbd391faab0078babe908e43980` (v0.1.2). Este ZIP incluye fuentes y assets compilados,
no `.git`, `.env`, vendor, node_modules, logs ni datos. Conserva el historial de tu repositorio.
No hace push, crea PR ni despliega automáticamente.

## 1. Preparar la rama y copiar archivos

En tu checkout existente, revisa los cambios propios y crea una rama. Si main tiene cambios locales,
consérvalos en un commit/rama antes d
e continuar; no uses reset --hard.

```powershell
git status
git switch main
git pull --ff-only origin main
git switch -c feature/phase-1-academic-administration
```

Extrae `SmartAulaUES_v0.2.0_fase1.zip` en otra carpeta. Copia **el contenido de SmartAulaUES** dentro de tu
checkout, sustituyendo archivos del proyecto. Comprueba que README.md y compose.yaml quedaron en la
raíz y no en una segunda subcarpeta. Conserva `.git`, tu `.env`, almacenamiento privado y dependencias
locales. En el Explorador puede haber archivos de configuración ocultos: copia también los que incluye
la entrega. No muevas ni borres tu instalación anterior con sus volúmenes.

```powershell
git diff --stat
git status --short
```

## 2. Actualizar sin reinicializar los datos

Mantén APP_KEY, credenciales de BD y contraseñas de usuarios. No copiar .env.example encima de .env,
no ejecutar migrate:fresh ni docker compose down -v. En Docker:

```powershell
docker compose up -d db
docker compose build app
docker compose run --rm app composer install
docker compose run --rm node npm ci
docker compose run --rm node npm run build
docker compose run --rm app php artisan optimize:clear
docker compose run --rm app php artisan migrate
docker compose up -d --force-recreate app
```

Sin Docker: `composer install`, `npm ci`, `npm run build`, `php artisan optimize:clear`,
`php artisan migrate`; reinicia el servidor. No necesitas repetir el seeder.

Migración nueva: `2026_10_13_000100_make_user_emails_case_insensitive.php` crea índice único
`lower(email)`. Si alguien introdujo correos que solo difieren por mayúsculas, PostgreSQL rechazará
el índice: identifica primero las cuentas y corrige sus correos conservando IDs e historial. Consulta:

```sql
SELECT lower(email) AS correo, count(*) AS cuentas
FROM users GROUP BY lower(email) HAVING count(*) > 1;
```

Luego vuelve a ejecutar migrate. Las cuentas demo originales no presentan ese conflicto.
Para pgAdmin añade PGADMIN_PORT/PGADMIN_EMAIL/PGADMIN_PASSWORD según README; no rota DB_PASSWORD.
El proyecto Compose sigue llamándose `siaa` y conserva el volumen anterior.

## 3. Verificar y subir la rama

Abre http://localhost:8080, entra con tus credenciales existentes y recorre el flujo del README.
Crea `siaa_test` si aún no existe; usa exclusivamente esa BD para pruebas.

```powershell
docker compose run --rm app composer validate --strict
docker compose run --rm app composer check-platform-reqs
docker compose run --rm app vendor/bin/pint --test
docker compose run --rm app php artisan test --compact
docker compose run --rm node npm test
git add .
git diff --cached --stat
git commit -m "feat: fase 1 de gestion academica SmartAulaUES v0.2.0"
git push -u origin feature/phase-1-academic-administration
```

Antes del commit comprueba que .env, vendor, node_modules, datos privados y el ZIP no están preparados.
`public/build` y `RELEASE.json` están excluidos de Git: el workflow/instalación recompila los recursos;
el manifiesto pertenece a la entrega ZIP.
Abre un Pull Request hacia main desde GitHub. Revisión de otro miembro y CI correcto antes de integrar.
CI configurado, pero el ZIP no constituye evidencia de que GitHub Actions ya se ejecutó.

Después de integrar el PR, en main actualizado:

```powershell
git switch main
git pull --ff-only origin main
git tag -a v0.2.0 -m "SmartAulaUES v0.2.0 - fase 1"
git push origin v0.2.0
```

Si la etiqueta ya existe, verifica a qué commit pertenece y no la sobrescribas. Las etiquetas
v0.1.0/v0.1.1/v0.1.2 siguen como registro histórico; no se borran para incorporar este incremento.
