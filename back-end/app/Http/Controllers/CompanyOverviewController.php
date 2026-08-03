<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Project;
use App\Models\Task;
use App\Services\AccessService;
use Illuminate\Http\Request;

class CompanyOverviewController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $projects = Project::query()
            ->withCount('tasks')
            ->with([
                'customer',
                'manager:user_code,user_name,user_avatar,user_color,user_job_title,user_department',
                'members:user_code,user_name,user_avatar,user_color,user_job_title,user_department',
            ])
            ->orderByDesc('project_updated_at')
            ->get()
            ->map(function (Project $project) use ($user) {
                return [
                    'code' => $project->project_code,
                    'name' => $project->project_name,
                    'description' => $project->project_description,
                    'color' => $project->project_color,
                    'status' => $project->project_status,
                    'health' => $project->project_health,
                    'start_date' => $project->project_start_date?->format('Y-m-d'),
                    'due_date' => $project->project_due_date?->format('Y-m-d'),
                    'progress' => (int) $project->project_progress,
                    'created_by' => $project->project_created_by,
                    'manager_code' => $project->project_manager_code,
                    'tasks_count' => $project->tasks_count,
                    'deadline_state' => $project->deadline_state,
                    'overdue_days' => $project->overdue_days,
                    'late_days' => $project->late_days,
                    'customer' => $project->customer,
                    'manager' => $project->manager,
                    'members' => $project->members,
                    'can_view' => AccessService::canViewProject($user, $project),
                    'can_create_task' => AccessService::canCreateTaskInProject($user, $project),
                ];
            });

        $tasks = Task::query()
            ->where(fn ($query) => $query->whereNull('task_project_code')->orWhereHas('project'))
            ->with([
                'assignee:user_code,user_name,user_avatar,user_color,user_job_title',
                'project:project_code,project_name,project_created_by,project_manager_code',
                'dependencies:task_code,task_status',
            ])
            ->orderByDesc('task_updated_at')
            ->get()
            ->map(function (Task $task) use ($user) {
                return [
                    'code' => $task->task_code,
                    'project_code' => $task->task_project_code,
                    'title' => $task->task_title,
                    'status' => $task->task_status,
                    'priority' => $task->task_priority,
                    'start_date' => $task->task_start_date?->format('Y-m-d'),
                    'due_date' => $task->task_due_date?->format('Y-m-d'),
                    'assignee_code' => $task->task_assignee_code,
                    'progress' => (int) $task->task_progress,
                    'is_blocked' => $task->is_blocked,
                    'blocked_reason' => $task->task_blocked_reason,
                    'delay_reason' => $task->task_delay_reason,
                    'recovery_plan' => $task->task_recovery_plan,
                    'tags' => $task->task_tags,
                    'deadline_state' => $task->deadline_state,
                    'overdue_days' => $task->overdue_days,
                    'late_days' => $task->late_days,
                    'project' => $task->project,
                    'assignee' => $task->assignee,
                    'can_view' => AccessService::canViewTask($user, $task),
                    'can_contribute' => AccessService::canContributeToTask($user, $task),
                ];
            });

        $projectLabels = Project::pluck('project_name', 'project_code');
        $taskLabels = Task::withTrashed()->pluck('task_title', 'task_code');
        $activities = Activity::with('user:user_code,user_name,user_avatar,user_color')
            ->orderByDesc('activity_created_at')
            ->limit(50)
            ->get()
            ->map(function (Activity $activity) use ($projectLabels, $taskLabels) {
                $targetLabel = match ($activity->activity_target_type) {
                    'Project' => $projectLabels[$activity->activity_target_code] ?? $activity->activity_target_code,
                    'Task', 'TaskComment' => $taskLabels[$activity->activity_target_code] ?? $activity->activity_target_code,
                    default => $activity->activity_target_code,
                };
                return [
                    'code' => $activity->activity_code,
                    'action' => $activity->activity_action,
                    'detail' => $activity->activity_detail,
                    'target_type' => $activity->activity_target_type,
                    'target_code' => $activity->activity_target_code,
                    'target_label' => $targetLabel,
                    'created_at' => $activity->activity_created_at,
                    'user' => $activity->user,
                ];
            });

        return response()->json([
            'projects' => $projects,
            'tasks' => $tasks,
            'activities' => $activities,
        ]);
    }
}
