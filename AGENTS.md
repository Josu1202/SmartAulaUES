# Entorno de trabajo SIAA

Este repositorio es el Sistema Integral de Asistencia Académica de una materia universitaria.
El usuario autorizó crear los cimientos y continuar por funciones. Leer README.md, CHANGELOG.md,
docs/project-state.md y la guía en docs/reference antes de cambiar el alcance.

- Laravel 13, PHP 8.4, PostgreSQL 17, Blade y fuentes Tabler 1.6.1 adjuntas.
- Negro, rojo y blanco. Reutilizar layouts/componentes; estilos SIAA en resources/css/app.css.
- No convertir a React/Next ni reemplazar Laravel. No introducir otra aplicación frontend.
- Módulos aún pendientes deben seguir identificados como pendientes; no simular guardados.
- Preservar nombres y relaciones de la guía. Policies por recurso, auth/active por ruta y CSRF.
- La aplicación guarda instantes en UTC y muestra America/El_Salvador.
- No subir .env, claves, vendor, node_modules, evidencias privadas ni datos reales a Git.
- No leer o editar materiales PLM que existen fuera de este repositorio.
- Aplicar migraciones nuevas al ampliar un esquema que ya tenga datos/versiones compartidas.
- Una función incluye pruebas útiles de sus reglas, autorización y fallos relevantes.
- Verificar composer validate, npm run build, npm test y php artisan test en una BD con sufijo _test.
- Actualizar VERSION, config/siaa.php, package.json, CHANGELOG.md y docs/project-state.md al liberar.
- Usar ramas feature/... y revisión antes de integrar. No hacer push/publicar sin instrucción.
- NFC del docente desde celular es prioridad. ESP32/PN532 y biometría son una ampliación posterior.
- Cuando se solicite un ZIP, excluir secretos/dependencias y registrar versión y estado verificable.
