<?php

namespace App\Actions\Academic;

use App\Actions\Attendance\ClosureWorkflow;
use App\Models\AbsenceRequest;
use App\Models\AcademicCycle;
use App\Models\AttendancePortal;
use App\Models\AuditLog;
use App\Models\Offering;
use App\Models\ReaderSession;
use App\Models\User;
use App\Queries\AttendanceSummary;
use App\Services\Attendance\ClosureSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class OfferingLifecycle
{
    public function __construct(private AuditRecorder $audit, private ClosureWorkflow $closures, private AttendanceSummary $summary, private ClosureSnapshot $snapshots) {}

    public function setActive(User $actor, Offering $offering, bool $active, string $reason): Offering
    {
        return DB::transaction(function () use ($actor, $offering, $active, $reason): Offering {
            $offering = $this->lock($offering);
            $actor = $this->authorize($actor, $offering, $reason);
            if ($offering->active === $active) {
                return $offering; // Repeated requests are a no-op, including audit.
            }
            if (! $active && ($issues = $this->deactivationIssues($offering)) !== []) {
                throw ValidationException::withMessages(['offering' => $issues]);
            }
            $before = $this->audit->snapshot($offering);
            $offering->forceFill(['active' => $active])->save();
            $this->audit->record($actor, $offering, $active ? 'offering.reactivated' : 'offering.deactivated', $before, trim($reason));

            return $offering;
        });
    }

    /** Diagnostic only. Mutation always repeats these checks inside the locked transaction. */
    public function deactivationIssues(Offering $offering): array
    {
        return DB::transaction(function () use ($offering): array {
            $offering = $this->lock($offering);
            $issues = [];
            foreach ($offering->sessions()->where('state', 'in_progress')->orderBy('id')->get() as $session) {
                $issues[] = 'Sesión #'.$session->id.' en curso: finalízala desde su detalle; no se finaliza al desactivar.';
            }
            foreach (AttendancePortal::whereHas('session', fn ($q) => $q->where('offering_id', $offering->id))->whereNull('closed_at')->orderBy('id')->get() as $portal) {
                $issues[] = 'Portal #'.$portal->id.($portal->expires_at <= now() ? ' vencido: usa Cerrar recursos vencidos.' : ' abierto: cierra el registro desde la sesión #'.$portal->session_id.'.');
            }
            foreach (ReaderSession::whereHas('portal.session', fn ($q) => $q->where('offering_id', $offering->id))->where('state', 'active')->orderBy('id')->get() as $reader) {
                $issues[] = 'Lector '.$reader->label.($reader->expires_at <= now() ? ' vencido: usa Cerrar recursos vencidos.' : ' activo: detén el lector o cierra su portal.');
            }
            if (AbsenceRequest::where('offering_id', $offering->id)->where('status', 'pending')->exists()) {
                $issues[] = 'Hay permisos pendientes: resuélvelos en Permisos antes de desactivar.';
            }
            foreach ($offering->periods()->orderBy('id')->lockForUpdate()->get() as $period) {
                $period->setRelation('offering', $offering);
                if ($period->state === 'closed') {
                    if (! $this->snapshots->valid($period->reports()->find($period->current_report_id), $period)) {
                        $issues[] = 'Cómputo '.$period->number.': cierre sin snapshot válido; reabre ciclo/cómputo y revisa sus datos.';
                    }

                    continue; // Closed results are never recalculated for A04.
                }
                if ($period->state !== 'open') {
                    $issues[] = 'Cómputo '.$period->number.' en cierre: completa o revisa el cierre antes de desactivar.';
                }
                $rows = $this->summary->captureForClosure($period);
                $issues = [...$issues, ...$this->closures->periodIssues($period, $rows, forDeactivation: true)];
            }

            return array_values(array_unique($issues));
        });
    }

    /** Explicit maintenance: expired resources only, no sessions/results/decisions changed. */
    public function closeExpiredResources(User $actor, Offering $offering, string $reason): void
    {
        DB::transaction(function () use ($actor, $offering, $reason): void {
            $offering = $this->lock($offering);
            $actor = $this->authorize($actor, $offering, $reason);
            $offering->periods()->orderBy('id')->lockForUpdate()->get();
            $offering->sessions()->orderBy('id')->lockForUpdate()->get();
            foreach (AttendancePortal::whereHas('session', fn ($q) => $q->where('offering_id', $offering->id))->orderBy('id')->lockForUpdate()->get() as $portal) {
                if ($portal->expires_at <= now() && $portal->closed_at === null) {
                    $before = $this->audit->snapshot($portal);
                    $portal->update(['closed_at' => now()]);
                    $this->audit->record($actor, $portal, 'portal.expired_closed', $before, $reason);
                }
                foreach ($portal->readerSessions()->where('state', 'active')->orderBy('id')->lockForUpdate()->get() as $reader) {
                    if ($reader->expires_at <= now() || $portal->closed_at !== null) {
                        $before = $this->audit->snapshot($reader);
                        $reader->update(['state' => 'closed']);
                        $this->audit->record($actor, $reader, 'reader.expired_closed', $before, $reason);
                    }
                }
            }
        });
    }

    public function deletionIssues(Offering $offering): array
    {
        $issues = [];
        if ($offering->cycle->status === 'closed') {
            $issues[] = 'Ciclo cerrado: reabre el ciclo antes de modificar su estructura.';
        }
        foreach (['enrollments' => 'Inscripciones', 'sessions' => 'Sesiones'] as $relation => $label) {
            if (($count = $offering->{$relation}()->count()) > 0) {
                $issues[] = $label.': '.$count.'. Conserva el grupo y su historial.';
            }
        }
        foreach (['attendances' => 'Asistencias', 'absence_requests' => 'Permisos y evidencias', 'news' => 'Noticias'] as $table => $label) {
            if (DB::table($table)->where('offering_id', $offering->id)->exists()) {
                $issues[] = $label.' vinculados al grupo.';
            }
        }
        foreach ($offering->periods()->orderBy('id')->get() as $period) {
            $history = AuditLog::where('entity_type', 'AssessmentPeriod')->where('entity_id', (string) $period->id)->get();
            // Only untouched structures copied at creation can be discarded. Fail closed for legacy data.
            if ($period->state !== 'open' || $period->closed_at !== null || $period->reopened_at !== null
                || $period->current_report_id !== null || $period->closure_checked_at !== null || ! empty($period->closure_issues)
                || $period->reports()->exists() || $period->sessions()->exists()
                || $period->format_period_id === null || $offering->cycle_format_id === null
                || $period->formatPeriod?->cycle_format_id !== $offering->cycle_format_id
                || $history->count() !== 1 || $history->first()->action !== 'period.created'
                || ClosureSnapshot::hash($history->first()->after ?? []) !== ClosureSnapshot::hash(json_decode(json_encode($this->audit->snapshot($period), JSON_THROW_ON_ERROR), true))) {
                $issues[] = 'Cómputo '.$period->number.': contiene historia o no se puede demostrar que sea una copia inicial vacía.';
            }
        }

        // Portals, readers and registration events depend on sessions (all FKs RESTRICT).
        return $issues;
    }

    public function delete(User $actor, Offering $offering, string $confirmation, string $reason): void
    {
        DB::transaction(function () use ($actor, $offering, $confirmation, $reason): void {
            $offering = $this->lock($offering);
            $actor = $this->authorize($actor, $offering, $reason, 'delete');
            if ($confirmation !== $offering->group_code) {
                throw ValidationException::withMessages(['confirmation' => 'Escribe exactamente el código del grupo para confirmar su eliminación.']);
            }
            $offering->periods()->orderBy('id')->lockForUpdate()->get();
            if (($issues = $this->deletionIssues($offering)) !== []) {
                throw ValidationException::withMessages(['offering' => $issues]);
            }
            $before = $this->audit->snapshot($offering) + ['cycle_code' => $offering->cycle->code, 'subject_code' => $offering->subject->code, 'subject_name' => $offering->subject->name];
            foreach ($offering->periods()->get() as $period) {
                $this->audit->record($actor, $period, 'period.empty_removed', $this->audit->snapshot($period), $reason);
                $period->delete();
            }
            AuditLog::create(['actor_id' => $actor->id, 'entity_type' => 'Offering', 'entity_id' => (string) $offering->id,
                'action' => 'offering.deleted', 'before' => $before, 'after' => null, 'reason' => trim($reason), 'created_at' => now()]);
            $offering->delete(); // Never cascade: every academic dependency retains its RESTRICT FK.
        });
    }

    private function lock(Offering $offering): Offering
    {
        $current = Offering::findOrFail($offering->id);
        $cycle = AcademicCycle::lockForUpdate()->findOrFail($current->cycle_id);
        $current = Offering::lockForUpdate()->findOrFail($current->id);
        if ($current->cycle_id !== $cycle->id) {
            throw ValidationException::withMessages(['offering' => 'El grupo cambió de ciclo. Actualiza la página.']);
        }

        return $current->setRelation('cycle', $cycle);
    }

    private function authorize(User $actor, Offering $offering, string $reason, string $ability = 'administer'): User
    {
        $actor = User::findOrFail($actor->id);
        Gate::forUser($actor)->authorize($ability, $offering);
        if (mb_strlen(trim($reason)) < 5 || mb_strlen($reason) > 1000) {
            throw ValidationException::withMessages(['reason' => 'Indica un motivo de entre 5 y 1000 caracteres.']);
        }

        return $actor;
    }
}
