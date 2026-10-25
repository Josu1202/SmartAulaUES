# Lotes manuales y CSV · A05 sobre SmartAulaUES 0.3.2

Solo coordinación. CSV UTF-8 (BOM opcional), coma, encabezados exactos en el orden indicado.
Hasta 500 registros/1 MB; líneas vacías se omiten. Las plantillas descargables tienen solo encabezados.
Lote manual de Estudiantes: hasta 100 filas del mismo ingreso/carrera, con la misma revisión/confirmación.
Ningún ejemplo ficticio se importa automáticamente.

## Estudiantes nuevos

```csv
name,email,student_code,entry_year,study_year
Ana Ejemplo,ana@example.test,AA27001,2027,1
Luis Ejemplo,luis@example.test,LE25002,2025,3
```

Carné obligatorio/único (máx. 30), correo único sin distinguir mayúsculas, nombre obligatorio.
entry_year: 1900–2200; study_year: 1–5 o el literal `egresado`.
Egresado se guarda con graduated=true y study_year=NULL; ingreso es independiente.
Se normalizan carné mayúsculas/correo minúsculas.
El perfil se fija a student; no columna role ni contraseña. Las cuentas existentes se rechazan.
Son dos datos actuales del estudiante; avanzar carrera no altera el historial de inscripciones.

## Docentes nuevos

```csv
name,email
Docente Ejemplo,docente@example.test
```

Perfil teacher, sin carné/años. Solo cuentas nuevas. Coordinación se crea individualmente.

## Años de estudiantes existentes

```csv
student_code,entry_year,study_year
AA27001,2027,1
LE25002,2025,4
```

kind student_years. Debe existir cada estudiante por carné; acepta cuentas activas/inactivas y preserva
su estado. Modifica solo entry_year/study_year, conserva nombre/correo/contraseña/perfil/inscripciones.
No permite códigos repetidos ni crea cuentas desconocidas. Úsalo para clasificar usuarios anteriores o
actualizar el año de carrera. No requiere contraseña inicial. Cada cambio y el lote quedan auditados.

## Inscripciones nuevas a un grupo

Selecciona un grupo activo de ciclo no cerrado; estudiantes existentes activos no egresados.
No cambia las cuentas ni sus años. Egresado conserva sus inscripciones/historial, sin nuevas matrículas.

```csv
student_code,active_from,active_to
AA27001,2027-01-11,
LE25002,2027-01-11,2027-03-31
```

Fechas AAAA-MM-DD dentro del ciclo; active_to vacío = sin retiro, inclusivo si se informa.
Una inscripción por estudiante/grupo; editar una vigente/retirada desde el grupo, sin borrar/recrear.
Lote completo debe caber según reserva anticipada del README. El CSV no selecciona grupo por fila.
Alternativa: Cargar estudiantes al grupo, filtrar años, seleccionar filas de la página y vigencia común.

## Plantilla compatible de la versión anterior

```csv
name,email,role,student_code
Ana Ejemplo,ana@example.test,student,AA27001
Docente Ejemplo,docente@example.test,teacher,
```

kind users se conserva para compatibilidad: altas teacher/student, sin coordinación, sin sobrescribir
cuentas ni contraseñas. Estudiantes importados así quedan con años sin asignar hasta edición/CSV de años.
Para nuevos archivos usa las plantillas separadas con los campos de año.

## Contraseñas iniciales y confirmación

Solo altas users/students/teachers: contraseña fuera del CSV, confirmada, mínimo 12 caracteres y
máximo 72 bytes UTF-8, sin NUL. No se incluye columna de contraseña.
La revisión guarda hash en la sesión, nunca texto de contraseña; SESSION_ENCRYPT=true en el ejemplo
predeterminado. No mostrar hashes ni contraseñas en preview/bitácora. Entrega acceso al titular de forma
privada. El cambio en Mi cuenta es obligatorio en el primer ingreso; hasta entonces se bloquean
las rutas académicas. Después del cambio se revocan sesiones y debe volver a iniciar sesión.
QR/enlace/etiqueta vigentes se abren nuevamente; no se extiende su ventana ni se persiste su token.

1. Descargar plantilla/cargar archivo o completar el lote manual. Cada carga crea una revisión
   independiente identificada en la URL; no sustituye otras pestañas. Máximo diez pendientes por sesión.
2. Ver errores por fila. Ninguna escritura académica antes de confirmar; error bloquea todo el lote.
3. Corregir y cargar de nuevo. Sin edición de celdas ni importación parcial en la pantalla de revisión.
4. Confirmar antes de 15 minutos, en la sesión/usuario propietario y con nonce/CSRF correctos.
   Exactamente al vencimiento ya no se confirma.
5. Revalidar la BD dentro de una transacción: duplicados, roles/estado, fechas, cupo, destino y cuentas
   cambiadas desde la revisión mediante huella de las cuentas. Si falla, ninguna fila se guarda.
6. Éxito: cambios individuales y resumen auditados; se retira revisión. Confirmar otra vez no duplica.

Rutas de preview/confirm usan bloqueo de sesión y throttling. Filas provienen de la revisión del servidor,
no de inputs ocultos del cliente. No son respaldos ni exportaciones de datos reales.
Rutas: /admin/students/batch, /admin/imports, /admin/imports/{preview|confirm},
/admin/imports/templates/{users|students|teachers|student_years|enrollments}.
