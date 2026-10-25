# Cuentas y recorridos locales

SmartAulaUES se abre en **http://127.0.0.1:8085/login**, desde el worktree actual. Son datos
ficticios; todos los correos utilizan el dominio reservado `example.test`. No son cuentas del
hosting ni una edición distinta de la aplicación. El nombre técnico `ExplorationSeeder` solo
aparece en herramientas y documentación.

## Contraseña y preparación

Desde PowerShell en `C:\Users\jozzz\Downloads\SmartAulaUES-A05`:

```powershell
docker compose exec -T app php artisan local:access
```

Muestra al operador la contraseña inicial conservada cifrada. No se publica aquí ni existe una
contraseña universal de producción. Una cuenta cuya contraseña hayas cambiado utiliza la nueva.
Las demás siguen con la inicial. La cuenta provisional solicita un único cambio personal al entrar.

El escenario ya está preparado. Para otra base local vacía:

```powershell
docker compose exec -T app php artisan local:scenario --generate-password
# Alternativa interactiva para elegirla sin mostrarla:
docker compose exec app php artisan local:scenario
```

Ejecutarlo otra vez no duplica ni actualiza nombres, fechas, contraseñas o estados. Si hay cuentas
o materias que colisionan con la preparación, falla sin sobrescribirlas. La preparación completa
es transaccional y serializada; un error revierte sus escrituras. No se ejecuta al arrancar Docker.
Para reconstruir exclusivamente `smartaulaues_local`, detener web y scheduler y seguir el
[procedimiento explícito y verificado](operacion-local-y-produccion.md#6-reconstruir-exclusivamente-la-base-local-desechable).
No usar esta preparación sobre otra BD ni sobre producción.

## Escenarios y recorrido sugerido

Los cinco ciclos se anclan al momento de preparación en America/El_Salvador. Repetir el seeder
conserva las fechas originales; reconstruir vuelve a calcularlas. El paso del tiempo puede
finalizar naturalmente la clase en curso y vencer una credencial pendiente.

| Dónde | Estado y uso |
| --- | --- |
| Inicio → grupos y gráficos | 52 cuentas, distribución de primero a quinto, egresados e inactivos; seis grupos por página, totales sobre todos los autorizados. |
| Materias y grupos → Ciclo actual | Ocho materias con grupo 01, seis docentes activos con asignaciones, 40 estudiantes iniciales con tres matrículas cada uno; un estudiante luego desactivado conserva su historia. |
| Detalle → cómputo 1 del ciclo actual | Fechas relativas −90 a −31 días; clases finalizadas, cierre con resultados conservados y auditoría. |
| Detalle → cómputo 2 | Fechas −30 a +29 días; cuatro clases realizadas, presencias, ausencias y permisos. Entre sus filas hay 100 %, 75 % y 50 %; un permiso aprobado puede modificar el resultado correspondiente. |
| Sesiones del ciclo actual | Clases futuras (+1/+7/+14/+28/+45 días), canceladas (+3), programadas sin iniciar (−2), presencial/en línea. Una clase de Cálculo I empieza al preparar y termina 45 minutos después. El scheduler la finaliza al vencer su horario. |
| Cálculo I → permisos | Una solicitud pendiente, una aprobada y una rechazada, con resoluciones y motivos ficticios coherentes. Un permiso pendiente hace provisional el resultado y bloquea el cierre, sin impedir finalizar una clase vencida. |
| Ciclo siguiente | Empieza +100 días y termina +250; tres grupos y 12 estudiantes con matrícula en cada uno, sesiones futuras y resultados sin sesiones evaluables. |
| Historial → Ciclo anterior | Fechas −420 a −180; dos grupos, cuatro clases realizadas y una sin inicio por grupo. Cómputos y ciclo cerrados; resultados desde el cierre conservado. Los tres egresados tienen aquí matrícula anterior. |
| Actuales → Ciclo pendiente de resolución | Fechas −160 a −20; está vencido pero abierto. Álgebra lineal tiene una solicitud pendiente identificable. Resolverla permite que el próximo procesamiento vuelva a evaluar el cierre. |
| Actuales → Ciclo en revisión | Fechas −170 a −70; ciclo y cómputo reabiertos con motivo y cierre anterior conservado. Sigue En revisión hasta recierre manual de Coordinación. |
| Filtro Desactivados → Ingeniería de software, grupo 02 | Grupo suspendido, con ocho matrículas y sin clases; consulta histórica conservada. Reactivar no reabre sus cómputos ya cerrados por fecha. |
| Credenciales NFC | Camila: alta pendiente; Daniel: activa; Sofía: revocada; Gabriel: pendiente vencida. La pendiente de Camila vence naturalmente después de su plazo. Emisión individual y lote seleccionan estudiantes activos. |
| Cuenta Inés Mendoza | Provisional, sin matrícula: acceso académico bloqueado hasta cambiar contraseña; los formularios conservan sus restricciones de seguridad. |
| Óscar Fuentes / Patricia Suárez | Estudiante y docente inactivos: no pueden iniciar sesión. Su estado se consulta desde Coordinación. |

Orden recomendado:

1. Entra como **Lucía Herrera**. Revisa Inicio, directorios por año/Egresado, ciclos, grupos activos
   y desactivados, Historial y resultados por cómputo. En Historial se conserva el resultado cerrado.
2. En el ciclo vencido revisa el impedimento y su permiso. Si lo resuelves, observarás el cierre
   posterior del scheduler; es un cambio real del escenario, que la repetición del seeder conserva.
3. Entra como **Ana Martínez**. Consulta sus grupos, la clase en curso si sigue vigente y los permisos.
   Crea una sesión futura dentro de un cómputo abierto; no puede administrar cuentas o matrículas.
4. Entra como **Camila Morales**, **Daniel Ramírez** o **Sofía Castro**. Consulta resultados e incidencias
   propios. No se entregan las filas ni las evidencias privadas de otros estudiantes.
5. Entra como **Inés Mendoza** y cambia la contraseña provisional. Al terminar debe iniciar sesión
   con su clave nueva. Volver a preparar el escenario no la restablece.
6. Con Coordinación descarga una plantilla CSV, provoca un error de correo/fecha/contraseña y
   revisa el mensaje. La confirmación válida conserva revisión por pestaña y revalida el lote.
7. En Credenciales prepara una nueva alta o selecciona un lote. Escribir/leer físicamente exige
   Android compatible, NFC activo y HTTPS. La verificación local de datos no demuestra ese hardware.

La preparación limita expresamente a su código el reloj histórico y una confirmación NFC sintética
para dejar estados explorables. Los resultados cerrados se generan mediante el flujo real de cierre;
las inscripciones de egresados se crean antes de marcar el egreso. No se debilitó el flujo NFC de
producción ni se crean matrículas nuevas de egresados. La etiqueta compartida sigue distinguiendo
acceso declarado de lectura física. Para registrar por QR/enlace presencial se aplican las reglas
geográficas vigentes; no se simulan coordenadas de estudiantes en el escenario.

## Lista completa de cuentas

Todas usan inicialmente la contraseña recuperable con `local:access`, salvo cambios personales
posteriores. Coordinación y las cuentas habituales no fuerzan cambio al entrar. El carné incluye
el año de ingreso y puede variar al reconstruir en otra fecha.

| Nombre | Correo | Perfil / condición | Acceso inicial |
| --- | --- | --- | --- |
| Lucía Herrera | lucia.herrera@example.test | Coordinación | Habitual |
| Ana Martínez | ana.martinez@example.test | Docente | Habitual |
| Carlos Rivera | carlos.rivera@example.test | Docente | Habitual |
| Beatriz López | beatriz.lopez@example.test | Docente | Habitual |
| Diego Flores | diego.flores@example.test | Docente | Habitual |
| Elena Cruz | elena.cruz@example.test | Docente | Habitual |
| Francisco Gómez | francisco.gomez@example.test | Docente | Habitual |
| Patricia Suárez | patricia.suarez@example.test | Docente | Inactivo |
| Camila Morales | camila.morales@example.test | Estudiante · 1.º año | Habitual |
| Daniel Ramírez | daniel.ramirez@example.test | Estudiante · 2.º año | Habitual |
| Sofía Castro | sofia.castro@example.test | Estudiante · 3.º año | Habitual |
| Gabriel Mendoza | gabriel.mendoza@example.test | Estudiante · 4.º año | Habitual |
| Valeria Aguilar | valeria.aguilar@example.test | Estudiante · 5.º año | Habitual |
| Andrés Romero | andres.romero@example.test | Estudiante · 1.º año | Habitual |
| Isabella Vásquez | isabella.vasquez@example.test | Estudiante · 2.º año | Habitual |
| Mateo Torres | mateo.torres@example.test | Estudiante · 3.º año | Habitual |
| Mariana Navarro | mariana.navarro@example.test | Estudiante · 4.º año | Habitual |
| Sebastián Santos | sebastian.santos@example.test | Estudiante · 5.º año | Habitual |
| Paula Reyes | paula.reyes@example.test | Estudiante · 1.º año | Habitual |
| Nicolás Ortiz | nicolas.ortiz@example.test | Estudiante · 2.º año | Habitual |
| Luciana Pineda | luciana.pineda@example.test | Estudiante · 3.º año | Habitual |
| Samuel Mejía | samuel.mejia@example.test | Estudiante · 4.º año | Habitual |
| Adriana Campos | adriana.campos@example.test | Estudiante · 5.º año | Habitual |
| David Cabrera | david.cabrera@example.test | Estudiante · 1.º año | Habitual |
| Victoria Molina | victoria.molina@example.test | Estudiante · 2.º año | Habitual |
| Alejandro Rivas | alejandro.rivas@example.test | Estudiante · 3.º año | Habitual |
| Gabriela Salazar | gabriela.salazar@example.test | Estudiante · 4.º año | Habitual |
| Javier Fuentes | javier.fuentes@example.test | Estudiante · 5.º año | Habitual |
| Daniela Morales | daniela.morales@example.test | Estudiante · 1.º año | Habitual |
| Emilio Ramírez | emilio.ramirez@example.test | Estudiante · 2.º año | Habitual |
| Natalia Castro | natalia.castro@example.test | Estudiante · 3.º año | Habitual |
| Leonardo Mendoza | leonardo.mendoza@example.test | Estudiante · 4.º año | Habitual |
| Carolina Aguilar | carolina.aguilar@example.test | Estudiante · 5.º año | Habitual |
| Tomás Romero | tomas.romero@example.test | Estudiante · 1.º año | Habitual |
| Fernanda Vásquez | fernanda.vasquez@example.test | Estudiante · 2.º año | Habitual |
| Rodrigo Torres | rodrigo.torres@example.test | Estudiante · 3.º año | Habitual |
| Andrea Navarro | andrea.navarro@example.test | Estudiante · 4.º año | Habitual |
| Luis Santos | luis.santos@example.test | Estudiante · 5.º año | Habitual |
| Julia Reyes | julia.reyes@example.test | Estudiante · 1.º año | Habitual |
| Ricardo Ortiz | ricardo.ortiz@example.test | Estudiante · 2.º año | Habitual |
| Claudia Pineda | claudia.pineda@example.test | Estudiante · 3.º año | Habitual |
| Pablo Mejía | pablo.mejia@example.test | Estudiante · 4.º año | Habitual |
| Raquel Campos | raquel.campos@example.test | Estudiante · 5.º año | Habitual |
| Manuel Cabrera | manuel.cabrera@example.test | Estudiante · 1.º año | Habitual |
| Teresa Molina | teresa.molina@example.test | Estudiante · 2.º año | Habitual |
| Hugo Rivas | hugo.rivas@example.test | Estudiante · 3.º año | Habitual |
| Mónica Salazar | monica.salazar@example.test | Estudiante · 4.º año | Habitual |
| Óscar Fuentes | oscar.fuentes@example.test | Estudiante · 5.º año | Inactivo |
| Renata Morales | renata.morales@example.test | Estudiante · Egresado | Habitual |
| Esteban Ramírez | esteban.ramirez@example.test | Estudiante · Egresado | Habitual |
| Verónica Castro | veronica.castro@example.test | Estudiante · Egresado | Habitual |
| Inés Mendoza | ines.mendoza@example.test | Estudiante · 4.º año | Cambio obligatorio |
