<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_portals', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('session_id')->constrained('class_sessions')->restrictOnDelete();
            $t->timestampTz('opens_at');
            $t->timestampTz('expires_at');
            $t->timestampTz('closed_at')->nullable();
            $t->jsonb('enabled_methods');
            $t->char('token_hash', 64)->unique();
            $t->timestampsTz();
        });
        Schema::create('reader_sessions', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignId('portal_id')->constrained('attendance_portals')->restrictOnDelete();
            $t->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $t->string('label', 100);
            $t->timestampTz('expires_at');
            $t->string('state', 20)->default('active');
            $t->timestampsTz();
        });
        Schema::create('nfc_credentials', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->char('token_hash', 64)->unique();
            $t->string('state', 20)->default('pending');
            $t->timestampTz('pending_expires_at')->nullable();
            $t->timestampTz('activated_at')->nullable();
            $t->timestampTz('revoked_at')->nullable();
            $t->timestampsTz();
        });
        Schema::create('attendances', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('offering_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('enrollment_id');
            $t->unsignedBigInteger('session_id');
            $t->string('status', 20);
            $t->string('method', 15)->nullable();
            $t->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $t->timestampTz('recorded_at');
            $t->timestampsTz();
            $t->unique(['session_id', 'enrollment_id']);
            $t->foreign(['enrollment_id', 'offering_id'])->references(['id', 'offering_id'])->on('enrollments')->restrictOnDelete();
            $t->foreign(['session_id', 'offering_id'])->references(['id', 'offering_id'])->on('class_sessions')->restrictOnDelete();
        });
        Schema::create('absence_requests', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('offering_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('enrollment_id');
            $t->unsignedBigInteger('session_id');
            $t->text('reason');
            $t->string('evidence_path')->nullable();
            $t->string('status', 20)->default('pending');
            $t->foreignId('resolved_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->text('resolution_note')->nullable();
            $t->timestampTz('resolved_at')->nullable();
            $t->timestampsTz();
            $t->unique(['session_id', 'enrollment_id']);
            $t->foreign(['enrollment_id', 'offering_id'])->references(['id', 'offering_id'])->on('enrollments')->restrictOnDelete();
            $t->foreign(['session_id', 'offering_id'])->references(['id', 'offering_id'])->on('class_sessions')->restrictOnDelete();
        });
        Schema::create('attendance_events', function (Blueprint $t): void {
            $t->uuid('event_id')->primary();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->uuid('reader_session_id')->nullable();
            $t->foreign('reader_session_id')->references('id')->on('reader_sessions')->restrictOnDelete();
            $t->foreignId('credential_id')->nullable()->constrained('nfc_credentials')->restrictOnDelete();
            $t->char('request_hash', 64);
            $t->string('outcome', 30);
            $t->timestampTz('received_at');
            $t->foreignId('attendance_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::create('report_versions', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('period_id')->constrained('assessment_periods')->restrictOnDelete();
            $t->unsignedInteger('version');
            $t->jsonb('snapshot');
            $t->string('policy_version', 40);
            $t->string('pdf_path')->nullable();
            $t->char('file_hash', 64)->nullable();
            $t->foreignId('generated_by')->constrained('users')->restrictOnDelete();
            $t->timestampsTz();
            $t->unique(['period_id', 'version']);
        });
        $checks = [
            'attendance_portals' => ['expires_at > opens_at'],
            'reader_sessions' => ["state IN ('active','closed')"],
            'nfc_credentials' => ["state IN ('pending','active','revoked')"],
            'attendances' => ["status IN ('present','absent','justified')", "method IS NULL OR method IN ('nfc','qr','link','manual')"],
            'absence_requests' => ["status IN ('pending','approved','rejected')"],
            'attendance_events' => ["outcome IN ('recorded','already_recorded','rejected')"],
            'report_versions' => ['version > 0'],
        ];
        foreach ($checks as $table => $rules) {
            foreach ($rules as $i => $rule) {
                DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_rule_{$i} CHECK ({$rule})");
            }
        }
    }

    public function down(): void
    {
        foreach (['report_versions', 'attendance_events', 'absence_requests', 'attendances', 'nfc_credentials', 'reader_sessions', 'attendance_portals'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
