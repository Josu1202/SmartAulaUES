# Entorno de trabajo SmartAulaUES

Este repositorio es SmartAulaUES, el sistema de asistencia académica de una materia universitaria.
El usuario autorizó crear los cimientos y continuar por funciones. Leer README.md, CHANGELOG.md,
docs/project-state.md y la guía en docs/reference antes de cambiar el alcance.

Desde v0.2.0 el trabajo se divide en tres fases por módulo completo. El usuario desarrolla fase 1
(cuentas/organización académica/CSV); miembro 2, clases/asistencia/permisos; miembro 3,
seguimiento/cierre/entrega. Leer docs/development-phases.md y docs/decisions.md.
Esta división reemplaza el reparto backend/frontend/integración antiguo de la guía.
Repositorio del usuario: https://github.com/Josu1202/SmartAulaUES.git. No afirmar que una entrega
ZIP está publicada en GitHub. El ZIP v0.2.0 no contiene .git; conservar el checkout del usuario.

- Laravel 13, PHP 8.4, PostgreSQL 17, Blade y fuentes Tabler 1.6.1 adjuntas.
- Negro, rojo y blanco. Reutilizar layouts/componentes; estilos SmartAulaUES en resources/css/app.css.
- No convertir a React/Next ni reemplazar Laravel. No introducir otra aplicación frontend.
- Módulos aún pendientes deben seguir identificados como pendientes; no simular guardados.
- Preservar nombres y relaciones de la guía. Policies por recurso, auth/active por ruta y CSRF.
- La aplicación guarda instantes en UTC y muestra America/El_Salvador.
- No subir .env, claves, vendor, node_modules, evidencias privadas ni datos reales a Git.
- No leer o editar materiales PLM que existen fuera de este repositorio.
- Aplicar migraciones nuevas al ampliar un esquema que ya tenga datos/versiones compartidas.
- Una función incluye pruebas útiles de sus reglas, autorización y fallos relevantes.
- Reutilizar app/Actions/Academic para escrituras/CSV. Motivos y auditoría en la misma transacción.
- Cupos reservan inscripciones futuras; retiro inclusivo y sin borrado. No excluir historial al editar fechas.
- Mantener orden de locks ciclo → grupo → recurso para nuevas operaciones de asistencia/cierre.
- Contraseñas/tokens/hash no se añaden a bitácoras ni al input recuperado. auth.session es obligatorio.
- Diferenciar pruebas PGlite/PDO de PostgreSQL/Compose nativo y NFC físico; documentar evidencia real.
- Verificar composer validate, npm run build, npm test y php artisan test en una BD con sufijo _test.
- Actualizar VERSION, config/smartaulaues.php, package.json, CHANGELOG.md y docs/project-state.md al liberar.
- Usar ramas feature/... y revisión antes de integrar. No hacer push/publicar sin instrucción.
- NFC del docente desde celular es prioridad. ESP32/PN532 y biometría son una ampliación posterior.
- Cuando se solicite un ZIP, excluir secretos/dependencias y registrar versión y estado verificable.
