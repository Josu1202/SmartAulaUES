# B05 — Escenarios ficticios versionados y actualización sin reinicialización

Base: SmartAulaUES 0.3.2; módulo B05 del Integrante 3. La lógica A01, el cierre A03 y los snapshots se consumen sin modificarlos.

## Alcance y seguridad

- **No se ejecuta automáticamente** al arrancar Compose ni mediante `DatabaseSeeder` ni durante una migración.
- Nuevo comando explícito `b05:demo small|full`, exclusivamente `APP_ENV=testing` sobre **siaa_test** o `APP_ENV=local` sobre **smartaulaues_local**; verifica el nombre efectivo con `current_database()`, la configuración PG y el nombre confirmado por el operador. Requiere **DEMO_SEED=true** y **DEMO_PASSWORD privado** de 12 caracteres como mínimo y 72 bytes UTF-8 como máximo.
- Rechaza todos los identificadores reservados `B05S-*` o `B05F-*` si hay colisiones sin manifiesto. Transacción única con advisory lock PG. Si algo falla (p. ej. el cierre histórico), no queda una carga parcial.
- La tabla `demo_scenario_runs` tiene código, versión, fecha de referencia, huella del manifiesto y conteos esperados. Una segunda ejecución **solo lee el marcador**. No modifica nombres, contraseñas, sesiones, matrículas, permisos, credenciales, snapshots ni archivos, aunque un usuario haya cambiado algo. Repetir con otra fecha, perfil o versión es rechazado; una futura evolución requiere nuevo código de escenario y migración explícita, nunca sobrescritura silenciosa.
- El escenario no crea credenciales NFC, tokens ni geolocalizaciones. Los primeros cinco estudiantes pueden asignarse a **etiquetas físicas pendientes de ensayo**, pero no se declara activación de tarjetas reales.
- No usar `migrate:fresh`, `db:wipe`, `key:generate` ni el seeder de exploración sobre datos ajenos. Migración B05 solo crea su tabla vacía. No constituye respaldo ni autoriza uso de datos demo en producción.

## Preparación y ejecución opcional (PowerShell)

En testing aislado, los tests gestionan por sí mismos `DEMO_SEED` y `DEMO_PASSWORD`. No ejecute el comando manual en esa BD mientras corre PHPUnit, porque PHPUnit la reinicializa para su suite.

Para **desarrollo local**, solo si acepta añadir estas identidades ficticias al escenario ya existente, ejecute desde la raíz del repositorio:

```powershell
# 1. Aplicar únicamente las migraciones pendientes sin eliminar datos:
docker compose -p smartaulaues-b01-pruebas -f compose.yaml exec -T app php artisan migrate --force

# 2. Dejar la contraseña solo en variable de sesión; introducirla sin eco:
$secure = Read-Host 'Contraseña privada para nuevas cuentas B05' -AsSecureString
$ptr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
try { $env:DEMO_PASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($ptr) }
finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($ptr) }

# 3. Habilitación explícita solo para este proceso del contenedor:
docker compose -p smartaulaues-b01-pruebas -f compose.yaml exec -T -e DEMO_SEED=true -e DEMO_PASSWORD app php artisan b05:demo small --reference=2026-11-08 --confirm-database=smartaulaues_local

# Opcional: sustituir small por full en otra BD vacía; small y full usan IDs distintos.
# 4. Eliminar la variable de la sesión PowerShell:
Remove-Item Env:DEMO_PASSWORD
```

Nota: para la **primera ejecución** use la fecha actual local en `--reference` (o suprima el parámetro para usarla automáticamente). Si la base ya contiene esa versión, repita la fecha original registrada; el comando nunca recalcula fechas existentes. `compose.yaml` mantiene `DEMO_SEED=false` normalmente; no alterar esa protección permanente. La contraseña nueva no queda en Git, CSV ni documentación.

## Manifiestos v1

| Métrica, creados solo por B05 | small (`B05S-V1`) | full (`B05F-V1`) |
| --- | ---: | ---: |
| Nuevas cuentas (coordinación/docentes/estudiantes) | 8 (1/1/6) | 64 (1/3/60) |
| Materias | 2 | 7 |
| Ciclos (activo relativo + histórico fijo cerrado) | 2 | 2 |
| Grupos (activos + histórico) | 2 (1+1) | 7 (6+1) |
| Inscripciones | 10 | 128 |
| Sesiones (realizadas + programadas + canceladas + histórico) | 14 | 76 |
| Versiones de reporte de cierre (snapshot real de A03) | 1 | 1 |
| Credenciales NFC / tokens físicos emitidos | 0 | 0 |

Cada grupo activo incluye ocho clases realizadas en small, diez en full, una futura y una cancelada. Todas las realizadas poseen fecha e inicio/finalización coherentes. Se diferencian presentes, ausentes, justificadas con solicitud aprobada, ausencias con solicitud pendiente/rechazada y una fila sin resultado para comprobar que no se inventa un porcentaje si falta cobertura. Las matrículas son vigentes desde el inicio del ciclo.

Los 60 estudiantes de full están distribuidos entre 1.º y 5.º año, en 6 grupos con 20 alumnos por grupo y 3 docentes (cada estudiante participa en 2 grupos). El histórico utiliza las fechas fijas **01/09/2025–15/12/2025**, cuatro clases realizadas, y se cierra a través de `ClosureWorkflow` generando una versión de `ReportVersion` inmutable. No se fuerza un snapshot artificial ni se altera el código de cierre de A03.

## Valores de muestra esperados

Los siguientes resultados son **hechos sintéticos de la fixture**, no una implementación alternativa de la fórmula oficial. Las comprobaciones de PHPUnit consultan `AttendanceSummary`, que consume la única calculadora de A01.

| Estudiante 01–06, primer grupo | small (8 clases) | full (10 clases) | Escenario |
| --- | --- | --- | --- |
| 01 | 8 presentes, 100,00 % | 10 presentes, 100,00 % | Asistencia completa |
| 02 | 6 presentes, 2 ausentes, 75,00 % | 8 presentes, 2 ausentes, 80,00 % | Asistencia con ausencias |
| 03 | 5 presentes, 1 justificada aprobada, 2 ausentes, 75,00 % | 7 presentes, 1 justificada aprobada, 2 ausentes, 80,00 % | Justificación acreditada |
| 04 | 5 presentes, 3 ausentes, 62,50 % | 7 presentes, 3 ausentes, 70,00 % | Solicitud pendiente, resultado provisional |
| 05 | 4 presentes, 4 ausentes, 50,00 % | 6 presentes, 4 ausentes, 60,00 % | Solicitud rechazada |
| 06 | 3 presentes, 4 ausentes, 1 sin resultado; porcentaje nulo | 4 presentes, 5 ausentes, 1 sin resultado; porcentaje nulo | Cobertura incompleta: revisión requerida |

En el historial fijo, el segundo estudiante tiene 3/4 presencias (75 %) y los demás 4/4 (100 %). Las tablas del dashboard y los PDF/CSV deben consumir `AttendanceSummary` / snapshot vigente, nunca sumar datos inventados. Las pruebas incluyen referencias, recuentos, hash de contraseña, repetición, colisiones y perfiles.

## Verificación de una actualización de SmartAulaUES 0.3.2

1. Haga una copia privada de la BD local para recuperación y preserve `APP_KEY`, hashes de usuario, matrículas y reportes existentes. No comparta contraseñas ni el backup.
2. En testing aislado ejecute migraciones en orden (nunca `migrate:fresh` en desarrollo), `php artisan test --filter=B05ScenarioTest`, la suite completa, Pint, npm test y Vite.
3. Sobre una copia de datos de 0.3.2 aplique `php artisan migrate --force` y compruebe que **solo** aparece `demo_scenario_runs`; B05 no se autosiembra.
4. Tome conteos/hashes antes/después. Ejecute `b05:demo small` una vez, cambie la contraseña y el nombre de un usuario sintético, repita con la misma referencia y compruebe que no se revierten. Revise el cierre histórico y el filtro por perfil. En un entorno compartido, no ejecute la carga sin autorización del equipo.
5. Para un cambio de versión futura, cree IDs/fixture nuevos y documente la migración. **Nunca** utilice un seeder de actualización global de cuentas.

La verificación de restauración de backup y hosting real es parte de B07; no se declara ejecutada aquí.
