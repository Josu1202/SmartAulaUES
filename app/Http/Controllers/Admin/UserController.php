<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:80'], 'role' => ['nullable', 'in:coordinator,teacher,student']]);
        $users = User::query()->when($filters['role'] ?? null, fn ($q, $role) => $q->where('role', $role))->when($filters['q'] ?? null, function ($q, $search): void {
            $q->where(function ($q) use ($search): void {
                $q->where('name', 'ilike', '%'.$search.'%')->orWhere('email', 'ilike', '%'.$search.'%')->orWhere('student_code', 'ilike', '%'.$search.'%');
            });
        })->orderBy('name')->paginate(12)->withQueryString();

        return view('admin.users.index', ['users' => $users, 'roles' => Role::cases()]);
    }
}
