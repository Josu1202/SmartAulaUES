# Contrato previsto para NFC

**Estado: diseño para próximas versiones. Las rutas de esta tabla aún no existen.**
La pantalla /teacher/terminal de v0.1.0 solo solicita permiso y lee etiquetas NDEF en el dispositivo.
No escribe, no envía el token y no registra asistencia.

| Ruta futura | Propósito |
| --- | --- |
| POST /teacher/reader-sessions | Abrir sesión lectora en un portal propio activo |
| POST /teacher/nfc-readings | Validar event_id, reader_session_id y credential_token y registrar en transacción |
| DELETE /teacher/reader-sessions/{id} | Cerrar lector propio |
| POST /admin/nfc-credentials | Emitir alta pendiente autorizada |
| POST /admin/nfc-credentials/{id}/confirm | Confirmar lectura después de escritura y activar |

Mismo dominio, HTTPS, cookies de sesión y CSRF. Actor obtenido de la sesión, nunca del cuerpo.
Formato NDEF: un registro de texto `SIAA:C1:<32 bytes aleatorios en base64url, sin padding>`.
Token completo solo en etiqueta/respuesta de alta pendiente; SHA-256 en BD, sin registrar tokens en logs.

RegisterAttendance deberá verificar rol, oferta propia, portal vigente, lector, credencial activa,
inscripción vigente, grupo, método y duplicados dentro de la transacción. Mismo event_id/cuerpo
repite respuesta; otro cuerpo con el mismo ID devuelve 409. Unicidad sesión/inscripción incluso concurrente.
201 recorded; 200 already_recorded; 401/403, 409, 422 y 429 deben distinguirse.

Esta demostración no impide copiar una etiqueta legible; requiere supervisión y revocación.
No ofrece HCE, NFC entre teléfonos ni cola offline automática. La respuesta del servidor confirma la asistencia.

## Prueba física pendiente

Registrar Android/modelo/Chrome, URL HTTPS, cinco etiquetas compatibles, 50 intentos, identidad asociada,
duplicados, permiso denegado, credencial revocada, portal vencido y fallo de red.
Detectar Web NFC en JavaScript o superar las pruebas NDEF automáticas no sustituye esta evidencia.
