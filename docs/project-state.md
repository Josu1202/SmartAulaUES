# Estado de continuidad · SmartAulaUES

Fecha de la base: 12/10/2026. Versión: 0.1.2. Rama: main. Tag: v0.1.2. Base original: v0.1.0.

## Decisiones vigentes

Laravel 13 + PHP 8.4 + PostgreSQL 17; vistas Blade; Tabler 1.6.1 adjunto; negro, rojo y blanco.
Nombre oficial: SmartAulaUES. Icono propio: birrete y marca de asistencia en `public/brand/`.
Una aplicación, tres perfiles, desarrollo universitario en seis semanas.
Terminal principal: Android del docente que lee etiquetas NDEF de estudiantes.
ESP32/PN532 y huella se consideran después del sistema web.

## Construido en esta entrega

Acceso Fortify, salida, cuentas inactivas, límite de login, menú por rol, Policies de consulta;
vistas de inicio, usuarios con búsqueda, ciclos, grupos, sesiones e inscripciones propias;
base de datos con restricciones; seed idempotente; prueba Web NFC sin escritura ni registro.
Dependencias PDF/QR instaladas; documentación, Docker de desarrollo, CI y control de versiones preparados.

Parche v0.1.1: `--no-reload` en el arranque Docker conserva las variables de conexión del contenedor
en el proceso HTTP. Se reprodujo y verificó con el Laravel instalado, sin ejecutar Docker aquí.
Prueba de infraestructura: 1 caso, 2 aserciones; forma parte de PHPUnit/CI.
README contiene la actualización de instalaciones v0.1.0. No cambia esquema, cuentas ni módulos.

v0.1.2 incorpora el nombre SmartAulaUES y el icono propio en toda la interfaz, paquetes y documentación.
La configuración del dominio es `config/smartaulaues.php`. CSS y plantillas usan prefijos smartaulaues.
Se conservan los identificadores técnicos de la BD/Docker y correos demo para reutilizar instalaciones.
`docs/update-v0.1.2.md` explica la actualización con un remoto Git existente.

## Pendiente, sin simulación

- CRUD académico y validación de docentes/estudiantes por rol al asignar; cupos transaccionales y fechas.
- CSV con vista previa; administración de cuentas y restablecimiento controlado.
- Programación, reprogramación, cancelación y finalización de clases desde formularios.
- Alta/confirmación/revocación de etiquetas y prueba con el Android real.
- Portal temporal, sesiones de lector e integración de RegisterAttendance con NFC/QR/enlace/manual.
- Autorizaciones de escritura por recurso, hash normalizado e idempotencia transaccional de eventos.
- Permisos, correcciones, notificaciones, calendario e historial del estudiante.
- Cálculo de 75 %, cierre, instantánea y PDF privado versionado.
- HTTPS, operación, respaldo/restauración y entrega de la demostración completa.

## Cómo retomar

1. Leer este archivo, CHANGELOG y docs/decisions.
2. Usar `smartaulaues/` o el ZIP SmartAulaUES_v0.1.2_base.zip si el espacio local dejó de estar disponible.
3. Crear una rama para la función solicitada, preservando diseño y arquitectura.
4. Añadir pruebas de su comportamiento y permisos; actualizar estado y changelog al liberar.
5. Crear la siguiente etiqueta y entrega. No presentar módulos pendientes como terminados.

No existe URL publicada ni remoto GitHub. No se han tomado decisiones de teléfono real ni de alojamiento.
