<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password && ! $request->routeIs('profile', 'profile.password', 'logout')) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Cambia tu contraseña provisional en Mi cuenta antes de continuar.', 'code' => 'password_change_required', 'redirect' => route('profile')], 403);
            }

            return redirect()->route('profile')->with('status', 'Debes cambiar tu contraseña provisional antes de realizar acciones académicas.');
        }

        return $next($request);
    }
}
