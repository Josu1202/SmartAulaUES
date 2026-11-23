# SmartAulaUES · Asistencia académica universitaria

SmartAulaUES es un proyecto de asignatura para gestionar matrículas, registrar
asistencia por NFC, QR, enlace o lista manual, resolver justificaciones y consultar
resultados académicos por cómputo. Usa Laravel, Blade y PostgreSQL.

La rama `main` integra A01–A05 y B01–B07. El código declara la versión `0.3.2`.

| Área | Funciones disponibles en `main` |
| --- | --- |
| Organización académica | Cuentas, ciclos, materias, grupos, formatos de ciclo, matrículas e importación CSV |
| Clases y asistencia | Sesiones, portales temporales, credenciales NFC, etiqueta compartida, QR, registro manual y permisos |
| Resultados y seguimiento | Cálculo por cómputo, paneles filtrados, historial, calendario, noticias y avisos |
| Cierre y operación | Cierres versionados, exportaciones PDF y CSV, reapertura, ayuda, escenarios de demostración, scheduler, cola y respaldo |

## Empezar

- [Instalación y operación local](docs/operacion-local-y-produccion.md). La URL local predeterminada es **http://127.0.0.1:8085/login**.
- [Cuentas ficticias y recorridos locales](docs/escenarios-y-cuentas-locales.md) y [manual por perfil](docs/B07_manual_usuarios.md).
- [Operación de producción, worker y restauración](docs/B07_operacion_entrega.md).
- [Despliegue público gratuito con OCI, Compose y Caddy](docs/deploy-gratuito.md).
- [GitHub Actions](https://github.com/Josu1202/SmartAulaUES/actions) y [protocolo de aceptación](docs/B07_aceptacion_final.md).

El protocolo de aceptación incluye el ensayo NFC/GPS con teléfonos reales bajo HTTPS,
la recuperación de servicios y una restauración aislada. El historial de implementación
se conserva en [CHANGELOG.md](CHANGELOG.md).

## Referencias técnicas

- [Contrato NFC](docs/nfc-contract.md) y [medición de ubicación](docs/a02-location-measurement.md).
- [Terminal física NFC y QR](docs/terminal-fisica.md), con API, simulador y protocolo de ensayo físico.
- [Cálculo de asistencia](docs/attendance-calculation-contract.md) y [cierre y reapertura](docs/a03-closures.md).
- [Formatos CSV](docs/csv-imports.md), [modelo de datos](docs/database.md) y [guía de desarrollo](docs/reference/Guia_de_desarrollo_SIAA_v2.pdf).
- [Pruebas y evidencia operativa](docs/operacion-verificacion.md), [versionado](docs/versioning.md) y [avisos de terceros](THIRD_PARTY_NOTICES.md).
