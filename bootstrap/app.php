<?php

use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\RequirePasswordChange;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrustDeploymentProxies;
use App\Support\HttpErrors;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->replace(TrustProxies::class, TrustDeploymentProxies::class);
        $middleware->alias(['active' => EnsureUserIsActive::class, 'role' => EnsureRole::class, 'password.changed' => RequirePasswordChange::class]);
        $middleware->web(append: [SecurityHeaders::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());
        $exceptions->render(fn (Throwable $exception, Request $request) => HttpErrors::render($exception, $request));
        $exceptions->report(fn (Throwable $exception) => HttpErrors::report($exception));
        $exceptions->dontFlash(['current_password', 'password', 'password_confirmation', 'initial_password', 'initial_password_confirmation', 'credential_token', 'portal_token', 'evidence', 'location']);
    })->create();
