<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // B04: estado de onboarding separado de los usuarios y del expediente académico.
        Schema::create('user_tutorial_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 20);
            $table->unsignedSmallInteger('version');
            $table->string('status', 12); // skipped | completed
            $table->timestampTz('finished_at');
            $table->timestamps();
            $table->unique(['user_id', 'role', 'version'], 'user_tutorial_progress_version_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_tutorial_progress');
    }
};
