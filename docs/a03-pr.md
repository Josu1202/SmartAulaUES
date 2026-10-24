## Cambio y resultado

Las sesiones iniciadas que quedaban en curso y los cómputos vencidos requerían seguimiento manual.
A03 añade finalización diaria de respaldo y cierres iniciales recuperables, con resultados congelados
e historial. Coordinación puede reabrir con motivo y corregir antes de un nuevo cierre manual.

Por ejemplo, diez sesiones programadas que terminan como ocho finalizadas y dos «Sin inicio
registrado» producen ocho clases evaluables: un estudiante presente en las ocho obtiene 100 %.
Las dos nunca iniciadas permanecen visibles y no generan ausencias.

Alcance y cambios:

- Guardar inicio real UTC y hora efectiva de finalización. El respaldo procesa una sesión tras
  terminar el día local de su inicio en America/El_Salvador; si cruza medianoche, respeta su fin
  programado. No amplía las ventanas QR, enlace, NFC o manual.
- Clasificar las programadas sin evidencia de inicio al procesar el cierre del cómputo. Asistencias,
  portales, auditoría de inicio y permisos pendientes/aprobados impiden clasificarlas sin revisión.
- Cerrar cómputos después de toda su fecha final local: finalizar, clasificar, validar y guardar
  snapshot completo de todos los estudiantes en una transacción. Permisos pendientes, datos
  faltantes/inconsistentes o reglamento desconocido bloquean el cierre con causas visibles.
- Cerrar ciclos vencidos solo con todos sus cómputos cerrados y snapshots válidos. El cierre no
  elimina ni archiva grupos y no puede ejecutarse desde el selector genérico de estado.
- Reutilizar ReportVersion para snapshots versionados e inmutables: fracciones, política, hechos
  por sesión e identidad/vigencia del estudiante al cierre, sin coordenadas, secretos ni evidencias privadas.
- Consultar cálculo vivo al estar abierto, snapshot vigente al cerrar y «En revisión» al reabrir.
  Añadir historial autorizado; el estudiante recibe solo su fila, también en versiones anteriores.
- Reservar cierre manual/reapertura y reparaciones extraordinarias a Coordinación. Exigir motivo
  para reaperturas/revisiones, conservar versiones y requerir recierre manual de recursos reabiertos.
  Si el ciclo está cerrado, debe reabrirse antes del cómputo afectado.
- Proteger asistencia, permisos, sesiones, calendarios y elegibilidad de inscripciones cerradas,
  incluidos lotes y CSV; permitir cambios independientes del resultado.
- Programar attendance:process-closures cada cinco minutos y conservar prune geográfico diario.
  Recuperar ejecuciones perdidas, aislar fallos por recurso y evitar versiones/resultados duplicados.
- Corregir controles visibles fuera del horario: ocultar apertura/lector/manual y conservar
  finalización, revisión y diagnóstico A02 sin registrar.

Se reutilizan el calculador A01 (75 %, presentes + justificadas aprobadas, comparación exacta),
el finalizador manual, registrador único, permisos, roles/policies, auditoría y locks académicos.
AssessmentPeriod y ReportVersion ya existían; la publicación/consulta de sus cierres es nueva.
Se conservan Laravel/Blade, dependencias y versión 0.3.2.

Migración incremental única: `2026_10_22_000100_add_academic_closures.php`. Añade inicio/finalización,
estado unstarted, referencia a versión vigente, marcas de cierre/reapertura/impedimentos y metadatos
de ReportVersion; permite actor Sistema nullable en asistencias/reportes. No rellena historia ni borra datos.
Ya aplicada localmente como batch 3; en otra copia se aplica mediante `php artisan migrate`.
El rollback se rechaza cuando existe historia A03 y exige una restauración compatible.

## Validación

Ejecución local del 23/10/2026, Compose/PHP 8.4.26 y PostgreSQL 17.11 nativo. Lectura y escritura
efectivas de siaa_test verificadas antes de pruebas destructivas; sin atribuir resultados a CI remoto.

- Batería final: 192 pruebas / 1433 aserciones, sin warnings ni omisiones; incluye regresión A01/A02.
- 39 pruebas JavaScript; build Vite correcto; Composer validate/platform correctos; Pint 177 archivos
  y git diff --check correctos. El build conserva un aviso Sass heredado sobre mixed-decls obsoleto.
- 16 comprobaciones concurrentes nativas: seis de registro, dos de lectura A01 y ocho de A03.
  En A03 se observa espera Lock entre procesos y se verifica el snapshot/rango/resultado final:
  dos cierres, registro/corrección, permiso, inscripción y reapertura frente a cierre de ciclo.
- Dos schedule:run reales en testing: finalización, ausencia de Sistema, snapshot automático y ciclo
  cerrado; la segunda ejecución conserva conteos y versión. Hora efectiva de procesamiento verificada.
- Ejemplo 8/8=100 %; 5/8=62.50 %, 6/8=75.00 % y 7/8=87.50 %; justificada aprobada favorable.
  Sin evaluables mantiene porcentaje/conclusión nulos; redondear a 75 % no cambia la comparación exacta.
- Snapshot de 22 estudiantes, segunda página incluida; privacidad actual/histórica; corrupción detectada;
  fallos antes y después de crear snapshot revierten reporte, asistencias, auditoría y estado juntos.
- Edge/CDP con tres perfiles a 1440/390/320 px, 20 capturas/medidas y cero excepciones JS.
  Formularios reales conservan v1=75 %, v2=100 %, v3=75 %. Sesión antigua/permisos se resuelven sin
  inventar inicio. D01 de controles fuera de horario se corrigió y se revalidó visualmente.

Informe completo: [docs/a03-verificacion-final.md](../docs/a03-verificacion-final.md).
Contrato/operación: [docs/a03-closures.md](../docs/a03-closures.md).
Capturas privadas locales en `%TEMP%\smartaulaues-a03-final-20261023`: comparar
`coordinator-legacy-session-390.png` con `coordinator-late-fixed-320.png` y
`teacher-late-fixed-390.png`; disponibles para adjuntar voluntariamente al PR, fuera de Git.

La migración incremental y la revisión conservaron conteos/huellas académicas de desarrollo.
Se preservaron .env, APP_KEY, Microsoft/ y el cambio previo de .env.example, excluidos del incremento.

## Alcance pendiente

- Verificar scheduler y respaldos/restauración en hosting (B07). Docker Compose actual inicia el
  servidor web, pero no un scheduler: ejecutar schedule:work por separado en local o configurar
  un único cron/supervisor en hosting. Esto procesa datos reales de la instalación elegida.
- Aceptación física de GPS/HTTPS de A02; los ensayos locales no acreditan dispositivos físicos.
- A03 no genera PDF/CSV de reportes ni implementa ranking, gráficas, archivo/eliminación de grupos
  o el resto de A04–A05/B01–B07. Sin nueva release ni cierre de fase 3.
- Sesiones antiguas sin inicio fiable requieren finalización manual; recuperación administrativa
  solo con after.started_at explícito en una única transición auditada válida. No se infiere del horario
  ni de created_at, cuyo valor antiguo corresponde al inicio de transacción.
- Los cambios mediante SQL externo que eludan acciones/locks quedan fuera de la garantía de
  inmutabilidad de la aplicación; el hash detecta alteraciones accidentales del snapshot.

Veredicto local: **Listo con pendientes externos explícitos**. La creación y revisión de este PR
no equivalen a integración, despliegue ni aceptación operativa del hosting.
