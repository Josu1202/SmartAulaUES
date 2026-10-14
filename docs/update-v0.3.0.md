# Actualizar a SmartAulaUES v0.3.0 · Fase 2

La entrega contiene fases 1 y 2 y puede actualizar v0.2.0 o la base v0.1.2 sin reiniciar datos.
Base local de desarrollo: v0.2.0, `47f48ca4c37efb33809bd8cdf29bc349b0bc5d65`.
La consulta del remoto todavía mostraba main/v0.1.2; verifica tu checkout actual antes de copiar.
Este ZIP no publica una rama ni sustituye el historial de GitHub.

## Conservar antes de copiar

| Elemento de tu instalación | Acción |
| --- | --- |
| .git | Conservar: contiene remoto, ramas e historial |
| .env y .env.testing | Conservar: credenciales y configuración local; ejemplos son solo referencia |
| APP_KEY y contraseñas de BD/usuarios | Mantener sus valores; no generar otra clave al actualizar |
| storage/app/private | Conservar evidencias/datos privados existentes |
| Volúmenes siaa_db / pgadmin_data | Conservar; no usar down -v |
| vendor y node_modules | Pueden conservarse localmente; sincronizar con install/npm ci y locks nuevos |
| public/build y cachés | Recompilar recursos y limpiar cachés; no son datos académicos |

Haz un respaldo de tu base antes de aplicar migraciones en la instalación compartida.
Extrae `SmartAulaUES_v0.3.0_fase2.zip` en otra carpeta. Copia el contenido de su carpeta
SmartAulaUES sobre la raíz de tu checkout, incluyendo archivos de configuración de la entrega.
README.md, artisan y compose.yaml deben quedar en la raíz, sin otra subcarpeta SmartAulaUES.
El ZIP no contiene .git, .env, dependencias, evidencias privadas ni una BD prellenada.

## Rama de trabajo

Guarda primero tus cambios propios en un commit/rama. No uses reset --hard ni fuerces el remoto.
Desde main limpio y actualizado:

```powershell
git status
git switch main
git pull --ff-only origin main
git switch -c feature/phase-2-attendance
```

Si fase 1 sigue en una rama sin integrar, usa esa rama como base de fase 2 y explica esa dependencia
en el PR. Si la rama de fase 2 ya existe, cambia a ella sin crearla de nuevo. Copia después las fuentes
del ZIP y revisa `git diff --stat` / `git status --short`. Conserva cambios propios de tu equipo;
una copia de ZIP no resuelve automáticamente conflictos con trabajo posterior.

## Instalar la actualización

En Docker, desde la raíz del proyecto:

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
`php artisan migrate` y reiniciar servidor. PHP debe permitir evidencia de 5 MB: upload_max_filesize
al menos 5M y post_max_size al menos 8M. Docker incorpora 6M/8M en docker/php/uploads.ini.
La actualización no necesita seed ni key:generate. No ejecutar migrate:fresh.

Las dos migraciones nuevas de fase 2 agregan años de estudiantes, formatos/períodos de formato,
relación del grupo/calendario y respuesta/HTTP/sesión de los eventos; conservan filas previas.
Los años de estudiantes anteriores quedan NULL (**Sin asignar**), sin inferencias automáticas.
Grupos/cómputos anteriores conservan sus fechas. Desde v0.1.2 también se aplica la migración v0.2.0
de correo único lower(email); consulta update-v0.2.0.md si había duplicados por mayúsculas.

Una restricción nueva permite solo una credencial NFC activa por usuario. Fase 1 no emitía etiquetas,
pero si agregaste credenciales manualmente y migrate falla, identifica duplicados antes de continuar:

```sql
SELECT user_id, count(*) FROM nfc_credentials
WHERE state = 'active' GROUP BY user_id HAVING count(*) > 1;
```

Conserva la etiqueta correcta, revoca las otras con revisión/auditoría del equipo y vuelve a migrar;
no borres resultados académicos para resolver el índice.

## Verificar y subir

Abre http://localhost:8080 con tus cuentas existentes. Recorre README: años/directorios, formato,
grupo, inscripción filtrada, clase propia, portal/manual/QR y permiso. Para NFC usa el servidor por
HTTPS accesible al Android, con APP_URL correcto; un túnel/deployment HTTPS no viene instalado en el ZIP.

La base exclusiva siaa_test debe existir. Las pruebas normales recrean tablas de esa base.
El script de carreras usa procesos independientes, requiere esquema migrado y limpia sus propios fixtures.

```powershell
docker compose run --rm app composer validate --strict
docker compose run --rm app composer check-platform-reqs
docker compose run --rm app vendor/bin/pint --test
docker compose run --rm app php artisan test --compact
docker compose run --rm app php tests/Concurrency/attendance-races.php --env=testing
docker compose run --rm node npm test
git add .
git diff --cached --stat
git commit -m "feat: fase 2 de SmartAulaUES v0.3.0"
git push -u origin feature/phase-2-attendance
```

Revisa que no se prepararon .env, dependencias, ZIP, archivos privados ni datos reales.
RELEASE.json y public/build son de la entrega, están excluidos de Git y se recompilan en CI.
Abre PR hacia main y solicita revisión de otro miembro. Comprueba CI y las pruebas físicas pendientes
en docs/test-results.md antes de considerar validada la instalación del equipo.

Después de integrar el PR:

```powershell
git switch main
git pull --ff-only origin main
git tag -a v0.3.0 -m "SmartAulaUES v0.3.0 - fase 2"
git push origin v0.3.0
```

Si la etiqueta ya existe, verifica su commit y conserva su identidad; no sobrescribas etiquetas compartidas.
La publicación de esta rama/etiqueta sigue siendo una operación separada de la entrega ZIP.
