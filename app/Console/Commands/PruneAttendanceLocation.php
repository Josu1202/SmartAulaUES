<?php

namespace App\Console\Commands;

use App\Models\AttendanceEvent;
use App\Models\AttendancePortal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneAttendanceLocation extends Command
{
    protected $signature = 'attendance:prune-location';

    protected $description = 'Retira referencias y evidencia geográfica después de 30 días; conserva asistencias e idempotencia';

    public function handle(): int
    {
        $cutoff = now()->subDays(30);
        [$portals, $events] = DB::transaction(fn () => [
            AttendancePortal::where('expires_at', '<', $cutoff)->whereNotNull('geo_reference')->update(['geo_reference' => null]),
            AttendanceEvent::where('received_at', '<', $cutoff)->whereNotNull('geo_validation')->update(['geo_validation' => null]),
        ]);
        $this->info("Referencias retiradas: {$portals}; evidencias retiradas: {$events}.");

        return self::SUCCESS;
    }
}
