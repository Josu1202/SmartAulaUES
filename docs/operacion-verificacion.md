# Verificación de datos, presentación, rendimiento y operación

Revisión local del 24/10/2026 (mediciones UTC del 25/10). Trabajo sobre
`feature/p3a-a05-integracion-final`, base `6273a307f3411a5e1ba40c427153cdf39e545a0e`, versión
0.3.2. No hay commit, push, PR, merge o despliegue remoto. Las evidencias anteriores de A05
se conservan en sus documentos y directorio privado; las de este incremento se identifican
con `operation-*` y `performance-*`. No se atribuyen a CI remoto.

## Entorno y datos

- Windows, Docker Desktop/Compose v2.40.3; PHP 8.4.27, PostgreSQL 17.11 nativo, Node 24,
  Edge 156.0.4314.42. Laravel/Blade y Tabler conservados.
- Worktree actual `C:\Users\jozzz\Downloads\SmartAulaUES-A05`; web `127.0.0.1:8085`, BD
  `smartaulaues_local` en otro volumen/red, puerto host 55433. Los servicios normales web y
  scheduler montan exactamente este código y usan `db:5432/smartaulaues_local`.
- Checkout habitual preservado en `SmartAulaUES`, rama A04, `.env.example` previo y Microsoft/.
  Su web 8080 y BD `siaa`/55432 no se reconstruyeron ni migraron. Ambos `.env` conservan
  su fecha original; APP_KEY y credenciales reales no se sustituyeron.
- Pruebas en el proyecto independiente `smartaulaues-tests`, únicamente `testing/siaa_test`,
  sin puerto público ni scheduler. Se verificaron conexiones efectivas antes de las suites.
- `ExplorationSeeder`: 52 cuentas (1 coordinación, 7 docentes, 44 estudiantes), 8 materias,
  5 ciclos, 16 grupos, 34 cómputos y 220 matrículas. Tres egresados con matrícula anterior;
  dos cuentas inactivas; una provisional. Fechas relativas, dominio `example.test` y nombres
  naturales. La [guía de cuentas](escenarios-y-cuentas-locales.md) enumera los 52 accesos.

La primera preparación dejó 126 sesiones, 944 resultados, cuatro permisos y 11 cierres
conservados válidos. El procesador cerró además un cómputo vacío vencido del grupo suspendido:
12 versiones válidas. Al vencer la clase inicialmente en curso añadió 15 resultados. Se
conservaron esos cambios reales al medir; no se forzó el reloj del servidor. La reconstrucción
final explícita del escenario se distingue de esa BD de medición y conserva su respaldo privado.

## Seeder, repetición y seguridad

El comando verifica entorno, host permitido, nombre configurado y servidor/base efectivos
de lectura y escritura. El seeder admite únicamente local/smartaulaues_local o testing/siaa_test;
producción y destinos desconocidos fallan antes de escribir. No tiene contraseña predeterminada.
La preparación usa una transacción y advisory lock, también alrededor de la obtención/guardado
de la contraseña, evitando que dos preparaciones guarden claves distintas para las mismas cuentas.

Se ejecutó `local:scenario` dos veces sobre el escenario existente: huella de cuentas/contraseñas,
reportes, auditoría y cantidades **idéntica**, sin duplicar ni restaurar cambios. Los tests repiten
además un mes después de cambiar un nombre y una contraseña: ambos se conservan. Una colisión
con cuentas ajenas detiene la preparación; la falta de contraseña tampoco crea datos.

Las pruebas anclan fechas en enero de 2027 y diciembre de 2028, verifican relaciones, fechas,
estados, creación anterior al inicio, ausencia de auditorías futuras y reposición del reloj al
terminar. La cohorte de ingreso toma el año del inicio del ciclo actual, incluso si cruza enero.
Los resultados contienen 100/75/50 %, cero evaluables, permisos y cierres válidos por el
calculador único. Marcar egresados ocurre después de sus matrículas; una nueva matrícula de
egresado sigue rechazada. Todos los reportes pasan `ClosureSnapshot::valid`.

La reconstrucción exige `local:rebuild --confirm-database=smartaulaues_local`, con web y worker
detenidos. Sin confirmación, no borra nada. Nunca elimina volúmenes o una BD ajena. La ejecución
final conserva previamente un dump privado y el acceso cifrado de la exploración anterior.
No implica autorización para aplicar este comando a instalaciones reales.

`configure-local.php` se ejecutó en un contenedor efímero: crea nuevas claves privadas solo
para una instalación nueva, configura la BD correcta, deshabilita la preparación antigua y
preserva exactamente un `.env` ya existente. No muestra sus secretos. `local:access` recupera
solo la clave ficticia cifrada y se rechaza desde producción. La primera cuenta de producción
requiere el comando explícito `app:create-coordinator`, con contraseña elegida y auditoría
sin secretos; no reemplaza cuentas existentes.

## Traducciones, redacción y navegador

La causa encontrada era un catálogo español incompleto: faltaban casi todas las reglas de
validación y también la paginación. Ahora están cubiertas recursivamente todas las reglas y
variantes del Laravel instalado, sus atributos utilizados, mensajes de fecha y contraseña,
catálogos de acceso y textos de paginación. No se creó una regla ficticia `expiredate`: esa
cadena exacta no existe en las reglas/código inspeccionados ni se reprodujo. Se corrige la
carencia comprobada, sin reemplazar indiscriminadamente claves por un aviso genérico.

Se provocaron errores desde formularios reales de Edge: fecha con formato incorrecto, fecha
final anterior, contraseña larga en CSV y selección inválida de credencial. HTML y JSON
mostraron mensajes españoles y la causa correspondiente. El límite bcrypt y la prohibición
de caracteres de control permanecen. Los inputs de contraseña no se recuperan después del error.

La revisión automatizada visitó **85 páginas y 266 comprobaciones** entre invitado,
Coordinación, docente, estudiante y cuenta provisional: accesos/listas/formularios, los 16 grupos,
Historial, desactivados, credenciales, permisos, importaciones, avisos, cuenta y terminal. Sin
errores JavaScript sin manejar ni desbordamiento de documento en los anchos examinados
1440/390/320. Se amplió la búsqueda a `validation`, `pagination`, `passwords`, `auth` y `audit`.
El primer recorrido detectó visualmente las etiquetas faltantes del paginador; se corrigieron
y se repitió el recorrido. Sus capturas iniciales permanecen identificadas como iniciales.

La bitácora desplegada muestra nombres de campos, estados, métodos y fechas legibles,
conversión horaria solo de instantes (las fechas de calendario no retroceden un día) y datos
sensibles protegidos. No reescribe el payload auditado. Se verificó en tres anchos y mediante
regresión, también manteniendo la bitácora española con locale/fallback alternativos.
Los controles propios del navegador, como el selector nativo de archivos, usan su idioma.

Se retiraron etiquetas de demostración y textos de programador visibles sobre contraseña,
credenciales, cierres y terminal. SmartAulaUES mantiene su nombre y versión normales. Los avisos
de compatibilidad/HTTPS/NFC/ubicación y las funciones pendientes conservan explicaciones reales.
Los errores HTTP 403/404/419/429/503 tienen HTML/JSON comprensible; ni APP_DEBUG=true entrega
trazas o SQL en esas respuestas. El diagnóstico privado conserva tipo, archivo, línea, frames
sin argumentos y huella de consulta sin bindings; no mensajes de excepción o cuerpos con secretos.

## Medición comparable del rendimiento

La comparación principal usa **el mismo PHP 8.4.27, código, BD y datos**: 52 usuarios, 16 grupos,
126 sesiones y 959 resultados. Una huella antes/después confirmó que no cambiaron los datos
académicos durante esas mediciones. Sin suite de tests, compilación o carga deliberada en paralelo.

- Antes: contenedor efímero con código/dependencias/vistas compiladas en el montaje Windows,
  OPcache desactivado. Reproduce el coste de la configuración anterior sin revertir código.
- Después: el Compose entregado, `vendor` y `storage/framework` en volúmenes Linux y OPcache
  activado con revisión de timestamps en desarrollo. Código/assets siguen montados desde Windows.
- `scripts/measure-performance.php` realiza cinco GET por pantalla dentro del kernel, registra
  SQL **sin bindings**, número de consultas, render Blade inclusivo y tiempo del servidor; luego
  cinco ejecuciones reales del procesador. Es un instrumento local protegido, no un endpoint público.
- Mediana de repeticiones 2–5; la primera se conserva aparte en el CSV. El arranque PHP incluye
  bootstrap y selección del usuario/grupo principal: **9.214 → 722 ms**. No equivale a una caída
  de caché del sistema operativo. Los tiempos de render pueden incluir SQL, no se deben sumar.
- El kernel reutilizado permite comparar trabajo de aplicación; sus milisegundos **no son TTFB HTTP**.
  La medición real de navegador se presenta por separado más abajo.

| Pantalla | Antes ms | Después ms | Consultas antes/después | SQL después ms |
| --- | ---: | ---: | ---: | ---: |
| Login | 61,0 | 13,6 | 0 / 0 | 0,0 |
| Inicio | 441,1 | 64,8 | 14 / 14 | 20,4 |
| Estudiantes | 205,9 | 32,2 | 3 / 3 | 3,3 |
| Docentes | 194,8 | 31,8 | 3 / 3 | 3,2 |
| Grupos | 413,2 | 54,9 | 9 / 9 | 11,5 |
| Sesiones | 214,2 | 52,5 | 10 / 10 | 12,8 |
| Detalle y resultados | 457,9 | 114,8 | 38 / 38 | 37,3 |
| Permisos | 259,6 | 50,6 | 9 / 9 | 10,7 |
| Importaciones | 279,7 | 39,9 | 4 / 4 | 4,5 |
| Credenciales | 239,8 | 40,9 | 6 / 6 | 5,8 |

[CSV completo con primera petición, SQL y vistas](operacion-rendimiento.csv).
Ejemplo de render: Inicio **410,4 → 37,8 ms** y grupo **371,3 → 58,4 ms**. La mejora principal
medida es E/S/carga/render, no reducción artificial de consultas ni un cache académico.
El rango SQL observado no justificó añadir índices sin evidencia adicional.

Se hicieron además **18 navegaciones HTTP reales en Edge** (tres por ruta/configuración), con
el mismo conjunto de datos y assets compilados. Mediana de las dos repeticiones posteriores:

| Ruta | TTFB antes ms | TTFB después ms |
| --- | ---: | ---: |
| Login | 3.569,1 | 274,4 |
| Inicio | 4.100,2 | 461,7 |
| Detalle de grupo | 5.197,2 | 801,1 |

El primer login de cada contexto de navegador fue 8.494,7/284,1 ms; no representa un arranque
frío idéntico del servicio. Se registraron carga y recursos `/build/`, tamaños transferidos y
cache del navegador. Login/assets también pasaron con Apache. El servidor local sigue siendo
de desarrollo, con concurrencia limitada; estos resultados no certifican capacidad productiva.

### Trabajo de aplicación y scheduler

Antes de optimizar consultas, el diagnóstico sobre el escenario de 944 resultados mostró
29 consultas en cada reintento del cierre bloqueado. Después son **25**: se consulta el permiso
pendiente de forma fresca bajo locks y se evita calcular todas las matrículas mientras ese
impedimento ya bloquea el cierre automático. Primero se conservan finalización y clasificación;
la revisión manual sigue completa y, resuelto el permiso, el siguiente pase calcula y cierra
normalmente. Lo verifican las pruebas de permisos/snapshot y concurrencia.

La comparación de infraestructura con el mismo código conserva esas 25 consultas: medianas
de pases posteriores **45,7/50,8 ms**. No se presenta esa variación como mejora. Las ejecuciones
iniciales antiguas de varios segundos incluían carga de PHP/archivos; las consultas del reintento
eran decenas de milisegundos. Los JSON iniciales y comparables se preservan por separado;
no se mezclan los primeros 18 usuarios con el escenario de 52 para afirmar una ganancia SQL.

Inicio carga seis grupos por página, con totales y próximas clases mediante subconsultas sobre
todos los grupos autorizados. Las regresiones verifican páginas posteriores, totales y sesiones
fuera de la primera página. Se retiró una relación docente innecesaria. La distribución de
estudiantes distingue Egresados de Sin asignar, sin sobrescribir sus conteos. No se cachearon
resultados académicos, permisos o autorización.

### Concurrencia y esperas

Con el worker normal activo, se midieron cinco GET del grupo del ciclo bloqueado y luego
20 GET mientras otro proceso hizo 30 pases adicionales de cierres: **25/25 respuestas 200**.
Medianas TTFB 793,4/699,7 ms; el ruido impide atribuir una ganancia a la carga. Los 30 pases
terminaron sin errores, mediana 58,8 ms. No hubo auditorías o resultados duplicados.

Un muestreador de `pg_stat_activity`/`pg_blocking_pids` tomó **420 muestras** aproximadamente
cada 25 ms: máximo un proceso bloqueado, en nueve muestras. Eso observa contención breve real;
no mide exactamente la duración de cada espera ni demuestra ausencia de bloqueos entre muestras.
La carga deliberada de 30 pases no es la cadencia normal de uno por minuto. Las carreras nativas
separadas verifican serialización e integridad bajo locks, no capacidad del hosting.

## Arranque, cierre real y reinicios

Los Compose local, testing y producción están versionados. Web tiene su proceso en CMD; el
contenedor vivo ya no depende de un servidor iniciado manualmente dentro de `sleep infinity`.
Scheduler es otro servicio con reinicio, exclusión PostgreSQL y salud verificable. Se conservan
los perfiles opcionales pgAdmin y túnel; el override móvil configura explícitamente URL/cookies/proxy.

La comprobación de primera puesta en marcha encontró una carrera al iniciar migraciones antes
de que PostgreSQL aceptara TCP. La guía usa `up -d --wait db`; el healthcheck verifica TCP en
127.0.0.1. La disponibilidad HTTP comprueba conexión, migraciones requeridas y manifest. Un cierre
de conexión transitorio durante el arranque se reintenta en el arnés de verificación.

Prueba operativa con reloj real, distinta de los tests con reloj controlado:

1. Una clase en línea se inició a las **04:45:04 UTC**, fin **04:48:00**; quince matrículas,
   una presencia manual, portal y lector activos. No se amplió ninguna ventana de registro.
2. A las 04:47:13 seguía en curso, un resultado y cero auditorías automáticas. Una clase
   programada anterior nunca iniciada mantenía cero resultados.
3. El servicio `schedule:work` registró su pase de 04:48:01. Finalización observada
   **04:48:04 UTC**: 15 resultados (1 presente/14 ausentes), portal/lector cerrados y
   **una** auditoría `session.auto_finished`. Cierres conservados: 12, sin alteración.
4. Repetir el registro original recuperó su confirmación (HTTP 200, `replayed=true`, mismo
   contenido académico). El arnés excluye ese marcador adicional al comparar el contenido.
5. Se terminó el proceso principal del worker con SIGKILL, comprobando su PID exacto.
   Docker lo recuperó automáticamente; aumentó RestartCount. Se reinició web y se comprobó
   disponibilidad real, sin iniciar Artisan manualmente.
6. `stop`/`up -d` de los tres servicios conservó la huella de datos académicos. Resultado,
   auditoría única, replay y recursos cerrados permanecieron iguales. Salud posterior reciente
   y sin errores. Las observaciones previas y posteriores están en JSON privados.

La prueba y sus IDs corresponden a la BD previa a la reconstrucción final, conservada en el dump
privado. No se atribuyen esos IDs a las nuevas filas del escenario preparado para abrir al terminar.

## Imagen de ejecución con Apache

Se construyó y arrancó `Dockerfile.production` en el proyecto separado
`smartaulaues-runtime-check`, con APP_ENV=production, APP_DEBUG=false y BD propia. Se comprobaron:
migración explícita, ausencia inicial de cuentas/datos ficticios, primera Coordinación explícita,
login real y assets 200, cookie de sesión Secure/HttpOnly/SameSite=Lax, rechazo de los tres comandos
locales, scheduler real, reinicio web y persistencia tras stop/start. La copia privada del escenario
local no existe dentro de esa imagen/volumen. No hubo seeding en ninguno de sus arranques.

La prueba usó **HTTP localhost**, cuya aceptación de cookies Secure en este navegador no equivale
a comprobar TLS del hosting. Se incluye vhost Nginx de ejemplo, pero el dominio/certificado y las
IP de proxy definitivos no se conocen. El proyecto de verificación quedó detenido, con su volumen
conservado. El entorno de revisión que queda activo es el Compose local de 8085.

## Resultados de regresión e inventarios

La regresión completa y las comprobaciones finales se registran en `storage/logs/operation-*`;
el inventario de casos se exporta del JUnit final a [operacion-pruebas-php.csv](operacion-pruebas-php.csv).
Se mantienen **44 pruebas JavaScript**, **37 comprobaciones concurrentes nativas** (6 registro,
2 resumen, 8 cierres, 15 administración de grupos y 6 integración) y la sonda incremental de A05.
Composer validate estricto, plataforma y auditoría sin vulnerabilidades conocidas; build Vite
aprobado. Pint se verificó sobre los PHP añadidos/modificados en este incremento.

Los resultados exactos finales de PHP y el estado listo para abrir se consolidan al final de este
informe. El inventario para un futuro commit está en [operacion-inventario.csv](operacion-inventario.csv);
incluye estados/tamaños/huellas del worktree y excluye secretos, dependencias y evidencia privada.
Los inventarios A05 anteriores describen aquella revisión, no las huellas actuales.

No se certifican CI remoto, HTTPS/proxy del hosting, correo externo, restauración de su backup,
NFC físico, geolocalización física o disponibilidad productiva. La carga local de 52 cuentas y
las verificaciones de paginación no establecen un límite de usuarios del servidor definitivo.
PDF/CSV de resultados, ranking y noticias conservan el alcance pendiente de Persona 3.

## Consolidado final y estado disponible

- **288 pruebas PHP / 4.229 aserciones**, sin warnings ni casos omitidos, en 173,40 s. JUnit
  final exportado al CSV de casos. Las comprobaciones adicionales de cronología del seeder
  pasan en ambos extremos de año; no se omitieron para reducir la duración de la suite.
- **44 JS**, **37 carreras nativas**, sonda incremental, Composer validate/plataforma/audit
  y build Vite aprobados. Pint final: **181 archivos aprobados**.
- **85 páginas / 266 comprobaciones** del recorrido principal, 18 mediciones HTTP y 25
  solicitudes durante el ensayo de contención. Tras reconstruir, otras **9 páginas** de sesión
  en curso verificaron los tres perfiles y anchos, con la cookie local independiente.
- [Índice visual nuevo](operacion-evidencias-visuales.csv): **45 PNG finales** (incluye Apache)
  y **32 iniciales** conservados para distinguir el hallazgo del paginador. Los PNG permanecen
  privados, fuera de Git; los 65 PNG de la revisión A05 anterior no se reemplazaron.
- Reconstrucción real autorizada sobre `smartaulaues_local`, después de verificar las dos
  conexiones y conservar dump completo/acceso cifrado anteriores. Preparación final en 37,42 s,
  repetición sin cambios y contraseña ficticia elegida preservada. Cero cambios en la BD habitual.
- Datos finales base: **52 cuentas, 5 ciclos, 8 materias, 16 grupos, 34 cómputos, 220 matrículas,
  126 sesiones, 944 resultados, 4 permisos y 12 cierres válidos**. Una clase de Cálculo I sigue
  en curso hasta su fin relativo; los estados evolucionan normalmente con el tiempo.
- Web, BD y scheduler locales **running/healthy**, con ejecución reciente sin errores.
  Los auxiliares antiguos y los proyectos de tests/Apache quedan detenidos después de verificar.
  El checkout habitual conserva su web/BD, `.env.example` previo y Microsoft/; su `.env`
  coincide exactamente con la copia privada preservada, incluida APP_KEY.

Abrir **http://127.0.0.1:8085/login**. Obtener la clave local con el comando de
[la guía de operación](operacion-local-y-produccion.md#4-cuentas-y-escenarios-locales).
El código continúa sin commit/staging/push, en la misma rama y base. El inventario de archivos
es una propuesta para revisión futura, no una publicación o una release.
