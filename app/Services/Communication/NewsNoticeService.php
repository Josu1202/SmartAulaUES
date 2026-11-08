<?php

namespace App\Services\Communication;

use App\Enums\Role;
use App\Models\Enrollment;
use App\Models\News;
use App\Models\Offering;
use App\Models\User;
use App\Notifications\AcademicNotice;
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
        $cycleId = $news->cycle_id;
        $groupIds = $news->offering_id !== null ? [$news->offering_id]
            : Offering::where('cycle_id', $cycleId)->pluck('id')->all();
        $publicationDate = $news->published_at->timezone('America/El_Salvador')->toDateString();
        $studentIds = Enrollment::whereIn('offering_id', $groupIds)
            ->where('active_from', '<=', $publicationDate)
            ->where(fn ($q) => $q->whereNull('active_to')->orWhere('active_to', '>=', $publicationDate))
            ->pluck('student_id')->all();
        $teacherIds = Offering::whereIn('id', $groupIds)->pluck('teacher_id')->all();
        $coordinatorIds = User::where('role', Role::Coordinator)->where('active', true)->pluck('id')->all();
        $ids = array_values(array_unique(array_merge($studentIds, $teacherIds, $coordinatorIds)));

        User::whereIn('id', $ids)->where('active', true)->orderBy('id')->chunkById(100, function ($users) use ($news, $event, $fingerprint): void {
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
