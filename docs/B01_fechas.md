# B01.6 — Fechas del dashboard docente

- Entrada: `ranking_date_from` / `ranking_date_to` (`AAAA-MM-DD`), ambas opcionales e inclusivas. Fechas académicas de `America/El_Salvador`. Rango invertido o fechas inválidas: validación rechazada.
- Ámbito: exclusivamente docente activo con grupos y cómputos autorizados; `visibleTo` y `AttendanceSummary` siguen aplicando autorización. El filtro se conserva al cambiar cómputo, ordenar, abrir detalle o paginar matriz; se reinicia al cambiar ciclo o grupo.
- **Cómputo abierto o reabierto**: `AttendanceSummary::forOffering` recibe el rango y utiliza A01 para recalcular **solo la vista provisional**, ajustando también el número de sesiones elegibles. El rango afecta ranking, detalle, tendencia y matriz. Ningún resultado se persiste.
- **Cómputo cerrado**: A03 prohíbe consultas fechadas de un snapshot. Se consume completo, sin alterar ranking/denominadores certificados ni versiones. Solo se limitan las fechas representadas en el detalle, la tendencia semanal y columnas de matriz; se indica expresamente la diferencia. No se emite nueva versión ni se altera el cierre.
- `period_tracking` y los indicadores generales no dependen del rango. La tabla y gráfica semanal emplean los mismos hechos filtrados, sumando conteos y denominadores, nunca promediando porcentajes.
- Casos esperados: límites inclusivos, fechas fuera del cómputo (sin clases evaluables), rango inválido, restricciones de docente/estudiante, conservación de paginación, snapshot inmutable.
- Validación pendiente en la estación del desarrollador: `php artisan test --filter=TeacherDateFiltersTest`, `vendor/bin/pint --test`, suite completa con PostgreSQL, UI a 320/390 px y build frontend. Registrar el resultado real antes del PR.
