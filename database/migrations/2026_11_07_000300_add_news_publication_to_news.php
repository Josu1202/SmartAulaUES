<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news', function (Blueprint $table): void {
            $table->string('status', 12)->default('draft');
            $table->timestampTz('published_at')->nullable();
            $table->index(['status', 'published_at'], 'news_status_published_idx');
        });
        DB::statement("ALTER TABLE news ADD CONSTRAINT news_publication_status_check CHECK (status IN ('draft','published','archived'))");
    }

    public function down(): void
    {
        if (DB::table('news')->where('status', '!=', 'draft')->exists()) {
            throw new RuntimeException('Existen noticias publicadas o archivadas; no se admite rollback que borre su estado.');
        }
        DB::statement('ALTER TABLE news DROP CONSTRAINT news_publication_status_check');
        Schema::table('news', function (Blueprint $table): void {
            $table->dropIndex('news_status_published_idx');
            $table->dropColumn(['status', 'published_at']);
        });
    }
};
