<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Fortify::loginView(fn () => view('auth.login'));
        Fortify::authenticateUsing(function (Request $request): ?User {
            $user = User::where('email', Str::lower(trim((string) $request->input('email'))))->first();
            if ($user && $user->active && Hash::check((string) $request->input('password'), $user->password)) {
                return $user;
            }

            return null;
        });
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(Str::transliterate(Str::lower((string) $request->input('email'))).'|'.$request->ip()));
    }
}
