<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_scenario_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('scenario_code', 30)->unique();
            $table->unsignedSmallInteger('version');
            $table->string('profile', 12);
            $table->date('reference_date');
            $table->char('manifest_hash', 64);
            $table->jsonb('expected_counts');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_scenario_runs');
    }
};
