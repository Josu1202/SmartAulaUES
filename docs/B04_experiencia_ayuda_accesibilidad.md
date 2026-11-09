# B04 — Tutorial por perfil, Ayuda y accesibilidad

## Alcance

El tutorial de SmartAulaUES utiliza el rol real del usuario autenticado, sin atribuirle permisos nuevos ni pedir GPS, NFC o cámara. Coordinación recibe cinco pasos (directorios, formatos, grupos, cierres, reportes), Docente seis (grupos, sesiones, métodos, permisos, seguimiento, exportaciones) y Estudiante cinco (calendario, asistencia propia, historial, permisos, avisos).

## Estado persistente

La migración `2026_11_08_000400_create_user_tutorial_progress_table` crea `user_tutorial_progress` con índice único `(user_id, role, version)`. La versión actual está en `TutorialCatalog::VERSION`. `skipped` y `completed` se guardan mediante `upsert` con fechas en PostgreSQL; la omisión sobrevive al cierre de sesión, borrado de cookies o cambio de dispositivo. Repetir el recorrido desde **Ayuda** no cambia el estado guardado; sólo completar u omitir vuelve a guardar el resultado. Un cambio futuro de versión permite presentar novedades por versión.

## Experiencia

- Primer acceso tras completar los requisitos de inicio de sesión: diálogo nativo accesible con 5–6 pasos del perfil. No se ofrece durante el cambio obligatorio de contraseña.
- Botones Anterior, Siguiente, Omitir, Finalizar y Cerrar. Escape registra omisión mediante el formulario POST con CSRF.
- `aria-labelledby`, descripción, aviso de progreso `aria-live`, foco al título/etapa, captura de Tab por el diálogo nativo y foco visible.
- Ayuda `/help` ofrece el recorrido repetible y todos los pasos con enlaces autorizados, incluso sin JavaScript. Ningún paso apunta a opciones de otro perfil.
- La interfaz reutiliza Tabler, el logo, los colores y `<x-icon>`; añade un icono Ayuda, foco consistente en formularios y una tabla desplegable de datos junto a las gráficas.
- Diálogo con altura acotada, ancho adaptable y botones táctiles; CSS dedicado a anchos 320/390 px y `prefers-reduced-motion`.

## Seguridad

Rutas GET y POST dentro de los middleware existentes `auth`, `active`, `auth.session`, `password.changed`. No se usan `localStorage`, cookies adicionales ni parámetros de usuario para guardar el progreso. El identificador del usuario siempre procede de la sesión. Los POST mantienen CSRF y limitación de frecuencia. La ayuda se limita a destinos que ya respetan sus políticas y middleware de acceso.

## Verificación recomendada

1. `php artisan migrate --force` **sólo en el entorno de desarrollo después de aprobar el parche**. No utilizar `migrate:fresh` ni sembrar usuarios reales.
2. Ejecutar `php artisan test --filter=B04TutorialTest` en PostgreSQL `_test`, luego suite completa.
3. Ejecutar `vendor/bin/pint --test`, `npm test`, `npm run build` y `php artisan view:cache`.
4. Desde una cuenta nueva de cada perfil, confirmar el recorrido correcto. Omitir, cerrar con Escape, volver a entrar y verificar que no reaparece automáticamente; repetir desde Ayuda.
5. Probar teclado con Tab / Shift+Tab, foco inicial, Escape, enlaces sin permisos ajenos y sin solicitud temprana de NFC/cámara/ubicación.
6. Usar la herramienta de tamaño responsivo del navegador en 320 px, 390 px y escritorio; probar scroll del diálogo y tablas, ambos temas si están disponibles, junto con movimiento reducido.
7. Comprobar con lector de pantalla si está disponible; no afirmar conformidad WCAG completa sin auditoría externa.

## Archivos académicos

No se modifica el calculador, el snapshot de cierre, las políticas de asistencia ni las migraciones de los módulos A01–A05 del Integrante 2.

## Limitaciones de verificación del paquete

El parche se preparó sobre la copia B02.5 del usuario reconstruida con el parche B03 conocido. Las pruebas de JS y la sintaxis PHP pueden verificarse durante la preparación del paquete; la suite Laravel real requiere el contenedor Docker del usuario (ZIP sin `vendor/`). Las capturas 320/390 px y el comportamiento de foco necesitan validación manual en navegador.
