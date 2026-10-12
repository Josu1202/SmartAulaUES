# Decisiones técnicas · 0.1.1

| Decisión | Motivo y aplicación |
| --- | --- |
| Seguir guía v2.0 | Alcance académico de seis semanas y NFC móvil como prioridad |
| PHP 8.4 y Laravel 13 | Coinciden con la guía; Composer resuelve para la plataforma mínima PHP 8.4 |
| PostgreSQL 17 obligatorio | Restricciones, JSONB, claves compuestas y pruebas con comportamiento PostgreSQL |
| Fortify sin registro público | Las cuentas pertenecen a la organización, no se autoasignan roles |
| Un rol por cuenta | coordinator, teacher o student; no se añade una biblioteca de permisos genérica |
| Blade y Tabler del ZIP | Reutilizar diseños sin convertir el proyecto Astro de Tabler en el backend |
| Compilar SCSS adjunto | 1.6.1 del ZIP; licencia, versión original y SHA-256 documentados en reference |
| Solo Bootstrap JS puntual | Dropdown y comportamiento del menú; no incorporar todos los plugins de la demo |
| Consultas reales con datos ficticios | Totales y listas provienen de la BD; no hay presencias ni porcentajes inventados |
| Esquema completo, operaciones por incrementos | Facilita las funciones siguientes; tablas preparadas no equivalen a módulo operativo |
| offering_id en solicitudes de permiso | Permite reforzar la relación sesión/inscripción del mismo grupo con FKs compuestas |
| Storage privado | Sin enlaces públicos para evidencias/reportes; descargas autorizadas se añaden después |
| UTC y zona de presentación separadas | timestampsTz para instantes; fechas académicas puras siguen siendo DATE |
| Propuesta 75 % | Umbral configurable; justificadas en denominador; falta implementar y validar el cálculo |
| Sin Sanctum aún | NFC móvil usa la sesión web. API del ESP32 y sus credenciales son ampliación posterior |
| Docker de desarrollo | PHP 8.4 y PostgreSQL 17 sin depender de un XAMPP con PHP 8.2 |
| Servidor Docker sin recarga de .env | `--no-reload` conserva la dirección `db:5432` inyectada por Compose; reiniciar app al cambiar .env |

La guía contiene decisiones por validar con coordinación: tardanzas, vigencias, plazos de permisos,
regla de justificadas y aplicación institucional del 75 %. Esta base no las convierte en políticas definitivas.
