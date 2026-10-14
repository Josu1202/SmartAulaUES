<?php

namespace App\Http\Controllers;

use App\Actions\Academic\UserManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('profile.show', ['user' => $request->user()]);
    }

    public function changePassword(Request $request, UserManager $manager): RedirectResponse
    {
        $data = $request->validate(['current_password' => ['required', 'current_password:web'], 'password' => ['required', 'string', 'min:12', 'max:128', 'confirmed', 'different:current_password']]);
        $manager->changeOwnPassword($request->user(), $data['password']);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login')->with('status', 'Contraseña actualizada. Inicia sesión con la nueva contraseña.');
    }
}
