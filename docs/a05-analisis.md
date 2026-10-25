# A05 · análisis e inventario de revisión

## Actualización operativa de esta revisión

El análisis e inventario iniciales siguientes se conservan como evidencia previa. Los cambios
posteriores de escenario neutral, textos, rendimiento y Docker están contrastados en
[operacion-verificacion.md](operacion-verificacion.md). Para el commit futuro usar
[el inventario actual](operacion-inventario.csv), no las huellas de la revisión inicial.

Base `6273a307f3411a5e1ba40c427153cdf39e545a0e` de origin/main, con PR #3–#6 integradas.
El checkout habitual tenía `.env.example` modificado y `Microsoft/` sin seguimiento. Se hizo
`git fetch origin` y se creó el worktree `../SmartAulaUES-A05`, rama
`feature/p3a-a05-integracion-final`, sin descartar ni trasladar esos cambios. Se conservó 0.3.2.

Se leyó la solicitud adjunta, AGENTS, README, decisiones, fases, estado y contratos A01–A04;
la guía local PDF de 37 páginas se extrajo para contrastar especialmente páginas 4–5, 12–21,
26–27. La guía propone NFC docente/etiqueta, CSV fijo y recuperación por Coordinación; las
decisiones posteriores A01 sobre justificadas y A05 sobre ends_at prevalecen expresamente.
Las notas originales no son necesarias: `change-notes-status.md` conserva su transcripción.

## Inventario y profundidad

El inventario adjunto `a05-inventario.csv` enumera archivos propios con categoría, tamaño y
SHA-256 para identificar la entrega revisada. Excluye dependencias, fuentes Tabler de proveedor,
artefactos compilados, secretos, Microsoft y materiales externos. Los hashes no son una prueba
de corrección ni sustituyen las verificaciones por escenario.
Son 280 archivos de código/configuración/pruebas/entrada pública y activos propios, incluidos
mensajes de idioma; los documentos se relacionan por sus enlaces, sin hashes autorreferentes.

| Área | Revisión realizada | Verificación asociada |
|---|---|---|
| Rutas, middleware, Fortify, policies | Acceso por rol/recurso, cuenta suspendida, auth.session, CSRF, cambio provisional, proxy y recursos privados | Authentication, Authorization, HttpsProxy, A05 y navegador |
| Acciones Academic/Attendance | Transacciones, orden de locks, revalidación, estados, auditoría, replay, cierres y reparaciones | Regresión funcional y scripts de concurrencia PostgreSQL |
| Modelos y migraciones | Campos/casts, FKs, unicidad, CHECK, estados y snapshots | Instalación del arnés en siaa_test y sonda de upgrade |
| Cálculo, consultas y catálogo | Denominador, umbral exacto, justificaciones, privacidad, carga por lote, diagnóstico GET y paginación | AttendanceCalculation, Summary/ResultsTable, A05 y carreras |
| Requests/controladores | Validación directa, fechas/roles, entradas secretas y rutas antiguas | Pruebas HTTP por perfil y errores |
| Blade y JavaScript de flujos afectados | Scope de formularios, errores HTTP/red, estado temporal, NFC, filtros y navegación móvil | Pruebas JS y escenarios Edge documentados |
| Operación | Compose, Dockerfile, scheduler, comandos, CI, setup/configure/package | Build, validaciones y ejecución local real |
| Pruebas | Suites existentes y nuevas, casos de error y harness nativo | Listado de casos y resultados en verificación final |

La revisión se concentra en el código propio ejecutable y las relaciones relevantes; no es
una auditoría formal de dependencias ni de cada activo de diseño. La evidencia de pruebas
existentes corresponde a su ejecución real, no a afirmar que cada línea de cada test fue
auditada individualmente. No se verifican un hosting inaccesible, teléfonos, etiquetas ni GPS
físicos. Véanse los límites por escenario, sin equiparar simulación NDEF con lectura física.

## Indicios anteriores contrastados

| N.º | Clasificación final del código | Hallazgo, consecuencia y corrección |
|---|---|---|
| 1 | Confirmado y corregido | Compose no ejecutaba scheduler. Servicio supervisado, frecuencia de un minuto, lock PG e indicador de ejecución. |
| 2 | Confirmado y corregido | CI omitía offering-management.php. Añadidos A04, A05 y sonda de upgrade. CI remoto no se declara ejecutado. |
| 3 | Confirmado y corregido | old(reason) y error global contaminaban otros formularios. Scopes por acción/recurso, incluidos filas y recuperación de campos. |
| 4 | Confirmado y corregido | Cambiar docente no verificaba su horario. Validación y reasignación atómicas con el lock usado por programación; accesos anteriores cerrados. |
| 5 | Confirmado y corregido | Repetir confirmación NFC exitosa fallaba. Replay de activa/token exacto sin nueva auditoría; revocada continúa bloqueada. |
| 6 | Confirmado y corregido | GET llamaba deactivationIssues con locks exclusivos y cálculo completo. Diagnóstico ligero separado; validación íntegra solo al mutar. |
| 7 | Confirmado y corregido | Sesiones/permisos carecían del catálogo Actual/Historial. Consulta común y pendientes de suspendidos localizables. |
| 8 | Confirmado y corregido | Selector CSV incluía grupos inactivos. Ahora ofrece activos de ciclos no cerrados; confirmar revalida estado. |
| 9 | Confirmado y corregido | Consola sin actualización temporal del portal. Vencimiento/foco ocultan recursos y bloquean intentos nuevos; servidor sigue siendo autoridad. |
| 10 | Confirmado y corregido | Una respuesta HTML perdía HTTP status. Mensajes para 401/403/419/422/429, deadline de red y conservación del evento ambiguo. |
| 11 | Confirmado y corregido | Avisos incluían toda matrícula. Se filtran por fecha inclusiva y cuenta activa. |
| 12 | Confirmado y corregido | Terminal prometía un registro futuro ya implementado. Texto sustituido por acceso real a clase. |
| 13 | Confirmado y corregido | Tres enums vacíos en Enums/Enums, sin referencias. Eliminados únicamente esos scaffolds. |
| 14 | Confirmado y corregido | Continuidad presentaba A04 local. Se documenta integración comprobada por PR #6 y trabajo A05 sin publicar. |
| 15 | Confirmado y corregido | Una clave academic_import compartía preview entre pestañas. Mapa por nonce, URL, propietario y expiración; revalidación al confirmar. |
| 16 | Confirmado y corregido | Año 1–10 repetido. Opciones comunes 1–5/Egresado, conversión explícita y CHECK/migración incremental. |
| 17 | Confirmado y corregido | Solo emisión NFC individual. Cola secuencial filtrada y seleccionada con confirmación por credencial. |
| 18 | Confirmado y corregido / No aplicable | Reportes dirige a resultados y cierres existentes. Noticias y exportación PDF siguen fuera de A05, a cargo de Persona 3; no se simulan. |

## Hallazgos adicionales

| ID | Impacto | Evidencia y solución |
|---|---|---|
| A05-T01 | Alto: incompatibilidad temporal aprobada | A03 esperaba medianoche. Processor/finishOverdue usan ends_at UTC, ventana final exclusiva y pruebas de frontera/recuperación. |
| A05-A01 | Alto: acceso con contraseña provisional | CSV no exigía cambio. Flag persistente, middleware, cambio/revocación de sesiones y compatibilidad de invitaciones sin extender vigencia. |
| A05-S01 | Alto: truncamiento de credenciales | Bcrypt acepta solo los primeros 72 bytes; formularios admitían 128 caracteres. Regla común de bytes/NUL y pruebas ASCII/UTF-8. |
| A05-S02 | Alto: aviso de seguridad HTTP | composer audit encontró CVE-2026-108082 en HTTP Foundation 8.0.15. Resolver mínimo compatible selecciona 7.4.21 corregido; sin dependencias nuevas. |
| A05-S03 | Seguridad de herramientas | Mismo audit encontró CVE-2026-109413 en YAML 8.0.15. Actualizado a 7.4.21 compatible; la app no ofrece un importador YAML al usuario. |
| A05-G01 | Alto: bypass de suspensión | closePeriod manual no comprobaba grupo activo. Guard dentro del lock y ampliación de la matriz A04; excepción de sistema intacta. |
| A05-C01 | Alto: autorización concurrente | Registro verificaba pertenencia antes de esperar locks. Reautoriza sobre grupo/cuenta actuales después de la espera. |
| A05-I01 | Medio: sobrescritura de clasificación | Confirmar CSV de años podía pisar una edición posterior. Huella de cuentas y revalidación bajo locks rechazan el lote completo. |
| A05-P01 | Medio: revisión con consultas por fila | CSV realizaba búsquedas por email/carné por cada fila. Carga conjunta; comparación reproducible de 1 y 500 filas. |
| A05-U01 | Medio: respuesta inesperada y replay | Un 200 no JSON podía descartar la UUID sin confirmación. Se conserva como resultado ambiguo para reintentar. |
| A05-U02 | Medio: retry después de vencer | Deshabilitar todos los botones podía impedir recuperar un envío manual perdido. El paquete pendiente conserva identidad y permite su replay. |
| A05-P02 | Medio: carga de sesiones | Detalle cargaba toda la historia de sesiones. Paginación separada y contador SQL, sin paginar sesiones del cálculo académico. |
| A05-O01 | Alto: recuperación del scheduler | El lock de caché `withoutOverlapping(30)` podía sobrevivir a una terminación abrupta e impedir procesar durante treinta minutos. Se conserva la exclusión global PostgreSQL y se retira ese segundo lock de cierres; liberación por conexión y recuperación verificadas localmente. |
| A05-U03 | Medio: selectores residuales | Dos formularios aún ofrecían 6–10 aunque el servidor ya rechazaba esos valores. Usan las mismas opciones 1–5/Egresado; regresión HTTP y navegador sobre ambas altas. |
| A05-U04 | Medio: registro manual sin recurso | Los botones podían aparecer disponibles sin ventana o con método manual excluido. Inicialización/apertura/cierre actualizan su disponibilidad; navegador verifica los tres estados y conserva replay pendiente. |
| A05-U05 | Medio: invitación vencida | La pantalla estudiantil conservaba el botón de un acceso cerrado/vencido. Timestamp efectivo y actualización por tiempo/foco bloquean intentos nuevos, conservando el paquete pendiente; servidor mantiene sus guards. |

El estado final de cada corrección y su evidencia ejecutada se registra en
`a05-verificacion-final.md`; este análisis no convierte inspecciones estáticas en pruebas físicas
ni se presenta por sí solo como criterio de aceptación de producción.
