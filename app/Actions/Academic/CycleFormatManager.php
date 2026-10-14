<?php

namespace App\Actions\Academic;

use App\Models\AcademicCycle;
use App\Models\CycleFormat;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CycleFormatManager
{
    public function __construct(private AuditRecorder $audit) {}

    public function save(User $actor, array $data, ?CycleFormat $format = null): CycleFormat
    {
        Gate::forUser($actor)->authorize($format ? 'update' : 'create', $format ?? CycleFormat::class);

        return DB::transaction(function () use ($actor, $data, $format): CycleFormat {
            $cycle = AcademicCycle::lockForUpdate()->findOrFail($data['cycle_id']);
            if ($cycle->status === 'closed') {
                $this->fail('cycle_id', 'El ciclo está cerrado.');
            }
            $record = $format ? CycleFormat::lockForUpdate()->findOrFail($format->id) : new CycleFormat(['version' => 1]);
            if ($record->exists && $record->cycle_id !== $cycle->id) {
                $this->fail('cycle_id', 'Conserva el ciclo original del formato.');
            }
            if ($record->exists && $record->offerings()->exists()) {
                $this->fail('periods', 'Este formato ya se utilizó. Crea otro formato para nuevas fechas; los grupos existentes conservan su calendario.');
            }
            $periods = collect($data['periods'])->sortBy('starts_on');
            $previousEnd = null;
            foreach ($periods as $period) {
                if ($period['ends_on'] < $period['starts_on'] || $period['starts_on'] < $cycle->starts_on->toDateString() || $period['ends_on'] > $cycle->ends_on->toDateString()) {
                    $this->fail('periods', 'Cada cómputo debe tener fechas coherentes dentro del ciclo.');
                }
                if ($previousEnd !== null && $period['starts_on'] <= $previousEnd) {
                    $this->fail('periods', 'Los cómputos del formato no pueden superponerse.');
                }
                $previousEnd = $period['ends_on'];
            }
            $before = $record->exists ? $this->audit->snapshot($record->load('periods')) : null;
            if (CycleFormat::where('cycle_id', $cycle->id)->where('name', $data['name'])->where('version', $record->version)->when($record->exists, fn ($q) => $q->whereKeyNot($record->id))->exists()) {
                $this->fail('name', 'Ya existe un formato con este nombre en el ciclo.');
            }
            $record->fill(['cycle_id' => $cycle->id, 'name' => $data['name'], 'active' => $data['active']])->save();
            if ($before) {
                $record->periods()->delete();
            }
            foreach ($periods as $period) {
                $record->periods()->create($period);
            }
            $this->audit->record($actor, $record->load('periods'), $before ? 'format.updated' : 'format.created', $before, $data['reason'] ?? null);

            return $record;
        });
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
