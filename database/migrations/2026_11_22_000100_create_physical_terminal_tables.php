<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('physical_terminals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 80);
            $table->string('secret_hash', 64)->unique();
            $table->boolean('enabled')->default(true);
            $table->jsonb('capabilities');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('last_seen_at')->nullable();
            $table->string('firmware', 40)->nullable();
        });
        Schema::create('terminal_assignments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('terminal_id')->constrained('physical_terminals')->restrictOnDelete();
            $table->foreignId('session_id')->constrained('class_sessions')->restrictOnDelete();
            $table->foreignId('portal_id')->constrained('attendance_portals')->restrictOnDelete();
            $table->foreignId('operator_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('reader_id')->nullable()->constrained('reader_sessions')->restrictOnDelete();
            $table->jsonb('methods');
            $table->timestampTz('assigned_at');
            $table->timestampTz('expires_at');
            $table->timestampTz('released_at')->nullable();
            $table->index(['terminal_id', 'released_at', 'expires_at']);
            $table->index(['session_id', 'released_at']);
        });
        Schema::create('terminal_commands', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('terminal_id')->constrained('physical_terminals')->restrictOnDelete();
            $table->foreignId('credential_id')->constrained('nfc_credentials')->restrictOnDelete();
            $table->foreignId('operator_id')->constrained('users')->restrictOnDelete();
            $table->string('state', 20);
            $table->text('delivery')->nullable();
            $table->uuid('lease_id')->nullable();
            $table->timestampTz('lease_until')->nullable();
            $table->timestampTz('expires_at');
            $table->index(['terminal_id', 'state', 'expires_at']);
        });
        DB::statement('CREATE UNIQUE INDEX terminal_assignments_one_current ON terminal_assignments (terminal_id) WHERE released_at IS NULL');
        Schema::create('terminal_qr_challenges', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('assignment_id')->constrained('terminal_assignments')->restrictOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->text('delivery')->nullable();
            $table->timestampTz('issued_at');
            $table->timestampTz('expires_at');
            $table->index(['assignment_id', 'issued_at']);
        });
        Schema::create('terminal_attendance_events', function (Blueprint $table): void {
            $table->uuid('event_id')->primary();
            $table->foreign('event_id')->references('event_id')->on('attendance_events')->restrictOnDelete();
            $table->foreignUuid('assignment_id')->constrained('terminal_assignments')->restrictOnDelete();
            $table->foreignUuid('challenge_id')->nullable()->constrained('terminal_qr_challenges')->restrictOnDelete();
        });
        Schema::create('terminal_operation_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('terminal_id')->constrained('physical_terminals')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 40);
            $table->uuid('resource_id')->nullable();
            $table->string('reason', 255)->nullable();
            $table->timestampTz('occurred_at');
            $table->index(['terminal_id', 'id']);
        });
    }

    public function down(): void
    {
        foreach (['terminal_operation_logs', 'terminal_attendance_events', 'terminal_qr_challenges', 'terminal_commands', 'terminal_assignments', 'physical_terminals'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
