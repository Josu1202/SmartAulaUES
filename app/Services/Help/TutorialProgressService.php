<?php

namespace App\Services\Help;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Persistencia por usuario, rol y versión: no depende de cookies ni localStorage. */
final class TutorialProgressService
{
    public const SKIPPED = 'skipped';

    public const COMPLETED = 'completed';

    public function status(User $user): ?string
    {
        return DB::table('user_tutorial_progress')
            ->where('user_id', $user->id)
            ->where('role', $user->role->value)
            ->where('version', TutorialCatalog::VERSION)
            ->value('status');
    }

    public function shouldOffer(User $user): bool
    {
        return $this->status($user) === null;
    }

    public function mark(User $user, string $status): void
    {
        if (! in_array($status, [self::SKIPPED, self::COMPLETED], true)) {
            throw new InvalidArgumentException('Estado del tutorial desconocido.');
        }

        $now = now();
        DB::table('user_tutorial_progress')->upsert([
            [
                'user_id' => $user->id,
                'role' => $user->role->value,
                'version' => TutorialCatalog::VERSION,
                'status' => $status,
                'finished_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['user_id', 'role', 'version'], ['status', 'finished_at', 'updated_at']);
    }
}
