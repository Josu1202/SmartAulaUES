<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\News;
use App\Models\User;

final class NewsPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->active;
    }

    public function view(User $actor, News $news): bool
    {
        return News::visibleTo($actor)->whereKey($news->id)->exists();
    }

    public function create(User $actor): bool
    {
        return $actor->active && in_array($actor->role, [Role::Coordinator, Role::Teacher], true);
    }

    public function update(User $actor, News $news): bool
    {
        if (! $this->create($actor) || $news->status === 'archived') {
            return false;
        }
        if ($actor->role === Role::Coordinator) {
            return true;
        }

        return $news->offering_id !== null && $news->author_id === $actor->id
            && $news->offering()->where('teacher_id', $actor->id)->exists();
    }

    public function publish(User $actor, News $news): bool
    {
        return $this->update($actor, $news) && $news->status === 'draft';
    }

    public function archive(User $actor, News $news): bool
    {
        return $this->update($actor, $news) && $news->status === 'published';
    }
}
