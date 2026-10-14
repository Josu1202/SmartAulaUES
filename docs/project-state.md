# Estado de continuidad · SmartAulaUES

Fecha de trabajo del equipo: 13/10/2026, America/El_Salvador. Entrega **v0.3.0, fase 2**.
Rama: `feature/phase-2-attendance`. Base local v0.2.0:
`47f48ca4c37efb33809bd8cdf29bc349b0bc5d65`.
Remoto: https://github.com/Josu1202/SmartAulaUES.git. Su última consulta mostraba main/v0.1.2
`446396cb61353fbd391faab0078babe908e43980`; no se comprobó integración de v0.2.0.
No se ha hecho push/PR/merge de esta entrega ni desplegado una URL pública.

## Instrucciones vigentes del usuario

Tres miembros humanos, módulos completos: usuario fase 1, miembro 2 asistencia/permisos, miembro 3
seguimiento/cierre/entrega. Nombres 2–3 pendientes. Solicitud actual: desarrollar fase 2 con docentes
separados de estudiantes, estudiantes masivos/por año, calendarios de cómputos reutilizables, docentes
limitados a sus sesiones y gráficas reales. README contiene el contexto completo para otras IAs.

Se eligieron dos campos: entry_year (ingreso) y study_year (carrera), porque “año” admitía ambos usos.
Sin promoción automática ni inferencia para usuarios antiguos. Ambos se filtran al inscribir grupos.
Formato de ciclo: fechas absolutas por ciclo, copia por grupo; utilizado/heredado inmutable. Variante
nueva requiere otro nombre/formato. El campo version existe, pero no hay un editor automático de versiones.

## Base técnica conservada

Laravel 13/PHP 8.4/PostgreSQL 17; Blade/Tabler 1.6.1 adjunto; negro/rojo/blanco, nombre e icono SmartAulaUES.
No React/Next. `siaa` en base/volumen/correos demo y prefijo NDEF mantiene compatibilidad técnica.
Instantes UTC, visualización/fecha académica America/El_Salvador. Sin datos institucionales reales.
pgAdmin opcional en perfil tools; no reemplaza las migraciones/formularios de Laravel.

## Operativo en v0.3.0

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
- Dos migraciones nuevas; Docker permite cargas 6M/8M; script de carreras independiente en CI.

## Verificación y límites

Consultar docs/test-results.md y verification.json para cantidades/evidencia final; no reutilizar cifras
antiguas como pruebas de esta versión. Runtime PHP 8.4.25/Node 24.19.0, PostgreSQL 17.5 PGlite/PDO,
casos aislados y aplicación real por HTTP. Navegador con NDEF simulado, sin teléfono físico.
Upgrade de esquema v0.2.0 conserva 23 cuentas/contraseñas, 40 inscripciones y 8 sesiones del seed.
Script concurrente tiene smoke con conexiones serializadas; concurrencia real y batería conjunta
quedan para PostgreSQL nativo/CI. Docker/pgAdmin, GitHub Actions y NFC Android/HTTPS físicos no ejecutados aquí.

## Próximo trabajo autorizado: estabilizar fase 2

1. Copiar fuentes a checkout conservando .git/.env/APP_KEY/storage/volúmenes; aplicar migraciones.
2. Ejecutar batería y carreras con PostgreSQL 17 nativo/Compose; revisar el PR con otro miembro.
3. Probar Android/Chrome real por HTTPS, cinco etiquetas y fallos/reintentos descritos en nfc-contract.md.
4. Validar con coordinación calendarios, años y escenarios de permisos de la institución.
5. Corregir hallazgos en ramas pequeñas y registrar versiones/evidencia. No tratar un mock NFC como
   prueba física ni un ZIP como publicación GitHub. El usuario pidió permanecer en fase 2.

Fase 3 sigue pendiente: política aprobada (75 %, denominador, tardanzas, plazos/justificadas), porcentaje
provisional, historial/calendario, cierre/snapshot/PDF privado/versionado, reapertura controlada, noticias,
respaldo/restauración, HTTPS definitivo y manuales. Avisos internos y conteos básicos ya existen en fase 2.
ESP32/PN532, biometría y APIs de hardware son ampliaciones posteriores.

Otra IA debe leer README, AGENTS, CHANGELOG, development-phases, decisions, nfc-contract y test-results.
Si scratch ya no está, recuperar SmartAulaUES_v0.3.0_fase2.zip; preservar el checkout Git del equipo.
No leer/editar materiales PLM externos. No editar migraciones compartidas ni publicar sin instrucción.
