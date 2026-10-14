# Evidencia de verificación · SmartAulaUES v0.2.0

## Fase 1 — 13/10/2026

| Comprobación ejecutada | Resultado |
| --- | --- |
| Instalación desde composer.lock/package-lock.json | Correcta; PHP 8.4.25 con pdo_pgsql y Node 24.19.0 |
| PHPUnit, casos ejecutados individualmente | **66/66 casos; 313 aserciones correctas** |
| Motor de datos de los casos PHP | PostgreSQL 17.5 embebido mediante PGlite/PDO; motor nuevo por caso |
| NDEF, npm test | 8/8 casos correctos |
| Composer validate/check-platform-reqs | Correctos; requisitos PHP explícitos |
| Pint, Vite y Blade | Correctos |
| Migraciones/seed por PDO | Aplicados; escenario ficticio original y alta de registros desde formularios |
| Navegador Chromium 153, acceso real de tres perfiles | 19 comprobaciones completadas; flujos detallados abajo |
| Nueve pantallas × tres anchos: 1440, 390 y 320 px | 27 mediciones; 0 desbordamientos horizontales de página |
| JavaScript durante el recorrido | 0 errores de página |
| CSRF en escritura real sin token | 419; escritura rechazada |
| Compose/pgAdmin | YAML, perfil opcional, rutas/conexión sin contraseña y etiqueta 9.17 comprobados; contenedores no ejecutados |

### Reglas verificadas en PHP

Se conserva la batería de acceso/autorización/constraints/seeder de la base y se amplía para comprobar:
creación completa de ciclo → materia → grupo → cómputo → inscripción; render de formularios;
403 a docentes/estudiantes; normalización y unicidad de correo/carné; rol con historial; desactivación
sin borrado; contraseña propia/restablecimiento y rechazo del hash de sesión anterior; docentes activos;
fechas dentro del ciclo/sin superposición; alcance de IDs anidados; identidad estable del grupo;
cupo anticipado, retiro inclusivo y capacidad mínima; conservación de asistencias/sesiones al editar
fechas, incluidas sesiones que cruzan medianoche; ciclos/cómputos cerrados de lectura y restricciones
al cerrar ciclo; CSV sin escritura en preview, errores/duplicados, confirmación completa, no sobreescritura,
conflictos o cupos que cambian entre pasos, caducidad/nonce, plantillas y escape HTML.

Cada caso se ejecutó con PHPUnit y conexión PDO a un motor aislado; se repitieron los casos afectados
por correcciones posteriores. El agregado final incluye resultados correctos de los 66 casos del
código entregado; no se presenta como una ejecución conjunta en PostgreSQL nativo.

### Recorrido real por navegador

Coordinación inició sesión, creó estudiante/ciclo/materia/grupo/cómputo, inscribió y registró retiro;
rechazó CSV duplicado, confirmó CSV de dos cuentas y CSV de inscripción al grupo; consultó la bitácora.
Se rechazó un POST sin token con 419. Docente consultó sus grupos y recibió 403 en administración.
Estudiante entró con la contraseña creada, consultó solo su inscripción en un grupo compartido,
recibió 403 en importaciones, cambió su contraseña, salió y volvió a entrar con la nueva.

Se midieron inicio, usuarios, alta de usuario, alta de ciclo, alta de grupo, detalle de grupo, CSV,
bitácora y cuenta en los tres anchos. Tablas usan desplazamiento dentro de sus contenedores;
la página no se desborda. Menú móvil/Escape y vistas de grupo/formulario inspeccionados visualmente.
La aplicación ejecutada es la Laravel del proyecto y su esquema PostgreSQL con datos ficticios;
no un HTML suelto ni un layout con usuario simulado. Las capturas/datos temporales no se incluyen en ZIP.

### Límites de esta evidencia

Este entorno no ejecuta Docker ni PostgreSQL nativo como servicio. PGlite soporta una conexión y no
reproduce el ciclo de conexiones de una batería conjunta ni demuestra concurrencia/carga nativas.
**Ejecutar la batería normal en CI/PostgreSQL 17 y Compose/pgAdmin en el equipo real sigue pendiente**.
El workflow existente lo configura; no se afirma que GitHub Actions ya pasó ni que se publicó la rama.

No se probó Android/NFC físico, escritura de etiquetas, HTTPS externo, registro de asistencia,
PDF oficial, respaldo/restauración o ESP32. Esas funciones y pruebas pertenecen a fases 2–3.
Los resultados de versiones anteriores se conservan a continuación sin atribuirlos a este incremento.

---

# Evidencia de verificación · SmartAulaUES v0.1.2

## Marca e icono v0.1.2 — 12/10/2026

| Comprobación ejecutada | Resultado |
| --- | --- |
| Marca por HTTP y render de Blade | SmartAulaUES en acceso y estructura de aplicación; sin SIAA en el texto visible |
| Icono SVG, PNG 192/512, favicon SVG/ICO | Generados e inspeccionados; icono y favicon servidos correctamente |
| Navegador Chromium 153 | Acceso real y estructura con coordinación en memoria, en 1440 × 960, 390 × 844 y 320 × 760 |
| Ajuste del logotipo y página | Nombre dentro del menú lateral; sin desbordamientos horizontales |
| Errores JavaScript | 0 durante la comprobación de marca |
| `php artisan test --testsuite=Infrastructure --compact` | 1 caso, 2 aserciones correctas |
| `npm test` | 8/8 casos correctos con los mensajes de la nueva marca |
| Composer, Pint, Vite y compilación de vistas | Correctos; las versiones de dependencias se conservaron |

El acceso se comprobó por HTTP sin una sesión iniciada. Para revisar el logotipo del menú se renderizó
el layout con una cuenta de coordinación en memoria. Estas comprobaciones no acceden a PostgreSQL
ni repiten los flujos de datos/roles de la base v0.1.0. El PHP disponible no tiene `pdo_pgsql`;
las pruebas de dominio de esta versión y el arranque completo Docker quedan pendientes en un entorno
con PostgreSQL/Docker. Las evidencias previas se conservan abajo con su versión original.

El esquema, el volumen Docker y las cuentas demo no se cambian por el nombre.
El prefijo NDEF técnico y la variable de zona horaria anterior mantienen compatibilidad.

## Parche v0.1.1 — 12/10/2026

| Comprobación ejecutada | Resultado |
| --- | --- |
| Reproducción HTTP con el Laravel instalado | Sin `--no-reload`: `127.0.0.1:55432`; con el parche: `db:5432` |
| `php artisan test --testsuite=Infrastructure --compact` | 1 caso, 2 aserciones correctas |
| `composer validate --strict` / `check-platform-reqs` | Correctos |
| `npm test` | 8/8 casos correctos |
| `npm run build` / `php artisan view:cache` | Correctos |
| Pint | Correcto |

La regresión crea una aplicación Laravel temporal, usa el comando real de `Dockerfile` y consulta
por HTTP la configuración PostgreSQL del proceso hijo. La base declarada es `siaa_test`, pero no
abre una conexión de datos: verifica el traspaso de variables, sin exponer contraseñas ni tocar tablas.
El caso se incorpora a la batería normal de PHPUnit y al workflow existente.

El parche se comprobó con PHP 8.4.1 y Node 24.19.0. No hay Docker en este entorno;
**el arranque completo de Compose queda por comprobar en tu instalación**.
Las pruebas de dominio y la revisión visual de v0.1.0, documentadas a continuación, no se repitieron
para este cambio de arranque. El PHP disponible en esta sesión no tiene `pdo_pgsql`.

## Evidencia conservada de la base v0.1.0

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
