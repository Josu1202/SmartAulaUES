<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('offering_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('cycle_id')->nullable()->constrained('academic_cycles')->restrictOnDelete();
            $t->string('title');
            $t->text('content');
            $t->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $t->timestampsTz();
        });
        DB::statement('ALTER TABLE news ADD CONSTRAINT news_scope_check CHECK (offering_id IS NOT NULL OR cycle_id IS NOT NULL)');
        Schema::create('notifications', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('type');
            $t->morphs('notifiable');
            $t->text('data');
            $t->timestampTz('read_at')->nullable();
            $t->timestampsTz();
        });
        Schema::create('audit_logs', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('entity_type', 100);
            $t->string('entity_id', 128);
            $t->string('action', 80);
            $t->jsonb('before')->nullable();
            $t->jsonb('after')->nullable();
            $t->text('reason')->nullable();
            $t->timestampTz('created_at')->useCurrent();
            $t->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'notifications', 'news'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
