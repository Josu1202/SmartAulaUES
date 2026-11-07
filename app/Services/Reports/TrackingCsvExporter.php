<?php

namespace App\Services\Reports;

use Symfony\Component\HttpFoundation\StreamedResponse;

/** CSV exploratorio no persistente; los CSV certificados siguen en VersionedCsvExporter. */
final class TrackingCsvExporter
{
    public function download(array $ranking): StreamedResponse
    {
        $group = $ranking['group'];
        $period = $ranking['period'];
        $source = $ranking['source'];
        $closed = $source === 'snapshot';
        $version = $closed ? $ranking['report_version'] : null;

        $filename = 'SmartAulaUES-seguimiento-grupo-'.$group->id.'-computo-'.$period->number.'.csv';

        return response()->streamDownload(function () use ($ranking, $group, $period, $source, $closed, $version): void {
            $output = fopen('php://output', 'wb');
            if ($output === false) {
                throw new \RuntimeException('No se pudo iniciar la exportación de seguimiento.');
            }

            try {
                fwrite($output, "\xEF\xBB\xBF");
                $this->writeRow($output, [
                    'Ciclo', 'Materia', 'Grupo', 'Cómputo', 'Origen', 'Versión certificada',
                    'Filtro desde solicitado', 'Filtro hasta solicitado', 'Filtro de fechas aplicado al ranking',
                    'Orden', 'Posición', 'Estudiante', 'Carné', 'Ausentes', 'Justificadas',
                    'Sesiones elegibles', 'Inasistencia (%)', 'Permisos pendientes', 'Estado del resultado',
                ]);

                foreach ($ranking['rows'] as $index => $student) {
                    $this->writeRow($output, [
                        $this->safeCell((string) $group->cycle->code),
                        $this->safeCell((string) $group->subject->name),
                        $this->safeCell((string) $group->group_code),
                        $period->number,
                        match ($source) {
                            'snapshot' => 'Cierre conservado',
                            'review' => 'Datos en revisión',
                            default => 'Datos provisionales',
                        },
                        $version ?? '',
                        $ranking['date_filter']['from'] ?? '',
                        $ranking['date_filter']['to'] ?? '',
                        ! $closed && ($ranking['date_filter']['from'] !== null || $ranking['date_filter']['to'] !== null) ? 'Sí' : 'No',
                        $ranking['sort'] === 'rate' ? 'Tasa de inasistencia' : 'Cantidad de ausencias',
                        $index + 1,
                        $this->safeCell((string) $student['name']),
                        $this->safeCell((string) ($student['code'] ?? '')),
                        $student['absent'],
                        $student['justified'],
                        $student['eligible'],
                        $student['absence_percentage'] ?? '',
                        $student['pending_requests'],
                        $this->safeCell(match ($student['state']) {
                            'provisional' => 'Calculable',
                            'no_sessions' => 'Sin sesiones evaluables',
                            'incomplete' => 'Resultado incompleto',
                            default => 'No disponible',
                        }),
                    ]);
                }
            } finally {
                fclose($output);
            }
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Igual criterio de neutralización de celdas de usuario que B02.2. */
    private function safeCell(string $value): string
    {
        return preg_match('/\A[\x00-\x20\x7F\x{00A0}\x{200B}\x{FEFF}]*[=+\-@]/u', $value) === 1
            ? "'".$value
            : $value;
    }

    private function writeRow(mixed $output, array $cells): void
    {
        if (fputcsv($output, $cells, ',', '"', '') === false) {
            throw new \RuntimeException('No se pudo escribir una fila del CSV.');
        }
    }
}
