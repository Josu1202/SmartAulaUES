<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_events', function (Blueprint $t): void {
            $t->foreignId('session_id')->nullable()->constrained('class_sessions')->restrictOnDelete();
            $t->jsonb('response')->nullable();
            $t->unsignedSmallInteger('http_status')->nullable();
        });
        DB::statement("CREATE UNIQUE INDEX nfc_credentials_one_active ON nfc_credentials (user_id) WHERE state = 'active'");
        Schema::table('class_sessions', fn (Blueprint $t) => $t->index(['offering_id', 'starts_at', 'state']));
    }

    public function down(): void
    {
        Schema::table('class_sessions', fn (Blueprint $t) => $t->dropIndex(['offering_id', 'starts_at', 'state']));
        DB::statement('DROP INDEX IF EXISTS nfc_credentials_one_active');
        Schema::table('attendance_events', function (Blueprint $t): void {
            $t->dropConstrainedForeignId('session_id');
            $t->dropColumn(['response', 'http_status']);
        });
    }
};
