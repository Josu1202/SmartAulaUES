<?php

namespace App\Services\Reports;

use App\Models\ReportVersion;
use App\Models\User;
use App\Notifications\AcademicNotice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Ramsey\Uuid\Uuid;

/** B02.4: avisos internos idempotentes, sin exponer archivos ni excepciones. */
final class ReportGenerationNotifier
{
    public function ready(int $reportId, int $actorId, string $format): void
    {
        $context = $this->authorizedContext($reportId, $actorId, $format);
        if ($context === null) {
            return;
        }
        [$report, $actor] = $context;

        $this->insertOnce(
            $actor,
            $report,
            $format,
            'ready',
            new AcademicNotice(
                'Reporte '.$format.' disponible',
                'El reporte del cómputo '.$report->period->number.' (versión '.$report->version.') ya está disponible.',
                $format === 'PDF'
                    ? route('reports.download', $report, absolute: false)
                    : route('reports.csv.download', $report, absolute: false),
            ),
        );
    }

    /** Solo se invoca tras agotar los reintentos; nunca expone el mensaje de la excepción. */
    public function failed(int $reportId, int $actorId, string $format): void
    {
        $context = $this->authorizedContext($reportId, $actorId, $format);
        if ($context === null) {
            return;
        }
        [$report, $actor] = $context;

        $this->insertOnce(
            $actor,
            $report,
            $format,
            'failed',
            new AcademicNotice(
                'No se pudo generar el reporte '.$format,
                'La generación del cómputo '.$report->period->number.' (versión '.$report->version.') falló. Puede reintentar desde Reportes o solicitar asistencia.',
                route('reports.index', absolute: false),
            ),
        );
    }

    /** @return array{ReportVersion, User}|null */
    private function authorizedContext(int $reportId, int $actorId, string $format): ?array
    {
        if (! in_array($format, ['PDF', 'CSV'], true)) {
            throw new \InvalidArgumentException('Formato de reporte desconocido.');
        }

        $actor = User::find($actorId);
        $report = ReportVersion::with('period')->find($reportId);
        if ($actor === null || $report === null || ! Gate::forUser($actor)->allows('view', $report)) {
            return null;
        }

        return [$report, $actor];
    }

    /** UUID determinista + restricción PK: dos workers no duplican el mismo aviso. */
    private function insertOnce(User $actor, ReportVersion $report, string $format, string $event, AcademicNotice $notice): void
    {
        $key = 'report:'.$event.':'.strtolower($format).':'.$report->id.':'.$actor->id;
        $data = $notice->toDatabase($actor) + ['event_key' => $key];
        $now = now();

        DB::table('notifications')->insertOrIgnore([
            'id' => Uuid::uuid5(Uuid::NAMESPACE_URL, 'smartaulaues:b02.4:'.$key)->toString(),
            'type' => AcademicNotice::class,
            'notifiable_type' => $actor->getMorphClass(),
            'notifiable_id' => $actor->id,
            'data' => json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'read_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
