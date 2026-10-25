<?php

namespace App\Support;

use App\Services\Attendance\AttendancePolicy;
use Carbon\CarbonImmutable;

/** Presentation only: the original audit payload is never rewritten. */
final class AuditValues
{
    public static function display(?array $values): string
    {
        if ($values === null || $values === []) {
            return 'Sin valores registrados';
        }
        $labels = ['name' => 'Nombre', 'email' => 'Correo', 'role' => 'Perfil', 'student_code' => 'Carné', 'active' => 'Habilitado',
            'entry_year' => 'Año de ingreso', 'study_year' => 'Año de carrera', 'graduated' => 'Egresado', 'must_change_password' => 'Cambio de contraseña obligatorio',
            'code' => 'Código', 'starts_on' => 'Fecha inicial', 'ends_on' => 'Fecha final', 'status' => 'Estado', 'state' => 'Estado',
            'cycle_id' => 'Ciclo', 'subject_id' => 'Materia', 'teacher_id' => 'Docente', 'offering_id' => 'Grupo', 'group_code' => 'Código de grupo',
            'capacity' => 'Cupo', 'cycle_format_id' => 'Formato de cómputos', 'number' => 'Número de cómputo', 'policy_version' => 'Reglamento',
            'closed_at' => 'Cierre', 'reopened_at' => 'Reapertura', 'closure_issues' => 'Impedimentos para el cierre', 'current_report_id' => 'Resultado conservado del cierre',
            'version' => 'Revisión del formato', 'periods' => 'Cómputos', 'student_id' => 'Estudiante', 'active_from' => 'Inicio de inscripción', 'active_to' => 'Fecha de retiro',
            'period_id' => 'Cómputo', 'starts_at' => 'Inicio programado', 'ends_at' => 'Fin programado', 'started_at' => 'Inicio real', 'finalized_at' => 'Finalización',
            'modality' => 'Modalidad', 'event_attendance_mode' => 'Ubicación del evento', 'change_reason' => 'Motivo del cambio', 'session_id' => 'Sesión',
            'opens_at' => 'Apertura del registro', 'expires_at' => 'Vencimiento', 'enabled_methods' => 'Métodos de registro', 'access_modality' => 'Modalidad de registro',
            'portal_id' => 'Ventana de registro', 'label' => 'Nombre del lector', 'user_id' => 'Cuenta', 'pending_expires_at' => 'Plazo de activación',
            'activated_at' => 'Activación', 'revoked_at' => 'Revocación', 'enrollment_id' => 'Inscripción', 'method' => 'Método de registro',
            'recorded_by' => 'Responsable del registro', 'recorded_at' => 'Fecha del registro', 'reason' => 'Motivo', 'resolved_by' => 'Responsable de resolución',
            'resolution_note' => 'Resolución', 'resolved_at' => 'Fecha de resolución', 'kind' => 'Tipo de importación', 'count' => 'Registros importados'];
        $rows = [];
        foreach ($values as $key => $value) {
            if ($key !== 'must_change_password' && preg_match('/password|token|hash|location|latitude|longitude|geo_reference/i', (string) $key)) {
                $rows[] = 'Información protegida: no se muestra.';

                continue;
            }
            $label = $labels[$key] ?? (is_int($key) ? 'Elemento '.($key + 1) : 'Información adicional');
            $rows[] = $label.': '.self::value((string) $key, $value);
        }

        return implode("\n", $rows);
    }

    private static function value(string $key, mixed $value): string
    {
        if ($value === null) {
            return 'Sin asignar';
        }
        if (is_bool($value)) {
            return $value ? 'Sí' : 'No';
        }
        if (is_array($value)) {
            return $value === [] ? 'Ninguno' : (array_is_list($value)
                ? implode('; ', array_map(fn ($item) => is_array($item) ? self::display($item) : self::value($key, $item), $value))
                : self::display($value));
        }
        if ($key === 'policy_version') {
            return AttendancePolicy::find((string) $value) ? 'Reglamento de asistencia disponible' : 'Reglamento no disponible';
        }
        if (str_ends_with($key, '_at') || in_array($key, ['starts_on', 'ends_on', 'active_from', 'active_to'], true)) {
            try {
                $date = CarbonImmutable::parse($value, 'UTC');

                return str_ends_with($key, '_at') ? $date->timezone('America/El_Salvador')->format('d/m/Y H:i:s') : $date->format('d/m/Y');
            } catch (\Throwable) {
                return 'Fecha no disponible';
            }
        }
        $options = ['coordinator' => 'Coordinación', 'teacher' => 'Docente', 'student' => 'Estudiante', 'active' => 'Activo', 'planned' => 'Planificado', 'closed' => 'Cerrado', 'open' => 'Abierto',
            'scheduled' => 'Programada', 'in_progress' => 'En curso', 'completed' => 'Finalizada', 'cancelled' => 'Cancelada', 'unstarted' => 'Sin inicio registrado',
            'present' => 'Presente', 'absent' => 'Ausente', 'justified' => 'Justificada', 'approved' => 'Aprobado', 'rejected' => 'Rechazado', 'pending' => 'Pendiente', 'revoked' => 'Revocada',
            'online' => 'En línea', 'in_person' => 'Presencial', 'event' => 'Evento', 'manual' => 'Manual', 'nfc' => 'NFC', 'nfc_tag' => 'Etiqueta compartida (declarado)', 'qr' => 'QR', 'link' => 'Enlace',
            'students' => 'Estudiantes', 'teachers' => 'Docentes', 'users' => 'Cuentas', 'student_years' => 'Años de estudiantes', 'enrollments' => 'Inscripciones'];

        return in_array($key, ['role', 'state', 'status', 'modality', 'event_attendance_mode', 'access_modality', 'method', 'enabled_methods', 'kind'], true)
            ? ($options[(string) $value] ?? 'Opción anterior conservada') : (string) $value;
    }
}
