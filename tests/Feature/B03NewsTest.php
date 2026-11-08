<?php

namespace Tests\Feature;

use App\Models\AcademicCycle;
use App\Models\Enrollment;
use App\Models\News;
use App\Models\Offering;
use App\Models\User;
use App\Services\Communication\NewsNoticeService;
use Tests\TestCase;

final class B03NewsTest extends TestCase
{
    private function login(User $actor): static
    {
        return $this->withSession(['password_hash_web' => $actor->password])->actingAs($actor);
    }

    public function test_teacher_can_publish_only_to_own_group_and_content_is_escaped(): void
    {
        $this->travelTo(now()->setDateTime(2026, 11, 7, 14, 0));
        $group = Offering::factory()->create();
        $teacher = $group->teacher;
        $student = User::factory()->create();
        $outsider = User::factory()->create();
        Enrollment::factory()->create(['student_id' => $student->id, 'offering_id' => $group->id]);

        $this->login($teacher)->post(route('news.store'), [
            'offering_id' => $group->id, 'title' => 'Información importante',
            'content' => '<script>alert("malicioso")</script>',
        ])->assertRedirect();
        $news = News::query()->sole();
        $this->assertSame('draft', $news->status);
        $this->login($student)->get(route('news.show', $news))->assertForbidden();
        $this->login($teacher)->post(route('news.publish', $news))->assertRedirect();
        $this->assertSame('published', $news->fresh()->status);
        $this->login($student)->get(route('news.show', $news))->assertOk()
            ->assertSee('&lt;script&gt;', false)->assertDontSee('<script>', false);
        $this->login($outsider)->get(route('news.show', $news))->assertForbidden();
        $this->get(route('news.index'))->assertDontSee('Información importante');
    }

    public function test_teacher_cannot_publish_for_cycle_or_foreign_group(): void
    {
        $teacher = User::factory()->teacher()->create();
        $cycle = AcademicCycle::factory()->create();
        $foreign = Offering::factory()->create();
        $this->login($teacher)->post(route('news.store'), [
            'cycle_id' => $cycle->id, 'title' => 'Noticia del ciclo', 'content' => 'Información para todos.',
        ])->assertSessionHasErrors('cycle_id');
        $this->post(route('news.store'), [
            'offering_id' => $foreign->id, 'title' => 'Noticia del grupo', 'content' => 'Contenido ajeno.',
        ])->assertForbidden();
        $this->assertDatabaseCount('news', 0);
    }

    public function test_coordinator_cycle_news_honors_withdrawal_at_publication_date(): void
    {
        $this->travelTo(now()->setDateTime(2026, 11, 7, 14, 0));
        $group = Offering::factory()->create();
        $allowed = User::factory()->create();
        $withdrawn = User::factory()->create();
        Enrollment::factory()->create(['student_id' => $allowed->id, 'offering_id' => $group->id]);
        Enrollment::factory()->create(['student_id' => $withdrawn->id, 'offering_id' => $group->id, 'active_to' => '2026-11-06']);
        $coordinator = User::factory()->coordinator()->create();
        $this->login($coordinator)->post(route('news.store'), [
            'cycle_id' => $group->cycle_id, 'title' => 'Noticia global segura', 'content' => 'Solo estudiantes vigentes.',
        ])->assertRedirect();
        $news = News::firstOrFail();
        $this->post(route('news.publish', $news))->assertRedirect();
        $this->login($allowed)->get(route('news.show', $news))->assertOk();
        $this->login($withdrawn)->get(route('news.show', $news))->assertForbidden();
    }

    public function test_archived_news_disappears_and_an_edit_is_audited(): void
    {
        $group = Offering::factory()->create();
        $coordinator = User::factory()->coordinator()->create();
        $news = News::create(['offering_id' => $group->id, 'author_id' => $coordinator->id,
            'title' => 'Noticia original', 'content' => 'Detalle original.', 'status' => 'draft']);
        $this->login($coordinator)->put(route('news.update', $news), [
            'offering_id' => $group->id, 'title' => 'Noticia revisada', 'content' => 'Detalle actualizado.',
        ])->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['entity_type' => 'News', 'entity_id' => (string) $news->id, 'action' => 'news.updated']);
        $this->post(route('news.publish', $news))->assertRedirect();
        $this->post(route('news.archive', $news))->assertRedirect();
        $student = User::factory()->create();
        Enrollment::factory()->create(['offering_id' => $group->id, 'student_id' => $student->id]);
        $this->login($student)->get(route('news.show', $news))->assertForbidden();
    }

    public function test_news_notice_deduplicates_and_excludes_foreign_student(): void
    {
        $this->travelTo(now()->setDateTime(2026, 11, 7, 14, 0));
        $group = Offering::factory()->create();
        $student = User::factory()->create();
        $outsider = User::factory()->create();
        Enrollment::factory()->create(['offering_id' => $group->id, 'student_id' => $student->id]);
        $news = News::create(['offering_id' => $group->id, 'author_id' => $group->teacher_id,
            'title' => 'Información oficial', 'content' => 'Una noticia segura.',
            'status' => 'published', 'published_at' => now()]);
        $service = app(NewsNoticeService::class);
        $hash = NewsNoticeService::fingerprint($news);
        $service->send($news->id, 'published', $hash);
        $service->send($news->id, 'published', $hash);
        $this->assertSame(1, $student->notifications()->count());
        $this->assertSame(0, $outsider->notifications()->count());
    }
}
