<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateReportPdf;
use App\Models\ReportExportRequest;
use App\Models\ReportVersion;
use App\Services\Attendance\ClosureSnapshot;
use App\Services\Reports\ReportExportLifecycle;
use App\Services\Reports\VersionedPdfExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReportPdfController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $versions = ReportVersion::query()
            ->whereHas('period.offering', fn ($query) => $query->visibleTo($user))
            ->with(['period.offering.subject', 'period.offering.cycle'])
            ->orderByDesc('id')->paginate(15);

        // Solo se consultan las solicitudes del usuario y de las versiones visibles.
        $exportRequests = ReportExportRequest::query()
            ->where('requested_by', $user->id)
            ->whereIn(
                'report_version_id',
                $versions->getCollection()->pluck('id')->all()
            )
            ->get()
            ->keyBy(
                fn (ReportExportRequest $item) => $item->report_version_id.':'.$item->format
            );

        return view('reports.index', compact('versions', 'exportRequests'));
    }

    public function generate(
        Request $request,
        ReportVersion $reportVersion,
        VersionedPdfExporter $exporter,
        ReportExportLifecycle $lifecycle
    ): RedirectResponse {
        Gate::authorize('view', $reportVersion);
        abort_unless(app(ClosureSnapshot::class)->valid($reportVersion, $reportVersion->period), 409);

        // Nunca se reencola un archivo certificado que ya existe.
        if ($reportVersion->pdf_path !== null || $reportVersion->file_hash !== null) {
            abort_unless($exporter->fileIsValid($reportVersion), 409,
                'El PDF registrado no supera la verificación de integridad. Solicite asistencia.');

            return to_route('reports.index')->with('status', 'El PDF ya está disponible para descargar.');
        }

        abort_if(in_array(config('queue.default'), ['sync', 'null', 'deferred', 'background'], true), 503,
            'La generación requiere una cola asíncrona configurada y un worker operativo.');

        $actor = $request->user();
        $token = $lifecycle->request($reportVersion, $actor, 'PDF');
        if ($token === null) {
            return to_route('reports.index')->with('status', 'Su solicitud de PDF sigue en proceso. No se ha encolado otra.');
        }

        // El job comprueba el token antes de generar y no modifica una solicitud reemplazada.
        try {
            GenerateReportPdf::dispatch($reportVersion->id, $actor->id, $token)
                ->onQueue('reports')->afterCommit();
        } catch (\Throwable $exception) {
            $lifecycle->dispatchFailed($reportVersion->id, $actor->id, 'PDF', $token);
            report($exception);

            abort(503, 'No fue posible encolar el PDF. Puede intentarlo nuevamente.');
        }

        return to_route('reports.index')->with('status', 'PDF solicitado. Actualice la página para comprobar su disponibilidad.');
    }

    public function download(ReportVersion $reportVersion, VersionedPdfExporter $exporter): StreamedResponse
    {
        Gate::authorize('view', $reportVersion);
        abort_unless(app(ClosureSnapshot::class)->valid($reportVersion, $reportVersion->period), 409);
        abort_unless($exporter->fileIsValid($reportVersion), 404);

        return Storage::disk('private')->download(
            $exporter->expectedPath($reportVersion),
            'SmartAulaUES-computo-'.$reportVersion->period_id.'-v'.$reportVersion->version.'.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }
}
