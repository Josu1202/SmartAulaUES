# Propuesta de PR · A04

Título: **feat: organizar grupos actuales e historial y su gestión administrativa (A04)**

Base `5172b91`, rama `feature/p3a-a04-gestion-grupos`. Texto propuesto según
`.github/pull_request_template.md`; no se creó PR ni commit.

## Cambio y resultado

Materias y grupos distingue Actuales e Historial según el estado del ciclo y permite consultar
desactivados con filtros de ciclo/estado. El inicio muestra únicamente grupos habilitados de ciclos
no cerrados, incluidos los vencidos abiertos. Coordinación puede suspender/reactivar con motivo y
auditoría; solo elimina grupos demostrablemente vacíos mediante confirmación explícita.

Las escrituras humanas revalidan la suspensión dentro del bloqueo académico común. Se conservan
sesiones futuras, permisos, consultas históricas, snapshots, replay y scheduler A03. Los resúmenes
históricos reutilizan los gráficos operativos existentes y se distinguen de las versiones de resultados.
Migración incremental `2026_10_23_000100_add_offering_administrative_state`, sin nueva release ni dependencias.

## Validación

- Regresión completa: 242 pruebas funcionales aprobadas; una falla transitoria de la sonda HTTP de
  infraestructura. Repetición aislada de esa sonda sin cambios: 1/1, 2 aserciones.
- A04 final: 53/53 y 308 aserciones, incluidas conservación al migrar y protección del rollback.
- JavaScript 39/39, build correcto; Composer/plataforma y Pint aprobados. Aviso Sass heredado documentado.
- PostgreSQL nativo: 15 comprobaciones A04 y 16 regresiones de registro/consulta/cierre, con procesos y
  esperas reales. Scheduler local ejecutado dos veces, sin duplicar versiones/auditoría.
- Edge: 23 capturas/medidas, tres perfiles, escritorio y móvil, teclado, motivos, confirmación, rechazo
  por URL directa y privacidad del snapshot. Cero excepciones JavaScript y sin desbordamiento de página.
- [Informe por escenario y rutas de capturas](a04-verification.md). Capturas sugeridas:
  `supplement-current-320.png`, `supplement-admin-disabled-390.png`, `supplement-student-snapshot-1440.png`
  en el directorio temporal indicado en el informe.

## Alcance pendiente

Aplicar la migración a desarrollo después de revisar el incremento; durante esta implementación
se conservó su esquema y datos. `.env`/APP_KEY, cambio previo `.env.example` y `Microsoft/` preservados;
los cambios previos ajenos deben mantenerse separados del incremento al preparar el commit.

Sin despliegue, CI remoto ni validación física de GPS/HTTPS/NFC. A05/B01–B07, nuevas gráficas,
ranking, PDF/CSV y archivo manual permanecen fuera de alcance. No hay defecto funcional A04 conocido
pendiente. [Contrato, operación y comandos PowerShell](a04-group-management.md).
