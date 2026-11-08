<?php

namespace App\Jobs;

use App\Services\Communication\NewsNoticeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class SendNewsNotice implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $newsId, public string $event, public string $fingerprint) {}

    public function handle(NewsNoticeService $service): void
    {
        $service->send($this->newsId, $this->event, $this->fingerprint);
    }
}
