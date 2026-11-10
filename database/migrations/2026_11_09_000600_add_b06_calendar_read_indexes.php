<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->index(['student_id', 'offering_id', 'active_from', 'active_to'], 'b06_enrollment_calendar_idx');
        });
        Schema::table('class_sessions', function (Blueprint $table): void {
            $table->index(['starts_at', 'id', 'offering_id'], 'b06_session_calendar_idx');
        });
    }

    public function down(): void
    {
        Schema::table('class_sessions', function (Blueprint $table): void {
            $table->dropIndex('b06_session_calendar_idx');
        });
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->dropIndex('b06_enrollment_calendar_idx');
        });
    }
};
