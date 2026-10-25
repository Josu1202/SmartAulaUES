<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class ReadinessController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::connection()->getPdo()->query('select 1');
            DB::connection()->getReadPdo()->query('select 1');
            if (! Schema::hasColumn('users', 'must_change_password') || ! Schema::hasTable('operation_runs') || ! is_file(public_path('build/manifest.json'))) {
                Log::warning('Disponibilidad: comprueba migraciones y public/build/manifest.json.');

                return response()->json(['status' => 'unavailable'], 503)->header('Cache-Control', 'no-store');
            }

            return response()->json(['status' => 'ok'])->header('Cache-Control', 'no-store');
        } catch (Throwable $exception) {
            Log::warning('Disponibilidad: no se pudo comprobar la base.', ['type' => $exception::class]);

            return response()->json(['status' => 'unavailable'], 503)->header('Cache-Control', 'no-store');
        }
    }
}
