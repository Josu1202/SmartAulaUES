<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_cycles', function (Blueprint $t): void {
            $t->id();
            $t->string('code', 30)->unique();
            $t->date('starts_on');
            $t->date('ends_on');
            $t->string('status', 20)->default('planned');
            $t->timestampsTz();
        });
        Schema::create('subjects', function (Blueprint $t): void {
            $t->id();
            $t->string('code', 30)->unique();
            $t->string('name');
            $t->boolean('active')->default(true);
            $t->timestampsTz();
        });
        Schema::create('offerings', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('cycle_id')->constrained('academic_cycles')->restrictOnDelete();
            $t->foreignId('subject_id')->constrained()->restrictOnDelete();
            $t->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $t->string('group_code', 20);
            $t->unsignedInteger('capacity');
            $t->timestampsTz();
            $t->unique(['cycle_id', 'subject_id', 'group_code']);
        });
        Schema::create('enrollments', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('offering_id')->constrained()->restrictOnDelete();
            $t->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $t->date('active_from');
            $t->date('active_to')->nullable();
            $t->timestampsTz();
            $t->unique(['offering_id', 'student_id']);
            $t->unique(['id', 'offering_id']);
        });
        Schema::create('assessment_periods', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('offering_id')->constrained()->restrictOnDelete();
            $t->unsignedSmallInteger('number');
            $t->date('starts_on');
            $t->date('ends_on');
            $t->string('state', 20)->default('open');
            $t->string('policy_version', 40);
            $t->timestampTz('closed_at')->nullable();
            $t->timestampsTz();
            $t->unique(['offering_id', 'number']);
            $t->unique(['id', 'offering_id']);
        });
        Schema::create('class_sessions', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('period_id');
            $t->foreignId('offering_id')->constrained()->restrictOnDelete();
            $t->timestampTz('starts_at');
            $t->timestampTz('ends_at');
            $t->string('modality', 20)->default('in_person');
            $t->string('state', 20)->default('scheduled');
            $t->text('change_reason')->nullable();
            $t->timestampsTz();
            $t->unique(['id', 'offering_id']);
            $t->index(['offering_id', 'starts_at']);
            $t->foreign(['period_id', 'offering_id'])->references(['id', 'offering_id'])->on('assessment_periods')->restrictOnDelete();
        });
        $checks = [
            'academic_cycles' => ['ends_on >= starts_on', "status IN ('planned','active','closed')"],
            'offerings' => ['capacity > 0'],
            'enrollments' => ['active_to IS NULL OR active_to >= active_from'],
            'assessment_periods' => ['number > 0', 'ends_on >= starts_on', "state IN ('open','closing','closed')"],
            'class_sessions' => ['ends_at > starts_at', "modality IN ('in_person','online','event')", "state IN ('scheduled','in_progress','completed','cancelled')", "state <> 'cancelled' OR (change_reason IS NOT NULL AND length(trim(change_reason)) > 0)"],
        ];
        foreach ($checks as $table => $rules) {
            foreach ($rules as $i => $rule) {
                DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_rule_{$i} CHECK ({$rule})");
            }
        }
    }

    public function down(): void
    {
        foreach (['class_sessions', 'assessment_periods', 'enrollments', 'offerings', 'subjects', 'academic_cycles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
