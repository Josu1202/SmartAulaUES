# Actualizar a SmartAulaUES v0.3.1 · Parche de fase 2

Este parche corrige el CI fallido de v0.3.0 compartido por el usuario el 14/10/2026:
`array_keys(): Argument #1 ($array) must be of type array, string given` al abrir la bitácora.
Sin .env, Laravel usaba en como idioma/fallback; no había traducciones audit en ese idioma.
También se reprodujeron las advertencias de lectura de .env ausente.

Los filtros ahora usan AuditLog::ENTITY_TYPES, sin depender de traducciones. Las etiquetas de
bitácora se solicitan en español, como el resto de esta interfaz; locale/fallback por defecto son es.
CI prepara .env.testing desde su ejemplo antes de Composer/pruebas y falla ante warnings.
Las regresiones fuerzan en/zz y fallback en para comprobar que el arreglo no depende de configurar CI en es.

## Archivos cambiados respecto de v0.3.0

| Archivo | Cambio |
| --- | --- |
| app/Models/AuditLog.php | Códigos válidos de entidades de auditoría |
| app/Http/Controllers/Admin/AuditController.php | Validación independiente del idioma; etiquetas del filtro |
| resources/views/admin/audit/index.blade.php | Etiquetas españolas y filtro recibido del controlador |
| config/app.php | Español por defecto para locale y fallback |
| .github/workflows/ci.yml | Preparación de entorno de pruebas; warnings visibles y tratados como fallos |
| tests/Feature/AuditLocaleTest.php | Render/filtros con es/en/zz; rechazo de etiquetas como códigos y valores desconocidos |
| VERSION, config/smartaulaues.php, package.json, package-lock.json | Versión 0.3.1; mismas dependencias |
| README.md, AGENTS.md, CHANGELOG.md, docs/project-state.md, docs/test-results.md, docs/verification.json, docs/update-v0.3.1.md | Contexto, fallo de CI, pruebas reales y actualización |

## Aplicar sobre tu repositorio

En la carpeta original que ya contiene Git:

```powershell
git status
git switch feature/phase-2-attendance
git pull --ff-only origin feature/phase-2-attendance
```

Conserva cambios propios pendientes antes de actualizar. Extrae SmartAulaUES_v0.3.1_fase2.zip
en otra carpeta y copia el contenido de SmartAulaUES sobre la raíz del checkout. README.md,
artisan y compose.yaml deben quedar directamente en la raíz del proyecto.
Puedes copiar únicamente los archivos de la tabla si ya tienes toda v0.3.0.
Incluye el nuevo test, la guía y la carpeta .github, que puede estar oculta en el explorador.

Conserva .git, .env, .env.testing propio, APP_KEY, storage/app/private y los volúmenes de datos.
El ZIP no contiene tus configuraciones ni evidencias. No se necesita cambiar dependencias,
generar otra clave, sembrar datos ni aplicar migraciones si ya estás en v0.3.0.
Desde versiones anteriores sigue primero docs/update-v0.3.0.md para instalar sus migraciones.

Limpia las vistas/configuraciones compiladas de tu instalación:

```powershell
docker compose exec app php artisan optimize:clear
```

Sin Docker: php artisan optimize:clear. Reinicia el proceso de aplicación si tu despliegue lo requiere.

## Subir la corrección a la misma rama

```powershell
git add .
git diff --cached --stat
git commit -m "fix: bitacora y entorno de pruebas en CI v0.3.1"
git push origin feature/phase-2-attendance
```

Revisa que solo se prepararon fuentes/documentación; no .env, dependencias ni archivos privados.
RELEASE.json, public/build y dist son de la entrega y están excluidos de Git.
Si el PR ya está abierto, se actualiza con este push y GitHub vuelve a ejecutar sus comprobaciones.
Si no existe, ábrelo hacia main: https://github.com/Josu1202/SmartAulaUES/compare/main...feature/phase-2-attendance?expand=1
Como fase 1 aún no está integrada en main, este PR conserva también esa base académica.

No crear otra rama de fase 2 ni reemplazar etiquetas compartidas para resolver este fallo.
Tras CI satisfactorio, revisión e integración, crea v0.3.1 sobre el commit integrado de main.

## Verificación de la entrega

108 casos / 610 aserciones correctas, ejecutados individualmente mediante PDO a PostgreSQL 17.5
PGlite, con locale/fallback en, sin .env y con .env.testing preparado como CI. Se activó
--fail-on-warning: cero advertencias PHPUnit. JavaScript: 14/14. Composer, Pint, Vite y Blade correctos.
La bitácora con datos y sus filtros se comprueba además con es/en/zz y fallback en.

Esta evidencia no certifica una nueva ejecución de GitHub Actions ni carreras con PostgreSQL nativo.
La nueva ejecución de CI queda pendiente de tu push. No se repitió el recorrido NFC físico;
la fase 3 sigue pendiente. Detalles e histórico: docs/test-results.md y docs/verification.json.
