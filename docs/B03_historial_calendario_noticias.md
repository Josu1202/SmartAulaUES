# B03 · Historial, calendario, noticias y avisos internos

**Base:** SmartAulaUES (archivo `SmartAulaUES_codigo.zip` recibido; B02.5 presente).
**Ámbito:** Integrante 3. No cambia cálculos de A01, reglas de A02, cierres ni snapshots de A03–A05.
**Estado de esta entrega:** paquete propuesto con sintaxis PHP verificada localmente; **pruebas de Laravel, DB, migración, Pint y CI deben ejecutarse en el entorno del usuario**.

## Rutas y perfiles

- `/history` (`history.index`): estudiante ve solo sus inscripciones, períodos, cálculo individual y sesiones; `AttendanceSummary::forEnrollment` sigue siendo fuente de resultados. Cerrado = snapshot validado de A03; abierto = cálculo de A. Paginación de hechos 25 por página. Docente y Coordinación consultan sus grupos autorizados con acceso al detalle existente.
- `/calendar` (`calendar.index`): sesiones por rango local de hasta 90 días (por defecto 30), ciclo y grupo; Coordinación también filtra por docente. Student solo ve eventos ocurridos dentro de `active_from..active_to` (fecha académica local, retiro inclusivo); docentes solo los grupos actualmente asignados. Sesión y permiso usan sus rutas/Policies existentes; no se modifican desde el calendario.
- `/news` (`news.index`): noticias publicadas para un grupo o ciclo, borradores solo para sus autores/coordinación. Coordinación publica para ciclo o grupo; el docente únicamente para sus grupos. Publicar y archivar usan POST CSRF con Policy, no GET. Cada noticia admite título, contenido de **texto plano escapado**, autor, fecha UTC, audiencia y estado.
- `/modules/news` redirige a `/news`; la navegación ofrece Calendario e Historial.

## Privacidad, retiro y cambios de responsable

- La `NewsPolicy` y el scope `News::visibleTo` restringen tanto listados como URLs directas; estudiantes solo consultan noticias publicadas mientras su inscripción era vigente en la fecha local de publicación. Una baja posterior no oculta noticias legítimamente vistas en su vigencia; una noticia publicada después de la baja no se concede. Docente reasignado deja de poder editar las noticias de su antiguo grupo.
- Información de inscripción, ausencias y permisos de otros estudiantes nunca se muestra en el historial individual. Sin sesiones elegibles no se presenta porcentaje artificial.
- Noticias nuevas usan una migración incremental (`2026_11_07_000300_add_news_publication_to_news.php`), sin editar la migración original. Mantener exclusivamente las columnas del módulo. El rollback bloquea borrar estado de noticias publicadas/archivadas.
- Todos los cambios de noticias quedan en `audit_logs`, visibles únicamente por la autorización administrativa existente. Las vistas siempre escapan el contenido; no hay HTML, links activos de autor ni JavaScript aceptado del usuario.

## Avisos

- `SendNewsNotice` en cola `default`: publicación / actualización **material** de noticia publicada, destinatarios actuales revalidados, clave UUID5 por noticia/evento/contenido/usuario; no duplica aviso en reintentos. No envía correos ni externos.
- `SendClosureNotice` en cola `default`: observador Eloquent registrado en `AppServiceProvider`, con `DB::afterCommit` para cierre certificado o diagnóstico bloqueado. En cierre se verifica `ClosureSnapshot` y se notifica a estudiantes del snapshot, docente actual y coordinación autorizados. Si bloqueado, **solo docente y coordinación** reciben mensaje genérico sin `closure_issues` o información de otros estudiantes.
- Los enlaces a grupos/noticias **revalidan Policy** al abrirlos. Lectura/marcado existentes no se modifican. Si una cuenta se desactiva o cambia de grupo antes de despachar, no recibe un aviso nuevo.
- El worker debe escuchar `default` (Compose local ya ejecuta `reports,default`; comprobar logs).

## Migración y verificaciones sugeridas

No ejecutar `migrate:fresh` ni `migrate:rollback` sobre datos reales; conservar DB, APP_KEY, volúmenes y credenciales. Después de crear una rama desde `origin/main` actualizado y verificar el parche:

```powershell
# Sintaxis y pruebas **en el contenedor aislado**
docker compose -p smartaulaues-b01-tests -f compose.testing.yaml up -d
docker compose -p smartaulaues-b01-tests -f compose.testing.yaml exec -T tests php artisan test --filter="B03(News|Timeline|ClosureNotice)Test"
docker compose -p smartaulaues-b01-tests -f compose.testing.yaml exec -T tests php artisan test
docker compose -p smartaulaues-b01-tests -f compose.testing.yaml exec -T tests vendor/bin/pint --test
# Solo si lo anterior aprueba, ejecutar migración incremental en desarrollo:
docker compose -p smartaulaues-b01-pruebas -f compose.yaml exec -T app php artisan migrate --force
docker compose -p smartaulaues-b01-pruebas -f compose.yaml restart worker
```

Comprobar tres perfiles, enlaces directos ajenos (403), noticias publicadas/archivadas, editadas con auditoría, calendario 320/390 px, inscripción retirada y docente reasignado. Revisar notificaciones e idempotencia tras worker real. Migración ejecutada + sintaxis correcta **no equivalen** a CI/hosting, NFC ni pruebas físicas.
