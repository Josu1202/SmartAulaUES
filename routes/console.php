<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Schedule::command('attendance:prune-location')->daily()->withoutOverlapping();
// The command owns a PostgreSQL session lock. A cache overlap lock could survive a
// killed worker for 30 minutes and prevent prompt recovery after a restart.
Schedule::command('attendance:process-closures')->everyMinute();
Schedule::command('terminal:prune-deliveries')->everyMinute()->withoutOverlapping();

Artisan::command('terminal:prune-deliveries', function (): void {
    DB::table('terminal_commands')->where('state', 'pending')->whereNotNull('delivery')
        ->where(fn ($query) => $query->where('expires_at', '<=', now())->orWhereIn(
            'credential_id',
            DB::table('nfc_credentials')->where('state', 'revoked')->select('id'),
        ))->update(['delivery' => null, 'state' => 'expired']);
    DB::table('terminal_qr_challenges')->whereNotNull('delivery')->where('expires_at', '<=', now())
        ->update(['delivery' => null]);
})->purpose('Retirar secretos de entrega vencidos de las terminales físicas');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
