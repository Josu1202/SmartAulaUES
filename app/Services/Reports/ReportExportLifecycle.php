<?php

namespace App\Services\Reports;

use App\Models\ReportExportRequest;
use App\Models\ReportVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class ReportExportLifecycle
{
    private const PENDING_MINUTES = 10;

    private const LEASE_MINUTES = 3;

    /**
     * Devuelve un token si debe encolarse un trabajo nuevo.
     * Devuelve null si ya existe una solicitud activa.
     */
    public function request(ReportVersion $report, User $actor, string $format): ?string
    {
        $this->validateFormat($format);

        return DB::transaction(function () use ($report, $actor, $format): ?string {
            $now = now();
            $token = (string) Str::uuid();

            $created = DB::table('report_export_requests')->insertOrIgnore([
                'report_version_id' => $report->id,
                'requested_by' => $actor->id,
                'format' => $format,
                'state' => ReportExportRequest::STATE_PENDING,
                'request_token' => $token,
                'attempts' => 0,
                'queued_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($created === 1) {
                return $token;
            }

            $record = $this->locked($report->id, $actor->id, $format);

            if ($record === null) {
                throw new \RuntimeException('No se encontró la solicitud de exportación.');
            }

            $pending = $record->state === ReportExportRequest::STATE_PENDING
                && $record->queued_at !== null
                && $record->queued_at->greaterThan($now->copy()->subMinutes(self::PENDING_MINUTES));

            $processing = $record->state === ReportExportRequest::STATE_PROCESSING
                && $record->lease_expires_at !== null
                && $record->lease_expires_at->greaterThan($now);

            if ($pending || $processing) {
                return null;
            }

            $record->update([
                'state' => ReportExportRequest::STATE_PENDING,
                'request_token' => $token,
                'attempts' => 0,
                'queued_at' => $now,
                'started_at' => null,
                'finished_at' => null,
                'lease_expires_at' => null,
            ]);

            return $token;
        });
    }

    /**
     * El worker solo puede iniciar la solicitud cuyo token sigue vigente.
     */
    public function started(int $reportId, int $actorId, string $format, string $token): bool
    {
        $this->validateFormat($format);

        return DB::transaction(function () use ($reportId, $actorId, $format, $token): bool {
            $record = $this->locked($reportId, $actorId, $format);

            if ($record === null
                || $record->request_token !== $token
                || ! in_array($record->state, [
                    ReportExportRequest::STATE_PENDING,
                    ReportExportRequest::STATE_PROCESSING,
                ], true)) {
                return false;
            }

            $now = now();

            $record->update([
                'state' => ReportExportRequest::STATE_PROCESSING,
                'attempts' => $record->attempts + 1,
                'started_at' => $now,
                'lease_expires_at' => $now->copy()->addMinutes(self::LEASE_MINUTES),
            ]);

            return true;
        });
    }

    public function succeeded(int $reportId, int $actorId, string $format, string $token): bool
    {
        return $this->finish($reportId, $actorId, $format, $token, ReportExportRequest::STATE_AVAILABLE);
    }

    public function failed(int $reportId, int $actorId, string $format, string $token): bool
    {
        return $this->finish($reportId, $actorId, $format, $token, ReportExportRequest::STATE_FAILED);
    }

    /**
     * Marca un fallo ocurrido antes de que el worker inicie el trabajo.
     * Solo puede afectar a la solicitud pendiente con el token vigente.
     */
    public function dispatchFailed(
        int $reportId,
        int $actorId,
        string $format,
        string $token
    ): bool {
        $this->validateFormat($format);

        return DB::transaction(function () use ($reportId, $actorId, $format, $token): bool {
            $record = $this->locked($reportId, $actorId, $format);

            if ($record === null
                || $record->request_token !== $token
                || $record->state !== ReportExportRequest::STATE_PENDING) {
                return false;
            }

            $record->update([
                'state' => ReportExportRequest::STATE_FAILED,
                'finished_at' => now(),
                'lease_expires_at' => null,
            ]);

            return true;
        });
    }

    private function finish(
        int $reportId,
        int $actorId,
        string $format,
        string $token,
        string $state
    ): bool {
        $this->validateFormat($format);

        return DB::transaction(function () use ($reportId, $actorId, $format, $token, $state): bool {
            $record = $this->locked($reportId, $actorId, $format);

            if ($record === null
                || $record->request_token !== $token
                || $record->state !== ReportExportRequest::STATE_PROCESSING) {
                return false;
            }

            $record->update([
                'state' => $state,
                'finished_at' => now(),
                'lease_expires_at' => null,
            ]);

            return true;
        });
    }

    private function locked(int $reportId, int $actorId, string $format): ?ReportExportRequest
    {
        return ReportExportRequest::query()
            ->where('report_version_id', $reportId)
            ->where('requested_by', $actorId)
            ->where('format', $format)
            ->lockForUpdate()
            ->first();
    }

    private function validateFormat(string $format): void
    {
        if (! in_array($format, [
            ReportExportRequest::FORMAT_PDF,
            ReportExportRequest::FORMAT_CSV,
        ], true)) {
            throw new InvalidArgumentException('Formato de exportación desconocido.');
        }
    }
}
