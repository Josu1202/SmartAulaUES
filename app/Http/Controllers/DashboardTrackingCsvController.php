<?php

namespace App\Http\Controllers;

use App\Queries\DashboardStatistics;
use App\Services\Reports\TrackingCsvExporter;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** B02.3: exporta el seguimiento docente reutilizando la consulta autorizada de B01. */
final class DashboardTrackingCsvController extends Controller
{
    public function __invoke(Request $request, DashboardStatistics $statistics, TrackingCsvExporter $exporter): StreamedResponse
    {
        $input = $request->validate([
            'ranking_cycle_id' => ['nullable', 'integer', 'min:1'],
            'ranking_group_id' => ['required', 'integer', 'min:1'],
            'ranking_period_id' => ['required', 'integer', 'min:1'],
            'ranking_sort' => ['nullable', 'in:absences,rate'],
            'ranking_date_from' => ['nullable', 'date_format:Y-m-d'],
            'ranking_date_to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        if (isset($input['ranking_date_from'], $input['ranking_date_to'])
            && $input['ranking_date_from'] > $input['ranking_date_to']) {
            throw ValidationException::withMessages([
                'ranking_date_to' => 'La fecha final debe ser igual o posterior a la inicial.',
            ]);
        }

        $ranking = $statistics->teacherAbsenceRanking(
            $request->user(),
            groupId: (int) $input['ranking_group_id'],
            periodId: (int) $input['ranking_period_id'],
            cycleId: isset($input['ranking_cycle_id']) ? (int) $input['ranking_cycle_id'] : null,
            sort: $input['ranking_sort'] ?? 'absences',
            dateFrom: $input['ranking_date_from'] ?? null,
            dateTo: $input['ranking_date_to'] ?? null,
            exportAll: true,
        );

        // Un fallo de integridad del snapshot no se convierte en un CSV vacío.
        abort_if($ranking['error'] !== null, 409, $ranking['error']);
        abort_unless($ranking['group'] !== null && $ranking['period'] !== null, 404);

        return $exporter->download($ranking);
    }
}
