<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_export_requests', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('report_version_id')
                ->constrained('report_versions')
                ->restrictOnDelete();

            $table->foreignId('requested_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('format', 3);
            $table->string('state', 12)->default('pending');

            $table->uuid('request_token');
            $table->unsignedInteger('attempts')->default(0);

            $table->timestampTz('queued_at');
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestampTz('lease_expires_at')->nullable();

            $table->timestamps();

            $table->unique(
                ['report_version_id', 'requested_by', 'format'],
                'report_export_requests_actor_format_unique'
            );

            $table->index(
                ['state', 'lease_expires_at'],
                'report_export_requests_recovery_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_export_requests');
    }
};
