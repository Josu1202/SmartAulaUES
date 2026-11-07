<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateReportPdf;
use App\Models\ReportVersion;
use App\Services\Attendance\ClosureSnapshot;
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

        return view('reports.index', compact('versions'));
    }

    public function generate(ReportVersion $reportVersion): RedirectResponse
    {
        Gate::authorize('view', $reportVersion);
        abort_unless(app(ClosureSnapshot::class)->valid($reportVersion, $reportVersion->period), 409);
        abort_if(in_array(config('queue.default'), ['sync', 'null', 'deferred', 'background'], true), 503,
            'La generación requiere una cola asíncrona configurada y un worker operativo.');

        // La petición no renderiza el PDF: el worker procesa el job después del commit.
        GenerateReportPdf::dispatch($reportVersion->id, auth()->id())->onQueue('reports')->afterCommit();

        return to_route('reports.index')->with('status', 'Generación solicitada. Actualice esta página para comprobar la disponibilidad del PDF.');
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
