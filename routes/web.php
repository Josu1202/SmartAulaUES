<?php

use App\Http\Controllers\Admin\AcademicCycleController;
use App\Http\Controllers\Admin\AssessmentPeriodController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\EnrollmentController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\OfferingController as AdminOfferingController;
use App\Http\Controllers\Admin\SubjectController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\OfferingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TerminalController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::middleware(['auth', 'active', 'auth.session'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/profile', ProfileController::class)->name('profile');
    Route::put('/profile/password', [ProfileController::class, 'changePassword'])->middleware('throttle:6,1')->name('profile.password');
    Route::get('/offerings', [OfferingController::class, 'index'])->name('offerings.index');
    Route::get('/offerings/{offering}', [OfferingController::class, 'show'])->name('offerings.show');
    Route::redirect('/modules/imports', '/admin/imports')->middleware('role:coordinator');
    Route::get('/modules/{module}', ModuleController::class)->name('modules.show');
    Route::prefix('admin')->name('admin.')->middleware('role:coordinator')->group(function (): void {
        Route::resource('users', UserController::class)->except(['show', 'destroy']);
        Route::put('/users/{user}/password', [UserController::class, 'resetPassword'])->middleware('throttle:10,1')->name('users.password');
        Route::resource('cycles', AcademicCycleController::class)->except(['show', 'destroy']);
        Route::resource('subjects', SubjectController::class)->except(['show', 'destroy']);
        Route::resource('offerings', AdminOfferingController::class)->only(['create', 'store', 'edit', 'update']);
        Route::resource('offerings.periods', AssessmentPeriodController::class)->only(['create', 'store', 'edit', 'update'])->scoped();
        Route::resource('offerings.enrollments', EnrollmentController::class)->only(['create', 'store', 'edit', 'update'])->scoped();
        Route::get('/audit', AuditController::class)->name('audit.index');
        Route::get('/imports', [ImportController::class, 'index'])->name('imports.index');
        Route::get('/imports/templates/{kind}', [ImportController::class, 'template'])->name('imports.template');
        Route::post('/imports/preview', [ImportController::class, 'preview'])->middleware('throttle:12,1')->block(60, 10)->name('imports.preview');
        Route::post('/imports/confirm', [ImportController::class, 'confirm'])->middleware('throttle:12,1')->block(60, 10)->name('imports.confirm');
    });
    Route::get('/teacher/terminal', TerminalController::class)->middleware('role:coordinator,teacher')->name('terminal.index');
});
