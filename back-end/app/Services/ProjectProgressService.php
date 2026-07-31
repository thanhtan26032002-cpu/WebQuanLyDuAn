<?php

namespace App\Services;

use App\Models\Project;

class ProjectProgressService
{
    public static function sync(?string $projectCode): void
    {
        if (! $projectCode) {
            return;
        }

        $project = Project::find($projectCode);
        if (! $project) {
            return;
        }

        if ($project->project_status === 'completed') {
            $progress = 100;
        } else {
            $tasks = $project->tasks()->get(['task_status', 'task_progress']);
            $progress = $tasks->isEmpty()
                ? 0
                : (int) round($tasks->avg(fn ($task) => $task->task_status === 'done' ? 100 : $task->task_progress));
        }

        if ((int) $project->project_progress !== $progress) {
            $project->updateQuietly(['project_progress' => $progress]);
        }
    }
}
