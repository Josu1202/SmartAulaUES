# A01 · evidencia de la tabla y reglamento vigente · 18/10/2026

Base 4873cbf, feature/p3a-a01-calculo-asistencia. Release 0.3.2 conservada, sin commit/push/merge.
Esta evidencia corresponde a la revisión con tabla; a01-verification.md conserva los resultados
anteriores a estos cambios y no se presenta como verificación de la implementación nueva.

## Estado de entrega

- Implementación terminada: calculador único, tabla mínima por cómputo/perfil, consulta colectiva,
  mantenimiento explícito y documentación. No incluye ranking, gráficas, exportaciones o certificación.
- Validación local completada: batería técnica final y revisión visual realizadas por el agente.
- Integrado mediante PR: no. La rama conserva trabajo local sin commit y el usuario mantiene la integración.

## Desarrollo: operación realmente aplicada

Se ejecutó `docker compose exec -T app php artisan attendance:normalize-development-policy --database=siaa`.
El comando comprobó entorno local, conexión pgsql, configuración y current_database() = siaa.
La revisión informó 2 candidatos y 0 cómputos protegidos por cierre/reportes.
Después de informar esa cantidad se ejecutó el mismo comando con `--apply`: actualizó 2.
Una nueva revisión informó 0 candidatos y 0 protegidos. No se ejecutaron seeders ni migraciones.
Solo policy_version/updated_at; fechas y relaciones académicas conservadas. Auditoría por cada cómputo,
acción period.policy_normalized, actor_id null (Sistema), antes/después y motivo de desarrollo.
Los tests verifican repetición, conservación de datos, producción/base incorrecta y protección de ReportVersion.

## Pruebas de la implementación nueva

Todo comando del proyecto se ejecutó por Docker Compose. Antes de reconstruir tablas se comprobó
lectura y escritura efectivas de siaa_test mediante scripts/check-test-database.php, encadenado con &&.
RefreshDatabase reconstruye exclusivamente esa base desechable. No se tocó .env ni se regeneraron claves.
Se creó un .env.testing temporal con la clave/credenciales existentes, sin imprimirlas ni incorporarlas a Git.

| Comprobación | Resultado observado |
| --- | --- |
| PHP completa antes del ajuste visual | 134/134, 831 aserciones, sin warnings, 182.50 s |
| PHP completa sobre el ajuste visual final | 134/134, 831 aserciones, sin warnings, 153.07 s, salida 0 |
| Pint completo | PASS, 164 archivos |
| composer validate --strict / check-platform-reqs | Correctos, PHP 8.4.26 |
| npm test | 14/14 |
| npm run build final | Correcto, 71 módulos, 27.35 s; aviso Sass mixed-decls heredado |
| attendance-races.php --env=testing | 4 carreras de registro correctas, PostgreSQL nativo |
| attendance-summary.php --env=testing | 2 carreras colectivas correctas, dos filas con mismo as_of |
| scripts/attendance-calculation-examples.php --env=testing | Individual y colectiva: 8 presentes + 1 justificada + 1 ausente, 9/10, 90.00 %, conclusión positiva; rollback final |
| git diff --check | Correcto |

Los comandos exactos de PHP y carreras están en attendance-calculation-contract.md. Composer se ejecutó
con GIT_CONFIG_COUNT=1, GIT_CONFIG_KEY_0=safe.directory y GIT_CONFIG_VALUE_0=/var/www/html limitados al
proceso del contenedor, sin cambiar configuración global. Node y PHP usan servicios node/app del Compose.

Cobertura añadida/actualizada:

- SessionWorkflow finaliza realmente una clase y crea la ausencia. AbsenceWorkflow recibe la solicitud;
  la ruta real del docente la aprueba. La misma asistencia cambia a justificada: la tabla recargada pasa
  de 80.00 % a 90.00 %, con 10 asistencias y 1 solicitud; la segunda resolución se rechaza sin duplicar.
- Retiro inclusivo según fecha local, inscripción tardía, cuenta inactiva e intersección vacía comprobados
  en consulta colectiva y HTML; docente y coordinación conservan acceso histórico.
- 21 estudiantes y 25 sesiones: segunda página mantiene denominador 25 y porcentaje correcto;
  selector persiste en enlaces, paginadores independientes y no hay resúmenes globales de asistencia.
- SQL por página permanece constante al pasar de 1 a 9 estudiantes (máximo comprobado: 14 consultas);
  no se escriben resultados y todas las filas comparten as_of.
- Perfiles, IDs de cómputos ajenos, cuenta inactiva, invitados, datos y motivos privados, incidencias propias,
  vacío y selección inicial por menor número/ID. Ruta de política retirada: 404; sin enlace de menú.
- Normalización: preview sin cambios, idempotencia, producción/base inválida rechazadas; reportes,
  ciclos/cómputos cerrados y closing protegidos. La comparación de reporte usa el registro ya recargado
  para incluir los campos nulos devueltos por PostgreSQL; no se relajó la protección.
- El script concurrente observa pg_stat_activity.wait_event_type = Lock antes del commit de los workflows.
  Después comprueba una página de dos filas coherente, sin lecturas mezcladas de permiso/resultado.

## Revisión visual

Navegador disponible: Edge 155.0.4283.62 headless, con inspección de capturas por el agente.
Servidor temporal aislado en localhost:8091, APP_ENV=testing, DB_DATABASE=siaa_test, sin modificar .env.
Fixtures ficticios de 4 inscripciones: 90 %, incompleto, cuenta inactiva sin intersección y permiso pendiente.
Se agregaron clases futura, en curso y vencida sin finalizar para revisar los avisos reales.
Coordinación/docente ven 4 filas; estudiante solo su propia fila. No se muestran códigos de política.

La primera inspección detectó título oculto por cabecera sticky y repetición de diez mensajes iguales.
Se corrigió scroll-margin-top y se agruparon incidencias por código dentro de cada fila con su cantidad;
el contrato conserva el detalle individual original. Se redujo padding de la tabla de forma localizada.
Se completaron nueve medidas finales: tres perfiles × 1440/390/320 px. Coordinación y docente ven
4 filas ficticias; estudiante ve 1 y no aparecen nombres ajenos en su bloque. Ancho de documento
1425/390/320 px respectivamente, sin desbordamiento de página. La tabla permite desplazamiento
horizontal y ofrece texto de ayuda; se revisaron capturas de ambos extremos en móvil. El selector
del docente cambió al cómputo sin sesiones y mostró Sin clases evaluables. Cero excepciones JavaScript.
El agente inspeccionó capturas finales de coordinación y docente en escritorio y estudiante/móvil,
incluyendo 90.00 %, datos incompletos, cuenta inactiva, pendientes y las categorías de sesiones.
Capturas y mediciones están fuera del repositorio, en la carpeta temporal smartaulaues-a01-review;
solo contienen fixtures ficticios. El navegador se cerró; el servidor temporal y todos los registros
visuales se retiraron de siaa_test, verificando antes sus IDs/correos de fixture. Se eliminó el archivo
temporal con credenciales. La política de ejecución bloqueó borrar el perfil de Edge: permanece en
esa carpeta temporal, fuera de Git. No es un defecto funcional de A01 ni un artefacto de entrega.

## Archivos de esta revisión

- Se conservan AttendanceCalculator y Services/Attendance/AttendancePolicy; se amplía AttendanceSummary
  con la consulta colectiva y se integra en OfferingController/show.blade.php y attendance-results.blade.php.
- Se retiran el controlador/vista de política independientes, su ruta y el enlace. app/Policies/AttendancePolicy
  (autorización de asistencias) permanece intacta. Layout académico: Campus; estilos limitados a la tabla.
- Se añaden acción y comando NormalizeDevelopmentAttendancePolicy, auditoría explícita de Sistema y etiqueta es.
- Factories vigentes, fixture 90 %, ejemplo ejecutable, pruebas de tabla/mantenimiento y concurrencia colectiva.
- Contrato, README, AGENTS, decisiones, estado, esquema, fases, CHANGELOG y notas externas actualizados.
- Sin migraciones nuevas, nuevas dependencias, cambios de versión o modificaciones a CI/arnés.

El entorno .env.testing temporal se retiró al terminar. .env no se modificó y el cambio previo de
.env.example conserva su huella. No se incorporan Microsoft/, logs, almacenamiento privado ni dependencias.
No quedan errores funcionales conocidos dentro del alcance de A01 tras esta verificación.

## Fuera del cierre de A01

A02–A05 y B01–B07 se documentan en change-notes-status.md y development-phases.md. No bloquean A01
por sí mismos. No se afirma aprobación formal universitaria, CI remoto, HTTPS definitivo ni NFC/GPS físico.
La tabla y el calculador devuelven datos actualizables; snapshots y resultados certificados pertenecen a A03.
La lectura consistente depende del protocolo de locks; SQL externo que lo ignore queda fuera de la garantía.
Los conteos de resultados coherentes excluyen filas inconsistentes, que se señalan sin inventar ausencias.
