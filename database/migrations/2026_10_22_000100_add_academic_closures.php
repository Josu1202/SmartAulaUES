<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_sessions', function (Blueprint $t): void {
            $t->timestampTz('started_at')->nullable();
            $t->timestampTz('finalized_at')->nullable();
            $t->index(['state', 'started_at']);
        });
        DB::statement('ALTER TABLE class_sessions DROP CONSTRAINT class_sessions_rule_2');
        DB::statement("ALTER TABLE class_sessions ADD CONSTRAINT class_sessions_rule_2 CHECK (state IN ('scheduled','in_progress','completed','cancelled','unstarted'))");
        Schema::table('attendances', fn (Blueprint $t) => $t->foreignId('recorded_by')->nullable()->change());
        Schema::table('report_versions', function (Blueprint $t): void {
            $t->foreignId('generated_by')->nullable()->change();
            $t->timestampTz('generated_at')->nullable();
            $t->string('origin', 15)->nullable();
            $t->char('snapshot_hash', 64)->nullable();
        });
        Schema::table('assessment_periods', function (Blueprint $t): void {
            $t->foreignId('current_report_id')->nullable()->constrained('report_versions')->restrictOnDelete();
            $t->timestampTz('reopened_at')->nullable();
            $t->jsonb('closure_issues')->nullable();
            $t->timestampTz('closure_checked_at')->nullable();
        });
        Schema::table('academic_cycles', function (Blueprint $t): void {
            $t->timestampTz('closed_at')->nullable();
            $t->timestampTz('reopened_at')->nullable();
            $t->jsonb('closure_issues')->nullable();
            $t->timestampTz('closure_checked_at')->nullable();
        });
    }

    public function down(): void
    {
        // Do not erase historical versions or invent human attribution in a rollback.
        if (DB::table('class_sessions')->where('state', 'unstarted')->exists()
            || DB::table('report_versions')->whereNotNull('snapshot_hash')->exists()
            || DB::table('attendances')->whereNull('recorded_by')->exists()) {
            throw new RuntimeException('A03 contiene historia: restaura un respaldo compatible; no se admite rollback destructivo.');
        }
        Schema::table('assessment_periods', function (Blueprint $t): void {
            $t->dropConstrainedForeignId('current_report_id');
            $t->dropColumn(['reopened_at', 'closure_issues', 'closure_checked_at']);
        });
        Schema::table('academic_cycles', fn (Blueprint $t) => $t->dropColumn(['closed_at', 'reopened_at', 'closure_issues', 'closure_checked_at']));
        Schema::table('report_versions', function (Blueprint $t): void {
            $t->foreignId('generated_by')->nullable(false)->change();
            $t->dropColumn(['generated_at', 'origin', 'snapshot_hash']);
        });
        Schema::table('attendances', fn (Blueprint $t) => $t->foreignId('recorded_by')->nullable(false)->change());
        DB::statement('ALTER TABLE class_sessions DROP CONSTRAINT class_sessions_rule_2');
        DB::statement("ALTER TABLE class_sessions ADD CONSTRAINT class_sessions_rule_2 CHECK (state IN ('scheduled','in_progress','completed','cancelled'))");
        Schema::table('class_sessions', function (Blueprint $t): void {
            $t->dropIndex(['state', 'started_at']);
            $t->dropColumn(['started_at', 'finalized_at']);
        });
    }
};
