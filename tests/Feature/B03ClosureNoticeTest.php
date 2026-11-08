<?php

namespace Tests\Feature;

use App\Actions\Attendance\ClosureWorkflow;
use App\Models\AssessmentPeriod;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Communication\ClosureNoticeService;
use Tests\TestCase;

final class B03ClosureNoticeTest extends TestCase
{
    public function test_closed_notice_is_deduplicated_and_excludes_foreign_students(): void
    {
        $this->travelTo(now()->setDateTime(2026, 10, 23, 12, 0));
        $period = AssessmentPeriod::factory()->create(['starts_on' => '2026-10-01', 'ends_on' => '2026-10-22']);
        $period->offering->cycle->update(['ends_on' => '2026-10-22']);
        $mine = Enrollment::factory()->create(['offering_id' => $period->offering_id]);
        $foreign = User::factory()->create();
        $coordinator = User::factory()->coordinator()->create();
        app(ClosureWorkflow::class)->closePeriod($coordinator, $period);
        $period->refresh();
        $notifier = app(ClosureNoticeService::class);
        $notifier->send($period->id, 'closed', $period->current_report_id, null);
        $notifier->send($period->id, 'closed', $period->current_report_id, null);
        $this->assertSame(1, $mine->student->notifications()->count());
        $this->assertSame(1, $coordinator->notifications()->count());
        $this->assertSame(0, $foreign->notifications()->count());
    }

    public function test_blocked_notice_never_reveals_private_diagnostic(): void
    {
        $period = AssessmentPeriod::factory()->create();
        $coordinator = User::factory()->coordinator()->create();
        $issues = ['MOTIVO_SENSIBLE_DE_OTRO_ESTUDIANTE'];
        $period->update(['closure_issues' => $issues]);
        $digest = hash('sha256', json_encode($issues, JSON_THROW_ON_ERROR));
        $service = app(ClosureNoticeService::class);
        $service->send($period->id, 'blocked', null, $digest);
        $service->send($period->id, 'blocked', null, $digest);
        $this->assertSame(1, $coordinator->notifications()->count());
        $this->assertStringNotContainsString('MOTIVO_SENSIBLE', json_encode($coordinator->notifications()->first()->data));
    }
}
