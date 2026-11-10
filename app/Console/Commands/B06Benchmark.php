<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Offering;
use App\Models\User;
use App\Queries\CalendarSessions;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Read-only before/after sampling of calendar queries for a real, authorized test account. */
final class B06Benchmark extends Command
{
    protected $signature = 'b06:benchmark
        {--student= : ID of an existing active student}
        {--confirm-database= : Exact configured database name}
        {--from= : Academic start date YYYY-MM-DD}
        {--to= : Academic end date YYYY-MM-DD}
        {--iterations=9 : Timed executions of each read path (3-30)}';

    protected $description = 'B06: compare legacy and optimized calendar (read-only, local/testing only)';

    public function handle(CalendarSessions $optimized): int
    {
        $configured = (string) config('database.connections.'.config('database.default').'.database');
        $actual = (string) DB::connection()->getDatabaseName();
        if (! app()->environment(['local', 'testing']) || config('database.default') !== 'pgsql'
            || ! in_array($configured, ['siaa_test', 'smartaulaues_local'], true)
            || $configured !== $actual || $this->option('confirm-database') !== $actual) {
            $this->error('Medición rechazada: entorno PostgreSQL local/test y confirmación exacta de BD obligatorios.');

            return self::FAILURE;
        }

        $studentId = filter_var($this->option('student'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $iterations = filter_var($this->option('iterations'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 3, 'max_range' => 30]]);
        if (! $studentId || ! $iterations) {
            $this->error('Indique --student y --iterations entre 3 y 30.');

            return self::FAILURE;
        }
        $student = User::find($studentId);
        if ($student === null || ! $student->active || $student->role !== Role::Student) {
            $this->error('El identificador no corresponde a un estudiante activo.');

            return self::FAILURE;
        }

        $timezone = 'America/El_Salvador';
        $from = $this->option('from') ?: now($timezone)->toDateString();
        if (! $this->validDate($from)) {
            $this->error('Fecha de inicio inválida.');

            return self::FAILURE;
        }
        $to = $this->option('to') ?: CarbonImmutable::parse($from, $timezone)->addDays(30)->toDateString();
        if (! $this->validDate($to) || $from > $to
            || CarbonImmutable::parse($from, $timezone)->diffInDays(CarbonImmutable::parse($to, $timezone)) > 90) {
            $this->error('Use fechas válidas, ordenadas y con intervalo máximo de 90 días.');

            return self::FAILURE;
        }
        $startUtc = CarbonImmutable::parse($from, $timezone)->startOfDay()->utc();
        $endUtc = CarbonImmutable::parse($to, $timezone)->endOfDay()->utc();
        $base = function () use ($student, $startUtc, $endUtc): Builder {
            return ClassSession::query()
                ->whereIn('offering_id', Offering::visibleTo($student)->select('offerings.id'))
                ->whereBetween('starts_at', [$startUtc, $endUtc]);
        };
        $legacy = function () use ($base, $student, $timezone): array {
            $enrollments = Enrollment::visibleTo($student)->get();
            $sessions = $base();
            $sessions->where(function (Builder $query) use ($enrollments, $timezone): void {
                foreach ($enrollments as $enrollment) {
                    $begin = CarbonImmutable::parse($enrollment->active_from->toDateString(), $timezone)->startOfDay()->utc();
                    $end = $enrollment->active_to
                        ? CarbonImmutable::parse($enrollment->active_to->toDateString(), $timezone)->endOfDay()->utc()
                        : null;
                    $query->orWhere(function (Builder $part) use ($enrollment, $begin, $end): void {
                        $part->where('offering_id', $enrollment->offering_id)->where('starts_at', '>=', $begin);
                        if ($end !== null) {
                            $part->where('starts_at', '<=', $end);
                        }
                    });
                }
                if ($enrollments->isEmpty()) {
                    $query->whereRaw('1 = 0');
                }
            });

            return $this->sessionIds($sessions);
        };
        $current = fn (): array => $this->sessionIds($optimized->forStudent($base(), $student));

        // Compare actual rows first: performance is not acceptable if scope changes.
        $expected = $legacy();
        if ($expected !== $current()) {
            $this->error('Los ID visibles difieren; no se consideran válidas las mediciones.');

            return self::FAILURE;
        }

        // Warm-up outside measurements. Paths run alternately to limit drift.
        $legacy();
        $current();
        $before = [];
        $after = [];
        for ($i = 0; $i < $iterations; $i++) {
            $before[] = $this->sample($legacy);
            $after[] = $this->sample($current);
        }
        $output = [
            'module' => 'B06',
            'database' => $actual,
            'environment' => app()->environment(),
            'php' => PHP_VERSION,
            'queue_driver' => config('queue.default'),
            'window' => ['from' => $from, 'to' => $to],
            'iterations' => $iterations,
            'student_enrollments' => Enrollment::where('student_id', $student->id)->count(),
            'sample_sessions' => count($expected),
            'baseline' => $this->summarize($before),
            'optimized' => $this->summarize($after),
            'result' => 'same_authorized_session_ids',
        ];
        if (Schema::hasTable('jobs')) {
            $output['queue_pending_at_measurement'] = DB::table('jobs')->count();
        }
        $this->line(json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $this->comment('La medición no incluye navegador, red móvil, GPS, NFC ni ejecución de jobs. Guarde el hardware y el entorno por separado.');

        return self::SUCCESS;
    }

    private function sessionIds(Builder $query): array
    {
        return array_map('intval', $query->orderBy('class_sessions.starts_at')->orderBy('class_sessions.id')
            ->limit(15)->pluck('class_sessions.id')->all());
    }

    private function sample(callable $read): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $start = hrtime(true);
        try {
            $read();
            $ms = (hrtime(true) - $start) / 1_000_000;
            $queries = count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        return ['ms' => $ms, 'queries' => $queries];
    }

    private function summarize(array $samples): array
    {
        $times = array_column($samples, 'ms');
        sort($times, SORT_NUMERIC);
        $queries = array_column($samples, 'queries');

        return [
            'p50_ms' => round($times[(int) ceil(count($times) * 0.50) - 1], 3),
            'p95_ms' => round($times[(int) ceil(count($times) * 0.95) - 1], 3),
            'queries_min' => min($queries),
            'queries_max' => max($queries),
        ];
    }

    private function validDate(string $value): bool
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
            return false;
        }

        return checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4));
    }
}
