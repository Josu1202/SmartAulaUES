# Decisiones técnicas · 0.3.2

| Decisión | Motivo y aplicación |
| --- | --- |
| Seguir guía v2.0 | Alcance académico de seis semanas y NFC móvil como prioridad |
| Nombre SmartAulaUES desde v0.1.2 | Solicitud del usuario; configuración central, vistas e icono propios |
| Identificadores de datos conservados | Evita reinicializar volumen PostgreSQL o duplicar cuentas al cambiar la marca |
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
| Storage privado | Sin enlaces públicos para evidencias/reportes; descarga de evidencias por Policy integrada en fase 2; PDF pendiente |
| UTC y zona de presentación separadas | timestampsTz para instantes; fechas académicas puras siguen siendo DATE |
| Propuesta 75 % | Umbral configurable; justificadas en denominador; falta implementar y validar el cálculo |
| Sin Sanctum aún | NFC móvil usa la sesión web. API del ESP32 y sus credenciales son ampliación posterior |
| Docker de desarrollo | PHP 8.4 y PostgreSQL 17 sin depender de un XAMPP con PHP 8.2 |
| Servidor Docker sin recarga de .env | `--no-reload` conserva la dirección `db:5432` inyectada por Compose; reiniciar app al cambiar .env |
| Tres fases por módulos completos | Solicitud del usuario 13/10/2026; cada responsable implementa servidor/vista/pruebas de su fase. Reemplaza el reparto A/B/C por especialidades de la guía |
| Fase 1 v0.2.0 | Administración de cuentas/ciclos/catálogo/grupos/cómputos/inscripciones, CSV y bitácora; fase 2 ampliada en v0.3.0, fase 3 pendiente |
| pgAdmin opcional | Perfil tools y volumen independiente; PostgreSQL/Laravel funcionan sin pgAdmin; conexión precargada sin contraseña |
| Correo sin distinguir mayúsculas | Normalización en formularios/login/CSV e índice único lower(email), migración incremental |
| Retiro inclusivo y reserva anticipada | active_to incluye el día final; inscripciones futuras reservan plaza; modificar vigencia existente al reincorporar |
| Preservar identidades e historial | No DELETE; identidad de grupo no cambia con datos; roles con enlaces no cambian; motivos para modificaciones sensibles |
| CSV completo por lote | Preview 15 min/500 filas/1 MB; hash inicial en sesión, no contraseña en CSV; revalidación y una transacción |
| Claves existentes conservadas en setup | scripts/setup.php genera APP_KEY solo si falta; actualizar no invalida sesiones cifradas/datos por rotar clave |
| Entrega ZIP sin Git | Preservar el checkout/remoto del equipo; copiar fuentes sobre rama, revisar y publicar por PR; historia registrada en CHANGELOG |

## Decisiones añadidas en fase 2

| Decisión | Motivo y aplicación |
| --- | --- |
| Directorios separados | Solicitud del usuario: docentes y estudiantes no se mezclan dentro de coordinación |
| Ingreso y carrera | “Año” puede ser cohorte o nivel; se guardan ambos y se filtran al inscribir |
| Sin años inventados | Migración deja NULL en anteriores; editar o actualizar por CSV de años |
| Actualización de años acotada | Solo esos dos campos por carné; preserva password/rol/estado e inscripciones |
| Lote manual + CSV | Formularios hasta 100; CSV hasta 500/1 MB; mismo review/confirm atómico de base |
| Formato por ciclo | Fechas absolutas configuradas una vez, copiadas a cada grupo; no propagar cambios retroactivos |
| Formato usado protegido | Si se utilizó, crear variante con otro nombre; grupo conserva su calendario heredado |
| Docente limitado | Administra sesión y resultados/permisos propios; cuentas/inscripción/grupo/cómputo son coordinación |
| Cancelar/reprogramar sin historial | Preservar asistencia/permisos; con historial, finalizar/corregir/resolver con auditoría |
| Un registro por sesión | Todos los métodos comparten validación, locks, unicidad y respuesta almacenada |
| UUID normalizado y lock global | Mayúsculas/IDs HTML no cambian identidad; serializa reuso inclusive entre ciclos |
| Confirmación del servidor | Sin optimismo offline; reintento conserva UUID/cuerpo tras pérdida de respuesta |
| Secretos temporales | Token NFC/portal hash en BD; enlace fragmento; memoria de página sin persistencia |
| Alta NFC comprobada | Pending/escribir/leer/activar, una etiqueta activa; no certifica desde servidor hardware físico |
| Justificación protegida | Resolución no se reabre ni se sobrescribe desde corrección común; diseño de reapertura en fase 3 |
| Fin y cuenta inactiva | Cuenta inactiva no elimina inscripción académica; fin usa vigencia inclusiva de la fecha |
| Conteos y avisos básicos ahora | Presentación solicitada con datos reales, accesibles y propios; sin porcentaje no aprobado |
| Cargas privadas | Evidencia 5 MB por Policy, PHP Docker 6M/8M, sin enlaces públicos |
| Evidencia distinta por entorno | PGlite/casos aislados y NDEF simulado comprobables; nativo, Docker/CI y Android físico pendientes |

La guía contiene decisiones por validar con coordinación: tardanzas, vigencias, plazos de permisos,
regla de justificadas y aplicación institucional del 75 %. Esta base no las convierte en políticas definitivas.

## HTTPS móvil de pruebas · v0.3.2

| Decisión | Motivo y aplicación |
| --- | --- |
| Quick Tunnel de la instalación actual | Pruebas físicas del celular sin otra BD/cuentas ni comprar dominio; PC/Docker encendidos |
| Perfil mobile opcional | tunnel no arranca con compose normal; detención no elimina volúmenes |
| Configuración del proxy por request | .env/config ya cargados al validar X-Forwarded-Proto/For; default sin proxy |
| Ignorar dominio/puerto reenviados | Solo Host original determina el destino de formularios/redirects; probados encabezados falsos |
| Cookie Secure y recursos compilados | HTTPS real del teléfono, sin public/hot que apunte al localhost de la computadora |
| Evidencia separada | TLS local con NDEF simulado comprobado; Docker/cloudflared y chips del usuario pendientes |
