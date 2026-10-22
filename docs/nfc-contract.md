# Contrato operativo NFC y asistencia · v0.3.0

Extensión local A02 sobre 0.3.2: [QR/enlace geográficos](a02-geographic-access.md).
POST de apertura y check-in presencial incluyen `location` (coordenadas/precisión/timestamp);
check-in incluye además `location.portal_id`. La modalidad efectiva es del servidor.
NFC/manual conservan este contrato. El fingerprint del evento incorpora ubicación cuando se envía;
replay previo a caducidad conserva respuesta y una lectura nueva necesita nuevo UUID.

Las rutas de este documento ya existen. /sessions/{session} es la consola operativa de clase.
/teacher/terminal enlaza clases en curso y conserva un diagnóstico separado que no registra asistencia.
Mismo dominio, sesión web, cuenta activa, auth.session y CSRF; NFC requiere HTTPS/Android compatible.
Prueba móvil por túnel Docker de v0.3.2: [mobile-https.md](mobile-https.md). El enlace se obtiene
en tu computadora y comparte la instalación/BD actual; no es el alojamiento definitivo.

## Credenciales de coordinación

| Ruta | Entrada y resultado |
| --- | --- |
| GET /admin/nfc-credentials | Directorio filtrable de estudiantes activos e historial de credenciales |
| POST /admin/nfc-credentials | student_id; 201 con credential_id, token y expiración del alta pendiente |
| POST /admin/nfc-credentials/{credential}/confirm | credential_token leído; 200 active si coincide y no venció |
| POST /admin/nfc-credentials/{credential}/revoke | reason; revocación auditada y regreso al directorio |

Formato NDEF: **un único registro de texto** `SIAA:C1:<32 bytes aleatorios en base64url sin padding>`.
El prefijo técnico mantiene compatibilidad; la interfaz se llama SmartAulaUES. No contiene nombre/carné.
La BD solo almacena SHA-256. Alta pendiente 10 minutos; una lectura de la etiqueta escrita confirma
el token y activa/reemplaza la credencial anterior. Índice parcial único para una activa por usuario.
Reemplazar/revocar conserva historial; pending/revoked nunca registra presencia.

UI: emitir → gesto del usuario para escribir → gesto para leer → comparar → POST confirm.
Token solo en memoria de esa página, respuesta de alta y etiqueta; no en localStorage ni bitácora.
Recargar pierde el secreto; emitir otra alta pendiente. Escritura/scan requieren permisos del dispositivo.
Ocultar la página aborta scan. La aplicación comprueba coincidencia, no puede certificar desde el servidor
que hubo una escritura física: un cliente autorizado podría enviar el token directamente.
Una etiqueta legible puede copiarse; supervisión y revocación son necesarias. No HCE/biometría/ESP32.

## Portal y lector de docente asignado/coordinación

| Ruta | Entrada y resultado |
| --- | --- |
| POST /sessions/{session}/portals | methods (nfc/qr/link/manual), minutes 1–60; 201 con portal_id, expires_at, QR PNG y enlace si habilitados |
| POST /portals/{portal}/close | Cierra portal y lectores; JSON o redirección según solicitud |
| POST /teacher/reader-sessions | portal_id, label; 201 con UUID de lector y expires_at |
| DELETE /teacher/reader-sessions/{readerSession} | Cierra lector propio (coordinación puede cerrar uno autorizado) |
| POST /teacher/nfc-readings | event_id UUID, reader_session_id UUID, credential_token; registro unificado |

Portal por sesión en curso y horario vigente, con ciclo activo al iniciar y cómputo abierto.
Expira al menor entre minutos y fin de clase; abrir uno nuevo cierra el anterior/lectores.
Lector está ligado al actor, portal y caducidad. Un docente no usa un lector ajeno aunque enseñe ese grupo.
Terminar clase cierra portales/lectores; expirar ventana no finaliza automáticamente la sesión.

## QR, enlace y manual

GET /check-in/{portal}?method=qr|link#t=<token> permite abrir el enlace y realizar login del estudiante.
El fragmento no va al servidor al pedir la página y se retira de la dirección tras leerlo.
El login recarga la página autenticada, conserva el fragmento temporalmente para recuperar el token
en memoria y obtiene CSRF de la sesión nueva. POST /check-in/{portal}: event_id, method y portal_token.
La identidad se toma de la sesión; no se acepta un student_id elegido por el navegador.
GET de invitado no muestra nombre del grupo/lista de estudiantes; el POST verifica autorización/vigencia.

POST /sessions/{session}/manual-attendance: event_id, portal_id, student_id y reason (5–1000 caracteres).
Solo docente asignado/coordinación y portal manual vigente. POST /sessions/{session}/attendances/{attendance}/correct
cambia presente/ausente con motivo, abierto y auditado; no sobrescribe una justificación ya resuelta.
No confundir corrección de resultado con reenvío de lectura ni con el futuro cierre de cómputo.
QR y enlace no certifican presencia física y requieren supervisión.

## RegisterAttendance: garantías y respuestas

Todas las vías usan app/Actions/Attendance/RegisterAttendance.php. Actor obtenido de la sesión.
Se normalizan UUID a minúsculas e IDs numéricos para la misma identidad entre HTML/JSON.
El hash canónico incluye actor/sesión/método/estudiante/portal/lector/motivo y hashes de secretos.

1. Advisory lock global por event_id; luego verificar actor/recurso y existencia del evento.
2. Evento idéntico: recuperar respuesta almacenada. Mismo UUID con otro actor/cuerpo: conflicto,
   sujeto primero a la autorización del recurso. No crear otra asistencia.
3. Evento nuevo: locks ciclo → grupo → cómputo → sesión → portal/lector → estudiante/credencial.
4. Verificar sesión/horario, método, portal vigente, lector propio, credencial activa/cuenta activa,
   inscripción vigente en fecha local inclusiva y pertenencia al grupo.
5. Una fila por sesión/inscripción. Presencia existente devuelve already_recorded; resultado ausente/
   justificado requiere revisión, no se transforma automáticamente. Escritura, auditoría y evento juntos.
6. Guardar evento/respuesta/HTTP sin token en claro. Rechazos del dominio de registro se guardan;
   errores de validación/autorización/estado cerrado previos a ese dominio no crean necesariamente evento.

| Código / status | Significado |
| --- | --- |
| 201 / recorded | Presencia nueva guardada |
| 200 / already_recorded | Esa inscripción ya tenía presencia, sin duplicar |
| 200 / replayed=true | Repetición idéntica de un éxito 201; resultado original recuperado |
| 401 / 403 | Sin sesión válida / rol, grupo, estudiante o lector no autorizado |
| 404 | Recurso no encontrado o fuera del ámbito anidado |
| 409 | UUID reutilizado con otra solicitud, sesión/ventana cerrada o resultado no compatible |
| 422 | Entrada inválida, credencial/inscripción inválida u otra regla de dominio |
| 419 / 429 | CSRF/sesión expirada o límite de solicitudes |

Un rechazo almacenado conserva su HTTP en replay. La identidad del estudiante se devuelve solo tras
validar todo el registro: attendance_id, student_id, student_name, student_code. No endpoint público para
resolver un token en una identidad. Token/hash/ruta de evidencia no entran a auditoría.

UI: sin confirmación anticipada ni cola offline. Error de red/5xx/429 mantiene packet/event_id para
reintento. Otro token/cuerpo obtiene otro UUID. Registrar o rechazar de forma definitiva limpia memoria.
Ante una lectura pendiente se pide reintentar o detener lector; no mezclar estudiantes en el mismo evento.
El token no se añade a logs, parámetros de URL del servidor, almacenamiento persistente ni errores recuperados.

## Aceptación pendiente en equipo real

Ejecutar CI/batería y tests/Concurrency/attendance-races.php con PostgreSQL 17 nativo. Escenarios:
UUID/cuerpo simultáneo, NFC/enlace/manual para un estudiante, UUID/cuerpos distintos y registro frente
al fin de clase. Smoke con conexiones PGlite serializadas solo valida el harness y resultados secuenciales.

Registrar Android/modelo/Chrome, URL HTTPS accesible desde celular, cinco etiquetas NDEF regrabables,
50 lecturas, asociación/duplicados, permiso denegado, token revocado, ventana vencida y fallo de red.
Probar escritura/lectura/alta nueva y revocación de la anterior, reintento sin falsa confirmación y páginas
en segundo plano. NDEF simulado en Chromium/pruebas de formato no sustituyen esta evidencia física.
