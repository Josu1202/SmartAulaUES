<?php

use App\Http\Controllers\PhysicalTerminalApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/terminal')->name('terminal.device.')->middleware('throttle:terminal-device')->group(function (): void {
    Route::post('/heartbeat', PhysicalTerminalApiController::class)->name('heartbeat');
    Route::get('/state', PhysicalTerminalApiController::class)->name('state');
    Route::post('/qr-challenges', PhysicalTerminalApiController::class)->name('qr');
    Route::post('/commands/claim', PhysicalTerminalApiController::class)->name('claim');
    Route::post('/commands/{command}/confirm-write', PhysicalTerminalApiController::class)->whereUuid('command')->name('confirm');
    Route::post('/commands/{command}/result', PhysicalTerminalApiController::class)->whereUuid('command')->name('result');
    Route::post('/nfc-readings', PhysicalTerminalApiController::class)->name('nfc');
});
