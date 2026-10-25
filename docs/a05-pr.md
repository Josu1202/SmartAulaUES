# Propuesta de PR (no creada)

**Título:** Integrar A05 y la operación reproducible de SmartAulaUES

**Base:** `main`, `6273a307f3411a5e1ba40c427153cdf39e545a0e` (A01–A04 integrados).
**Rama:** `feature/p3a-a05-integracion-final`. Versión 0.3.2 conservada, sin release.

## Cambio y resultado

Una clase iniciada podía permanecer en curso después de su horario; las revisiones CSV
podían mezclarse entre pestañas y el contenedor de revisión perdía el servidor al reiniciarse.
A05 finaliza las clases al alcanzar `ends_at` UTC, mantiene cada revisión CSV independiente
y arranca web y scheduler desde Compose, con comprobaciones de salud y recuperación del proceso.

- Integra años 1–5 y Egresado explícito, contraseñas provisionales con cambio obligatorio,
  CSV atómico revalidado bajo locks, emisión NFC secuencial e idempotente, etiqueta compartida,
  reasignación docente y formularios independientes. Conserva autorización, privacidad del
  estudiante, auditoría, reaperturas y versiones históricas. A02 geográfico conserva sus límites.
- Mantiene la ventana `[starts_at, ends_at)`, replay antes de comprobar vencimiento/bloqueo
  y registro único. Una sesión nunca iniciada no genera ausencias artificiales; los permisos
  pendientes bloquean el cierre y las reaperturas requieren recierre manual.
- Añade `ExplorationSeeder`: 52 cuentas ficticias con nombres naturales y correos `example.test`,
  cinco ciclos, 16 grupos, matrículas, sesiones, permisos y resultados conservados válidos.
  Fechas relativas y repetición sin sobrescribir cambios. La contraseña se elige o genera
  privadamente y se conserva cifrada; no hay credencial predeterminada publicada.
- Completa traducciones, validación y errores HTML/JSON comprensibles; presenta campos y fechas
  de bitácora sin reescribir los registros. Retira textos internos visibles. Inicio pagina
  grupos y conserva los totales; el scheduler evita el cálculo colectivo cuando un permiso
  pendiente ya impide el cierre automático, manteniendo la revisión manual completa.
- Separa Compose local, pruebas desechables y producción; incluye Dockerfiles PHP 8.4,
  PostgreSQL 17, Node 24, OPcache, volúmenes Linux, scheduler supervisado, salud web/BD/esquema/assets,
  imagen Apache, ejemplo de proxy Nginx y configuración de producción con secretos externos.
  Web y scheduler no migran ni siembran datos al arrancar.
- Actualiza únicamente Symfony HTTP Foundation/YAML a versiones compatibles 7.4.21 en
  `composer.lock`; no añade paquetes. Retira tres enums duplicados vacíos sin referencias.

### Migración y actualización

Migración incremental: `2026_10_24_000100_complete_a05_integration.php`. Añade `graduated`,
`must_change_password`, el método `nfc_tag`, restricciones y `operation_runs`.
Los años legados 6–10 detienen la migración: deben clasificarse expresamente, sin inferir egreso.
Egresado conserva inscripciones históricas y no recibe nuevas. El rollback rechaza datos
`nfc_tag` incompatibles con la restricción anterior; no elimina esos registros para forzarlo.

Respaldar BD y archivos privados, conservar APP_KEY y `.env`, detener el scheduler durante
la actualización y aplicar `migrate --force` explícitamente. Actualizar `vendor` con
`composer install` aunque se reconstruya la imagen: su volumen persistente no se sustituye
al cambiar el lock. Compilar assets y comprobar salud web y heartbeat después.
La [guía de operación](operacion-local-y-produccion.md) contiene las órdenes completas de
instalación, actualización, diagnóstico, pruebas y producción.

La instalación habitual no se migró ni reconstruyó. La BD local ficticia tiene reconstrucción
protegida por nombre/conexiones y confirmación explícita; su preparación anterior está respaldada.
Preparar esta entrega no ejecuta reconstrucciones. En producción, la primera Coordinación se
crea mediante un comando explícito con contraseña elegida, sin seeder automático.

## Validación

Resultados locales ya ejecutados sobre este código, con PostgreSQL nativo aislado
`testing/siaa_test` y comprobación efectiva de lectura/escritura antes de las suites:

- **288 pruebas PHP / 4.229 aserciones**, sin warnings; **44 pruebas JS** y **37 comprobaciones
  concurrentes nativas** secuenciales. Sonda de actualización incremental aprobada.
- Composer validate estricto, requisitos de plataforma y auditoría sin vulnerabilidades;
  Pint completo sobre 208 archivos PHP, compilación Vite y `git diff --check` aprobados.
- Edge: 85 páginas y 266 comprobaciones; perfiles y anchos 1440/390/320, validación real de
  formularios, privacidad y ausencia de errores JS sin manejar. El [índice visual](operacion-evidencias-visuales.csv)
  identifica las capturas; las imágenes privadas se conservan fuera de Git.
- Comparación del mismo código/PHP/datos en Windows: mediana HTTP de Inicio **4.100,2 → 461,7 ms**
  y detalle de grupo **5.197,2 → 801,1 ms**, con volúmenes Linux y OPcache. Son mediciones locales;
  el [CSV](operacion-rendimiento.csv) y el [informe](operacion-verificacion.md) separan kernel,
  SQL, vistas, HTTP, primeras peticiones y contención. El reintento de cierre bloqueado pasa
  de 29 a 25 consultas; la comparación de infraestructura del scheduler no acredita mejora SQL.
- Scheduler real: sesión finalizada cuatro segundos después de su fin, una auditoría y
  15 resultados. SIGKILL, reinicio web, stop/start, persistencia y replay verificados sin duplicar.
- Imagen Apache comprobada localmente con `APP_ENV=production`: login, assets, BD, cookies,
  scheduler, reinicios y ausencia de seed automático. La prueba HTTP en localhost no acredita TLS.

Trazabilidad: [informe operativo final](operacion-verificacion.md),
[casos PHP](operacion-pruebas-php.csv), [inventario actual](operacion-inventario.csv) y
[cuentas/escenarios](escenarios-y-cuentas-locales.md). La [verificación A05 anterior](a05-verificacion-final.md)
se conserva identificada como histórica; sus cifras no sustituyen las finales anteriores.

## Alcance pendiente

No están certificados CI remoto, hosting, dominio/TLS/proxy definitivo, SMTP, backups y
restauración en el servidor de destino, ni capacidad productiva. El Compose y los ejemplos
permiten preparar esa operación; requieren secretos externos y comprobación en el entorno real.
NFC/NDEF emulado y ubicación probada localmente no acreditan NFC/GPS físicos.

PDF/CSV de resultados, ranking y noticias siguen pendientes de Persona 3; no se presentan
como funciones terminadas. No se amplían las ventanas de registro ni los límites geográficos.
Esta preparación deja los archivos revisados en staging para revisión del usuario;
no crea commit, push, PR, merge, release ni despliegue remoto.
