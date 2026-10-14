<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Academic\UserManager;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\SaveUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:80'], 'role' => ['nullable', 'in:coordinator,teacher,student']]);
        $users = User::query()->when($filters['role'] ?? null, fn ($q, $role) => $q->where('role', $role))->when($filters['q'] ?? null, function ($q, $search): void {
            $q->where(function ($q) use ($search): void {
                $q->where('name', 'ilike', '%'.$search.'%')->orWhere('email', 'ilike', '%'.$search.'%')->orWhere('student_code', 'ilike', '%'.$search.'%');
            });
        })->orderBy('name')->paginate(12)->withQueryString();

        return view('admin.users.index', ['users' => $users, 'roles' => Role::cases()]);
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('admin.users.form', ['user' => new User(['role' => Role::Student, 'active' => true]), 'roles' => Role::cases()]);
    }

    public function store(SaveUserRequest $request, UserManager $manager): RedirectResponse
    {
        $manager->save($request->user(), $request->validated());

        return to_route('admin.users.index')->with('status', 'Cuenta creada. Entrega la contraseña inicial al titular de forma privada.');
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('admin.users.form', ['user' => $user, 'roles' => Role::cases()]);
    }

    public function update(SaveUserRequest $request, User $user, UserManager $manager): RedirectResponse
    {
        $manager->save($request->user(), $request->validated(), $user);

        return to_route('admin.users.index')->with('status', 'Cuenta actualizada.');
    }

    public function resetPassword(ResetPasswordRequest $request, User $user, UserManager $manager): RedirectResponse
    {
        $manager->resetPassword($request->user(), $user, $request->validated('password'), $request->validated('reason'));

        return to_route('admin.users.index')->with('status', 'Contraseña restablecida. Las sesiones anteriores fueron revocadas.');
    }
}
