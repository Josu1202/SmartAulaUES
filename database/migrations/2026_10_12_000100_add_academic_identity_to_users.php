<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException('SIAA requiere PostgreSQL para garantizar las restricciones del modelo.');
        }
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 20)->default('student')->index();
            $table->string('student_code', 25)->nullable()->unique();
            $table->boolean('active')->default(true);
        });
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('coordinator','teacher','student'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['role', 'student_code', 'active']);
        });
    }
};
