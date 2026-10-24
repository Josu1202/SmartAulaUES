<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offerings', function (Blueprint $table): void {
            $table->boolean('active')->default(true);
            $table->index(['cycle_id', 'active']);
        });
    }

    public function down(): void
    {
        if (DB::table('offerings')->where('active', false)->exists()
            || DB::table('audit_logs')->whereIn('action', ['offering.deactivated', 'offering.reactivated'])->exists()) {
            throw new RuntimeException('No se retira el estado administrativo después de utilizar A04. Conserva su historia.');
        }
        Schema::table('offerings', function (Blueprint $table): void {
            $table->dropIndex(['cycle_id', 'active']);
            $table->dropColumn('active');
        });
    }
};
