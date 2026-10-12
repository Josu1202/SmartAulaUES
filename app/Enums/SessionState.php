<?php

namespace App\Enums;

enum SessionState: string
{
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Programada', self::InProgress => 'En curso', self::Completed => 'Realizada', self::Cancelled => 'Cancelada'
        };
    }
}
