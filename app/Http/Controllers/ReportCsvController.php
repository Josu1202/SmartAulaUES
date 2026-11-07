<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateReportCsv;
use App\Models\ReportVersion;
use App\Services\Attendance\ClosureSnapshot;
use App\Services\Reports\ReportExportLifecycle;
use App\Services\Reports\VersionedCsvExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReportCsvController extends Controller
{
    public function generate(
        Request $request,
        ReportVersion $reportVersion,
        VersionedCsvExporter $exporter,
        ReportExportLifecycle $lifecycle
    ): RedirectResponse {
        Gate::authorize('view', $reportVersion);
        abort_unless(app(ClosureSnapshot::class)->valid($reportVersion, $reportVersion->period), 409);

        if ($reportVersion->csv_path !== null || $reportVersion->csv_hash !== null) {
            abort_unless($exporter->fileIsValid($reportVersion), 409,
                'El CSV registrado no supera la verificación de integridad. Solicite asistencia.');

            return to_route('reports.index')->with('status', 'El CSV ya está disponible para descargar.');
        }

        abort_if(in_array(config('queue.default'), ['sync', 'null', 'deferred', 'background'], true), 503,
            'La generación requiere una cola asíncrona configurada y un worker operativo.');

        $actor = $request->user();
        $token = $lifecycle->request($reportVersion, $actor, 'CSV');
        if ($token === null) {
            return to_route('reports.index')->with('status', 'Su solicitud de CSV sigue en proceso. No se ha encolado otra.');
        }

        try {
            GenerateReportCsv::dispatch($reportVersion->id, $actor->id, $token)
                ->onQueue('reports')->afterCommit();
        } catch (\Throwable $exception) {
            $lifecycle->dispatchFailed($reportVersion->id, $actor->id, 'CSV', $token);
            report($exception);

            abort(503, 'No fue posible encolar el CSV. Puede intentarlo nuevamente.');
        }

        return to_route('reports.index')->with('status', 'CSV solicitado. Actualice la página para comprobar su disponibilidad.');
    }

    public function download(ReportVersion $reportVersion, VersionedCsvExporter $exporter): StreamedResponse
    {
        Gate::authorize('view', $reportVersion);
        abort_unless(app(ClosureSnapshot::class)->valid($reportVersion, $reportVersion->period), 409);
        abort_unless($exporter->fileIsValid($reportVersion), 404);

        return Storage::disk('private')->download(
            $exporter->expectedPath($reportVersion),
            'SmartAulaUES-computo-'.$reportVersion->period_id.'-v'.$reportVersion->version.'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']
        );
    }
}
