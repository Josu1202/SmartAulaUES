<?php

namespace App\Services\Communication;

use App\Enums\Role;
use App\Models\Enrollment;
use App\Models\News;
use App\Models\Offering;
use App\Models\User;
use App\Notifications\AcademicNotice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Ramsey\Uuid\Uuid;

final class NewsNoticeService
{
    public static function fingerprint(News $news): string
    {
        return hash('sha256', implode('|', [
            $news->title, $news->content, (string) $news->offering_id, (string) $news->cycle_id,
            $news->published_at?->toISOString() ?? '',
        ]));
    }

    public function send(int $newsId, string $event, string $fingerprint): void
    {
        $news = News::find($newsId);
        if ($news === null || $news->status !== 'published' || ! hash_equals(self::fingerprint($news), $fingerprint)) {
            return;
        }
        $groupIds = $news->offering_id !== null
            ? [$news->offering_id]
            : Offering::query()->where('cycle_id', $news->cycle_id)->select('id');
        $publicationDate = $news->published_at->timezone('America/El_Salvador')->toDateString();

        // B06: PostgreSQL resolves recipients in subqueries; workers no longer
        // materialize every enrollment, teacher and coordinator ID in PHP.
        // This is a union via OR over users, so each recipient is visited once.
        User::query()->where('active', true)
            ->where(static function (Builder $query) use ($groupIds, $publicationDate): void {
                $query->where('role', Role::Coordinator)
                    ->orWhereIn('id', Offering::query()->whereIn('id', $groupIds)->select('teacher_id'))
                    ->orWhereIn('id', Enrollment::query()
                        ->whereIn('offering_id', $groupIds)
                        ->where('active_from', '<=', $publicationDate)
                        ->where(static fn (Builder $active) => $active->whereNull('active_to')->orWhere('active_to', '>=', $publicationDate))
                        ->select('student_id'));
            })
            ->chunkById(100, function ($users) use ($news, $event, $fingerprint): void {
                foreach ($users as $actor) {
                    if ($actor->id === $news->author_id || ! Gate::forUser($actor)->allows('view', $news)) {
                        continue;
                    }
                    $key = 'b03:news:'.$event.':'.$news->id.':'.$fingerprint.':'.$actor->id;
                    $notice = new AcademicNotice(
                        $event === 'published' ? 'Nueva noticia académica' : 'Noticia académica actualizada',
                        $news->title,
                        route('news.show', $news, absolute: false),
                    );
                    $now = now();
                    DB::table('notifications')->insertOrIgnore([
                        'id' => Uuid::uuid5(Uuid::NAMESPACE_URL, $key)->toString(),
                        'type' => AcademicNotice::class, 'notifiable_type' => $actor->getMorphClass(),
                        'notifiable_id' => $actor->id,
                        'data' => json_encode($notice->toDatabase($actor) + ['event_key' => $key], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                        'read_at' => null, 'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            });
    }
}
