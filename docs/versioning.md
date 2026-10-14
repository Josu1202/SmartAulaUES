# Desarrollo por versiones · SmartAulaUES

main contiene incrementos revisados/instalables. feature/... contiene trabajo con criterios y pruebas.
Usar develop solo si el equipo acuerda una integración intermedia. Cada miembro implementa su módulo
completo; ninguna IA debe asignar nombres ficticios o afirmar que el ZIP ya fue publicado.

1. Issue con perfil, flujo, errores, criterios y responsable; rama desde base correcta.
2. Implementar servidor/vista/permisos/validaciones y pruebas útiles.
3. Registrar verificación real y actualizar README, CHANGELOG y docs/project-state.
4. PR/revisión de otro miembro, CI con PostgreSQL nativo y aceptación requerida antes de integrar.
5. Actualizar VERSION, config/smartaulaues.php, package.json y package-lock.json al liberar.
6. Commit/etiqueta anotada; empaquetar desde checkout limpio y assets compilados.

| Versión | Estado / resultado |
| --- | --- |
| 0.1.0 | Base Laravel/acceso/consultas/diseño/diagnóstico NFC |
| 0.1.1 | Corrección de conexión del servidor HTTP Docker |
| 0.1.2 | Marca SmartAulaUES e icono, actualización compatible |
| 0.2.0 | Fase 1: acceso/cuentas/CRUD académico/cupos/vigencias/CSV/bitácora |
| 0.3.0 | Fase 2: años/directorios/formatos, sesiones, credenciales y registro NFC/QR/enlace/manual, permisos/evidencias, correcciones, avisos y conteos |
| Próximas 0.x | Hallazgos/estabilización de fase 2 y luego incrementos concretos de fase 3 |
| 1.0.0-demo | Propuesta futura: integración/HTTPS/respaldo y demostración aceptada |

El plan anterior sugería 0.3 para credenciales, 0.4 para registro y 0.5 para permisos. La solicitud
actual reúne esos flujos en v0.3.0; no existen entregas 0.4/0.5 por el mero hecho de estar previstas.
Las etiquetas registran fuente; no certifican despliegue, CI o pruebas físicas.

Remoto https://github.com/Josu1202/SmartAulaUES.git. Base local v0.2.0:
47f48ca4c37efb33809bd8cdf29bc349b0bc5d65. Último main remoto observado: v0.1.2,
446396cb61353fbd391faab0078babe908e43980. v0.3.0 se prepara en feature/phase-2-attendance,
con historial local conservado y ZIP sin .git. Seguir update-v0.3.0.md para copiar/publicar desde el
checkout del usuario; revisar dependencia de fase 1 si todavía no se integró. No sobrescribir etiquetas.

`composer run package` requiere commit limpio, usa archivos versionados y public/build, excluye
secretos/dependencias/datos y crea ZIP/checksum/manifiesto con commit y hashes. No hace push.
