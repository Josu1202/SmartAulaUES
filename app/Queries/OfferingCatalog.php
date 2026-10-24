<?php

namespace App\Queries;

use App\Models\AcademicCycle;
use App\Models\Offering;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class OfferingCatalog
{
    public function filters(Request $request): array
    {
        $data = $request->validate([
            'view' => ['nullable', 'in:current,history'], 'availability' => ['nullable', 'in:active,inactive,all'],
            'cycle_id' => ['nullable', 'integer', 'min:1'], 'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $view = $data['view'] ?? 'current';

        return ['view' => $view, 'availability' => $data['availability'] ?? ($view === 'history' ? 'all' : 'active'),
            'cycle_id' => isset($data['cycle_id']) ? (int) $data['cycle_id'] : null];
    }

    public function query(User $actor, array $filters): Builder
    {
        return Offering::visibleTo($actor)->catalog($filters['view'], $filters['availability'], $filters['cycle_id']);
    }

    public function cycles(User $actor)
    {
        return AcademicCycle::whereHas('offerings', fn ($q) => $q->visibleTo($actor))->orderByDesc('starts_on')->orderByDesc('id')->get();
    }
}
