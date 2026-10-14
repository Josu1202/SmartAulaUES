<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AuditController extends Controller
{
    public function __invoke(Request $request): View
    {
        Gate::authorize('viewAny', User::class);
        $filters = $request->validate(['entity' => ['nullable', Rule::in(AuditLog::ENTITY_TYPES)]]);

        return view('admin.audit.index', [
            'logs' => AuditLog::with('actor')->when($filters['entity'] ?? null, fn ($q, $entity) => $q->where('entity_type', $entity))->orderByDesc('id')->paginate(15)->withQueryString(),
            'entityLabels' => collect(AuditLog::ENTITY_TYPES)->mapWithKeys(fn ($entity) => [$entity => __('audit.entities.'.$entity, [], 'es')])->all(),
        ]);
    }
}
