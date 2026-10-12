# Desarrollo por versiones

main contiene el incremento instalable. feature/nombre-de-funcion contiene una tarea breve.
Usar develop solo si el equipo decide necesitar integración intermedia.

1. Crear issue con perfil, flujo, criterios de aceptación y responsable.
2. `git switch -c feature/academic-cycles`.
3. Implementar función, validación, permisos, interfaz, errores y pruebas.
4. Ejecutar las comprobaciones pertinentes; registrar resultados y actualizar CHANGELOG/project-state.
5. Abrir PR y revisar con otra persona del equipo; integrar sin secretos ni archivos generados.
6. Al liberar, actualizar VERSION, config/siaa.php y package.json; `npm install --package-lock-only`.
7. Commit y etiqueta anotada: `git tag -a v0.2.0 -m "v0.2.0 - módulo académico"`.

| Incremento orientativo | Resultado |
| --- | --- |
| 0.1.0 | Base Laravel, acceso, consultas, diseño y diagnóstico NFC |
| 0.1.1 | Corrección de variables de conexión en el servidor HTTP Docker |
| 0.2.0 | CRUD académico, asignaciones, vigencias e inscripciones con cupos |
| 0.3.0 | Credenciales NDEF, confirmación de alta y lector autenticado |
| 0.4.0 | Registro unificado NFC/QR/enlace/manual, idempotencia y finalización |
| 0.5.0 | Permisos, correcciones, avisos e historial |
| 0.6.0 | Cierre de cómputos, cálculo y PDF versionado |
| 1.0.0-demo | Integración estabilizada, respaldo/HTTPS y demostración de seis semanas |

Los números son una propuesta para registrar avances; cada solicitud concreta define el próximo alcance.
La viabilidad NFC/HTTPS se comprueba en la primera semana, aunque su integración tenga otra etiqueta.
No se usan etiquetas de versión para afirmar un despliegue que no se ha hecho.
