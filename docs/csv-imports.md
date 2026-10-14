# Importaciones CSV · v0.2.0

Solo coordinación. Dos archivos independientes; UTF-8 (BOM opcional), separador coma, encabezados
exactos y en el mismo orden. Hasta 500 registros/1 MB; líneas vacías se omiten. Un registro citado
puede contener comas. Los ejemplos son ficticios; no se cargan automáticamente.

## Usuarios

```csv
name,email,role,student_code
Ana Ejemplo,ana@example.test,student,AA27001
Docente Ejemplo,docente@example.test,teacher,
```

`role`: teacher/student. Carné obligatorio para estudiante y vacío para docente. Correo/carné únicos
respecto del archivo y de la BD; se normaliza correo a minúsculas y carné a mayúsculas. No permite
crear coordinación, actualizar cuentas existentes ni cambiar sus contraseñas.

La contraseña inicial se introduce aparte en el formulario, con confirmación y mínimo 12 caracteres;
no hay columna de contraseña. La revisión almacena únicamente el hash en la sesión cifrada del entorno
predeterminado. No se incluye en bitácora ni preview. Entregar contraseña de forma privada; cada titular
puede cambiarla en Mi cuenta. La función no exige automáticamente cambio en el primer login.

## Inscripciones

Selecciona primero el grupo de destino. Los estudiantes deben existir con perfil student y estar activos.

```csv
student_code,active_from,active_to
AA27001,2027-01-11,
BB27002,2027-01-11,2027-03-31
```

Fechas AAAA-MM-DD dentro del ciclo elegido. `active_to` vacío indica sin retiro; si se informa es
inclusivo y no puede preceder active_from. Cada estudiante aparece una vez por grupo; inscripciones
existentes se editan desde el detalle del grupo. El lote completo debe caber según la regla del README.

## Revisión y confirmación

1. Descargar plantilla, completarla y cargar archivo. Una carga reemplaza la revisión anterior.
2. Ver datos/errores por registro. Cualquier error bloquea la confirmación. No escribe datos académicos.
3. Corregir archivo y cargarlo de nuevo; no hay edición de celdas en la web ni importación parcial.
4. Confirmar dentro de 15 minutos y desde la misma sesión/usuario. El servidor conserva los datos y
   verifica nonce/CSRF/propietario; no acepta filas ocultas enviadas por el cliente.
5. Revalidar cuentas, fechas, estado del ciclo, duplicados y cupo dentro de una transacción. Si cambió
   algo, todo se rechaza y se conserva la revisión para consultar errores actuales.
6. Éxito: altas e inscripciones auditadas, resumen de lote sin CSV completo/contraseña; revisión retirada.
   Repetir la confirmación no vuelve a crear registros. Rutas de carga/confirmación serializan la sesión.

Las plantillas descargadas contienen encabezados vacíos: no hay filas de prueba que se importen por error.
No son respaldos ni una exportación completa de usuarios. Rutas: `/admin/imports`,
`/admin/imports/preview`, `/admin/imports/confirm`, `/admin/imports/templates/{users|enrollments}`.
