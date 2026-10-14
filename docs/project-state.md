# Estado de continuidad · SmartAulaUES

Fecha de trabajo del equipo: 14/10/2026, America/El_Salvador. Entrega **v0.3.2, fase 2**.
Rama: `feature/mobile-https`. Base pública main/v0.3.1:
`7b9b11826b0488cac26cb9febfa7f2741f186c45`.
Remoto: https://github.com/Josu1202/SmartAulaUES.git. Consulta del 14/10/2026 para esta entrega:
main tiene VERSION=0.3.1; fase 2 está en `a697e84589cb0c3a7c65a2520d12644a93c3b711`,
también VERSION=0.3.1. Ambos árboles son iguales al código local v0.3.1 previamente entregado.
Fases 1 y 2, incluida la corrección de bitácora/entorno CI, ya están integradas en main.
El usuario pide HTTPS para probar desde el celular: se prepara Cloudflare Quick Tunnel hacia su Docker,
con sus mismas cuentas/BD, sin dominio/cuenta de Cloudflare ni despliegue independiente.
El enlace lo generará el contenedor tunnel en su computadora. No se ha iniciado aquí ni hecho push de v0.3.2.
Docker/Cloudflare reales, NFC físico y nueva ejecución CI siguen pendientes; no afirmar esos resultados.

## Instrucciones vigentes del usuario

Tres miembros humanos, módulos completos: usuario fase 1, miembro 2 asistencia/permisos, miembro 3
seguimiento/cierre/entrega. Nombres 2–3 pendientes. Solicitud actual: desarrollar fase 2 con docentes
separados de estudiantes, estudiantes masivos/por año, calendarios de cómputos reutilizables, docentes
limitados a sus sesiones y gráficas reales. Ahora se habilitan pruebas móviles por HTTPS manteniendo fase 2.
README contiene el contexto completo para otras IAs.

Se eligieron dos campos: entry_year (ingreso) y study_year (carrera), porque “año” admitía ambos usos.
Sin promoción automática ni inferencia para usuarios antiguos. Ambos se filtran al inscribir grupos.
Formato de ciclo: fechas absolutas por ciclo, copia por grupo; utilizado/heredado inmutable. Variante
nueva requiere otro nombre/formato. El campo version existe, pero no hay un editor automático de versiones.

## Base técnica conservada

Laravel 13/PHP 8.4/PostgreSQL 17; Blade/Tabler 1.6.1 adjunto; negro/rojo/blanco, nombre e icono SmartAulaUES.
No React/Next. `siaa` en base/volumen/correos demo y prefijo NDEF mantiene compatibilidad técnica.
Instantes UTC, visualización/fecha académica America/El_Salvador. Sin datos institucionales reales.
pgAdmin opcional en perfil tools; no reemplaza las migraciones/formularios de Laravel.

## Operativo desde v0.3.0; conservado en v0.3.2

Fase 1 completa conservada: acceso/contraseñas/revocación, cuentas/perfiles/estado/carné/correo,
ciclos/materias/grupos/cupos, cómputos individuales, inscripción/retiro/fechas históricas, CSV y bitácora.

- Directorios Estudiantes, Docentes y Coordinación; altas individuales con perfil fijo.
- Estudiantes con ingreso/carrera; lote manual 100, CSV 500/1 MB y CSV que cambia únicamente años de
  cuentas existentes. Preview 15 min, nonce/propietario/CSRF, revalidación y lote atómico.
- Inscripción de estudiantes activos por filtro/selección, vigencia común y cupo, páginas de 25.
- CycleFormat/CycleFormatPeriod, calendario copiado al crear grupo, fechas/rango/no solapamiento,
  formato utilizado y cómputos heredados protegidos. Grupos previos no se migran a otro calendario.
- Sesiones de docente asignado/coordinación: programación, reprogramación/cancelación con motivo,
  estados, fin con ausentes/justificadas y preservación de presencia; horario propio no solapado.
- Portales temporales, QR PNG, enlace por fragmento, login de estudiante conservando token en memoria;
  lector NFC propio con expiración/cierre. NFC/manual/QR/enlace convergen en RegisterAttendance.
- UUID ligado a actor/cuerpo, advisory lock global y una respuesta almacenada; replay y conflicto 409;
  resultado único inscripción/sesión. UI no confirma lecturas sin respuesta y reutiliza UUID al reintentar.
- Credenciales pendiente/escritura/lectura/activación/reemplazo/revocación; una activa por usuario,
  hashes en BD y tokens temporales fuera de logs. Alta pendiente 10 min.
- Correcciones presente/ausente con motivo; no sobrescribir justificación resuelta. Calendario cerrado bloquea.
- Permisos preventivos/posteriores, evidencia PDF/JPG/PNG privada 5 MB, decisión del docente/coordinación,
  justificación y avisos internos. Pendientes visibles tras finalizar; resolución no se reabre todavía.
- Gráficas accesibles con conteos autorizados y vacíos honestos; no porcentaje institucional.
- Auditoría y traducciones/filtros para entidades de ambas fases; no secretos ni rutas privadas en snapshots.
- Dos migraciones de v0.3.0; Docker permite cargas 6M/8M; script de carreras independiente en CI.
- v0.3.2: perfil mobile/tunnel, proxy HTTPS configurable, guía con URL/cookies/caché/reinicio y vuelta a HTTP local. No migraciones nuevas.

## Verificación y límites

Consultar docs/test-results.md y verification.json para cantidades/evidencia final; no reutilizar cifras
antiguas como pruebas de esta versión. Runtime PHP 8.4.25/Node 24.19.0, PostgreSQL 17.5 PGlite/PDO,
casos aislados y aplicación real por HTTP. Navegador con NDEF simulado, sin teléfono físico. En v0.3.2 se repitió el recorrido por un proxy TLS local
(HTTPS): contexto seguro, cookies Secure, login, URLs/recursos y formularios comprobados; no túnel público real.
Upgrade de esquema v0.2.0 conserva 23 cuentas/contraseñas, 40 inscripciones y 8 sesiones del seed.
Script concurrente tiene smoke previo con conexiones serializadas; concurrencia real y batería conjunta
quedan para PostgreSQL nativo/CI. El CI de v0.3.0 reportado falló antes de ejecutar ese script; no se consultaron resultados CI de la integración posterior.
Docker/pgAdmin y NFC Android/HTTPS físicos no ejecutados aquí.

## Próximo trabajo autorizado: estabilizar fase 2

1. Copiar v0.3.2 sobre un checkout de main/v0.3.1 en feature/mobile-https; conservar .git/.env/APP_KEY/storage/volúmenes.
2. Seguir docs/mobile-https.md: crear el túnel en Docker, configurar la URL/cookies/proxy y reiniciar app.
3. Ejecutar batería y carreras con PostgreSQL 17 nativo/Compose; revisar el PR con otro miembro.
4. Probar Android/Chrome real por HTTPS, cinco etiquetas y fallos/reintentos descritos en nfc-contract.md.
5. Validar con coordinación calendarios, años y escenarios de permisos de la institución.
6. Corregir hallazgos en ramas pequeñas y registrar versiones/evidencia. No tratar un mock NFC como
   prueba física ni un ZIP como publicación GitHub. El usuario pidió permanecer en fase 2.

Fase 3 sigue pendiente: política aprobada (75 %, denominador, tardanzas, plazos/justificadas), porcentaje
provisional, historial/calendario, cierre/snapshot/PDF privado/versionado, reapertura controlada, noticias,
respaldo/restauración, HTTPS definitivo y manuales. Avisos internos y conteos básicos ya existen en fase 2.
ESP32/PN532, biometría y APIs de hardware son ampliaciones posteriores.

Otra IA debe leer README, AGENTS, CHANGELOG, development-phases, decisions, nfc-contract y test-results.
Si scratch ya no está, recuperar SmartAulaUES_v0.3.2_fase2.zip; preservar el checkout Git del equipo.
No leer/editar materiales PLM externos. No editar migraciones compartidas ni publicar sin instrucción.
