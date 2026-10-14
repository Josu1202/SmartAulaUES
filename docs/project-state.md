# Estado de continuidad · SmartAulaUES

Fecha: 13/10/2026. Versión entregada: **0.2.0**. Rama de trabajo:
`feature/phase-1-academic-administration`. Base: v0.1.2/main, commit
`446396cb61353fbd391faab0078babe908e43980`.
Remoto del usuario: **https://github.com/Josu1202/SmartAulaUES.git**. La base pública se recuperó y
comparó con ese commit; los cambios de esta entrega no se han subido ni integrado en GitHub.

## Decisiones vigentes

Laravel 13/PHP 8.4/PostgreSQL 17, Blade/Tabler 1.6.1; negro, rojo y blanco. Nombre SmartAulaUES,
icono propio en public/brand. Se conservan `siaa` en BD/proyecto/volumen/correos demo para reutilizar datos.
UTC para instantes; America/El_Salvador para presentación y fecha de vigencia/cupo.

Solicitud actual: **tres fases, una por miembro humano**, usuario responsable de fase 1. Se sustituye
el reparto por especialidades de la guía por módulos completos. README y docs/development-phases.md
son el contexto principal. Fases 2–3 no implementadas; responsables nominales todavía pendientes.
NFC móvil Android/Chrome/HTTPS es prioridad; hardware/biometría posteriores.

## Implementado en v0.2.0

- Administración de cuentas, estado, perfiles/carné y correos normalizados; índice único lower(email).
- Restablecimiento por coordinación con motivo y cambio personal de contraseña. Hash/revocación de
  sesiones/remember_token; middleware auth.session. No hay envío de correo ni autoservicio de recuperación.
- Crear/editar ciclos, catálogo y grupos; validar docentes activos/cupos y conservar identidad con historial.
- Cómputos dentro de ciclo sin superposición; cómputos cerrados/en cierre y ciclos cerrados de lectura.
- Inscripciones de estudiantes activos; cupo con lock padre; vigencia/retiro inclusivo, sin borrado;
  protección de fechas asociadas a asistencias/solicitudes existentes.
- CSV independiente de usuarios e inscripciones: plantillas, UTF-8/comas, 500 filas/1 MB, preview de 15 min,
  errores, nonce/propietario/CSRF, revalidación, transacción completa y confirmación no repetible.
- Bitácora de cambios con campos permitidos, actor y motivo; sin secretos.
- pgAdmin 9.17 opcional, localhost:5050, perfil tools, volumen separado, conexión sin contraseña en JSON.
- Forms Blade compartidos, navegación y diseño responsive; pruebas de reglas/roles/CSV/acceso.

Archivo central: app/Actions/Academic/AcademicRegistry.php. Acciones de usuarios, auditoría e importación
en la misma carpeta. Controladores Admin y Requests solo coordinan autorización/entrada. No duplicar reglas.

## Límites y pendientes

- Fase 2: formularios de clases, estados, portales/lector, alta/revocación NFC y registro unificado;
  QR/enlace/manual, ausentes, permisos, evidencias privadas y correcciones.
- Fase 3: porcentaje/historial/calendario, avisos/notificaciones, política validada, cierre/snapshot/PDF
  privado, HTTPS, respaldo/restauración probado e integración final.
- Validar 75 %, tardanzas, justificadas, denominador, plazos y reglas de retiro. config/attendance.php
  contiene solo propuesta demo; policy_version de nuevos cómputos usa esa propuesta.
- PostgreSQL/Compose/pgAdmin nativos y GitHub Actions deben verificarse en equipo con Docker/CI.
- Teléfono NFC y escritura/comprobación física de etiquetas todavía pendientes. No se ha desplegado una URL pública.
- Tests y navegador del entorno usan PostgreSQL 17.5 embebido vía PGlite/PDO. Registrar las limitaciones
  en docs/test-results.md; no confundirlo con pruebas nativas de concurrencia/carga.

## Cómo continuar con otra IA

1. Leer README, AGENTS.md, CHANGELOG, docs/development-phases.md, decisions.md y test-results.md.
2. Recuperar `SmartAulaUES_v0.2.0_fase1.zip` si el workspace ya no está; usar el checkout GitHub existente
   para preservar su historial. ZIP sin .git/.env/dependencias; no equivale a cambios publicados.
3. Completar la comprobación Docker/CI de fase 1 y asignar los nombres de responsables 2–3.
4. Trabajar funciones de fase 2 en ramas pequeñas con Policies del docente asignado, fechas de vigencia,
   estado de ciclo/cómputo y orden de locks ciclo → grupo → recurso.
5. Probar NFC físico pronto. El diagnóstico actual lee NDEF pero no registra ni escribe etiquetas.
6. Actualizar README/estado/changelog con cada función/versionado. No presentar tablas/dependencias
   preparadas como módulos terminados. No hacer push, merge ni publicación sin instrucción del usuario.

Una actualización conserva .env, APP_KEY, contraseñas y volumen; aplica únicamente nuevas migraciones.
`docs/update-v0.2.0.md` incluye la incorporación a GitHub mediante rama/PR sin reemplazar `.git`.
