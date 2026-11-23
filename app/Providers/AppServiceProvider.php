<?php

namespace App\Providers;

use App\Jobs\SendClosureNotice;
use App\Models\AssessmentPeriod;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Paginator::useBootstrapFive();
        RateLimiter::for('terminal-device', function (Request $request): array {
            $operation = $request->route()->getName();
            $key = hash('sha256', $request->bearerToken() ?? '');

            return [
                Limit::perMinute(600)->by('terminal-ip:'.$request->ip()),
                Limit::perMinute($operation === 'terminal.device.nfc' ? 120 : 45)->by($operation.':'.$key),
            ];
        });
        // Read-only side effect: A03 retains ownership of closure calculations/snapshots.
        AssessmentPeriod::updated(static function (AssessmentPeriod $period): void {
            $event = null;
            $reportId = null;
            $digest = null;
            if ($period->wasChanged('state') && $period->state === 'closed' && $period->current_report_id !== null) {
                $event = 'closed';
                $reportId = $period->current_report_id;
            } elseif ($period->wasChanged('closure_issues') && ! empty($period->closure_issues)) {
                $event = 'blocked';
                $digest = hash('sha256', json_encode($period->closure_issues, JSON_THROW_ON_ERROR));
            }
            if ($event === null) {
                return;
            }
            $periodId = $period->id;
            DB::afterCommit(static function () use ($periodId, $event, $reportId, $digest): void {
                SendClosureNotice::dispatch($periodId, $event, $reportId, $digest)->onQueue('default');
            });
        });
    }
}
