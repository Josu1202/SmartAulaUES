<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Academic\UserManager;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\SaveUserRequest;
use App\Models\User;
use App\Rules\StudentLevel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);
        $directoryRole = $request->route('directory_role');
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:80'], 'entry_year' => ['nullable', 'integer', 'between:1900,2200'], 'study_year' => ['nullable', new StudentLevel], 'active' => ['nullable', 'in:0,1']]);
        $users = User::query()->where('role', $directoryRole ?? 'coordinator')->when($filters['entry_year'] ?? null, fn ($q, $year) => $q->where('entry_year', $year))->when($filters['study_year'] ?? null, fn ($q, $year) => $q->studyLevel($year))->when(isset($filters['active']), fn ($q) => $q->where('active', $filters['active']))->when($filters['q'] ?? null, function ($q, $search): void {
            $q->where(function ($q) use ($search): void {
                $q->where('name', 'ilike', '%'.$search.'%')->orWhere('email', 'ilike', '%'.$search.'%')->orWhere('student_code', 'ilike', '%'.$search.'%');
            });
        })->orderBy('name')->paginate(12)->withQueryString();

        return view('admin.users.index', ['users' => $users, 'roles' => Role::cases(), 'directoryRole' => $directoryRole, 'prefix' => $this->prefix($request)]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', User::class);

        $role = Role::from($request->route('directory_role') ?? 'coordinator');

        return view('admin.users.form', ['user' => new User(['role' => $role, 'active' => true, 'entry_year' => $role === Role::Student ? now(config('smartaulaues.display_timezone'))->year : null, 'study_year' => $role === Role::Student ? 1 : null]), 'roles' => [$role], 'prefix' => $this->prefix($request)]);
    }

    public function store(SaveUserRequest $request, UserManager $manager): RedirectResponse
    {
        $manager->save($request->user(), $request->validated());

        return to_route($this->prefix($request).'.index')->with('status', 'Cuenta creada. Entrega la contraseña inicial al titular de forma privada.');
    }

    public function edit(Request $request, User $user): View
    {
        Gate::authorize('update', $user);
        $this->scopeDirectory($request, $user);

        return view('admin.users.form', ['user' => $user, 'roles' => [$user->role], 'prefix' => $this->prefix($request)]);
    }

    public function update(SaveUserRequest $request, User $user, UserManager $manager): RedirectResponse
    {
        $this->scopeDirectory($request, $user);
        $manager->save($request->user(), $request->validated(), $user);

        return to_route($this->prefix($request).'.index')->with('status', 'Cuenta actualizada.');
    }

    public function resetPassword(ResetPasswordRequest $request, User $user, UserManager $manager): RedirectResponse
    {
        $manager->resetPassword($request->user(), $user, $request->validated('password'), $request->validated('reason'));

        $prefix = match ($user->role) {
            Role::Student => 'admin.students', Role::Teacher => 'admin.teachers', default => 'admin.users',
        };

        return to_route($prefix.'.index')->with('status', 'Contraseña restablecida. Las sesiones anteriores fueron revocadas.');
    }

    private function prefix(Request $request): string
    {
        return match ($request->route('directory_role')) {
            'student' => 'admin.students', 'teacher' => 'admin.teachers', default => 'admin.users'
        };
    }

    private function scopeDirectory(Request $request, User $user): void
    {
        if ($request->route('directory_role')) {
            abort_unless($user->role->value === $request->route('directory_role'), 404);
        }
    }
}
