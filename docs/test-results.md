# Evidencia de verificación · v0.1.0

Fecha: 12/10/2026. Código: versión base SIAA, etiqueta v0.1.0.

## Resultados ejecutados

| Comprobación | Resultado |
| --- | --- |
| Instalación limpia desde composer.lock | Correcta en PHP 8.4.1 |
| composer validate --strict / check-platform-reqs | Correctos |
| composer audit / npm audit --omit=dev | Sin avisos de vulnerabilidad en las dependencias revisadas |
| Migraciones y seed | Aplicados; 23 usuarios, 2 grupos, 40 inscripciones, 8 sesiones, 0 asistencias |
| Pruebas PHP de acceso, autorización, integridad y seeder | 27/27 casos, 80 aserciones; cada caso con un motor PostgreSQL aislado |
| Pruebas JavaScript NDEF | 8/8 casos correctos |
| Compilación Vite y Blade | Correcta |
| Estilo PHP con Pint | Correcto |
| Navegador escritorio 1440 × 960 y móvil 390 × 844 | Pantallas revisadas; sin desbordamiento horizontal de página |
| Recorrido de tres perfiles | Acceso, grupos asignados, directorio y consulta privada del estudiante correctos |
| CSRF por HTTP real | Solicitud POST sin token rechazada con 419 |
| Menú móvil | Apertura, cierre y Escape correctos |
| Errores JavaScript durante el recorrido | 0 |

## Qué cubren los casos PHP

- Acceso/salida, contraseña incorrecta, usuario inactivo, sesión desactivada y cinco intentos de login.
- Registro público deshabilitado y cabeceras de seguridad.
- Roles en URLs directas, grupos propios, inscripción privada y módulos pendientes autorizados.
- Checks de capacidad/rol/cancelación; FK de período/grupo y de asistencia/inscripción;
  duplicados por inscripción/grupo y sesión/inscripción.
- Cantidades del escenario ficticio, repetición del seed sin duplicados ni cambio de contraseñas,
  rechazo del seeder en producción y contraseña explícita.

## Entorno y límites de la evidencia

Se utilizó PHP 8.4.1, Node 24.19.0 y PostgreSQL 17.5 mediante PGlite en este entorno de trabajo.
Para los 27 casos PHP se creó un motor nuevo por caso: el adaptador del motor embebido no reproduce
correctamente el ciclo de conexiones/transacciones de toda la batería ejecutada en un único servidor.
Los casos individuales pasaron; **la batería conjunta en PostgreSQL nativo sigue pendiente**.
El workflow de GitHub ejecuta la batería normal con PHP 8.4 y un servicio PostgreSQL 17 real;
se ejecutará al conectar el repositorio. No se afirma que ese workflow ya pasó.

La navegación HTTP usó la misma aplicación Laravel y el esquema PostgreSQL del escenario ficticio.
Se inspeccionaron visualmente acceso, inicio, menú móvil y terminal de prueba. No se incluyen capturas
como archivos de entrega ni datos reales de estudiantes.

No se ejecutó Docker en este entorno. No se ha probado un teléfono con NFC, escritura de cinco etiquetas,
HTTPS externo, concurrencia de registro, PDF, respaldo/restauración ni el prototipo físico.
Las pruebas NDEF validan formato/decodificación; no demuestran lectura física ni registro de asistencia.

## Próxima evidencia

Ejecutar CI con PostgreSQL nativo, confirmar el Android/Chrome/HTTPS e incorporar los casos de cada
función que se construya. Registrar dispositivo, versión de código, entrada y resultado observado.
