# Notas de cambio · estado y asignación

La petición posterior de datos, presentación, rendimiento y operación se implementa sobre
los mismos avances A05: [estado y evidencia nuevos](operacion-verificacion.md),
[comandos vigentes](operacion-local-y-produccion.md) y [cuentas](escenarios-y-cuentas-locales.md).
No modifica la versión ni atribuye como completadas las aceptaciones físicas/hosting de Persona 3.

## Estado vigente A05 · 24/10

Las siete solicitudes transcritas se atienden en A05: textos residuales, año 1–5/Egresado,
contraseña común con cambio obligatorio, NFC por lote, teléfono con etiqueta compartida,
fin automático en ends_at y formularios aislados. [Decisiones y límites](a05-decisiones.md),
[entrega](a05-entrega.md), [evidencia](a05-verificacion-final.md).
Web NFC no emula una tarjeta; la etiqueta compartida conserva A02 y no acredita por sí sola
lectura física. NFC/GPS reales y hosting siguen pendientes externos. La espera diaria A03
queda sustituida expresamente; los registros y la tabla inferiores son históricos.

22/10/2026: A01/A02 integrados por PR #3/#4, base bf07b01. A03 local para revisión:
la solicitud de finalizar automáticamente se concreta como respaldo al terminar el día local del inicio
real, sin ampliar ventanas ni finalizar solo por ends_at. Cierres, snapshots y reapertura en
[a03-closures.md](a03-closures.md); [verificación](a03-verification.md).
La fila histórica «Finalizar clase al vencer horario» de abajo queda sustituida por este contrato.
Sin release nueva; GPS/HTTPS físico y hosting pendientes. El resto conserva su asignación F3 r1.

Actualización A02, 21/10/2026: QR/enlace geográficos implementados localmente en su rama, sin release
ni integración de A02. Ensayo físico pendiente. [Contrato](a02-geographic-access.md) y
[evidencia](a02-verification.md). NFC secuencial/teléfono sin tarjeta y finalización automática
continúan fuera de este incremento. A01 integrado en main, base comprobada 18c7596.

Se leyó el original `notasDeCambio_SmartAulaUES.txt` proporcionado en Downloads y se contrastó con código.
Esta lista no declara aceptación completa de A02 ni entrega de A03–A05/B01–B07.

| Solicitud | Estado | Evidencia / trabajo restante | Módulo o asignación sugerida |
| --- | --- | --- | --- |
| Eliminar Entorno de demostración / Simulación | Parcial global; cumplido en A01 | La tabla expresa reglamento vigente y cálculo actualizable; el layout académico muestra Campus. Login aún conserva su texto condicional. No se quitaron protecciones DEMO_SEED ni se presentaron fixtures como reales. | Limpieza restante de experiencia B04/B05, sin bloquear A01 |
| Carrera de 1.º a 5.º y egresados | Pendiente | Requests/BD actuales aceptan 1–10. Conservar entry_year separado. Requiere definir representación de egresado y migración nueva; no inventar un entero. | Organización académica / coordinación con A05 y B04 |
| Clave inicial común para altas masivas | Cumplido en la implementación existente | ImportController exige contraseña común fuera del CSV, confirmación y 12–128 caracteres; guarda hash en revisión. CsvImporter::confirm aplica ese hash a todas las altas. Lote manual reutiliza el flujo. Pruebas CSV/lote existentes conservadas; no se reimplementó. | Ya existente, mantener contrato en A05 |
| Credenciales NFC secuenciales por año | Pendiente | Emisión actual individual. Requiere selección de lote, avance por estudiante, expiración y confirmación física de cada etiqueta, sin tokens persistidos. | Incremento propio de credenciales fase 2 / coordinación con A02 |
| NFC desde teléfono del estudiante sin tarjeta | Pendiente de decisión y viabilidad | Web NFC actual lee/escribe NDEF; no se ha validado un mecanismo que convierta el teléfono del alumno en una tarjeta compatible. No se promete HCE o sustitución universal. | Estudio propio de registro/NFC, fuera de A01 |
| Finalizar clase al vencer horario | Pendiente | SessionWorkflow finaliza explícitamente. Expirar portal no finaliza una clase ni prueba que se realizó. Se deben definir condiciones/evidencia, idempotencia y scheduler, sin inventar clases ni aprobar permisos. | A03 con operación B07 |

Ranking, gráficas, tendencias, dashboards y exportaciones siguen asignados a B01/B02; la tabla mínima
de resultados forma parte de A01. Certificación, snapshots y reapertura corresponden a A03.
