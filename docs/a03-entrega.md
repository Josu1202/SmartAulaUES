# Entrega A03 para revisión y publicación manual

Estado revisado: rama `feature/p3a-a03-cierres-reapertura`, HEAD `bf07b01`, versión **0.3.2**.
A01 y A02 ya están integrados en la base; A03 sigue como cambios locales. El índice está vacío.
La referencia local origin/main coincide con bf07b01; no se consultó el remoto mediante fetch.
La migración A03 está aplicada en la base local siaa como **batch 3**, confirmado por consulta de lectura.

Esta entrega prepara instrucciones. El agente no ejecutó staging, commit, push, creación de PR,
merge, migraciones ni inicio del scheduler durante esta preparación. Solo revisó código/estado y
actualizó esta documentación, la descripción del PR y la lista explícita de archivos.

## 1. Funcionalidades reales

Administración es el rol existente **Coordinación**. «Sistema» identifica ejecuciones automáticas,
sin inventar una cuenta humana. Snapshot significa una copia completa de los resultados al cerrar.

| Funcionalidad | Cómo funciona y cuándo se ejecuta | Quién puede utilizarla | Dónde verla en la interfaz |
| --- | --- | --- | --- |
| **Nuevo: inicio real y finalización diaria de respaldo** | Al iniciar se guarda la hora real. El procesador finaliza sesiones en curso después de terminar el día local de ese inicio y una vez vencido su horario. Conserva presentes, genera faltantes como ausentes/justificados y cierra portales/lectores. Se intenta cada cinco minutos con scheduler activo. | Sistema; docente propio y Administración conservan la finalización manual existente. | Sesiones y asistencia → Abrir sesión: inicio real y estado Finalizada. Fuera de horario desaparecen apertura, lector y registro manual; quedan finalización, revisión y diagnóstico. |
| **Nuevo: Sin inicio registrado** | Al procesar el cierre de un cómputo vencido, una programada sin evidencia de inicio ni permisos pendientes/aprobados pasa a este estado. Se conserva, sin ausencias y fuera del denominador. Vencer el horario de una clase por sí solo no la clasifica. | Sistema o Administración al validar/cerrar; los demás consultan según su ámbito. | Lista/filtro de Sesiones y asistencia, detalle y conteos de Resultados de asistencia del grupo. |
| **Nuevo: cierre de cómputo con validación** | Después de toda la fecha final local: finaliza elegibles, clasifica nunca iniciadas, comprueba datos y guarda cierre/snapshot juntos. Los impedimentos mantienen el cómputo abierto y se reintentan sin duplicar auditorías idénticas. | Sistema para cierres iniciales; solo Administración puede cerrar manualmente. | Materias y grupos → grupo → Resultados de asistencia → seleccionar cómputo → Validar y cerrar cómputo; impedimentos y fecha/versión al cerrar. |
| **Nuevo: cierre de ciclo** | Tras terminar su fecha final, exige cómputos definidos y cerrados con snapshots válidos en todos sus grupos, sin sesiones o permisos pendientes. Conserva los grupos. | Sistema para cierre inicial; Administración para cierre manual/reapertura. | Ciclos académicos: Validar y cerrar ciclo, fecha de cierre y detalles de impedimentos por grupo/cómputo. |
| **Nuevos cierres efectivos y protección ampliada** | Una vez cerrado, las escrituras que alteran resultados o elegibilidad se rechazan en el servidor: registro, corrección, permisos, sesiones/fechas e inscripciones, incluidos CSV/lotes. Los cambios independientes del resultado continúan permitidos. | Afecta a todos los actores, incluida Administración; una corrección del resultado requiere reapertura. | Acciones del detalle de sesión/permisos y administración académica; mensajes al intentar una operación incompatible. Los bloqueos no dependen de ocultar botones. |
| **Nuevo: publicación y consulta de snapshots** | Al cerrar se conserva todo el grupo, aunque la tabla tenga varias páginas, con política, fracción, conteos y hechos explicativos. Cerrado lee su copia vigente; abierto calcula en vivo. Un cerrado sin snapshot válido muestra inconsistencia. | Administración; docente de su grupo; estudiante únicamente su propia fila. | Resultados de asistencia: Cómputo cerrado, versión y fecha; porcentaje/conclusión conservados. No hay una pantalla independiente de PDF. |
| **Nuevo: historial de cierres** | El selector consulta versiones anteriores sin sobrescribirlas. Los resultados siguen siendo los de su cierre, aunque cambien los datos vivos posteriormente. | Los mismos ámbitos autorizados, también para versiones anteriores. | Resultados de asistencia → Historial de cierres → Consultar versión. |
| **Nuevo: reapertura administrativa** | Motivo obligatorio y auditoría. Vuelve a cálculo vivo En revisión, conserva el historial y exige cierre manual para crear N+1. Reabrir el ciclo no reabre todos sus cómputos. No reactiva portales. | Solo Administración/Coordinación activa. | Reabrir cómputo en Resultados; Reabrir ciclo en Ciclos académicos. |
| **Nuevo: resolución administrativa de inconsistencias** | Recuperar inicio solo con evidencia auditada inequívoca; conciliar finalización de una vencida contradictoria/finalizada; reabrir una resolución de permiso con motivo y volver a resolverla. No registra presencias retrospectivas por conciliación. | Solo Administración para operaciones extraordinarias; la resolución ordinaria de permisos conserva sus actores existentes. | Detalle de sesión: Recuperar inicio desde la auditoría / Revisar finalización y resultados. Detalle de permiso: Reabrir resolución para revisión administrativa. |

**Reutilizado:** inicio/finalización manual, presentes/ausentes/justificadas, solicitud y resolución
ordinaria de permisos, tabla y cálculo A01, paginación, roles/policies, bitácora, registrador único,
idempotencia y locks. AssessmentPeriod y ReportVersion ya existían. A03 conecta y amplía esas piezas
para un cierre verificable; no vuelve a implementar A01/A02 ni agrega PDF/CSV de reportes.

## 2. Qué cambia por perfil

| Perfil | Cambios visibles y acciones |
| --- | --- |
| Administración / Coordinación | Desde el grupo valida/cierra y reabre cómputos; desde Ciclos académicos valida/cierra y reabre ciclos. Ve causas de bloqueo, snapshots e historial. En sesiones puede revisar finalizaciones o recuperar inicio fiable con motivo. En permisos puede reabrir decisiones extraordinarias. También queda sujeta a bloqueos: no puede forzar un cierre ni cambiar resultados cerrados. |
| Docente | En sus grupos ve inicio real, Finalizada/Sin inicio registrado, impedimentos y resultados actuales/históricos. Conserva iniciar/finalizar, registrar/corregir y resolver permisos según las reglas anteriores mientras el ámbito permita escribir. Después del horario no se ofrecen apertura/lector/manual, pero siguen finalización y medición sin registrar. No tiene cierre manual, reapertura ni revisión administrativa extraordinaria. |
| Estudiante | En Mis materias → grupo ve su resultado provisional, cerrado o En revisión y su historial. En sesión ve su fila y sus permisos. No recibe filas ni impedimentos colectivos de otros alumnos. Registro y solicitud de permiso conservan restricciones de horario/estado; no tiene acciones de cierre ni reapertura. |

## 3. Reglas definitivas y diferencias aclaradas

- **Tiempo:** instantes UTC; días académicos America/El_Salvador. Una fecha final 22/10 permanece
  abierta hasta 22/10 23:59:59 local. El primer intento posterior puede cerrar desde 23/10 00:00
  local, equivalente a 06:00 UTC. La ejecución efectiva depende del scheduler de cinco minutos.
- **Respaldo diario:** no basta vencer ends_at ni se esperan 24 horas. Debe haber terminado el día
  del inicio real; si la clase válida atraviesa medianoche, se espera además su fin programado.
- **Finalización:** conserva resultados existentes. Solo el finalizador genera ausencias/justificadas
  faltantes para la fecha académica de la sesión; alta y retiro son inclusivos. Los permisos pendientes
  permiten finalizar la clase, pero bloquean el cierre del cómputo.
- **Bloqueos de cómputo:** fecha final vigente, sesión sin resolver/inicio antiguo desconocido,
  programada con asistencias/portal/evidencia contradictoria, permisos pendientes o preventivos aprobados
  que impiden clasificarla, calendario fuera de rango, datos fuera de vigencia, resultado faltante o
  incompatible con un permiso y reglamento no calculable. No existe «forzar cierre».
- **Ciclo:** exige fecha final concluida, cómputos definidos para cada grupo, todos cerrados con
  snapshot válido y sin sesiones o permisos pendientes. Indica grupo/cómputo que lo impide. La edición
  general de estado no puede eludir el flujo de cierre/reapertura.
- **Cálculo A01:** mínimo 75 %, numerador presentes + justificadas con permiso aprobado,
  denominador sesiones finalizadas elegibles. Comparación exacta 4 × favorables >= 3 × evaluables;
  el redondeo mostrado no cambia la conclusión. Canceladas/sin inicio no cuentan. Sin evaluables:
  porcentaje y conclusión nulos. Los faltantes/inconsistencias no se convierten en ausencia al consultar.
- **Snapshot:** completo y atómico con el cierre. Versiona identidad/vigencia, regla, fracción,
  resultados y hechos por sesión; no guarda coordenadas, contraseñas/tokens ni documentos/motivos privados.
  La lectura autorizada filtra al estudiante. No se recalcula en vivo un cerrado inconsistente.
- **Reapertura:** solo Coordinación activa; motivo de 5–1000 caracteres tras validación. Conserva
  versiones e historial de actor/fecha/motivo. El marcador de revisión no se borra y evita recierre
  automático. El recierre manual genera N+1 exactamente una vez; repetir la solicitud no duplica.
  Si el ciclo está cerrado: reabrir ciclo → cómputo afectado → corregir → cerrar cómputo → cerrar ciclo.
- **Permisos extraordinarios:** reabrir una decisión la devuelve a pendiente y conserva la decisión
  anterior en bitácora. Se resuelve nuevamente por el flujo ordinario; una justificación incompatible
  se concilia explícitamente en la sesión. No hay una aprobación/reparación silenciosa del scheduler.
- **Sesiones antiguas:** no se rellena started_at. La recuperación exige una sola transición auditada
  scheduled → in_progress con after.started_at explícito, dentro del horario y no futuro. El created_at
  antiguo corresponde al inicio de transacción y no acredita el inicio real. Sin evidencia: finalizar manualmente.
- **Concurrencia:** conserva orden ciclo → grupo → cómputo/sesión → recurso y advisory lock/replay
  del registrador. Un período bloqueado no impide procesar otros; cada recurso confirma su transacción.

No se encontró una desviación nueva respecto del acuerdo vigente. Hay dos precisiones frente a una
lectura simplificada del pedido inicial: una clase que cruza medianoche no se trunca a las 00:00 y
un inicio antiguo no se reconstruye usando created_at. Ambas quedaron acordadas/documentadas en AGENTS
y el contrato A03. Se mantienen sin ampliar QR/enlace/NFC/manual ni cambiar la fórmula/límites A02.

## 4. Aplicación local sin borrar datos y scheduler

La **única migración nueva** es `2026_10_22_000100_add_academic_closures.php`:

| Tabla | Cambio |
| --- | --- |
| class_sessions | started_at/finalized_at nullable, índice estado/inicio y CHECK con unstarted. |
| assessment_periods | current_report_id, reopened_at, closure_issues, closure_checked_at. closed_at ya existía. |
| academic_cycles | closed_at, reopened_at, closure_issues, closure_checked_at. |
| report_versions | generated_at, origin, snapshot_hash; generated_by admite Sistema/null. Tabla, número único de versión y campos de exportación ya existían. |
| attendances | recorded_by admite Sistema/null. |

No rellena historia ni ejecuta seeders. En **este equipo ya está aplicada (batch 3)**. migrate no
la repetirá; si aparecen otras migraciones pendientes, revísalas antes de aplicar todo el conjunto.
No usar migrate:fresh/refresh ni docker compose down -v para instalar A03. El rollback rechaza
eliminar historia A03; una reversión posterior necesita respaldo y versión de aplicación compatibles.

Los siguientes comandos son para ejecución personal. Este documento no los ejecuta.

### 4.1 Iniciar y comprobar la base efectiva de la aplicación

```powershell
Set-Location (Join-Path $env:USERPROFILE 'Downloads\SmartAulaUES')
docker compose up -d app
if ($LASTEXITCODE -ne 0) { throw 'No se inició Compose.' }

@'
<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$connection = Illuminate\Support\Facades\DB::connection();
$write = $connection->getPdo()->query('select current_database()')->fetchColumn();
$read = $connection->getReadPdo()->query('select current_database()')->fetchColumn();
if (!$app->environment('local') || $write !== 'siaa' || $read !== 'siaa') {
    throw new RuntimeException('Se esperaba local/siaa en lectura y escritura. Revisar configuracion.');
}
echo "PASS local/siaa en lectura y escritura.\n";
'@ | docker compose exec -T app php
if ($LASTEXITCODE -ne 0) { throw 'Base efectiva distinta de la esperada; detener aplicacion de migraciones.' }
```

No copia archivos .env ni regenera APP_KEY. Si tu configuración efectiva cambió deliberadamente,
revisa esa diferencia antes de adaptar la guarda; no la omitas para ejecutar sobre otra base por accidente.

### 4.2 Respaldo privado y migración incremental

```powershell
$a03BackupDir = Join-Path $env:LOCALAPPDATA 'SmartAulaUES\backups'
New-Item -ItemType Directory -Path $a03BackupDir -Force | Out-Null
$a03BackupName = 'pre-a03-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.dump'
docker compose exec -T db pg_dump -U siaa -d siaa -Fc -f "/tmp/$a03BackupName"
if ($LASTEXITCODE -ne 0) { throw 'No se completo el respaldo.' }
docker compose cp "db:/tmp/$a03BackupName" (Join-Path $a03BackupDir $a03BackupName)
if ($LASTEXITCODE -ne 0) { throw 'No se copio el respaldo al equipo.' }

docker compose exec -T app php artisan migrate:status
if ($LASTEXITCODE -ne 0) { throw 'No se pudo consultar migraciones.' }
docker compose exec -T app php artisan migrate --pretend
if ($LASTEXITCODE -ne 0) { throw 'Fallo la vista previa.' }
```

Revisa la salida. En este checkout A03 debe figurar aplicada. Si solo estuviera pendiente A03 en
otra copia compatible, continúa; si hay cambios inesperados, detente y revísalos primero.

```powershell
docker compose exec -T app php artisan migrate
if ($LASTEXITCODE -ne 0) { throw 'Fallo la migracion; revisar antes de continuar.' }
docker compose exec -T app php artisan optimize:clear
if ($LASTEXITCODE -ne 0) { throw 'Fallo la limpieza de caches de ejecucion.' }
docker compose run --rm node npm run build
if ($LASTEXITCODE -ne 0) { throw 'Fallo el build.' }
docker compose exec -T app php artisan schedule:list
```

El dump se genera dentro del contenedor y se copia como archivo para conservar sus bytes también
en Windows PowerShell 5.1. Queda fuera del repositorio. migrate es incremental; optimize:clear
limpia cachés de ejecución, no tablas académicas. No hay dependencias nuevas de Composer/npm en A03.

### 4.3 Activar el scheduler

**Docker no lo inicia automáticamente.** compose.yaml no contiene un servicio scheduler y el CMD
del Dockerfile solo inicia `artisan serve`. schedule:list muestra configuración; no ejecuta tareas.

En una **segunda terminal PowerShell**, desde el repositorio:

```powershell
Set-Location (Join-Path $env:USERPROFILE 'Downloads\SmartAulaUES')
docker compose exec -T app php artisan schedule:work
```

Mantén esa terminal, Docker y el equipo funcionando. Ctrl+C detiene ese worker. Examina eventos
cada minuto: A03 es elegible cada cinco minutos y prune geográfico continúa diario. Cuando el equipo
vuelva a funcionar, el procesador recuperará vencidos en una ejecución posterior. No garantiza ejecución
durante suspensión/apagado. **Este worker procesa siaa real**, no los fixtures de verificación.

Para una pasada manual inmediata del procesador A03, cuando quieras aplicar todos los vencidos:

```powershell
docker compose exec -T app php artisan attendance:process-closures
```

En hosting con PHP del proyecto, configurar **un solo mecanismo**, cron cada minuto o worker supervisado:

```cron
* * * * * cd /ruta/SmartAulaUES && php artisan schedule:run >> /ruta/privada/scheduler.log 2>&1
```

No se añade cron ni servicio persistente con esta entrega. Su instalación y evidencia operativa
en hosting siguen pendientes; tener Docker/web levantados no demuestra automatización activa.

## 5. Verificación y límites

Evidencia ejecutada del 23/10 en [a03-verificacion-final.md](a03-verificacion-final.md), no una nueva
batería ejecutada al redactar esta entrega. En esta preparación se revisó el diff y diff --check.

| Escenario ejecutado | Resultado concreto |
| --- | --- |
| Diez programadas, ocho impartidas y dos nunca iniciadas | Ocho completed + dos unstarted; el titular presente en las ocho obtuvo 8/8 = 100 %. Repetir comando no cambió asistencias/reportes/auditorías. |
| Límite A01 y permisos | 5/8 = 62.50 % no alcanza; 6/8 = 75.00 % alcanza con cinco presentes + una justificada aprobada; 7/8 = 87.50 % alcanza. Redondear 30000/40001 a 75 % visual no lo aprueba. |
| Medianoche y calendario | Antes de 00:00 no finaliza; después sí. Inicio local 23:59:58 finaliza dos segundos después; una clase válida hasta 01:00 espera esa hora. |
| Atomicidad y datos incompletos | Fallar antes o después de crear snapshot revierte cierre y cambios de su transacción; otro cómputo independiente progresa y el siguiente intento recupera el afectado. |
| Historial y privacidad | Snapshot incluye 22 alumnos, incluso segunda página. El estudiante ve una fila actual/histórica. Formularios conservan versiones 75 % → 100 % → 75 %. |
| Sesión antigua/permisos | Finalización y aprobación por formularios permiten cerrar al 100 % sin inventar started_at. Los motivos y bloqueos se muestran. |
| Scheduler y concurrencia | Dos schedule:run reales: Sistema, fecha efectiva y un solo reporte automático. Dieciséis comprobaciones nativas, incluidas esperas Lock y estado/snapshot final íntegros. |
| Interfaz fuera de horario | D01 mostraba apertura/lector/manual después del fin. Se corrigió; regresión y capturas posteriores conservan finalizar/revisar/medir y ocultan el registro. |

Resultado global: **192 pruebas PHP / 1433 aserciones sin warnings**, 39 JS, 16 comprobaciones
concurrentes, dos ejecuciones reales del scheduler y 20 capturas/medidas. Composer, build, Pint
(177 archivos) y diff correctos. Aviso Sass heredado documentado. No se atribuye a CI remoto.

**Pendientes externos:** scheduler y respaldo/restauración en hosting (B07); aceptación física
GPS/HTTPS de A02. Las pruebas locales/viewport móvil no acreditan hardware ni operación remota.

**Límites de A03:** no genera PDF/CSV de reportes, rankings/gráficas ni archivo/eliminación de grupos;
no reconstruye un inicio antiguo sin evidencia; los recursos reabiertos requieren acción manual.
La inmutabilidad se aplica mediante modelos/acciones/locks; SQL externo que los eluda queda fuera
de esa garantía. No quedan defectos conocidos pendientes de A03 según la verificación local.

## 6. Título y descripción del PR

Título para copiar:

```text
feat: cerrar cómputos y ciclos con snapshots y reapertura A03
```

La descripción completa lista para copiar está en [a03-pr.md](a03-pr.md), **sin título añadido al cuerpo**.
Sigue exactamente las tres secciones de `.github/pull_request_template.md`: Cambio y resultado,
Validación y Alcance pendiente. Incluye alcance, reutilización, migración, evidencia y límites.
Puedes abrirla y copiarla a tu portapapeles, sin crear/publicar nada:

```powershell
Get-Content -LiteralPath 'docs/a03-pr.md' -Raw -Encoding utf8 | Set-Clipboard
```

## 7. PowerShell: revisión, selección, commit, push y PR por tu cuenta

Los siguientes bloques **no fueron ejecutados por el agente**. Ejecútalos por separado y revisa
cada resultado antes de avanzar. No requieren cambiar de rama ni regenerar configuración.

### 7.1 Revisar el estado real y la selección

```powershell
Set-Location (Join-Path $env:USERPROFILE 'Downloads\SmartAulaUES')
if ((git branch --show-current) -ne 'feature/p3a-a03-cierres-reapertura') {
    throw 'Rama distinta de la revisada. Detener y revisar.'
}
if (@(git diff --cached --name-only).Count -gt 0) {
    throw 'El indice ya contiene archivos. Revisarlos antes; no mezclar staging ajeno.'
}
git status --short
git log -4 --oneline

# Lista explicita revisada: 50 archivos, incluidos estos documentos de entrega.
$a03Files = @(Get-Content -LiteralPath 'docs/a03-files.txt' -Encoding utf8)
$a03Files
$a03Missing = @($a03Files | Where-Object { -not (Test-Path -LiteralPath $_ -PathType Leaf) })
if ($a03Missing.Count -gt 0) { throw "Faltan archivos: $($a03Missing -join ', ')" }
git diff --check
if ($LASTEXITCODE -ne 0) { throw 'Revisar errores del diff antes de agregar archivos.' }
git diff --stat -- $a03Files
git diff -- $a03Files
# El diff anterior no dibuja contenido de los nuevos sin seguimiento; ver sus nombres:
git ls-files --others --exclude-standard -- $a03Files

# Actualizar solo referencias remotas; no integra ni altera tus cambios de trabajo.
git fetch origin
if ($LASTEXITCODE -ne 0) { throw 'No se pudieron actualizar las referencias remotas.' }
git log --oneline HEAD..origin/main
```

No se ejecutó fetch durante la preparación. Si ese último log muestra avances de main, revisa su
relación con A03 antes de continuar. Estos comandos no hacen rebase/merge ni force push automáticamente.

### 7.2 Agregar solo A03 y revisar el diff que irá al commit

Después de revisar el contenido de `docs/a03-files.txt` y los nuevos archivos:

```powershell
if (@(git diff --cached --name-only).Count -gt 0) {
    throw 'El indice cambio desde la revision; revisar su contenido primero.'
}
git add -- $a03Files
if ($LASTEXITCODE -ne 0) { throw 'Fallo git add; revisar el indice antes de continuar.' }
$a03Unexpected = @(git diff --cached --name-only | Where-Object { $_ -notin $a03Files })
if ($a03Unexpected.Count -gt 0) { throw "Seleccion inesperada: $($a03Unexpected -join ', ')" }
git diff --cached --check
if ($LASTEXITCODE -ne 0) { throw 'Corregir el diff preparado antes del commit.' }
git diff --cached --stat
git diff --cached --name-only
git diff --cached
# Debe producir salida vacia:
git diff --cached --name-only -- .env .env.example Microsoft
git status --short
```

La lista contiene rutas de archivos concretos, no directorios ni comodines. Excluye `.env`, todas
las variantes de configuración privada, la modificación previa de `.env.example`, Microsoft/,
dependencias, builds, dumps, capturas, perfiles y logs. Los nuevos archivos sí aparecen completos
en `git diff --cached`, por eso esa es la revisión final del contenido antes de confirmar.

### 7.3 Commit y push, cuando apruebes personalmente ese diff

```powershell
git commit -m "feat: cerrar computos y ciclos con snapshots y reapertura A03"
if ($LASTEXITCODE -ne 0) { throw 'No se creo el commit; no continuar al push.' }
git show --stat --oneline HEAD
git status --short
git push -u origin feature/p3a-a03-cierres-reapertura
if ($LASTEXITCODE -ne 0) { throw 'El push fallo; revisar el motivo sin forzarlo.' }
```

Después del commit es normal que `.env.example` siga modificada y Microsoft/ siga sin seguimiento:
se preservan fuera del commit. Si aparece otro archivo, revísalo; no lo agregues automáticamente.

### 7.4 Abrir y revisar el PR hacia main

1. En GitHub abre el repositorio Josu1202/SmartAulaUES → Pull requests → New pull request.
2. Selecciona **base: main** y **compare: feature/p3a-a03-cierres-reapertura**. Revisa la comparación
   completa, no solo el último commit, y después Create pull request. Pega título y cuerpo preparados.
   También puedes iniciar la comparación en:
   `https://github.com/Josu1202/SmartAulaUES/compare/main...feature/p3a-a03-cierres-reapertura`.
3. En Files changed confirma que solo están los archivos A03 revisados: migración incremental,
   acciones/consultas/vistas, pruebas, CI y documentación. .env.example, Microsoft/ y artefactos privados
   deben quedar fuera. Revisa especialmente privacidad histórica, motivo obligatorio, locks y calendario.
4. Adjunta si lo deseas las capturas ficticias indicadas en el cuerpo; no subas perfiles/logs/dumps
   ni documentos privados. En Checks espera la ejecución real de SmartAulaUES CI/foundation.
5. Comprueba Composer/plataforma/Pint, build/JS, guarda de BD, batería PHP sin warnings y los tres
   scripts concurrentes. Las cifras locales anteriores no sustituyen esos resultados remotos.
6. Atiende comentarios/conflictos y verifica que la base siga siendo main. Vuelve a revisar el diff
   y los controles tras cualquier actualización. La decisión de merge queda para tu revisión posterior;
   esta entrega no ejecuta ni incluye un comando de merge.

Se conservaron .env/APP_KEY y el SHA de la modificación previa de .env.example durante esta preparación.
La versión y dependencias permanecen intactas. El estado verificable sigue siendo local, sin publicar.
