<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_versions', function (Blueprint $table): void {
            $table->string('csv_path')->nullable();
            $table->char('csv_hash', 64)->nullable();
        });
    }

    public function down(): void
    {
        // No destruir metadatos de archivos históricos registrados.
        if (DB::table('report_versions')->whereNotNull('csv_path')->orWhereNotNull('csv_hash')->exists()) {
            throw new RuntimeException('Existen CSV históricos registrados; no se admite eliminar sus metadatos.');
        }

        Schema::table('report_versions', function (Blueprint $table): void {
            $table->dropColumn(['csv_path', 'csv_hash']);
        });
    }
};
