# Desarrollo por versiones

main contiene el incremento instalable. feature/nombre-de-funcion contiene una tarea breve.
Usar develop solo si el equipo decide necesitar integración intermedia.

1. Crear issue con perfil, flujo, criterios de aceptación y responsable.
2. `git switch -c feature/academic-cycles`.
3. Implementar función, validación, permisos, interfaz, errores y pruebas.
4. Ejecutar las comprobaciones pertinentes; registrar resultados y actualizar CHANGELOG/project-state.
5. Abrir PR y revisar con otra persona del equipo; integrar sin secretos ni archivos generados.
6. Al liberar, actualizar VERSION, config/smartaulaues.php y package.json; `npm install --package-lock-only`.
7. Commit y etiqueta anotada: `git tag -a v0.2.0 -m "v0.2.0 - módulo académico"`.

| Incremento orientativo | Resultado |
| --- | --- |
| 0.1.0 | Base Laravel, acceso, consultas, diseño y diagnóstico NFC |
| 0.1.1 | Corrección de variables de conexión en el servidor HTTP Docker |
| 0.1.2 | Nombre SmartAulaUES e icono propio, con actualización compatible |
| 0.2.0 | Fase 1: cuentas/contraseñas, CRUD académico, cupos/vigencias, CSV con preview y bitácora |
| 0.3.0 | Credenciales NDEF, confirmación de alta y lector autenticado |
| 0.4.0 | Registro unificado NFC/QR/enlace/manual, idempotencia y finalización |
| 0.5.0 | Permisos, correcciones, avisos e historial |
| 0.6.0 | Cierre de cómputos, cálculo y PDF versionado |
| 1.0.0-demo | Integración estabilizada, respaldo/HTTPS y demostración de seis semanas |

Los números son una propuesta para registrar avances; cada solicitud concreta define el próximo alcance.
La viabilidad NFC/HTTPS se comprueba en la primera semana, aunque su integración tenga otra etiqueta.
No se usan etiquetas de versión para afirmar un despliegue que no se ha hecho.

Remoto actual: https://github.com/Josu1202/SmartAulaUES.git. La base pública v0.1.2 se verificó
en el commit 446396cb61353fbd391faab0078babe908e43980. v0.2.0 se entrega como ZIP, con su
rama local de desarrollo; no se ha integrado ni publicado automáticamente en GitHub.
Seguir docs/update-v0.2.0.md para copiar fuentes al checkout existente y abrir PR hacia main.
Las fases posteriores pueden tener varias versiones; docs/development-phases.md define responsabilidades.
