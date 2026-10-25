# Entrega local de SmartAulaUES para revisión

Worktree `C:\Users\jozzz\Downloads\SmartAulaUES-A05`, rama
`feature/p3a-a05-integracion-final`, base exacta `6273a307f3411a5e1ba40c427153cdf39e545a0e`
(origin/main, PR #6 de A04). Versión **0.3.2** conservada. Es una actualización de SmartAulaUES,
sin commit, push, PR, merge, release o despliegue remoto.

## Uso y evidencia vigentes

**La guía única de comandos es [operacion-local-y-produccion.md](operacion-local-y-produccion.md).**
Sustituye el servidor iniciado manualmente con `docker exec`, los auxiliares A05 y el Compose
privado de la primera revisión. El Compose versionado inicia web, BD y scheduler normalmente.

URL: **http://127.0.0.1:8085/login**. [Cuentas y recorridos](escenarios-y-cuentas-locales.md):
52 cuentas naturales, contraseña privada recuperable y una BD local propia. Las suites usan
otra BD y no destruyen esta revisión. La nueva evidencia está en
[operacion-verificacion.md](operacion-verificacion.md), con [rendimiento](operacion-rendimiento.csv),
[casos PHP](operacion-pruebas-php.csv) e [inventario para commit futuro](operacion-inventario.csv).

La [verificación A05 anterior](a05-verificacion-final.md) conserva sus 268 pruebas/1952 aserciones,
44 JS, 37 comprobaciones nativas y 65 capturas como evidencia de aquella revisión; no describe
los servicios ni los datos que quedan activos ahora. Sus comandos privados y cuentas A05
fueron sustituidos para la revisión normal. Las evidencias originales se conservan.

## Funcionalidad integrada

- Finalización por horario, scheduler cada minuto, exclusión PostgreSQL y salud operativa.
  Sin ampliar ventanas o generar ausencias de sesiones nunca iniciadas. Pendientes bloquean
  el cierre; reaperturas conservan versiones y necesitan recierre manual.
- Año 1–5/Egresado explícito; filtros, CSV e historia conservada. No se matriculan egresados nuevos.
- CSV por pestaña/propietario/plazo, revalidado y atómico. Contraseña provisional obligatoria,
  revocación de sesiones y límite bcrypt seguro.
- NFC individual/lote con confirmación repetible; etiqueta compartida declarada con A02.
- Reasignación docente bajo locks, sin clase en curso o conflicto; autoría original conservada.
- Formularios independientes, filtros Actual/Historial, vencimiento y errores comprensibles,
  avisos aplicables y diagnóstico GET ligero sin cálculo colectivo de desactivación.
- Escenario relativo con acciones de dominio, cronología coherente, repetición segura y
  reconstrucción exclusivamente local verificada; sin una edición especial visible.
- Traducción completa, errores sin trazas/SQL y bitácora legible sin alterar su contenido.
- Inicio paginado y reintentos del scheduler acotados; volúmenes Linux y OPcache medidos.
- Compose normal, imagen Apache y configuración de producción separada. pgAdmin y túnel
  opcionales conservados; override móvil explícito sin editar `.env`.

[Contratos y decisiones](a05-decisiones.md), [análisis anterior](a05-analisis.md),
[propuesta de PR actualizada](a05-pr.md).

## Migración y preservación

`2026_10_24_000100_complete_a05_integration.php` agrega egreso explícito, contraseña
provisional, método `nfc_tag` e indicador operativo. No cambia migraciones compartidas ni
contraseñas previas. Años legados 6–10 detienen la migración hasta clasificación explícita.
El downgrade se niega a eliminar historia `nfc_tag`; usar recuperación compatible. No se
agregaron otras migraciones para la preparación operativa.

Checkout habitual, `.env`/APP_KEY, cambio previo `.env.example`, Microsoft/ y materiales privados
se preservan. No se migró ni reconstruyó su BD `siaa`. La nueva `smartaulaues_local` sí se preparó
con los comandos explícitos autorizados, respaldando la exploración anterior. `siaa_test`
es independiente y se verifica antes de las suites. No se publican secretos o evidencias privadas.

Actualizar instalaciones existentes requiere respaldo y migraciones incrementales, conservando
claves. La reconstrucción local no es el procedimiento de actualización del hosting. El regreso
a la carpeta habitual después de integrar la rama está en la guía; no se movió ni eliminó
el worktree con cambios pendientes.

## Aceptaciones externas

La evidencia distingue navegador real de NDEF emulado y reloj controlado de scheduler real.
La imagen Apache se ejercitó localmente; HTTP localhost no valida el TLS definitivo. Siguen
pendientes dominio/certificado/proxy, SMTP si se utiliza, restauración/capacidad del hosting,
NFC físico y geolocalización física A02. La etiqueta compartida no acredita lectura física ni
emulación de tarjeta. PDF/CSV de resultados, ranking y noticias mantienen el alcance de Persona 3.
