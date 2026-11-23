<?php

namespace App\Actions\Attendance;

use Closure;

final readonly class TerminalRegistration
{
    public function __construct(
        public string $assignmentId,
        public ?string $challengeId,
        public ?string $tokenHash,
        public Closure $validate,
    ) {}
}
