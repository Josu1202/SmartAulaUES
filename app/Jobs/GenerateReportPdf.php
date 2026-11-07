<?php

namespace App\Jobs;

use App\Models\ReportExportRequest;
use App\Models\ReportVersion;
use App\Models\User;
use App\Services\Reports\ReportExportLifecycle;
use App\Services\Reports\ReportGenerationNotifier;
use App\Services\Reports\VersionedPdfExporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Gate;
use RuntimeException;
use Throwable;

/** B02: revalida actor y versión al ejecutarse, nunca confía en la solicitud original. */
final class GenerateReportPdf implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public int $reportId, public int $actorId, public ?string $requestToken = null) {}

    public function handle(VersionedPdfExporter $exporter, ?ReportExportLifecycle $lifecycle = null): void
    {
        $lifecycle ??= app(ReportExportLifecycle::class);
        // Jobs existentes con constructor de dos parámetros mantienen su comportamiento.
        if ($this->requestToken !== null
            && ! $lifecycle->started($this->reportId, $this->actorId, 'PDF', $this->requestToken)) {
            // Si falló la notificación tras generar el archivo, reintenta ese aviso.
            $available = ReportExportRequest::query()
                ->where('report_version_id', $this->reportId)
                ->where('requested_by', $this->actorId)
                ->where('format', 'PDF')
                ->where('request_token', $this->requestToken)
                ->where('state', ReportExportRequest::STATE_AVAILABLE)
                ->exists();
            if ($available) {
                $actor = User::find($this->actorId);
                $report = ReportVersion::find($this->reportId);
                if ($actor !== null && $report !== null
                    && Gate::forUser($actor)->allows('view', $report)
                    && $exporter->fileIsValid($report)) {
                    app(ReportGenerationNotifier::class)->ready($report->id, $actor->id, 'PDF');
                }
            }

            return; // Solicitud obsoleta: no genera otro archivo.
        }
        $actor = User::find($this->actorId);
        $report = ReportVersion::find($this->reportId);
        if ($actor === null || $report === null) {
            throw new RuntimeException('El actor o la versión ya no está disponible.');
        }
        Gate::forUser($actor)->authorize('view', $report);
        $exporter->generate($report);
        if (! $exporter->fileIsValid($report->fresh())) {
            throw new RuntimeException('La exportación registrada no pasó la verificación de integridad.');
        }

        if ($this->requestToken !== null
            && ! $lifecycle->succeeded($this->reportId, $this->actorId, 'PDF', $this->requestToken)) {
            return; // Un trabajo obsoleto no debe notificar una solicitud reemplazada.
        }

        app(ReportGenerationNotifier::class)->ready($report->id, $actor->id, 'PDF');
    }

    public function failed(Throwable $exception): void
    {
        // El callback se ejecuta solo cuando el job ha agotado sus reintentos.
        // La notificación no incorpora datos del error, que pueden ser sensibles.
        if ($this->requestToken !== null
            && ! app(ReportExportLifecycle::class)->failed($this->reportId, $this->actorId, 'PDF', $this->requestToken)) {
            return; // No marca como fallida una solicitud que ya fue reemplazada.
        }

        app(ReportGenerationNotifier::class)->failed($this->reportId, $this->actorId, 'PDF');
    }
}
