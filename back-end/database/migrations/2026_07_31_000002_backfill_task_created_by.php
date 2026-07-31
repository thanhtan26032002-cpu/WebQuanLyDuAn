<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $fallbackOwner = DB::table('users')
            ->whereIn('user_role', ['admin', 'project_manager'])
            ->orderByRaw("case when user_role = 'admin' then 0 else 1 end")
            ->orderBy('user_created_at')
            ->value('user_code');

        DB::table('tasks')
            ->whereNull('task_created_by')
            ->orderBy('task_code')
            ->chunkById(100, function ($tasks) use ($fallbackOwner) {
                foreach ($tasks as $task) {
                    $owner = null;
                    if ($task->task_project_code) {
                        $owner = DB::table('projects')
                            ->where('project_code', $task->task_project_code)
                            ->value('project_created_by');
                    } elseif ($task->task_assignee_code) {
                        $owner = DB::table('users')
                            ->where('user_code', $task->task_assignee_code)
                            ->whereIn('user_role', ['admin', 'project_manager'])
                            ->value('user_code');
                    }

                    DB::table('tasks')->where('task_code', $task->task_code)->update([
                        'task_created_by' => $owner ?: $fallbackOwner,
                    ]);
                }
            }, 'task_code');
    }

    public function down(): void
    {
        // Không xóa dữ liệu chủ sở hữu đã được xác lập vì có thể đã được cập nhật sau migration.
    }
};
