<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Never infer graduation from a legacy year number.
        if (DB::table('users')->where('study_year', '>', 5)->exists()) {
            throw new RuntimeException('A05: clasifica explícitamente los años 6–10 antes de migrar; no equivalen a Egresado.');
        }
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('graduated')->default(false);
            $table->boolean('must_change_password')->default(false);
        });
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_years_check');
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_years_check CHECK ((entry_year IS NULL OR entry_year BETWEEN 1900 AND 2200) AND (study_year IS NULL OR study_year BETWEEN 1 AND 5) AND (NOT graduated OR (role = \'student\' AND study_year IS NULL)))');
        DB::statement('ALTER TABLE attendances DROP CONSTRAINT attendances_rule_1');
        DB::statement("ALTER TABLE attendances ADD CONSTRAINT attendances_rule_1 CHECK (method IS NULL OR method IN ('nfc','nfc_tag','qr','link','manual'))");
        Schema::create('operation_runs', function (Blueprint $table): void {
            $table->string('name')->primary();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->unsignedInteger('errors')->default(0);
        });
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE attendances DROP CONSTRAINT attendances_rule_1');
        DB::statement("ALTER TABLE attendances ADD CONSTRAINT attendances_rule_1 CHECK (method IS NULL OR method IN ('nfc','qr','link','manual'))");
        Schema::dropIfExists('operation_runs');
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_years_check');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['graduated', 'must_change_password']));
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_years_check CHECK ((entry_year IS NULL OR entry_year BETWEEN 1900 AND 2200) AND (study_year IS NULL OR study_year BETWEEN 1 AND 10))');
    }
};
