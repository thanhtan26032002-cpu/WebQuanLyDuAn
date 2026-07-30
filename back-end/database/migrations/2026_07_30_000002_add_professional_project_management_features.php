<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('user_member_code', 50)->nullable()->after('user_code')->index();
            $table->json('user_notification_preferences')->nullable()->after('user_department');
        });

        Schema::table('members', function (Blueprint $table) {
            $table->decimal('member_weekly_capacity_hours', 6, 2)->default(40)->after('member_online');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->string('project_health', 30)->default('on_track')->after('project_status')->index();
            $table->string('project_update_cadence', 30)->default('weekly')->after('project_health');
        });

        Schema::create('project_updates', function (Blueprint $table) {
            $table->string('update_code', 50)->primary();
            $table->string('update_project_code', 50);
            $table->string('update_author_code', 50);
            $table->string('update_health', 30)->default('on_track');
            $table->text('update_completed')->nullable();
            $table->text('update_risks')->nullable();
            $table->text('update_next_steps')->nullable();
            $table->timestamp('update_created_at')->nullable();
            $table->timestamp('update_updated_at')->nullable();

            $table->foreign('update_project_code')->references('project_code')->on('projects')->cascadeOnDelete();
            $table->foreign('update_author_code')->references('user_code')->on('users')->cascadeOnDelete();
            $table->index(['update_project_code', 'update_created_at']);
        });

        Schema::create('project_milestones', function (Blueprint $table) {
            $table->string('milestone_code', 50)->primary();
            $table->string('milestone_project_code', 50);
            $table->string('milestone_name');
            $table->text('milestone_description')->nullable();
            $table->date('milestone_target_date')->nullable();
            $table->unsignedInteger('milestone_sort_order')->default(0);
            $table->timestamp('milestone_created_at')->nullable();
            $table->timestamp('milestone_updated_at')->nullable();

            $table->foreign('milestone_project_code')->references('project_code')->on('projects')->cascadeOnDelete();
            $table->index(['milestone_project_code', 'milestone_target_date']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->string('task_milestone_code', 50)->nullable()->after('task_project_code')->index();
            $table->text('task_blocked_reason')->nullable()->after('task_progress');
            $table->boolean('task_blocked_override')->default(false)->after('task_blocked_reason');
            $table->string('task_recurrence', 30)->nullable()->after('task_tags');
            $table->date('task_recurrence_until')->nullable()->after('task_recurrence');
            $table->timestamp('task_completed_at')->nullable()->after('task_deleted_at');
        });

        Schema::create('task_dependencies', function (Blueprint $table) {
            $table->string('dependency_code', 50)->primary();
            $table->string('dependency_task_code', 50);
            $table->string('dependency_depends_on_code', 50);
            $table->timestamp('dependency_created_at')->nullable();
            $table->timestamp('dependency_updated_at')->nullable();

            $table->unique(['dependency_task_code', 'dependency_depends_on_code'], 'task_dependency_unique');
            $table->foreign('dependency_task_code')->references('task_code')->on('tasks')->cascadeOnDelete();
            $table->foreign('dependency_depends_on_code')->references('task_code')->on('tasks')->cascadeOnDelete();
        });

        Schema::create('task_watchers', function (Blueprint $table) {
            $table->string('watcher_code', 50)->primary();
            $table->string('watcher_task_code', 50);
            $table->string('watcher_user_code', 50);
            $table->timestamp('watcher_created_at')->nullable();
            $table->timestamp('watcher_updated_at')->nullable();

            $table->unique(['watcher_task_code', 'watcher_user_code']);
            $table->foreign('watcher_task_code')->references('task_code')->on('tasks')->cascadeOnDelete();
            $table->foreign('watcher_user_code')->references('user_code')->on('users')->cascadeOnDelete();
        });

        Schema::table('task_work_logs', function (Blueprint $table) {
            $table->unsignedInteger('worklog_duration_minutes')->nullable()->after('worklog_time');
        });

        Schema::create('saved_views', function (Blueprint $table) {
            $table->string('view_code', 50)->primary();
            $table->string('view_user_code', 50);
            $table->string('view_name');
            $table->string('view_scope', 30)->default('tasks');
            $table->json('view_filters');
            $table->boolean('view_is_favorite')->default(false);
            $table->timestamp('view_created_at')->nullable();
            $table->timestamp('view_updated_at')->nullable();

            $table->foreign('view_user_code')->references('user_code')->on('users')->cascadeOnDelete();
            $table->index(['view_user_code', 'view_scope']);
        });

        Schema::create('project_automations', function (Blueprint $table) {
            $table->string('automation_code', 50)->primary();
            $table->string('automation_project_code', 50)->nullable();
            $table->string('automation_rule', 50);
            $table->boolean('automation_enabled')->default(true);
            $table->json('automation_config')->nullable();
            $table->timestamp('automation_created_at')->nullable();
            $table->timestamp('automation_updated_at')->nullable();

            $table->foreign('automation_project_code')->references('project_code')->on('projects')->cascadeOnDelete();
        });

        DB::table('users')->orderBy('user_code')->get()->each(function ($user) {
            $memberCode = DB::table('members')
                ->whereRaw('LOWER(member_email) = ?', [mb_strtolower($user->user_email)])
                ->value('member_code');

            DB::table('users')->where('user_code', $user->user_code)->update([
                'user_member_code' => $memberCode,
                'user_notification_preferences' => json_encode([
                    'assignment' => true,
                    'deadline' => true,
                    'comments' => true,
                    'mentions' => true,
                    'blocked' => true,
                ], JSON_UNESCAPED_UNICODE),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_automations');
        Schema::dropIfExists('saved_views');

        Schema::table('task_work_logs', function (Blueprint $table) {
            $table->dropColumn('worklog_duration_minutes');
        });

        Schema::dropIfExists('task_watchers');
        Schema::dropIfExists('task_dependencies');

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn([
                'task_milestone_code',
                'task_blocked_reason',
                'task_blocked_override',
                'task_recurrence',
                'task_recurrence_until',
                'task_completed_at',
            ]);
        });

        Schema::dropIfExists('project_milestones');
        Schema::dropIfExists('project_updates');

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['project_health', 'project_update_cadence']);
        });

        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('member_weekly_capacity_hours');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['user_member_code', 'user_notification_preferences']);
        });
    }
};
