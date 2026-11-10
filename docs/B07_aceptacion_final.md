# B07 — Protocolo de aceptación integral de SmartAulaUES

**Estado inicial:** pendiente de ejecución conjunta. No confundir pruebas automatizadas con evidencia de teléfono real, servidor HTTPS, backup o restauración.

| Caso | Procedimiento | Resultado esperado | Evidencia requerida |
| --- | --- | --- | --- |
| O01 CI | PR ejecuta Composer, Pint, Vite, npm test, PHPUnit y carreras PostgreSQL 17 | Todas las etapas verdes, sin desactivar pruebas previas | URL del run y commit |
| O02 Scheduler | Con datos aislados, dejar vencer sesión/cómputo; reiniciar scheduler | Recupera cierre pendiente, no duplica resultados ni snapshot | Tiempos UTC, consultas, estado final |
| O03 Cola | Pedir PDF/CSV en entorno aislado, detener/reiniciar worker | Job pasa pendiente→disponible o error recuperable, sin doble archivo | Logs redactados, estado, hash |
| O04 HTTPS | Acceder por dominio/SSL real a 320/390 px desde red móvil | Certificado válido, cookie Secure/HttpOnly, login e imágenes correctas | Navegador, URL sin credenciales |
| O05 NFC | Android/Chrome compatible, cinco etiquetas NDEF, 50 intentos, fallos/reintentos | Validación de identidad y de método; no duplicados | Modelo/hardware, tasa observada |
| O06 GPS | Medir precisión y proximidad con varias posiciones | Política vigente aplicada; limitación física documentada | Hardware, mediciones, decisión de A |
| O07 Backup | Detener escrituras, respaldar BD, privado y secretos con age | Cifrado, SHA256SUMS verificable, copia externa | Manifiesto sin secretos, custodio |
| O08 Restore | Proyecto y BD vacíos independientes, usar restore.sh | Login, historia, permisos, adjuntos y PDF histórico íntegros | Duración real y hash; sin material secreto |
| O09 Upgrade | Desde DB persistente de versión anterior, aplicar migraciones incrementales | APP_KEY/volumen y usuarios conservados; nuevo código operativo | Commit previo y posterior, tiempos |
| O10 Recorrido | Coordinación→CSV→sesión→NFC/QR/manual→permiso→ranking→cierre→PDF→reapertura | Datos reales/esperados comprobables, sin violar permisos | Guion ejecutado por ambos integrantes |

## Registro a completar (un bloque por ensayo)

- Caso / fecha exacta y zona: **pendiente**
- Responsable y segundo revisor: **pendiente**
- Commit, imagen, entorno y base **no sensible**: **pendiente**
- Hardware, navegador, red y URLs HTTPS sin tokens: **pendiente**
- Datos de prueba y esperado: **pendiente**
- Observado, duración, incidencias, reintentos y hash de archivos autorizados: **pendiente**
- Evidencia protegida y ubicación institucional: **pendiente**
- Resultado: **NO EJECUTADO / APROBADO / RECHAZADO / BLOQUEADO**
- Acción, responsable y fecha acordada: **pendiente**

## Criterio de aceptación

No declarar fase 3 completa ni producción lista solo porque CI esté verde. Se requiere aceptación formal de A01/A02 (incluida limitación de precisión), de cierre/snapshot, de acceso móvil, de colas y scheduler recuperables, y de al menos una restauración aislada con tiempo medido. La guía menciona RPO 24h/RTO 4h como metas iniciales **a medir**, no compromisos ya alcanzados. El equipo decide alojamiento, custodio de secretos, frecuencia/retención de backups y responsables operativos.
