# Terminal física NFC y QR

La terminal agrega un canal ESP32-S3/PN532 al registrador existente. El QR de proyección conserva su URL y el flujo con cámara externa. El QR de la OLED contiene un token Base32 de 20 caracteres y se lee en **Escanear QR de terminal** tras iniciar sesión como estudiante. NFC individual utiliza la credencial NDEF de SmartAulaUES. El registro por etiqueta compartida conserva su flujo.

## Preparación y operación

1. Aplica las migraciones incrementales con `php artisan migrate` a la instalación autorizada y recompila Vite. Mantén el scheduler del proyecto activo para retirar entregas vencidas con `terminal:prune-deliveries`.
2. Como Coordinación, entra en **Terminales físicas**. Registra nombre y capacidades. Copia el secreto desde la respuesta privada al almacenamiento local del dispositivo. El identificador sirve para inventario y las solicitudes se autentican con el secreto.
3. Provisiona localmente la URL base HTTPS, los certificados de confianza, Wi-Fi de 2,4 GHz y el secreto. Usa USB o configuración privada. Si cambia la URL del túnel, actualiza la configuración del dispositivo. Una rotación invalida la clave anterior y cierra asignaciones y trabajos.
4. **Credenciales NFC → Escribir con terminal física** prepara un trabajo para un estudiante y una terminal conectada. Presenta la etiqueta, comprueba lectura posterior y espera respuesta del servidor. La activación anterior se conserva hasta que la nueva se confirma. La vista de trabajos permite actualizar el estado o cancelar.
5. Docente o Coordinación abre el portal ordinario habilitando NFC, QR o ambos. Desde la sesión, entra en **Vincular o liberar terminal física** y elige dispositivo y métodos habilitados. Para una sesión presencial, abre el portal desde el lugar donde se instalará la terminal para capturar la referencia A02.
6. El estudiante puede escanear el QR proyectado con la cámara externa o el código de la OLED desde SmartAulaUES. Cuando corresponde, la confirmación de QR corto solicita ahí mismo una ubicación fresca. Se aplica la misma política A02 y se registra el método académico `qr`.

## API de dispositivo v1

Base `/api/v1/terminal`. Envía `Authorization: Bearer <secreto>` y JSON. El secreto son 64 caracteres hexadecimales aleatorios. Los endpoints de dispositivo están separados de las cookies de usuario y requieren HTTPS fuera de local y testing. Mantén verificación TLS incluso si el servidor usa un túnel. Las respuestas sensibles son privadas y sin caché.

| Método y recurso | Entrada | Respuesta exitosa |
| --- | --- | --- |
| POST /heartbeat | `firmware` opcional, hasta 40 caracteres ASCII | `server_time`; actualiza la última señal |
| GET /state | Sin cuerpo | `server_time`, `mode` (`idle`, `attendance`, `write`), `assignment` nullable con ID, portal, métodos, vencimiento y desafío actual |
| POST /qr-challenges | `assignment_id` UUID | ID de desafío, terminal vinculada mediante asignación, portal, token, tiempos, segundos de vigencia y parámetros QR |
| POST /commands/claim | Objeto vacío | `command` nullable con ID, `lease_id`, `lease_until`, `server_time` y `credential_token` |
| POST /commands/{id}/confirm-write | `lease_id` y `readback` NDEF exacto | `status: confirmed`, con `replayed` si repite la confirmación |
| POST /commands/{id}/result | `lease_id` y `failure` | Estado terminal `incompatible`, `write_error`, `readback_error` o `timeout` |
| POST /nfc-readings | `event_id`, `assignment_id` y `credential_token` | 201 `recorded` o 200 `already_recorded` / replay sin datos del estudiante |

Los errores usan 401 para acceso revocado o inválido, 403 para falta de autorización, 404 para recurso ajeno o inexistente, 409 para conflicto o vigencia, 422 para validación y 429 para límite de solicitudes. Trata la pérdida de respuesta, 429 y 5xx como resultado ambiguo. Reenvía el mismo cuerpo con el mismo UUID o concesión, usando backoff. Un 401 requiere revisar la credencial provisionada.

Envía heartbeat cada 30 s y consulta estado aproximadamente cada 3 s cuando esté en actividad. Una señal de más de 90 s figura fuera de línea. La generación de QR es POST y devuelve el mismo desafío durante el intervalo de rotación de 60 s. Cada desafío vence como máximo a los 90 s y queda limitado por la asignación. Pueden coexistir el actual y el anterior aún vigente. Calcula el plazo de visualización con reloj monotónico y un margen que cubra el tiempo de red. Retira el símbolo al alcanzar ese límite.

El lector web hace POST con sesión y CSRF a `/student/terminal-qr/resolve` con `short_token`. Luego confirma mediante `/student/terminal-qr/check-in` con `event_id`, `challenge_id`, `portal_id`, `short_token` y, para presencial, `location` A02. El código puede servir a varios estudiantes y la asistencia se atribuye al usuario autenticado. Cámara y geolocalización se habilitan en la página del lector. jsQR se empaqueta con Vite como alternativa al detector nativo; el vídeo se procesa en el navegador.

## Contrato para el firmware

- Usa el dispositivo autenticado durante toda la operación. Configura un timeout de red, limita el tamaño de JSON y mantén en memoria como máximo la operación pendiente. Para leer NFC usa detección de retirada antes de admitir otra presentación.
- En modo escritura, reclama el trabajo y conserva la concesión. Escribe un único registro de texto NDEF UTF-8 `SIAA:C1:<43 caracteres base64url>`. Retira y vuelve a leer la etiqueta. Compara el registro completo y envía la lectura posterior. Un UID aislado es insuficiente y una confirmación de relectura es una declaración del dispositivo autenticado. Evita comandos de bloqueo permanente de la etiqueta.
- En modo asistencia, envía la credencial de etiqueta y el UUID del evento, sin indicar estudiante. Muestra «Confirmada» o «Ya registrada» tras un resultado positivo y «Sin confirmar» ante pérdida de respuesta.
- Dibuja el token exacto en QR Model 2 versión 1, modo alfanumérico, corrección M sin aumento automático. Son 21 módulos más cuatro de margen por lado. Usa dos píxeles por módulo, oscuro sobre fondo claro, para una caja de 58 × 58 px. En 128 × 64 su origen centrado es (35, 3). Si no caben 58 × 58, informa incompatibilidad.
- Estados mínimos de la pantalla: conectando, disponible, «Acerca etiqueta», «Escribiendo», «Verificando», QR vigente, «Confirmada», «Ya registrada», «Sin confirmar» y «Sin conexión». Descarta secretos y datos de la operación al concluir.
- Antes de elegir driver y pines, identifica la variante de ESP32-S3, el selector y niveles del PN532, el controlador I²C de la pantalla y los chips de las etiquetas. SPI para PN532 e I²C para OLED son las interfaces de referencia. SSD1306 y SH1106 requieren comprobar el módulo real.

El repositorio entrega el contrato del firmware y el cliente de referencia por simulador. La compilación, la escritura NDEF con PN532 y la lectura física desde la OLED requieren identificar esas placas y ensayar el montaje.

## Simulador y ensayo de aceptación

En un ambiente ficticio, configura `SMARTAULAUES_TERMINAL_URL` y `SMARTAULAUES_TERMINAL_SECRET` de forma privada. Ejecuta con Node 24:

```text
node scripts/physical-terminal-simulator.mjs heartbeat
node scripts/physical-terminal-simulator.mjs state
node scripts/physical-terminal-simulator.mjs write --tag-file RUTA_PRIVADA_NUEVA --replay
node scripts/physical-terminal-simulator.mjs nfc --tag-file RUTA_PRIVADA_NUEVA --replay
node scripts/physical-terminal-simulator.mjs qr
```

Emite el trabajo antes de `write` y vincula el dispositivo antes de `nfc` o `qr`. El archivo simula una etiqueta y contiene un secreto ficticio. Guárdalo fuera del repositorio en una carpeta privada. `qr` imprime el token como haría la pantalla; evita registrar esa salida. `write --failure incompatible` simula una etiqueta no admitida. `--replay` descarta la primera confirmación y reenvía el mismo contenido.

El ensayo físico requiere modelos, versión de firmware, etiqueta, navegador, URL HTTPS, distancia y resultado observados. Comprueba escritura seguida de lectura en PN532 y teléfono, reintento por pérdida de Wi-Fi, lectura de OLED con distintos teléfonos, A02, expiración y cierre de portal. Comprueba también alimentación, corriente y autonomía con el montaje identificado. Una matriz decodificada en pruebas JS solo valida el formato digital de referencia.

## Verificación de la ampliación

- Composer validó el manifiesto y los requisitos de plataforma. Pint aprobó los archivos PHP de la ampliación y Vite compiló el lector y su decoder.
- `npm test` aprobó 48 pruebas, incluidas la validación del formato y la decodificación de una matriz alfanumérica 1-M con margen de cuatro módulos.
- En Docker, la sonda de conexión confirmó PostgreSQL y lectura/escritura en `testing/siaa_test`. La regresión completa pasó 389 pruebas y 4.987 aserciones. Tres pruebas de terminal aportaron 76 aserciones.
- Pasaron la sonda incremental A04 → A05 y los scripts nativos de concurrencia. `tests/Concurrency/physical-terminal.php --env=testing` comprobó una asignación exclusiva y un QR bloqueado por el cierre concurrente del portal.
- El simulador completó por HTTP de loopback, dentro del entorno aislado, heartbeat, entrega de escritura, relectura, activación, asignación, asistencia NFC, repetición de ambas confirmaciones y solicitud de un desafío QR.

## Almacenamiento y cambios de integración

La migración añade tablas de terminal, asignación, trabajo, desafío, procedencia y bitácora de terminal. Los secretos de autenticación y reconocimiento se guardan como hashes. Los contenidos recuperables de trabajos y desafíos se cifran con APP_KEY y se retiran al confirmar, cancelar o vencer. Los backups pueden contener contenido temporal cifrado. Conserva la clave y aplica la política de custodia de B07.

El registrador conserva las firmas y respuestas del canal original. La entrada interna de terminal añade asignación y desafío a la firma, y persiste su procedencia en la misma transacción. Las operaciones de registro toman el advisory lock del evento, el lock de terminal y luego los locks académicos. Las demás mutaciones del canal toman el lock de terminal antes de sus recursos. Los cierres académicos conservan su protocolo y el registro comprueba vigencia bajo locks.

`CredentialManager` añade una revocación restringida a altas pendientes para cancelar trabajos. `bootstrap/app.php` registra las rutas API y marca campos sensibles; `SecurityHeaders` limita cámara y ubicación a la nueva página. El proveedor configura límites del dispositivo y `routes/console.php` añade limpieza de entregas. Las vistas de sesión, credenciales y navegación enlazan las pantallas nuevas y Vite carga el lector al abrirlo. Las autorizaciones académicas, el QR normal, los cierres y los reportes reutilizan sus contratos vigentes.
