<?php

namespace App\Jobs;

use App\Services\Communication\ClosureNoticeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class SendClosureNotice implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $periodId,
        public string $event,
        public ?int $reportId = null,
        public ?string $issueDigest = null,
    ) {}

    public function handle(ClosureNoticeService $service): void
    {
        $service->send($this->periodId, $this->event, $this->reportId, $this->issueDigest);
    }
}
