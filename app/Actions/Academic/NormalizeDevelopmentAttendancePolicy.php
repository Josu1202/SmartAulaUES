<?php

namespace App\Actions\Academic;

use App\Models\AcademicCycle;
use App\Models\AssessmentPeriod;
use App\Models\Offering;
use App\Services\Attendance\AttendancePolicy;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class NormalizeDevelopmentAttendancePolicy
{
    public function __construct(private AuditRecorder $audit) {}

    /** Explicit maintenance only. Preview and apply both recheck database and protected records. */
    public function run(string $expectedDatabase, bool $apply = false): array
    {
        $allowed = app()->environment('testing') ? 'siaa_test' : (app()->environment('local') ? 'siaa' : null);
        if ($allowed === null || $expectedDatabase !== $allowed || config('database.default') !== 'pgsql' || config('database.connections.pgsql.database') !== $expectedDatabase) {
            throw new RuntimeException('Normalización permitida solo en local/siaa o testing/siaa_test, con base explícita.');
        }
        if (DB::connection()->getPdo()->query('select current_database()')->fetchColumn() !== $expectedDatabase) {
            throw new RuntimeException('La base efectiva no coincide con la base de desarrollo indicada.');
        }

        return DB::transaction(function () use ($apply, $expectedDatabase): array {
            $cycles = AcademicCycle::orderBy('id')->lockForUpdate()->get();
            $offerings = Offering::whereIn('cycle_id', $cycles->modelKeys())->orderBy('id')->lockForUpdate()->get();
            $periods = AssessmentPeriod::whereIn('offering_id', $offerings->modelKeys())->orderBy('id')->lockForUpdate()->get();
            $closedCycles = $cycles->where('status', 'closed')->modelKeys();
            $protectedGroups = $offerings->filter(fn ($offering) => ! $offering->active || in_array($offering->cycle_id, $closedCycles, true))->modelKeys();
            $protected = $periods->filter(fn ($period) => $period->state !== 'open' || $period->closed_at !== null || in_array($period->offering_id, $protectedGroups, true) || $period->reports()->exists());
            $candidates = $periods->whereNotIn('id', $protected->modelKeys())->where('policy_version', '!=', AttendancePolicy::VERSION);
            if ($apply) {
                foreach ($candidates as $period) {
                    $before = $this->audit->snapshot($period);
                    // A direct field assignment preserves academic dates and all related data.
                    $period->policy_version = AttendancePolicy::VERSION;
                    $period->save();
                    $this->audit->recordSystem($period, 'period.policy_normalized', $before, 'Normalización explícita del reglamento de datos de desarrollo; sin aprobación institucional atribuida.');
                }
            }

            return ['database' => $expectedDatabase, 'candidates' => $candidates->count(), 'protected' => $protected->count(), 'updated' => $apply ? $candidates->count() : 0];
        });
    }
}
