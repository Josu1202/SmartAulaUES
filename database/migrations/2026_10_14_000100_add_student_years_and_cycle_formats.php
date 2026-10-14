<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t): void {
            $t->unsignedSmallInteger('entry_year')->nullable();
            $t->unsignedSmallInteger('study_year')->nullable();
            $t->index(['role', 'active', 'entry_year', 'study_year']);
        });
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_years_check CHECK ((entry_year IS NULL OR entry_year BETWEEN 1900 AND 2200) AND (study_year IS NULL OR study_year BETWEEN 1 AND 10))');
        Schema::create('cycle_formats', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('cycle_id')->constrained('academic_cycles')->restrictOnDelete();
            $t->string('name', 120);
            $t->unsignedSmallInteger('version')->default(1);
            $t->boolean('active')->default(true);
            $t->timestampsTz();
            $t->unique(['cycle_id', 'name', 'version']);
            $t->unique(['id', 'cycle_id']);
        });
        Schema::create('cycle_format_periods', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('cycle_format_id')->constrained()->restrictOnDelete();
            $t->unsignedSmallInteger('number');
            $t->date('starts_on');
            $t->date('ends_on');
            $t->timestampsTz();
            $t->unique(['cycle_format_id', 'number']);
        });
        DB::statement('ALTER TABLE cycle_format_periods ADD CONSTRAINT cycle_format_dates_check CHECK (number > 0 AND ends_on >= starts_on)');
        Schema::table('offerings', function (Blueprint $t): void {
            $t->unsignedBigInteger('cycle_format_id')->nullable();
            $t->foreign(['cycle_format_id', 'cycle_id'])->references(['id', 'cycle_id'])->on('cycle_formats')->restrictOnDelete();
        });
        Schema::table('assessment_periods', function (Blueprint $t): void {
            $t->foreignId('format_period_id')->nullable()->constrained('cycle_format_periods')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assessment_periods', fn (Blueprint $t) => $t->dropConstrainedForeignId('format_period_id'));
        Schema::table('offerings', function (Blueprint $t): void {
            $t->dropForeign(['cycle_format_id', 'cycle_id']);
            $t->dropColumn('cycle_format_id');
        });
        Schema::dropIfExists('cycle_format_periods');
        Schema::dropIfExists('cycle_formats');
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_years_check');
        Schema::table('users', function (Blueprint $t): void {
            $t->dropIndex(['role', 'active', 'entry_year', 'study_year']);
            $t->dropColumn(['entry_year', 'study_year']);
        });
    }
};
