<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Attendance\CredentialManager;
use App\Http\Controllers\Controller;
use App\Models\NfcCredential;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class NfcCredentialController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', NfcCredential::class);
        $data = $request->validate(['q' => ['nullable', 'string', 'max:80']]);
        $students = User::where('role', 'student')->where('active', true)->when($data['q'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q->where('name', 'ilike', '%'.$search.'%')->orWhere('student_code', 'ilike', '%'.$search.'%')))->orderBy('name')->limit(200)->get();

        return view('admin.credentials.index', ['students' => $students, 'credentials' => NfcCredential::with('user')->latest('id')->paginate(15)]);
    }

    public function store(Request $request, CredentialManager $manager): JsonResponse
    {
        $data = $request->validate(['student_id' => ['required', 'integer', 'exists:users,id']]);
        $result = $manager->issue($request->user(), User::findOrFail($data['student_id']));

        return response()->json(['credential_id' => $result['credential']->id, 'token' => $result['token'], 'expires_at' => $result['credential']->pending_expires_at->toIso8601String()], 201);
    }

    public function confirm(Request $request, NfcCredential $credential, CredentialManager $manager): JsonResponse
    {
        $data = $request->validate(['credential_token' => ['required', 'string', 'regex:/\ASIAA:C1:[A-Za-z0-9_-]{43}\z/']]);
        $manager->confirm($request->user(), $credential, $data['credential_token']);

        return response()->json(['status' => 'active']);
    }

    public function revoke(Request $request, NfcCredential $credential, CredentialManager $manager): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $manager->revoke($request->user(), $credential, $data['reason']);

        return to_route('admin.credentials.index')->with('status', 'Credencial revocada.');
    }
}
