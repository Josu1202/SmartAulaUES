<?php

namespace App\Enums;

enum SessionState: string
{
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Unstarted = 'unstarted';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Programada', self::InProgress => 'En curso', self::Completed => 'Finalizada', self::Cancelled => 'Cancelada', self::Unstarted => 'Sin inicio registrado'
        };
    }
}
