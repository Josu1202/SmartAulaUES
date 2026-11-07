<?php

namespace App\Services\Reports;

use App\Models\ReportVersion;
use App\Services\Attendance\ClosureSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** B02: CSV únicamente del snapshot histórico de A03, sin consultas ni cálculos de asistencia en vivo. */
final class VersionedCsvExporter
{
    public function expectedPath(ReportVersion $report): string
    {
        return 'reports/period-'.$report->period_id.'/version-'.$report->version.'-report-'.$report->id.'.csv';
    }

    public function generate(ReportVersion $report): string
    {
        return DB::transaction(function () use ($report): string {
            // Serializa reintentos y solicitudes concurrentes sobre una única versión.
            $report = ReportVersion::query()->lockForUpdate()->findOrFail($report->id);
            if (! app(ClosureSnapshot::class)->valid($report, $report->period)) {
                throw new RuntimeException('El snapshot no pasó la verificación de integridad.');
            }

            $path = $this->expectedPath($report);
            $disk = Storage::disk('private');
            if ($report->csv_path !== null || $report->csv_hash !== null) {
                if ($report->csv_path !== $path || ! $this->fileIsValid($report)) {
                    throw new RuntimeException('El CSV registrado no supera la verificación de integridad.');
                }

                return $path;
            }

            $content = $this->contentFromSnapshot($report->snapshot);
            $hash = hash('sha256', $content);
            if (! $disk->put($path, $content) || ! $disk->exists($path)) {
                throw new RuntimeException('No se pudo guardar el CSV privado.');
            }
            if (! hash_equals($hash, hash('sha256', $disk->get($path)))) {
                throw new RuntimeException('No se pudo verificar el CSV almacenado.');
            }

            // No se alteran el snapshot, la política ni los metadatos del PDF.
            $report->update(['csv_path' => $path, 'csv_hash' => $hash]);

            return $path;
        });
    }

    public function fileIsValid(ReportVersion $report): bool
    {
        if ($report->csv_path === null || $report->csv_hash === null
            || $report->csv_path !== $this->expectedPath($report)) {
            return false;
        }
        $disk = Storage::disk('private');

        return $disk->exists($report->csv_path)
            && hash_equals($report->csv_hash, hash('sha256', $disk->get($report->csv_path)));
    }

    /** Cada fila incorpora los metadatos inmutables; ningún campo procede de modelos académicos actuales. */
    private function contentFromSnapshot(array $snapshot): string
    {
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            throw new RuntimeException('No se pudo preparar el CSV.');
        }

        try {
            // Excel reconoce así UTF-8 con tildes y caracteres especiales.
            fwrite($stream, "\xEF\xBB\xBF");
            $this->writeRow($stream, [
                'Cómputo ID', 'Versión', 'Número de cómputo', 'Inicio', 'Fin', 'Corte UTC',
                'Política', 'Umbral mínimo (%)', 'Estudiante', 'Carné', 'Presentes',
                'Ausentes', 'Justificadas', 'Elegibles', 'Asistencia (%)', 'Conclusión',
            ]);
            foreach ($snapshot['rows'] as $row) {
                $calculation = $row['calculation'];
                $counts = $calculation['counts'];
                $conclusion = $calculation['conclusion'] ?? match ($calculation['state']) {
                    'no_sessions' => 'Sin sesiones evaluables',
                    'incomplete' => 'Resultado incompleto',
                    default => 'Sin conclusión disponible',
                };
                $this->writeRow($stream, [
                    $snapshot['period']['id'],
                    $snapshot['version'],
                    $snapshot['period']['number'],
                    $snapshot['period']['starts_on'],
                    $snapshot['period']['ends_on'],
                    $snapshot['generated_at'],
                    $this->safeCell($snapshot['policy_version']),
                    $snapshot['policy']['minimum_percentage'] ?? '',
                    $this->safeCell($row['student_name']),
                    $this->safeCell($row['student_code'] ?? ''),
                    $counts['present'] ?? 0,
                    $counts['absent'] ?? 0,
                    $counts['justified'] ?? 0,
                    $counts['eligible'] ?? 0,
                    $calculation['percentage'] ?? '',
                    $this->safeCell($conclusion),
                ]);
            }
            rewind($stream);
            $content = stream_get_contents($stream);
            if ($content === false) {
                throw new RuntimeException('No se pudo leer el CSV generado.');
            }

            return $content;
        } finally {
            fclose($stream);
        }
    }

    /** Evita ejecución de fórmulas incluso si el texto empieza con espacios o controles. */
    private function safeCell(string $value): string
    {
        return preg_match('/\A[\x00-\x20\x7F\x{00A0}\x{200B}\x{FEFF}]*[=+\-@]/u', $value) === 1
            ? "'".$value
            : $value;
    }

    private function writeRow(mixed $stream, array $cells): void
    {
        if (fputcsv($stream, $cells, ',', '"', '') === false) {
            throw new RuntimeException('No se pudo escribir una fila del CSV.');
        }
    }
}
