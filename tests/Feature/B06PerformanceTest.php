<?php

namespace Tests\Feature;

use App\Models\AssessmentPeriod;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\News;
use App\Models\Offering;
use App\Models\User;
use App\Queries\CalendarSessions;
use App\Services\Communication\NewsNoticeService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class B06PerformanceTest extends TestCase
{
    public function test_calendar_filters_in_sql_at_both_local_day_boundaries_without_leaking_foreign_sessions(): void
    {
        $this->travelTo(now()->setDateTime(2026, 11, 9, 14, 0));
        $student = User::factory()->create();
        $group = Offering::factory()->create();
        $period = AssessmentPeriod::factory()->create(['offering_id' => $group->id]);
        Enrollment::factory()->create(['student_id' => $student->id, 'offering_id' => $group->id,
            'active_from' => '2026-11-09', 'active_to' => '2026-11-09']);
        $before = $this->createCalendarSession($group, $period, '2026-11-09T05:30:00Z'); // 08 Nov 23:30 local
        $first = $this->createCalendarSession($group, $period, '2026-11-09T06:00:00Z'); // 09 Nov 00:00 local
        $last = $this->createCalendarSession($group, $period, '2026-11-10T05:30:00Z'); // 09 Nov 23:30 local
        $after = $this->createCalendarSession($group, $period, '2026-11-10T06:00:00Z'); // 10 Nov 00:00 local

        $foreignGroup = Offering::factory()->create();
        $foreignPeriod = AssessmentPeriod::factory()->create(['offering_id' => $foreignGroup->id]);
        $foreign = $this->createCalendarSession($foreignGroup, $foreignPeriod, '2026-11-09T15:00:00Z');
        $query = ClassSession::query()->whereIn('offering_id', Offering::visibleTo($student)->select('offerings.id'));
        $ids = app(CalendarSessions::class)->forStudent($query, $student)->orderBy('id')->pluck('class_sessions.id')->all();
        $this->assertSame([$first->id, $last->id], $ids);
        $this->assertNotContains($before->id, $ids);
        $this->assertNotContains($after->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
        $this->assertSame(0, DB::table('attendances')->count());

        // The full view must also enforce the SQL scope, not only our service test.
        $this->withSession(['password_hash_web' => $student->password])->actingAs($student)
            ->get(route('calendar.index', ['from' => '2026-11-08', 'to' => '2026-11-10']))
            ->assertOk()->assertSee('Calendario');
    }

    public function test_calendar_fetches_one_sql_statement_regardless_of_enrollment_count(): void
    {
        $student = User::factory()->create();
        for ($i = 0; $i < 6; $i++) {
            $group = Offering::factory()->create();
            Enrollment::factory()->create(['student_id' => $student->id, 'offering_id' => $group->id]);
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $query = ClassSession::query()->whereIn('offering_id', Offering::visibleTo($student)->select('offerings.id'));
            app(CalendarSessions::class)->forStudent($query, $student)->limit(15)->get();
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('exists', strtolower($queries[0]['query']));
        $this->assertStringNotContainsString('calendar_enrollments', json_encode($queries[0]['bindings']));
    }

    public function test_b06_benchmark_rejects_unconfirmed_database_and_compares_both_read_paths(): void
    {
        $student = User::factory()->create();
        $this->artisan('b06:benchmark', ['--student' => $student->id, '--confirm-database' => 'incorrecta', '--iterations' => 3])
            ->assertExitCode(1);
        $this->artisan('b06:benchmark', [
            '--student' => $student->id, '--confirm-database' => 'siaa_test',
            '--from' => '2026-11-09', '--to' => '2026-11-10', '--iterations' => 3,
        ])->assertExitCode(0);
    }

    public function test_news_worker_processes_more_than_one_chunk_once_and_excludes_others(): void
    {
        $this->travelTo(now()->setDateTime(2026, 11, 9, 14, 0));
        $group = Offering::factory()->create();
        $students = User::factory()->count(102)->create();
        $outsider = User::factory()->create();
        $withdrawn = User::factory()->create();
        $enrollments = $students->map(fn (User $student): array => [
            'offering_id' => $group->id, 'student_id' => $student->id,
            'active_from' => '2026-01-01', 'active_to' => null,
        ])->all();
        $enrollments[] = ['offering_id' => $group->id, 'student_id' => $withdrawn->id,
            'active_from' => '2026-01-01', 'active_to' => '2026-11-08'];
        DB::table('enrollments')->insert($enrollments);
        $news = News::create(['offering_id' => $group->id, 'author_id' => $group->teacher_id,
            'title' => 'Prueba de procesamiento por lotes', 'content' => 'Contenido ficticio.',
            'status' => 'published', 'published_at' => now()]);
        $service = app(NewsNoticeService::class);
        $fingerprint = NewsNoticeService::fingerprint($news);
        $service->send($news->id, 'published', $fingerprint);
        $service->send($news->id, 'published', $fingerprint);
        $this->assertSame(102, DB::table('notifications')->count());
        $this->assertSame(1, $students->last()->notifications()->count());
        $this->assertSame(0, $outsider->notifications()->count());
        $this->assertSame(0, $withdrawn->notifications()->count());
    }

    private function createCalendarSession(Offering $group, AssessmentPeriod $period, string $start): ClassSession
    {
        return ClassSession::factory()->create([
            'offering_id' => $group->id, 'period_id' => $period->id,
            'starts_at' => $start, 'ends_at' => CarbonImmutable::parse($start)->addMinutes(45),
        ]);
    }
}
