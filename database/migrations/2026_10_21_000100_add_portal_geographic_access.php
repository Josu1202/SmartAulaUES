<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_sessions', function (Blueprint $table): void {
            $table->string('event_attendance_mode', 20)->nullable();
        });
        DB::statement("ALTER TABLE class_sessions ADD CONSTRAINT class_sessions_event_attendance_mode_check CHECK (event_attendance_mode IS NULL OR event_attendance_mode IN ('in_person', 'online'))");
        Schema::table('attendance_portals', function (Blueprint $table): void {
            $table->string('access_modality', 20)->nullable();
            $table->jsonb('geo_reference')->nullable();
        });
        Schema::table('attendance_events', function (Blueprint $table): void {
            $table->jsonb('geo_validation')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_events', fn (Blueprint $table) => $table->dropColumn('geo_validation'));
        Schema::table('attendance_portals', fn (Blueprint $table) => $table->dropColumn(['access_modality', 'geo_reference']));
        Schema::table('class_sessions', fn (Blueprint $table) => $table->dropColumn('event_attendance_mode'));
    }
};
