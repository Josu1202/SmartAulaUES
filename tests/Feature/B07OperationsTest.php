<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class B07OperationsTest extends TestCase
{
    public function test_status_reports_healthy_empty_queue_without_modifying_academic_tables(): void
    {
        $before = DB::table('academic_cycles')->count();
        $this->artisan('b07:status')->assertExitCode(0);
        $this->assertSame($before, DB::table('academic_cycles')->count());
    }

    public function test_status_fails_for_stale_pending_queue_and_recovers_with_reasonable_threshold(): void
    {
        DB::table('jobs')->insert([
            'queue' => 'default', 'payload' => '{}', 'attempts' => 0,
            'reserved_at' => null, 'available_at' => now()->subMinutes(20)->timestamp,
            'created_at' => now()->subMinutes(20)->timestamp,
        ]);
        $this->artisan('b07:status', ['--max-oldest-seconds' => 900])->assertExitCode(1);
        $this->artisan('b07:status', ['--max-oldest-seconds' => 1800])->assertExitCode(0);
    }

    public function test_status_requires_recent_successful_scheduler_when_requested(): void
    {
        $this->artisan('b07:status', ['--require-scheduler' => true])->assertExitCode(1);
        DB::table('operation_runs')->updateOrInsert(
            ['name' => 'academic-closures'],
            ['started_at' => now()->subSeconds(3), 'finished_at' => now(), 'errors' => 0],
        );
        $this->artisan('b07:status', ['--require-scheduler' => true])->assertExitCode(0);
        DB::table('operation_runs')->where('name', 'academic-closures')->update(['errors' => 1]);
        $this->artisan('b07:status', ['--require-scheduler' => true])->assertExitCode(1);
    }

    public function test_status_rejects_negative_thresholds(): void
    {
        $this->artisan('b07:status', ['--max-pending' => -1])->assertExitCode(1);
    }

    public function test_production_compose_enables_durable_supervised_worker(): void
    {
        $compose = file_get_contents(base_path('compose.production.yaml'));
        $this->assertStringContainsString('QUEUE_CONNECTION: database', $compose);
        $this->assertStringContainsString('DB_QUEUE_RETRY_AFTER: "180"', $compose);
        $this->assertStringContainsString('  worker:', $compose);
        $this->assertStringContainsString('"queue:work"', $compose);
        $this->assertStringContainsString('restart: unless-stopped', $compose);
        $this->assertStringContainsString('scripts/check-queue-worker.php', $compose);
        $this->assertStringNotContainsString('QUEUE_CONNECTION: sync', $compose);
    }
}
