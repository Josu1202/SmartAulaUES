<?php

namespace App\Enums;

enum Role: string
{
    case Coordinator = 'coordinator';
    case Teacher = 'teacher';
    case Student = 'student';

    public function label(): string
    {
        return match ($this) {
            self::Coordinator => 'Coordinación', self::Teacher => 'Docente', self::Student => 'Estudiante'
        };
    }
}
