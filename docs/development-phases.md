# Tres fases de desarrollo · SmartAulaUES

Actualización vigente 24/10: A01–A04 integrados por PR #3–#6; base A05 `6273a30`.
A05 integral de Persona 2 está implementado localmente para revisión en worktree aislado,
sin release/publicación. [Entrega](a05-entrega.md), [decisiones](a05-decisiones.md) y
[verificación](a05-verificacion-final.md). Las referencias inferiores a A04 local/A05 pendiente
son históricas. No se declaran implementados PDF, noticias ni módulos B de Persona 3.

A 23/10/2026: A01/A02/A03 integrados por PR #3/18c7596, #4/bf07b01 y #5/5172b91.
A04 local en feature/p3a-a04-gestion-grupos: [contrato](a04-group-management.md) y
[evidencia](a04-verification.md). No release nueva ni integración de A04. Aceptación física A02 y
hosting pendientes; A05/B01–B07 no se declaran entregados. El reparto inferior queda subordinado a F3 r1.

## Reparto vigente F3 r1

El integrante 2 desarrolla 3A: A01 política/cálculo, A02 QR geográfico, A03 cierre y reapertura,
A04 grupos y A05 CSV. El integrante 3 desarrolla 3B: B01 dashboards, B02 PDF/CSV,
B03 historia/noticias, B04 experiencia, B05 demo, B06 rendimiento y B07 operación.
Este reparto sustituye la asignación histórica de fase 3 completa al miembro 3, conservada abajo.
A01 local implementa reglamento vigente, cálculo único y tabla básica por cómputo en el grupo;
normaliza datos de desarrollo explícitamente, sin tocar cierres/reportes. B consume el contrato
en attendance-calculation-contract.md. El resto sigue pendiente; no hay certificación institucional.

## Contexto histórico de fases 1–2 y propuesta inicial de fase 3

Organización solicitada el 13/10/2026: un miembro humano por fase, con módulos completos
(servidor/interfaz/reglas/pruebas). Usuario: fase 1; nombres de miembros 2–3 aún pendientes.
Esta división conserva RF01–RF08 de la guía y sustituye su reparto por especialidades.

## Fase 1 · Cuentas y organización académica · Miembro 1

Entrega inicial v0.2.0, conservada en v0.3.0. Acceso Fortify, perfiles/estado/carné, contraseña propia
y restablecimiento; ciclos/materias/grupos/docentes/cupos; cómputos individuales; inscripción con
vigencia/retiro inclusivo; CSV revisado y atómico; auditoría, instalación y pgAdmin opcional.

Criterios implementados: unicidad/correo normalizado, perfil con historial estable, docente activo,
fechas dentro del ciclo/cómputo, cupo que reserva inscripciones futuras, sin borrado ni pérdida de
fechas históricas, sin importaciones parciales ni contraseñas en bitácora. Pruebas regresivas se conservan.
La aceptación Docker/CI del equipo sigue pendiente; un ZIP no demuestra publicación en GitHub.

## Fase 2 · Clases, asistencia y permisos · Miembro 2

Entrega de código v0.3.0 sobre v0.2.0. Las anotaciones del usuario agregan mejoras de organización
que se implementan aquí para agilizar la creación de grupos. El responsable no modifica la arquitectura.

| Función | Criterio implementado | Verificación adicional requerida |
| --- | --- | --- |
| Directorios | Docentes/estudiantes/coordinación separados; perfil fijo en formulario | Aceptación con datos del equipo |
| Estudiantes por año | Ingreso y carrera; individual, lote manual, altas CSV y años de cuentas existentes | Clasificar registros reales, sin inferencias automáticas |
| Inscripción filtrada | Seleccionar cohortes/años en lote; vigencia y cupo atómicos | Recorrido con grupos reales |
| Formato de ciclo | Calendario común definido una vez, copiado por grupo; usado/heredado inmutable | Calendarios institucionales correctos |
| Sesiones propias | Horario/modalidad/cómputo existente, no solapamiento docente, reprogramación con motivo | El docente no edita estudiantes ni calendario |
| Estados de clase | scheduled → in_progress → completed; cancelar sin historial y con motivo | Finalización manual; cron pendiente de decisión |
| Portal | Ventana 1–60 min limitada al fin de clase; métodos y cierre lector | Vigencia/HTTPS en instalación real |
| Credencial | Pendiente → escribir → leer/confirmar → activa; revocar/reemplazar; solo hashes | Android/Chrome/HTTPS y cinco etiquetas físicas |
| Registro unificado | NFC/QR/enlace/manual, actor/recurso/enrollment/ventana; una fila por sesión/inscripción | Carreras reales contra PostgreSQL nativo |
| Reintentos | UUID/cuerpo estable, replay, conflicto al cambiar solicitud; sin confirmación anticipada | Red móvil real y reconexión |
| Finalización | Conserva presentes; genera ausente/justificada para vigencia local inclusiva | Casos del calendario institucional |
| Corrección | Presente/ausente con motivo y auditoría, conserva justificaciones resueltas | Reapertura controlada pertenece a fase 3 |
| Permisos | Solicitud preventiva/posterior, evidencia privada 5 MB, resolver solo docente/coordinación | MIME/límites/descarga en servidor definitivo |
| Avisos internos | Cambio/cancelación/solicitud/resolución; lectura por propietario | No hay envío externo |
| Gráficas | Conteos reales por estado/resultado/método y año; ámbito del usuario | Porcentaje oficial pendiente de política |

Salida de código: formularios y acciones operativos, reglas/URLs ajenas/duplicados/caducidad cubiertos,
calendarios anteriores preservados, eventos y decisiones auditados sin secretos. La evidencia de
PGlite y NDEF simulado está documentada; no equivale a aceptación completa del dispositivo o servicio nativo.
Ejecutar CI y `tests/Concurrency/attendance-races.php` con PostgreSQL 17 real y documentar el recorrido NFC.

## Fase 3 · Seguimiento, cierre y entrega · Miembro 3

Depende de datos reales de sesiones y resultados de fase 2. Los avisos internos y conteos básicos ya
funcionan; no repetirlos como otra aplicación ni reemplazarlos por estadísticas inventadas.

1. Confirmar política: 75 %, denominador, tardanzas, justificadas, plazos, cancelaciones y retiros.
   Versionar política aprobada. El `policy_version` existente es una propuesta de demostración.
2. Seguimiento provisional por estudiante/cómputo: cálculo reproducible, historial/calendario,
   estados pendientes y diferencia entre datos provisionales y resultado cerrado.
3. Cierre transaccional: validar clases/solicitudes, bloquear escrituras, snapshot de resultados/política,
   numerar versión y generar PDF privado; reintentar no duplica cierre. Diseñar reapertura/corrección
   posterior explícita, con historial de versiones. No cambiar closed a open mediante un selector común.
4. PDF privado por Policy y snapshot; noticias por ciclo/grupo; avisos derivados de cierre si corresponde.
5. Integración: CI/Compose/PostgreSQL nativo, Android/HTTPS/cookies, accesibilidad, sin demo,
   respaldo/restauración probado, manuales técnico/usuario y demostración final.

Salida: cálculo/PDF usan el mismo snapshot, cierre no admite pendientes ni escrituras tardías,
privacidad comprobada y restauración reproducible. No afirmar despliegue si solo existe configuración.

## Contratos de continuidad

- Offering: materia/ciclo/grupo; Enrollment estable con fechas inclusivas y acceso histórico.
- CycleFormat define fechas de un ciclo; AssessmentPeriod es la copia por grupo, no el mismo registro
  compartido para cerrar todos los grupos. Un formato utilizado nunca cambia los grupos anteriores.
- AcademicRegistry / AcademicSessionLock: ciclo → grupo → cómputo/sesión → recurso. RegisterAttendance
  toma primero advisory lock global por event_id, inclusive entre solicitudes de distintos ciclos.
- Una clase completada tiene resultados para inscritos vigentes; no convertir una presencia a ausencia
  desde registro. Correcciones auditadas y justificaciones conservadas; cierre es una acción distinta.
- Datos/enlaces privados por `visibleTo` + Policy. Menú o rol general no basta para autorizar un recurso.
- AuditRecorder: whitelist explícita por entidad; sin tokens/contraseñas/huellas/rutas de evidencia.
- Instantes UTC, fechas/horas de interfaz y vigencia en America/El_Salvador. Comparar enums como enums.
- CSV y lote manual comparten review/confirm, nonce de sesión y acciones. El único CSV de actualización
  permitido actualmente cambia dos años de estudiantes por carné; no sobreescribe otros campos.
- Fortify + active + auth.session + CSRF; NFC usa sesión web, sin Sanctum/ESP32/HCE ni cola offline.
- Trabajar feature/... con issue/criterios, revisión de otro miembro y CI; nuevas migraciones únicamente.
  Cada incremento actualiza README, CHANGELOG y project-state. Las etiquetas no prueban publicación.

La guía propone seis semanas/12 horas semanales por persona: primeras dos organización y viabilidad NFC,
siguientes dos asistencia/permisos, últimas dos seguimiento e integración. Ajustar fechas con el equipo;
esta entrega no inventa fechas de compromiso ni los nombres pendientes.
