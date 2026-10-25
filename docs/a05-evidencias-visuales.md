# A05 · índice de revisión en Edge

Edge **156.0.4314.42** real, modo headless con Playwright externo al repositorio, HTTP local
en 127.0.0.1:8085, APP_ENV=local y datos sintéticos de siaa_test. Esto activa CSRF real;
no se confunde el navegador con las pruebas HTTP de Laravel en testing.

Resultado: **74 comprobaciones**, **64 medidas de ancho**, **65 PNG distintos**, cero errores
JavaScript capturados. Anchuras 1440/390/320 px. Ninguna medida tuvo ancho global mayor que
el viewport; las tablas extensas usan desplazamiento horizontal interno. Las 55 medidas del
primer recorrido también comprobaron asociación de labels/aria-label: ninguna entrada visible
inventariada carecía de nombre. Es accesibilidad básica, no una auditoría WCAG completa.

El índice [a05-evidencias-visuales.csv](a05-evidencias-visuales.csv) identifica ruta, ancho,
archivo privado y SHA-256. Los archivos están en `storage/app/private/a05-evidence/`, ignorados
por Git, junto a `browser-results.json`, `browser-a05.py` y `browser-a05-followup.py`.
No se capturaron contraseñas, tokens, coordenadas del estudiante ni herramientas de red.
Los paneles que comparten invitaciones se ocultaron antes de fotografiar; la prueba conserva
sus resultados sin registrar los cuerpos secretos de las solicitudes.

## Pantallas y recorridos

| Perfil | Pantallas en los tres tamaños | Verificación interactiva |
|---|---|---|
| Coordinación | Importación, credenciales, Egresado, sesiones, permisos, historial, alta individual y por lote | Dos pestañas con previews/confirmaciones independientes; error de reset aislado; años exactamente 1–5/Egresado |
| Docente | Dashboard, sesiones, permisos, terminal, detalle de sesión y resultados del grupo | Apertura/cierre, método manual ausente y ventana inexistente deshabilitados; expiración de minuto real y replay del envío perdido |
| Estudiante | Dashboard, sesiones, permisos, etiqueta compartida, detalle de sesión y resultados propios | Invitación cerrada deshabilitada; alternativa sin Web NFC disponible |
| Provisional | Perfil a 390 px | Ruta académica redirige a cambio de contraseña |

Se inspeccionaron visualmente imágenes representativas de escritorio y móvil mediante el visor
local: imports-1440, credentials-390 y credentials-320, detalle docente-49-390 y student-create-390.
Las demás capturas tienen aserciones de navegador/medidas; no se atribuye inspección manual
individual a las 65 imágenes.

## Evidencias destacadas (archivos locales)

- [Importación en escritorio](../storage/app/private/a05-evidence/imports-1440.png).
- [Credenciales a 320 px, filtros apilados](../storage/app/private/a05-evidence/credentials-320.png).
- [Año y alta de estudiante a 390 px](../storage/app/private/a05-evidence/student-create-390.png).
- [Error aislado de restablecimiento](../storage/app/private/a05-evidence/isolated-reset-error.png).
- [Lote NFC con respuesta perdida recuperada](../storage/app/private/a05-evidence/nfc-batch-emulated-confirmation-retry.png).
- [Registro manual recuperado después de vencer](../storage/app/private/a05-evidence/manual-lost-response-replay-after-expiry.png).
- [Invitación cerrada del estudiante](../storage/app/private/a05-evidence/closed-student-invitation-390.png).

El flujo NFC sustituyó **solo NDEFReader** por un emulador: dos estudiantes seleccionados,
preparación por turno, escritura/lectura, confirmación real del servidor cuya primera respuesta
se descartó, relectura idempotente, fallo de escritura del segundo turno/reintento y cancelación
de otra alta. Los endpoints, CSRF, autorización y BD fueron reales. No demuestra lectura o
escritura de una etiqueta física. Otros contextos de navegador carecieron de esa sustitución
y mostraron las alternativas del dispositivo no compatible.

En el caso manual se esperó el vencimiento **real de un minuto**: primera escritura confirmada
en servidor con respuesta perdida, nuevo registro deshabilitado, recuperación del foco y replay
con exactamente la misma UUID y cuerpo. El panel confirma la asistencia tras recibir la respuesta.
El POST administrativo sin token CSRF devolvió **419** en este servidor real.

## Repetición local

Primero verificar siaa_test y sembrar A05AcceptanceSeeder en una base de pruebas limpia. Mantener
suites y scheduler detenidos. Iniciar el servidor aislado según a05-entrega.md, compilar assets
y ejecutar los scripts privados en orden con Python/Playwright y el ejecutable de Edge instalados:

```powershell
python storage/app/private/a05-evidence/browser-a05.py
python storage/app/private/a05-evidence/browser-a05-followup.py
```

Los scripts contienen exclusivamente la contraseña del fixture, no credenciales reales. Crear
otra vez el escenario de pruebas antes de repetirlos, porque sus CSV crean cuentas y sus
credenciales/asistencias son escrituras reales en la base desechable. No ejecutarlos contra
desarrollo/producción. No iniciar estos scripts mientras una suite reconstruye siaa_test.
