# Evidencia local de A01 · 17–18/10/2026 (El Salvador)

Registro histórico anterior a la tabla y a la autorización de normalización. La versión vigente del
contrato está en attendance-calculation-contract.md; evidencia nueva en a01-table-verification.md.
Las decisiones sobre pantalla independiente y conservación de políticas demo se sustituyeron después.

Base `4873cbf`, rama `feature/p3a-a01-calculo-asistencia`. Trabajo sin commit, sin publicación
y sin cambiar release 0.3.2. Docker Compose real sobre Windows; PHP 8.4.26, PostgreSQL 17.11,
Node 24.22.0. No se usó PGlite en esta ejecución.

Continuación del 18/10: se revisaron los cambios existentes sin reimplementar A01. La sesión
de la última batería dejó de estar disponible tras la interrupción. Los contenedores estaban
detenidos; se iniciaron los mismos con `docker compose start db app`, sin recrearlos ni borrar volúmenes.
Se repite únicamente la batería PHP cuyo resultado final faltaba confirmar; Composer, Node y
las carreras nativas ya tenían resultados observados y no se repiten sin cambios que lo justifiquen.

## Protección de datos

Antes de las pruebas se ejecutó `scripts/check-test-database.php` dentro del mismo entorno Compose:
APP_ENV=testing, conexión pgsql, configuración siaa_test y `current_database()` de lectura y escritura
igual a siaa_test. El preflight se encadenó con `&&` a los comandos que usan BD.
RefreshDatabase solo reconstruyó esta base desechable, según autorización expresa del usuario.
No se ejecutaron migraciones ni seed en siaa, ni key:generate, down -v, commit, push o merge.

Se preparó un .env.testing temporal, ignorado por Git, con credenciales locales sin imprimirlas
y la clave existente, sin regenerarla. Se retiró al terminar, junto con la copia diagnóstica de la sonda
en /tmp; sus metadatos confirmaron que seguía siendo el archivo creado para esta tarea.
.env no se modificó. El cambio previo de .env.example conserva
la misma huella SHA-256. Microsoft/Windows/PowerShell/ModuleAnalysisCache queda ajeno al incremento.

## Comandos y resultados

Los comandos se ejecutaron desde la raíz del proyecto. La opción de Git para Composer es
solo del proceso del contenedor: no se cambió safe.directory global ni la configuración del checkout.

| Comando | Resultado |
| --- | --- |
| `docker compose exec -T -e APP_ENV=testing -e DB_DATABASE=siaa_test app php scripts/check-test-database.php` | PASS: lectura y escritura efectivas siaa_test |
| `docker compose exec -T -e GIT_CONFIG_COUNT=1 -e GIT_CONFIG_KEY_0=safe.directory -e GIT_CONFIG_VALUE_0=/var/www/html app sh -c 'composer validate --strict && composer check-platform-reqs'` | JSON válido; requisitos de plataforma correctos |
| `docker compose exec -T app vendor/bin/pint --test` | PASS, 160 archivos; la posterior corrección de la sonda HTTP pasó también Pint |
| `docker compose run --rm node npm test` | PASS, 14/14 |
| `docker compose run --rm node npm run build` | PASS; 71 módulos, 33.06 s; advertencia Sass descrita abajo |
| `docker compose exec -T -e APP_ENV=testing -e DB_DATABASE=siaa_test app sh -c 'php scripts/check-test-database.php && php artisan test --compact --display-warnings --fail-on-warning'` | PASS final 18/10: 124/124, 728 aserciones, sin warnings; 128.00 s, código de salida 0 |
| `docker compose exec -T -e APP_ENV=testing -e DB_DATABASE=siaa_test app sh -c 'php scripts/check-test-database.php && php tests/Concurrency/attendance-races.php --env=testing && php tests/Concurrency/attendance-summary.php --env=testing'` | PASS, cuatro carreras existentes y dos lecturas concurrentes nuevas |
| `git diff --check` | PASS |

## Hallazgos corregidos durante la verificación

- Un helper nuevo colisionaba con TestCase::session; se renombró a createSession.
- El fixture cancelado necesitaba el motivo obligatorio exigido por PostgreSQL; se corrigió.
- La primera batería completa tuvo 123 casos correctos y un fallo de DockerEnvironmentTest.
  La sonda HTTP usaba un segundo por petición y no obtenía respuesta a tiempo en el montaje Windows.
  Un ensayo inicial con cinco segundos respondió, pero una batería posterior volvió a agotar ese tiempo.
  El 18/10 se midieron 14.36 segundos hasta obtener respuesta válida con una copia temporal de la prueba.
  Se ajustó la sonda a 30 segundos por petición y un plazo global de arranque de 45 segundos,
  conservando las mismas aserciones de host/puerto/base y el fallo ante respuesta ausente/incorrecta.
  Los errores esperables de conexión durante el arranque ahora se capturan con Guzzle (ya instalado),
  en vez de `@file_get_contents`, que producía warnings PHPUnit aunque luego la respuesta fuera correcta.
  No se cambió RefreshDatabase, tests/TestCase.php, phpunit.xml ni CI.
- Composer inicialmente informó propiedad Git dudosa del bind mount; la excepción acotada al proceso
  permitió repetir validate y requisitos sin advertencias de propiedad, sin cambios globales.
- Tras ajustar la espera con la medición real, la batería completa del 18/10 pasó, incluida la sonda
  HTTP de infraestructura. Se mantienen activas --display-warnings y --fail-on-warning.

## Qué demuestran las pruebas

Los 13 casos nuevos cubren fixtures compartidos, comparación sin redondeo (incluido 75.00 mostrado
con fracción inferior a 75 %), cero sesiones, justificadas aprobadas, pendientes/rechazados,
faltantes/inconsistencias sin escritura, retiro local inclusivo, categorías de sesiones,
versiones demo/desconocidas, privacidad, rangos inválidos, pantalla y asignación solo a nuevos cómputos.

Las cuatro carreras existentes prueban UUID/cuerpo repetido, métodos simultáneos, UUID/cuerpos
distintos y registro frente a finalización. Las dos nuevas usan procesos independientes y observan
el estado de espera real en pg_stat_activity: el lector espera el commit de SessionWorkflow::finish
y AbsenceWorkflow::resolve, y obtiene resultados/permisos coherentes. Los scripts limpian sus fixtures.

## Archivos del incremento

- Nuevos: `app/Services/Attendance/AttendancePolicy.php`, `AttendanceCalculator.php`,
  `app/Queries/AttendanceSummary.php`, `app/Http/Controllers/AttendancePolicyController.php`,
  `resources/views/attendance/policy.blade.php` y `scripts/check-test-database.php`.
- Integración: `config/attendance.php`, `app/Actions/Academic/AuditRecorder.php`,
  `routes/web.php` y `resources/views/components/sidebar.blade.php`.
- Pruebas nuevas: `tests/Feature/AttendanceCalculationTest.php`, `AttendancePolicyTest.php`,
  `tests/Fixtures/attendance-calculation.php` y `tests/Concurrency/attendance-summary.php`.
- Verificación de infraestructura: `tests/Infrastructure/DockerEnvironmentTest.php`, limitada a
  la petición HTTP, sus reintentos y tiempos medidos; conserva las aserciones existentes.
- Documentación: README, AGENTS, CHANGELOG, `docs/project-state.md`, `development-phases.md`,
  `decisions.md`, `database.md`, `test-results.md`, `attendance-calculation-contract.md` y este archivo.
- `.env.example` es el cambio previo del usuario, conservado; no pertenece al incremento.
  `Microsoft/` queda sin seguimiento y fuera del inventario de entrega.

## Límites

- Sass advierte que la opción `mixed-decls` en vite.config.js es obsoleta. La compilación termina;
  no se modificaron dependencias ni configuración de estilos en A01.
- Se probaron rutas Blade mediante HTTP de pruebas Laravel; no se realizó revisión visual manual
  de la pantalla en navegador/celular. No se afirma NFC físico, GPS, túnel público ni CI remoto.
- No hay nueva interfaz de porcentajes, rankings, certificación, cierre ni PDF. La integración
  B01/B02/A03 consumirá el contrato documentado. Políticas existentes permanecen sin reasignación.
- La lectura compartida puede esperar escrituras de cualquier grupo del mismo ciclo. No cubre
  SQL externo que omita el protocolo de locks. Lotes colectivos necesitan su propia lectura conjunta.
