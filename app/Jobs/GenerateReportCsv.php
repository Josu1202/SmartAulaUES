<?php

namespace App\Jobs;

use App\Models\ReportVersion;
use App\Models\User;
use App\Services\Reports\ReportGenerationNotifier;
use App\Services\Reports\VersionedCsvExporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Gate;
use RuntimeException;
use Throwable;

/** Revalida el usuario y la autorización cuando el worker ejecuta el trabajo. */
final class GenerateReportCsv implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public int $reportId, public int $actorId) {}

    public function handle(VersionedCsvExporter $exporter): void
    {
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

        app(ReportGenerationNotifier::class)->ready($report->id, $actor->id, 'CSV');
    }

    public function failed(Throwable $exception): void
    {
        // El callback se ejecuta solo cuando el job ha agotado sus reintentos.
        // La notificación no incorpora datos del error, que pueden ser sensibles.
        app(ReportGenerationNotifier::class)->failed($this->reportId, $this->actorId, 'CSV');
    }
}
