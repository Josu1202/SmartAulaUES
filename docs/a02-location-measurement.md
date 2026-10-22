# A02 · adquisición acotada y medición de precisión · 22/10/2026

Incremento local sobre la implementación A02, rama `feature/p3a-a02-qr-geolocalizacion`, versión
0.3.2 conservada. Incluye la configuración posterior autorizada para ensayo: radio **30 m** e
incertidumbre máxima **10 m** por dispositivo; conserva `distancia + ambas incertidumbres ≤30 m`.
Sustituye los límites iniciales de 5 m/2,5 m y el ensayo de 50 m/15 m. Implementación para revisión;
aceptación física de A02 pendiente. Sin commit/push/merge/release.
Valida **proximidad aproximada al docente, no presencia dentro del aula**.

El usuario reportó el 22/10 que ninguno de los dispositivos reales comprobados permitió abrir QR/enlace
por precisión insuficiente. No se proporcionaron valores, modelos ni mediciones individuales; no se
inventan resultados. Esta mejora permite obtenerlos antes de decidir la política definitiva. La configuración final 30 m/10 m
todavía no ha sido validada en dispositivos reales.

## Qué cambia

Cada gesto explícito de medir, abrir QR/enlace presencial o registrar obtiene lecturas mediante
`watchPosition` durante un máximo de **15 segundos**. Se solicita alta precisión y caché cero.
Un temporizador propio limita también la espera del permiso, que el timeout nativo puede excluir.
Se conserva una sola candidata reciente: la de menor incertidumbre, con empate a favor del instante
más reciente. No se promedian ubicaciones ni se reduce artificialmente la precisión reportada.
Se descartan lecturas inválidas, futuras fuera de tolerancia o que ya no serían recientes al seleccionar.
El número de lecturas depende de lo que entregue el navegador; una única lectura disponible no se
presenta como múltiples mediciones. La ventana no se prolonga buscando una precisión que no llega.

Al terminar, denegar permiso, cancelar, ocultar la pestaña o abandonar la página, se llama `clearWatch`,
se retiran temporizador y listeners y se descarta la candidata al resolver/rechazar. Los callbacks tardíos
no envían registros ni modifican el resultado. Cancelar no cuenta como fallo geográfico. Al volver a la
página se necesita otro gesto; no se reanuda seguimiento. El diálogo nativo de permisos pertenece al
navegador: la página puede cancelar su solicitud, pero no controla el cierre visual de ese diálogo.

La interfaz muestra mejor precisión reciente obtenida, requisito por dispositivo, cantidad de lecturas
válidas y motivo de error. El redondeo hacia arriba a dos decimales evita mostrar como 10,00 m un valor
ligeramente superior al límite. Esa presentación nunca cambia la validación sobre el valor original.
El botón Cancelar adquisición solo aparece mientras se obtienen lecturas. Los controles se restauran.
Reintentos, recomendación después de tres fallos y paquetes idempotentes tras fallo de red se conservan.

Se añade **Medir precisión sin registrar** en el registro del docente y en el check-in presencial del
estudiante. El estudiante también puede medir en el detalle autorizado de su clase, aunque no haya
portal abierto. Esta medición solo muestra números en esa página: no abre portales, no envía solicitudes
de asistencia y no guarda datos. No hay una pantalla independiente ni permisos nuevos.

## Configuración de prueba y reglas conservadas

- Radio de **30 m**, incertidumbre individual **>0 y ≤10 m**, fórmula `d + aD + aE ≤30 m`.
  Radio e incertidumbre se definen en `config/geolocation.php`; no se aceptan cambios del navegador.
  Con ambas incertidumbres en 10 m, la separación estimada máxima es 10 m.
- Antigüedad máxima **30 s**, tolerancia futura **5 s**, referencia fija al abrir el portal.
- Validación del servidor, autorizaciones, inscripción, locks, idempotencia y cálculo A01.
- Clases en línea sin adquisición; NFC/manual con sus reglas existentes.
- Sin nuevas migraciones, dependencias, variables env, coordenadas en logs o historial de movimientos.

Una precisión individual suficiente no confirma por sí sola la asistencia: el servidor todavía debe
validar distancia, incertidumbre combinada y las demás reglas. Puede seguir siendo imposible abrir
el portal con los dispositivos probados: se debe medir también con los nuevos límites. El radio no
identifica las paredes del aula y puede aceptar ubicaciones fuera de ella.

API y límites: [W3C Geolocation](https://www.w3.org/TR/geolocation/).

## Actualizar recursos

Desde la raíz de esta rama:

```powershell
docker compose run --rm node npm run build
```

El build es necesario porque cambió el mensaje JavaScript de confirmación. Si la instalación usa
caché de configuración (`bootstrap/cache/config.php`), ejecutar además:

```powershell
docker compose exec -T app php artisan config:clear
```

En este checkout esa caché no estaba activa. No hace falta otra migración ni reiniciar el servidor
de desarrollo habitual. Si la app estaba apagada, iniciarla con `docker compose up -d app`.

Los portales leen los límites actuales: una referencia previa >10 m exige cerrar/reabrir; no se
reescriben evidencias. Para esta prueba, cerrar siempre los portales previos y abrir uno nuevo.
Recargar la página en ambos dispositivos para usar el JavaScript nuevo. Si usas un túnel HTTPS,
mantén tu configuración existente y la misma URL en ambos celulares; no regenerar APP_KEY.
Esta mejora no requiere otra migración. El soporte de conexión está en [mobile-https.md](mobile-https.md).

## Prueba sencilla con dos dispositivos reales

1. Usar HTTPS, una clase presencial y cuentas autorizadas. Mantener cada equipo quieto durante
   la adquisición. Registrar modelo, sistema/navegador y si la prueba es interior o exterior, sin coordenadas.
2. **Docente:** abrir el detalle de su clase y pulsar **Medir precisión sin registrar**. Permitir ubicación
   y esperar hasta 15 s. Anotar la mejor precisión y el número de lecturas que aparecen. Repetir tres veces.
3. **Estudiante:** iniciar sesión en su dispositivo, entrar a Sesiones y asistencia y al detalle de la
   clase en la que está inscrito. Pulsar el mismo botón y repetir tres veces. Funciona sin QR ni portal
   abierto; así puede medir aunque el docente haya obtenido precisión insuficiente.
4. Si ambos dispositivos alcanzan ≤10 m, cerrar los portales anteriores, abrir QR/enlace como docente
   y registrar como estudiante. Cada acción inicia una nueva adquisición; la medición previa no se reutiliza ni registra.
   Anotar separación física medida y si el servidor acepta, indica fuera del área o incertidumbre
   combinada insuficiente. Repetir cerca de la referencia, a unos 10 m y más allá de 30 m, con el
   docente quieto en el punto inicial. Una lectura individual suficiente no garantiza aceptación.
   No interpretar un éxito como presencia dentro del aula.
5. Durante otra medición, pulsar Cancelar: debe detenerse, quedar disponible Reintentar/Medir y no
   abrirse portal ni asistencia. Cambiar de pestaña o bloquear el teléfono durante otra adquisición:
   al volver debe estar cancelada, sin reanudar. Comprobar que puede iniciarse otra medición.
6. Denegar permiso y probar precisión insuficiente: mensajes específicos y recomendación tras tres
   fallos, manteniendo disponible un cuarto intento. Continuar con NFC/manual si no alcanza el requisito.

| Rol/dispositivo | Entorno | Intento | Mejor precisión (m) | Lecturas válidas | Resultado |
| --- | --- | --- | --- | --- | --- |
| Docente | Interior/exterior | 1–3 | Anotar lo mostrado | Anotar | Suficiente/insuficiente/denegado/cancelado |
| Estudiante | Interior/exterior | 1–3 | Anotar lo mostrado | Anotar | Suficiente/insuficiente/denegado/cancelado |

No incluir coordenadas, tokens o QR completos en los resultados que se compartan. Los números
de precisión son estimaciones del dispositivo, no una prueba de su exactitud real. Conservar separado
el resultado del diagnóstico y el resultado real del registro. A02 continúa abierto a aceptación física.

## Comprobación física mínima por HTTPS (pendiente)

Cerrar el portal anterior, recargar ambos equipos y abrir uno nuevo como docente, con referencia
fija y precisión ≤10 m. Usar estudiante activo e inscrito y sesión en horario. Repetir QR y enlace.

| Caso | Comprobación esperada |
| --- | --- |
| Cerca de la referencia inicial | Con incertidumbres ≤10 m y suma estimada ≤30 m, registro confirmado por el servidor; una sola asistencia. Si falla, anotar precisión y mensaje, sin darlo por aceptado. |
| Separación física medida mayor de 30 m | QR/enlace rechazados, sin nueva asistencia. Registrar separación, precisiones y resultado; una aceptación inesperada requiere revisar el ensayo, no certificar GPS. |
| Clase no presencial | Con ubicación denegada, apertura y registro por QR/enlace sin adquisición de ubicación. |

Las pruebas automáticas no sustituyen estos tres casos. Compartir solo separación, precisiones y
resultado; no coordenadas, tokens ni QR completos. Un éxito no acredita presencia dentro del aula.

## Verificación de este incremento

Pruebas y resultados de esta revisión se registran en [a02-verification.md](a02-verification.md),
separados de la batería del 21/10. Las pruebas con reloj/ubicación simulados verifican selección,
límite temporal, cancelación, liberación, diagnóstico y flujo; no demuestran mejora de GPS real.
