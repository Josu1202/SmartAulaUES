# Plan de tres fases · SmartAulaUES

Decisión del usuario: 13/10/2026. Un responsable humano por fase; cada uno trabaja módulos completos.
La guía original reparte especialidades A/B/C. Este plan cambia el reparto, conserva RF01–RF08 y sus
restricciones. Miembro 1: usuario. Miembros 2 y 3: nombres pendientes. No se asignan personas ficticias.

## Fase 1 · Cuentas y organización académica · Miembro 1

Entrega inicial: v0.2.0. Requisitos: RF01, RF02, organización de cómputos de RF03 y auditoría de RF08.

| Función | Criterio de aceptación | Estado |
| --- | --- | --- |
| Cuentas | Crear con perfil/carné, correo único, contraseña confirmada; editar/desactivar sin perder historial | Implementado |
| Recuperación y cambio de contraseña | Coordinación requiere motivo; titular valida contraseña actual; revoca sesiones | Implementado |
| Ciclos y catálogo | Código único, fechas válidas, estado y preservación de referencias | Implementado |
| Grupos | Ciclo/materia/docente activo, código único por ciclo/materia y cupo positivo | Implementado |
| Cómputos | Número único por grupo, dentro del ciclo, sin superposición; no mover sesiones fuera del rango | Implementado |
| Inscripciones | Estudiante activo, unicidad, vigencia dentro del ciclo, cupo transaccional y retiro inclusivo | Implementado |
| CSV | Encabezados fijos, preview, errores, caducidad, confirmación completa y revalidación | Implementado |
| Bitácora | Actor, acción, datos no secretos, motivo; misma transacción que la escritura | Implementado |
| Entorno/documentación | Instalación/actualización, pgAdmin opcional, contratos, pruebas y continuidad | Implementado; Docker/CI real por verificar |

No pertenece a fase 1: programar clases desde formularios, guardar asistencia, alta de etiquetas,
resolver permisos ni calcular/cerrar porcentajes. Las sesiones del seed son demostración de consulta.
Esta entrega no genera cuentas institucionales reales ni importa permisos de coordinación por CSV.

Ruta de aceptación humana: recorrer README con cuentas de tres perfiles; ejecutar CI y Compose;
consultar datos con pgAdmin opcional. La evidencia de pruebas está en docs/test-results.md.

## Fase 2 · Clases, asistencia y permisos · Miembro 2

Depende de usuarios, grupos, cómputos e inscripciones de fase 1. Debe conservar las reglas de escritura
académica y el aislamiento por recurso. Entregas pequeñas sugeridas: v0.3.0, v0.4.0 y v0.5.0.

1. Programación de sesiones: fechas/horario dentro del cómputo y ciclo, modalidades presencial/en línea/evento,
   transición scheduled → in_progress → completed; cancelación y reprogramación requieren motivo.
2. Credenciales NDEF: crear pendiente, escribir/comprobar etiqueta, confirmar alta, revocar; guardar hash
   del token, no token en claro. Comprobar Android/Chrome real sobre HTTPS y al menos cinco etiquetas.
3. Lector web: docente autenticado y asignado al grupo; reader_session/portal vigente. La página diagnóstica
   existente no se convierte en evidencia de asistencia hasta integrar servidor, validaciones e idempotencia.
4. Una acción RegisterAttendance compartida por NFC/QR/enlace/manual. Validar actor/recurso, ventana,
   método, credencial, inscripción vigente en la fecha y sesión no cancelada/cerrada. Una fila por
   inscripción/sesión. UUID idempotente ligado al cuerpo: repetición idéntica conserva resultado y
   reutilización con otro cuerpo se rechaza. Pruebas de concurrencia con PostgreSQL nativo.
5. QR/enlace temporales: autenticación del estudiante, token como hash, caducidad y anti-reuso; nunca
   confiar en IDs enviados por el navegador. Manual autorizado al docente del grupo/coordinación.
6. Finalización con ausentes, corrección con motivo, permisos/justificaciones y evidencias privadas:
   acceso solo al estudiante titular/docente asignado/coordinación; resolución auditada y notificación.

Criterios de salida: datos consistentes entre métodos; estudiantes de otro grupo, sesiones cerradas,
retirados/inactivos, tokens vencidos y docentes ajenos bloqueados. Reintentos y llamadas simultáneas
no duplican resultados. Las evidencias no se sirven desde public/storage. Toda transición tiene pruebas.

## Fase 3 · Seguimiento, cierre e integración · Miembro 3

Depende de clases realizadas, resultados/justificaciones y correcciones de fase 2. Entregas sugeridas:
v0.6.0 y v1.0.0-demo. Preparar los criterios/política con coordinación antes de calcular.

1. Seguimiento provisional: porcentaje reproducible por estudiante/cómputo, timeline/calendario,
   avisos y notificaciones internas; diferencia entre valor provisional y resultado cerrado.
2. Política: confirmar 75 %, tardanzas, justificadas, denominador, vigencias, clases canceladas,
   plazos de solicitud/corrección y tratamiento de retiros. Versionar política aprobada.
3. Cierre transaccional: validar clases, resolver pendientes, bloquear nuevas escrituras, snapshot
   de resultados/política, numerar versión y generar PDF privado verificable. Reintentar no crea
   cierres duplicados. Reapertura/corrección posterior debe diseñarse explícitamente y auditarse;
   no habilitar simplemente un selector de estado para cambiar closed a open.
4. Descargas por Policies: PDF del recurso autorizado; ninguna evidencia/reporte público adivinable.
5. Integración y entrega: pruebas de tres perfiles, accesibilidad/responsividad, HTTPS/cookies,
   configuración sin demo, backup/restore probado, manual técnico/usuario y demostración final.

Criterios de salida: cálculo y PDF usan el mismo snapshot; cierre no admite inconsistencias ni
pendientes; acceso privado comprobado; restauración reproduce datos; CI y recorrido del dispositivo
real documentados con versión, entorno y resultado. No afirmar despliegue si solo se preparó configuración.

## Coordinación del equipo

La guía propone seis semanas: 216 h disponibles (3 × 12 × 6), 180 h de alcance y 36 h de reserva.
Planificar las funciones con ese presupuesto; recortar extras antes de comprometer el registro y cierre.
Semanas 1–2: fase 1 y viabilidad NFC. Semanas 3–4: fase 2. Semanas 5–6: fase 3 y estabilización.
Las fases pueden preparar trabajo en paralelo; integrar depende de contratos, no solo del calendario.

Cada responsable crea issues pequeños con perfil/flujo/errores/criterios y ramas feature/...;
implementa servidor/vista/pruebas de su módulo y recibe revisión de otro miembro. main solo recibe
incrementos revisados con CI. docs/project-state.md identifica estado, rama, versión y tarea siguiente.
No cambiar migraciones ya compartidas; añadir nuevas. No mezclar nombres técnicos/branding ni reemplazar
la arquitectura. El responsable de fase 3 coordina pruebas finales; cada responsable prueba su trabajo.

## Contratos entre fases

- `Offering` identifica materia/ciclo/grupo; todas las sesiones/cómputos/inscripciones pertenecen a esa oferta.
- `Enrollment` tiene identidad estable y fechas inclusivas. El acceso histórico no permite registrar
  asistencia fuera de vigencia. No borrar/recrear la inscripción para liberar una plaza.
- `AcademicRegistry` bloquea ciclo → grupo → recurso. Nuevas escrituras de clase/cierre deben adoptar
  el mismo orden y comprobar ciclo/period state vigente dentro de la transacción. Locks de cupo no
  sustituyen pruebas concurrentes con PostgreSQL nativo.
- `AssessmentPeriod.policy_version` es la propuesta demo actual. El cierre requiere política aprobada
  y snapshot inmutable. Fase 1 no calcula porcentajes ni produce reportes oficiales.
- `ClassSession.state` es enum `SessionState`; comparar enum o `->value`, no string directo.
- `AuditRecorder` usa listas de campos permitidos. Nuevos modelos/acciones requieren un snapshot
  explícito; nunca serializar el modelo completo con contraseñas/tokens/rutas privadas.
- Consultas: `visibleTo` + Policy por recurso. Roles de menú no reemplazan autorización de URL.
- Contraseñas: Fortify normaliza correo; auth.session compara hash de sesión. Restablecimientos
  revocan sesiones en DB y remember_token; pruebas de cambios de cuenta deben simular sesiones nuevas.
- CSV actual pertenece a fase 1. Integraciones futuras no deben introducir importaciones parciales
  ni sobrescribir registros existentes con resultados antiguos.
- Futuras APIs/ESP32 se diseñan después: no activar Sanctum ni credenciales de hardware sin requerimiento.
