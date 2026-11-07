# B02.5 — Estados de exportación y paginación

## Objetivo

Incorporar seguimiento persistente de las solicitudes PDF y CSV,
reintentos seguros y validación de reportes de varias páginas.

## Funcionalidades

- Estados independientes por reporte, usuario y formato.
- Estados pending, processing, available y failed.
- Prevención de solicitudes repetidas mientras están activas.
- Identificación de trabajos mediante request_token.
- Recuperación de solicitudes vencidas.
- Respuesta HTTP 503 y estado recuperable ante fallos de despacho.
- Notificaciones de disponibilidad o fallo definitivo.
- Identificación de cierres vigentes y versiones históricas.
- Conservación de los motivos sensibles en la auditoría administrativa.

## Seguridad e integridad

- Descargas autorizadas y almacenamiento privado.
- Archivos verificados mediante SHA-256.
- Exportaciones generadas desde snapshots certificados.
- Sin modificaciones al cálculo académico ni al flujo de cierres.
- Los exportadores existentes conservan sus bloqueos de concurrencia.

## Validación

- Suite completa: 351 pruebas, 4643 aserciones.
- Laravel Pint: 235 archivos aprobados.
- Prueba de PDF con 90 estudiantes: aprobada.
- PDF generado con cuatro páginas.
- Encabezados repetidos verificados visualmente en la página 2.
- Pruebas de estados, permisos, reintentos y fallos de despacho.
- Interfaz de reportes comprobada en desarrollo.
- Worker de generación CSV ejecutado satisfactoriamente.

## Consideraciones

Los estados son independientes para cada usuario solicitante.
Una solicitud vencida puede reintentarse con un nuevo token.
Los documentos certificados permanecen inmutables.

La inspección visual confirmó la presentación general y la
repetición de encabezados en la segunda página; no constituye
una inspección individual de todos los límites entre páginas.