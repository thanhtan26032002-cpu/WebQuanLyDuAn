<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->text('project_delay_reason')->nullable()->after('project_due_date');
            $table->text('project_recovery_plan')->nullable()->after('project_delay_reason');
            $table->timestamp('project_completed_at')->nullable()->after('project_recovery_plan')->index();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->text('task_delay_reason')->nullable()->after('task_due_date');
            $table->text('task_recovery_plan')->nullable()->after('task_delay_reason');
        });

        Schema::create('deadline_extensions', function (Blueprint $table) {
            $table->string('extension_code', 50)->primary();
            $table->string('extension_target_type', 20);
            $table->string('extension_target_code', 50);
            $table->date('extension_old_due_date');
            $table->date('extension_new_due_date');
            $table->text('extension_reason');
            $table->string('extension_created_by', 50);
            $table->timestamp('extension_created_at')->nullable();
            $table->timestamp('extension_updated_at')->nullable();

            $table->foreign('extension_created_by')->references('user_code')->on('users')->cascadeOnDelete();
            $table->index(
                ['extension_target_type', 'extension_target_code', 'extension_created_at'],
                'deadline_extension_target_index'
            );
        });

        DB::table('projects')
            ->where('project_status', 'completed')
            ->whereNull('project_completed_at')
            ->update(['project_completed_at' => DB::raw('project_updated_at')]);
    }

    public function down(): void
    {
        Schema::dropIfExists('deadline_extensions');

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['task_delay_reason', 'task_recovery_plan']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['project_delay_reason', 'project_recovery_plan', 'project_completed_at']);
        });
    }
};
