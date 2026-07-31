<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->string('activity_project_code', 50)->nullable()->after('activity_user_code');
            $table->index(['activity_project_code', 'activity_created_at'], 'activities_project_created_index');
            $table->foreign('activity_project_code')->references('project_code')->on('projects')->nullOnDelete();
        });

        DB::table('activities')
            ->where('activity_target_type', 'Project')
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('projects')
                    ->whereColumn('projects.project_code', 'activities.activity_target_code');
            })
            ->update(['activity_project_code' => DB::raw('activity_target_code')]);

        DB::table('activities')
            ->whereIn('activity_target_type', ['Task', 'TaskComment'])
            ->orderBy('activity_code')
            ->chunkById(100, function ($activities) {
                foreach ($activities as $activity) {
                    $projectCode = DB::table('tasks')
                        ->where('task_code', $activity->activity_target_code)
                        ->value('task_project_code');
                    if ($projectCode) {
                        DB::table('activities')->where('activity_code', $activity->activity_code)->update([
                            'activity_project_code' => $projectCode,
                        ]);
                    }
                }
            }, 'activity_code');
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropForeign(['activity_project_code']);
            $table->dropIndex('activities_project_created_index');
            $table->dropColumn('activity_project_code');
        });
    }
};
