<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateReportCsv;
use App\Models\ReportVersion;
use App\Services\Attendance\ClosureSnapshot;
use App\Services\Reports\VersionedCsvExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReportCsvController extends Controller
{
    public function generate(ReportVersion $reportVersion): RedirectResponse
    {
        Gate::authorize('view', $reportVersion);
        abort_unless(app(ClosureSnapshot::class)->valid($reportVersion, $reportVersion->period), 409);
        abort_if(in_array(config('queue.default'), ['sync', 'null', 'deferred', 'background'], true), 503,
            'La generación requiere una cola asíncrona configurada y un worker operativo.');

        GenerateReportCsv::dispatch($reportVersion->id, auth()->id())->onQueue('reports')->afterCommit();

        return to_route('reports.index')->with('status', 'CSV solicitado. Actualice esta página para comprobar su disponibilidad.');
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
