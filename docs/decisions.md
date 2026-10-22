# Decisiones técnicas · 0.3.2

## A02 · adquisición y configuración para ensayo físico del 22/10

- Tras mejorar adquisición/diagnóstico, el usuario acuerda radio de 30 m e incertidumbre máxima
  de 10 m para docente y estudiante, sin cambiar la fórmula. Sustituye los ensayos 5 m/2,5 m y 50 m/15 m.
  Valida proximidad aproximada al docente, no presencia dentro del aula; requiere validación física.
- watchPosition durante <=15 s desde el gesto, con deadline propio incluso si el permiso queda pendiente;
  clearWatch y retiro de timer/listeners al concluir/cancelar/ocultar/salir. Sin reanudación automática.
- Una sola candidata en memoria, mejor precisión reciente y empate por instante; no promediar ni guardar
  trayectoria. Diagnóstico sin coordenadas ni nuevas escrituras, en pantallas autorizadas existentes.
- Medición independiente del estudiante sin portal, para no depender de que el equipo docente alcance
  primero el límite. No genera asistencia ni constituye validación de proximidad.
- [Guía y límites](a02-location-measurement.md); aceptación física completa aún pendiente.

## Decisiones vigentes A02 (sin release)

- [Contrato y fundamento](a02-geographic-access.md); A01 integrado en base 18c7596.
- Reutilizar modality; Evento según lugar (guía p15) añade event_attendance_mode nullable explícito.
  Sin clasificación automática: falta de modalidad efectiva rechaza QR/enlace, permite NFC/manual.
- Referencia fija y modalidad efectiva guardadas por portal; los límites se leen de la configuración vigente.
  No se añade edición en curso;
  cerrar/reabrir para cambiar referencia. El snapshot de modalidad se comprueba al registrar.
  No hay snapshot de radio/incertidumbre: intentos nuevos usan 30 m/10 m incluso en portales existentes.
  Una referencia previa >10 m requiere reapertura. Evidencias y respuestas históricas no se reescriben;
  replay recupera la respuesta ya resuelta antes de aplicar la validación nueva.
- Haversine, d+aD+aE <=30 m, precisiones >0 y <=10 m, evidencia <=30 s, futuro <=5 s;
  alta precisión solicitada, caché cero y timeout API 15 s. Parámetros técnicos centralizados.
- RegisterAttendance valida después del replay bajo locks existentes. Ubicación en fingerprint,
  sin cuerpo persistido. Nueva lectura requiere UUID nuevo; fallo de red conserva el paquete.
- Evidencia de éxito sin coordenadas estudiantiles ni historial geográfico adicional de fallos.
  Prune a 30 días conserva UUID/hash/respuesta. Scheduler requiere operación real verificada.
- Blade existente; geolocation=(self) solo en las dos pantallas necesarias; sin servicios/dependencias.
- Ensayo simulado no demuestra precisión física ni protección absoluta frente a ubicación falsificada.

## Decisiones vigentes A01 (sin nueva versión de lanzamiento)

- Reparto F3 r1: integrante 2 A01–A05; integrante 3 B01–B07.
- Política project-2026-v1: 75 %, (presentes + justificadas aprobadas) / realizadas elegibles.
  Decisión del proyecto; aprobación institucional no documentada. Sin tardanzas inferidas.
- Comparación entera sin redondeo; mostrar dos decimales y las dos conclusiones confirmadas.
- Reglamento vigente inmutable en código; configuración selecciona versión para nuevos cómputos.
  La decisión posterior del usuario autoriza normalizar los registros actuales de desarrollo de forma
  explícita, repetible y auditada, solo en local/siaa o testing/siaa_test. Se excluyen cierres/reportes.
  Se actualizaron 2, sin pérdida de datos/fechas. Demo/desconocida aún produce policy_unavailable
  si se consulta sin normalizar: no hay alias ni migración automática.
- Tabla básica dentro del detalle del grupo, con selector de cómputo; sin pantalla o menú de política.
  Versionado interno, sin mensajes de propuesta o aprobación pendiente. No se atribuye aprobación formal.
- Consulta colectiva por página con locks compartidos, as_of común y tres cargas de sesiones/resultados/permisos,
  sin consultas por estudiante. Todas las sesiones del cómputo se consideran para cada fila.
  Paginadores independientes page/results_page; filtro period_id persistente; no hay resúmenes globales.
- Datos incompletos/inconsistentes: conteos e incidencias propias, sin porcentaje/conclusión ni reparación.
- Permisos pendientes: cálculo provisional si resultados completos; certificación se reserva para A03.
- Lectura consistente con locks compartidos ciclo → grupo → cómputo; escrituras existentes toman
  ciclo exclusivo. Las lecturas vivas de un período cerrado no sustituyen snapshots.
- RefreshDatabase autorizado solo en siaa_test desechable, verificada mediante conexión efectiva.
  Se conservan RefreshDatabase, tests/TestCase.php y CI. La sonda HTTP de infraestructura recibió
  un timeout de petición mayor y reintentos explícitos para Docker/Windows. No modificar .env ni regenerar claves.

Las decisiones históricas siguientes sobre política demo y reparto se sustituyen por las anteriores.

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
