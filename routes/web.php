<?php

use App\Http\Controllers\Admin\AcademicCycleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\OfferingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TerminalController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::middleware(['auth', 'active'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/profile', ProfileController::class)->name('profile');
    Route::get('/offerings', [OfferingController::class, 'index'])->name('offerings.index');
    Route::get('/offerings/{offering}', [OfferingController::class, 'show'])->name('offerings.show');
    Route::get('/modules/{module}', ModuleController::class)->name('modules.show');
    Route::prefix('admin')->name('admin.')->middleware('role:coordinator')->group(function (): void {
        Route::get('/users', UserController::class)->name('users.index');
        Route::get('/cycles', AcademicCycleController::class)->name('cycles.index');
    });
    Route::get('/teacher/terminal', TerminalController::class)->middleware('role:coordinator,teacher')->name('terminal.index');
});
