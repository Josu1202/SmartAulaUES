<?php

namespace App\Services\Help;

use App\Enums\Role;

/** B04: textos y destinos reales de la navegación, sin permitir rutas entre perfiles. */
final class TutorialCatalog
{
    // Incrementar solo para un recorrido nuevo relevante; el histórico queda conservado.
    public const VERSION = 1;

    /** @return list<array{title: string, description: string, route: string, icon: string}> */
    public function forRole(Role $role): array
    {
        return match ($role) {
            Role::Coordinator => [
                ['title' => 'Directorios', 'description' => 'Desde Estudiantes y Docentes, administre cuentas y perfiles. Las acciones requieren su autorización.', 'route' => 'admin.students.index', 'icon' => 'users'],
                ['title' => 'Formatos de ciclos', 'description' => 'Revise los formatos y cómputos asignados al ciclo antes de organizar las sesiones.', 'route' => 'admin.formats.index', 'icon' => 'calendar'],
                ['title' => 'Grupos y carga', 'description' => 'En Materias y grupos consulte docentes, cupos e inscripciones. Use el detalle para revisar cada grupo.', 'route' => 'offerings.index', 'icon' => 'book'],
                ['title' => 'Cierres académicos', 'description' => 'Desde Ciclos académicos compruebe los estados y pendientes antes de cualquier cierre o reapertura.', 'route' => 'admin.cycles.index', 'icon' => 'checklist'],
                ['title' => 'Reportes y versiones', 'description' => 'En Resultados y cierres encontrará los PDF y CSV privados de las versiones certificadas.', 'route' => 'reports.index', 'icon' => 'file'],
            ],
            Role::Teacher => [
                ['title' => 'Materias y grupos', 'description' => 'Consulte únicamente sus grupos asignados y entre al detalle para ver sus cómputos.', 'route' => 'offerings.index', 'icon' => 'book'],
                ['title' => 'Sesiones de clase', 'description' => 'En Sesiones y asistencia gestione las sesiones y revise su estado.', 'route' => 'sessions.index', 'icon' => 'calendar'],
                ['title' => 'Métodos de asistencia', 'description' => 'En Terminal NFC encontrará el lector autorizado; los métodos QR y manual se habilitan durante las sesiones correspondientes.', 'route' => 'terminal.index', 'icon' => 'nfc'],
                ['title' => 'Solicitudes y permisos', 'description' => 'Abra Permisos para revisar las solicitudes y tomar decisiones según su autorización.', 'route' => 'requests.index', 'icon' => 'file'],
                ['title' => 'Seguimiento', 'description' => 'En Inicio encontrará indicadores y el seguimiento de ausencias de sus grupos.', 'route' => 'dashboard', 'icon' => 'chart'],
                ['title' => 'Exportar resultados', 'description' => 'En Resultados y cierres solicite los PDF y CSV privados; consulte sus estados antes de descargar.', 'route' => 'reports.index', 'icon' => 'file'],
            ],
            Role::Student => [
                ['title' => 'Calendario', 'description' => 'Consulte fechas y horarios de sus sesiones inscritas; puede filtrar por ciclo.', 'route' => 'calendar.index', 'icon' => 'calendar'],
                ['title' => 'Su asistencia', 'description' => 'En Sesiones y asistencia revise sus registros. Para confirmar asistencia, siga el método habilitado por el docente en la sesión.', 'route' => 'sessions.index', 'icon' => 'checklist'],
                ['title' => 'Historial académico', 'description' => 'Revise su propio historial de asistencias, ausencias y cómputos, sin acceder a datos ajenos.', 'route' => 'history.index', 'icon' => 'chart'],
                ['title' => 'Permisos', 'description' => 'En Permisos consulte sus solicitudes de justificación y su resolución.', 'route' => 'requests.index', 'icon' => 'file'],
                ['title' => 'Avisos y noticias', 'description' => 'En Avisos consulte las notificaciones propias y en Noticias las publicaciones autorizadas.', 'route' => 'notifications.index', 'icon' => 'bell'],
            ],
        };
    }
}
