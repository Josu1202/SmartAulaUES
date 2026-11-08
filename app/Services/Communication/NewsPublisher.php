<?php

namespace App\Services\Communication;

use App\Enums\Role;
use App\Jobs\SendNewsNotice;
use App\Models\AuditLog;
use App\Models\News;
use App\Models\Offering;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class NewsPublisher
{
    public function create(User $actor, array $data): News
    {
        Gate::forUser($actor)->authorize('create', News::class);

        return DB::transaction(function () use ($actor, $data): News {
            $this->validateAudience($actor, $data);
            $news = News::create(array_merge($data, ['author_id' => $actor->id, 'status' => 'draft']));
            $this->audit($actor, $news, 'news.created', null);

            return $news;
        });
    }

    public function update(User $actor, News $news, array $data): void
    {
        DB::transaction(function () use ($actor, $news, $data): void {
            $news = News::lockForUpdate()->findOrFail($news->id);
            Gate::forUser($actor)->authorize('update', $news);
            $this->validateAudience($actor, $data);
            $before = $this->state($news);
            $news->update($data);
            if ($this->state($news) !== $before) {
                $this->audit($actor, $news, 'news.updated', $before);
                if ($news->status === 'published') {
                    $this->queueNotice($news, 'updated');
                }
            }
        });
    }

    public function publish(User $actor, News $news): void
    {
        DB::transaction(function () use ($actor, $news): void {
            $news = News::lockForUpdate()->findOrFail($news->id);
            Gate::forUser($actor)->authorize('publish', $news);
            $before = $this->state($news);
            $news->update(['status' => 'published', 'published_at' => now()]);
            $this->audit($actor, $news, 'news.published', $before);
            $this->queueNotice($news, 'published');
        });
    }

    public function archive(User $actor, News $news): void
    {
        DB::transaction(function () use ($actor, $news): void {
            $news = News::lockForUpdate()->findOrFail($news->id);
            Gate::forUser($actor)->authorize('archive', $news);
            $before = $this->state($news);
            $news->update(['status' => 'archived']);
            $this->audit($actor, $news, 'news.archived', $before);
        });
    }

    private function validateAudience(User $actor, array $data): void
    {
        if (isset($data['cycle_id']) === isset($data['offering_id'])) {
            throw ValidationException::withMessages(['offering_id' => 'Seleccione exactamente un grupo o ciclo.']);
        }
        if (isset($data['cycle_id'])) {
            if ($actor->role !== Role::Coordinator) {
                throw ValidationException::withMessages(['cycle_id' => 'Solo Coordinación publica noticias para un ciclo.']);
            }

            return;
        }
        if (! isset($data['offering_id'])) {
            throw ValidationException::withMessages(['offering_id' => 'Seleccione un grupo o un ciclo.']);
        }
        $group = Offering::findOrFail($data['offering_id']);
        if ($actor->role !== Role::Coordinator && ($actor->role !== Role::Teacher || $group->teacher_id !== $actor->id)) {
            abort(403);
        }
    }

    private function state(News $news): array
    {
        return $news->only(['title', 'content', 'offering_id', 'cycle_id', 'status', 'published_at']);
    }

    private function audit(User $actor, News $news, string $action, ?array $before): void
    {
        AuditLog::create([
            'actor_id' => $actor->id, 'entity_type' => 'News', 'entity_id' => (string) $news->id,
            'action' => $action, 'before' => $before, 'after' => $this->state($news),
            'reason' => null, 'created_at' => now(),
        ]);
    }

    private function queueNotice(News $news, string $event): void
    {
        $id = $news->id;
        $fingerprint = NewsNoticeService::fingerprint($news);
        DB::afterCommit(static function () use ($id, $event, $fingerprint): void {
            SendNewsNotice::dispatch($id, $event, $fingerprint)->onQueue('default');
        });
    }
}
