<?php

namespace App\Support;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class HttpErrors
{
    public static function render(Throwable $exception, Request $request): mixed
    {
        $json = $request->is('api/*') || $request->expectsJson();
        if ($exception instanceof ValidationException) {
            return $json ? response()->json(['message' => 'Revisa los datos indicados.', 'errors' => $exception->errors()], $exception->status) : null;
        }
        if ($exception instanceof AuthenticationException) {
            return $json ? response()->json(['message' => 'Tu sesión terminó. Inicia sesión nuevamente.'], 401) : redirect()->guest(route('login'));
        }
        if ($exception instanceof HttpResponseException) {
            return null;
        }
        $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;
        if ($exception instanceof QueryException) {
            $status = ($exception->errorInfo[0] ?? null) === '23505' ? 409 : 503;
        }
        $message = match ($status) {
            400 => 'No pudimos interpretar la solicitud. Revisa los datos e intenta nuevamente.',
            401 => 'Tu sesión terminó. Inicia sesión nuevamente.',
            403 => 'Tu cuenta no tiene permiso para esta acción.',
            404 => 'No encontramos esta página o el registro ya no está disponible.',
            405 => 'Esta acción no está disponible desde esta página.',
            409 => 'Los datos cambiaron o el registro ya existe. Actualiza la página antes de continuar.',
            413 => 'El archivo es demasiado grande. Selecciona uno más pequeño.',
            419 => 'Tu sesión terminó. Actualiza la página e inicia sesión nuevamente.',
            422 => 'Revisa los datos indicados.',
            429 => 'Has realizado demasiadas solicitudes. Espera un minuto e intenta nuevamente.',
            503 => 'El servicio no está disponible en este momento. Intenta nuevamente en unos minutos.',
            default => 'No pudimos completar la operación. Intenta nuevamente; si continúa, avisa a Coordinación.',
        };
        // Only these explicitly public domain messages may pass through.
        if (in_array($exception->getMessage(), [
            'Este identificador ya pertenece a otra solicitud.',
            'Actualiza tu acceso antes de registrar asistencia.',
            'La inscripción no pertenece a esta versión.',
        ], true)) {
            $message = $exception->getMessage();
        }
        $headers = $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [];
        $headers['Cache-Control'] = 'no-store';

        return $json ? response()->json(['message' => $message], $status, $headers)
            : response()->view('errors.request', compact('status', 'message'), $status, $headers);
    }

    public static function report(Throwable $exception): bool
    {
        // No exception objects/messages, bindings, URL queries or frame arguments.
        $context = ['type' => $exception::class, 'file' => $exception->getFile(), 'line' => $exception->getLine(),
            'route' => app()->bound('request') ? request()->route()?->getName() : null,
            'frames' => array_map(fn ($frame) => array_intersect_key($frame, array_flip(['file', 'line', 'class', 'function'])), array_slice($exception->getTrace(), 0, 12))];
        if ($exception instanceof QueryException) {
            $context['sqlstate'] = $exception->errorInfo[0] ?? null;
            $context['query_fingerprint'] = hash('sha256', $exception->getSql());
        }
        Log::error('Operación interrumpida; diagnóstico privado sin contenido de la solicitud.', $context);

        return false; // Suppress default serialization of the exception and its bindings.
    }
}
