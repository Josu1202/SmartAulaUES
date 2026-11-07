<?php

namespace App\Services\Reports;

use App\Models\ReportVersion;
use App\Services\Attendance\ClosureSnapshot;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** B02: materializa exclusivamente el snapshot inmutable de A03, nunca datos vivos. */
final class VersionedPdfExporter
{
    public function expectedPath(ReportVersion $report): string
    {
        return 'reports/period-'.$report->period_id.'/version-'.$report->version.'-report-'.$report->id.'.pdf';
    }

    public function generate(ReportVersion $report): string
    {
        return DB::transaction(function () use ($report): string {
            // Un retry y un doble clic serializan sobre la misma versión, no crean otra.
            $report = ReportVersion::query()->lockForUpdate()->findOrFail($report->id);
            $period = $report->period;
            if (! app(ClosureSnapshot::class)->valid($report, $period)) {
                throw new RuntimeException('El snapshot no pasó la verificación de integridad.');
            }

            $path = $this->expectedPath($report);
            $disk = Storage::disk('private');
            if ($report->pdf_path !== null || $report->file_hash !== null) {
                if ($report->pdf_path !== $path || ! $this->fileIsValid($report)) {
                    throw new RuntimeException('El PDF registrado no supera la verificación de integridad.');
                }

                return $path;
            }

            $period->loadMissing(['offering.subject', 'offering.cycle', 'offering.teacher']);
            $pdf = Pdf::loadView('reports.period-pdf', ['report' => $report, 'period' => $period])
                ->setOption('isRemoteEnabled', false)
                ->setOption('isPhpEnabled', false)
                ->setOption('isJavascriptEnabled', false)
                ->setOption('defaultFont', 'DejaVu Sans');
            $content = $pdf->output();
            if (! str_starts_with($content, '%PDF-')) {
                throw new RuntimeException('El generador no devolvió un archivo PDF válido.');
            }
            $hash = hash('sha256', $content);
            if (! $disk->put($path, $content) || ! $disk->exists($path)) {
                throw new RuntimeException('No se pudo guardar el PDF privado.');
            }
            if (! hash_equals($hash, hash('sha256', $disk->get($path)))) {
                throw new RuntimeException('No se pudo verificar el PDF almacenado.');
            }

            $report->update(['pdf_path' => $path, 'file_hash' => $hash]);

            return $path;
        });
    }

    public function fileIsValid(ReportVersion $report): bool
    {
        if ($report->pdf_path === null || $report->file_hash === null
            || $report->pdf_path !== $this->expectedPath($report)) {
            return false;
        }
        $disk = Storage::disk('private');

        return $disk->exists($report->pdf_path)
            && hash_equals($report->file_hash, hash('sha256', $disk->get($report->pdf_path)));
    }
}
