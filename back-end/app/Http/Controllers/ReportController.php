<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Services\AccessService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function myWork(Request $request)
    {
        $memberCode = AccessService::userCode($request->user());
        $today = now()->toDateString();
        $upcomingUntil = now()->addDays(7)->toDateString();
        $assignedQuery = Task::query()->where('task_assignee_code', $memberCode);
        $activeTasks = (clone $assignedQuery)
            ->where('task_status', '!=', 'done')
            ->with(['project', 'assignee', 'dependencies'])
            ->get();
        $recentlyCompleted = (clone $assignedQuery)
            ->where('task_status', 'done')
            ->with(['project', 'assignee', 'dependencies'])
            ->orderByDesc('task_completed_at')
            ->orderByDesc('task_updated_at')
            ->limit(20)
            ->get();

        $taskDueDate = fn (Task $task) => $task->task_due_date?->toDateString();
        $sortTasks = fn ($items) => $items->sortBy(function (Task $task) use ($taskDueDate) {
            $priority = ['high' => 0, 'medium' => 1, 'low' => 2][$task->task_priority] ?? 3;
            $status = $task->task_status === 'in_progress' ? 0 : 1;

            return sprintf(
                '%d|%s|%d|%s',
                $priority,
                $taskDueDate($task) ?: '9999-12-31',
                $status,
                $task->task_created_at
            );
        })->values();

        $overdue = $sortTasks($activeTasks->filter(
            fn (Task $task) => $taskDueDate($task) && $taskDueDate($task) < $today
        ));
        $dueToday = $sortTasks($activeTasks->filter(
            fn (Task $task) => $taskDueDate($task) === $today
        ));
        $upcoming = $sortTasks($activeTasks->filter(
            fn (Task $task) => $taskDueDate($task) > $today && $taskDueDate($task) <= $upcomingUntil
        ));
        $later = $sortTasks($activeTasks->filter(
            fn (Task $task) => $taskDueDate($task) > $upcomingUntil
        ));
        $withoutDeadline = $sortTasks($activeTasks->filter(
            fn (Task $task) => ! $task->task_due_date
        ));

        return response()->json([
            'owner' => [
                'code' => $request->user()->user_code,
                'name' => $request->user()->user_name,
            ],
            'summary' => [
                'total_assigned' => (clone $assignedQuery)->count(),
                'active' => $activeTasks->count(),
                'in_progress' => $activeTasks->where('task_status', 'in_progress')->count(),
                'overdue' => $overdue->count(),
                'due_today' => $dueToday->count(),
                'upcoming' => $upcoming->count(),
                'blocked' => $activeTasks->filter(fn (Task $task) => $task->is_blocked)->count(),
                'completed' => (clone $assignedQuery)->where('task_status', 'done')->count(),
            ],
            'sections' => [
                'overdue' => $overdue,
                'today' => $dueToday,
                'upcoming' => $upcoming,
                'later' => $later,
                'no_deadline' => $withoutDeadline,
                'recently_completed' => $recentlyCompleted,
            ],
            'meta' => [
                'upcoming_days' => 7,
                'recently_completed_limit' => 20,
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function index(Request $request)
    {
        AccessService::authorize(AccessService::canViewReports($request->user()), 'Chỉ quản trị viên hoặc quản lý dự án mới được xem báo cáo tổng hợp.');
        $tasks = $this->visibleTasks($request)->with(['project', 'assignee', 'workLogs'])->get();
        $projects = AccessService::scopeManagedProjects(Project::query(), $request->user())
            ->with('manager')
            ->get();
        $today = now()->toDateString();

        $completedByWeek = collect(range(7, 0))->map(function (int $weeksAgo) use ($tasks) {
            $start = now()->subWeeks($weeksAgo)->startOfWeek();
            $end = $start->copy()->endOfWeek();
            $count = $tasks->filter(function (Task $task) use ($start, $end) {
                $completedAt = $task->task_completed_at ?: ($task->task_status === 'done' ? $task->task_updated_at : null);

                return $completedAt && Carbon::parse($completedAt)->betweenIncluded($start, $end);
            })->count();

            return ['label' => $start->format('d/m'), 'count' => $count];
        })->values();

        $overdue = $tasks
            ->filter(fn (Task $task) => $task->task_status !== 'done'
                && $task->task_due_date
                && $task->task_due_date->toDateString() < $today)
            ->values();

        $workload = $tasks->where('task_status', '!=', 'done')
            ->whereNotNull('task_assignee_code')
            ->groupBy('task_assignee_code')
            ->map(function ($memberTasks) {
                $member = $memberTasks->first()->assignee;
                $estimated = round((float) $memberTasks->sum('task_estimated_hours'), 2);
                $capacity = (float) ($member?->user_weekly_capacity_hours ?? 40);

                return [
                    'member' => $member,
                    'task_count' => $memberTasks->count(),
                    'estimated_hours' => $estimated,
                    'capacity_hours' => $capacity,
                    'load_percent' => $capacity > 0 ? (int) round(($estimated / $capacity) * 100) : 0,
                ];
            })->values();

        $estimateVsActual = $tasks->filter(fn (Task $task) => $task->task_estimated_hours !== null)
            ->map(fn (Task $task) => [
                'task_code' => $task->task_code,
                'title' => $task->task_title,
                'estimated_hours' => (float) $task->task_estimated_hours,
                'actual_hours' => round($task->actual_minutes / 60, 2),
            ])->values();

        $cycleDurations = $tasks->filter(fn (Task $task) => $task->task_status === 'done')
            ->map(function (Task $task) {
                $end = $task->task_completed_at ?: $task->task_updated_at;

                return $end && $task->task_created_at
                    ? Carbon::parse($task->task_created_at)->diffInHours(Carbon::parse($end))
                    : null;
            })->filter(fn ($hours) => $hours !== null);

        $completedTasksWithDeadline = $tasks->filter(fn (Task $task) => $task->task_status === 'done'
            && $task->task_due_date
            && $task->task_completed_at);
        $lateTasks = $completedTasksWithDeadline
            ->filter(fn (Task $task) => $task->late_days > 0)
            ->sortByDesc('late_days')
            ->values();
        $completedProjectsWithDeadline = $projects->filter(fn (Project $project) => $project->project_status === 'completed'
            && $project->project_due_date
            && $project->project_completed_at);
        $lateProjects = $completedProjectsWithDeadline
            ->filter(fn (Project $project) => $project->late_days > 0)
            ->sortByDesc('late_days')
            ->values();
        $overdueProjects = $projects
            ->filter(fn (Project $project) => $project->deadline_state === 'overdue')
            ->sortByDesc('overdue_days')
            ->values();

        return response()->json([
            'completed_by_week' => $completedByWeek,
            'overdue' => [
                'total' => $overdue->count(),
                'by_project' => $overdue->groupBy('task_project_code')->map(fn ($items) => [
                    'project' => $items->first()->project,
                    'count' => $items->count(),
                ])->values(),
                'by_assignee' => $overdue->groupBy('task_assignee_code')->map(fn ($items) => [
                    'assignee' => $items->first()->assignee,
                    'count' => $items->count(),
                ])->values(),
            ],
            'workload' => $workload,
            'estimate_vs_actual' => $estimateVsActual,
            'average_cycle_hours' => $cycleDurations->isEmpty() ? 0 : round($cycleDurations->avg(), 1),
            'overdue_projects' => [
                'total' => $overdueProjects->count(),
                'items' => $overdueProjects->take(20)->values(),
            ],
            'late_completion' => [
                'tasks' => [
                    'total' => $lateTasks->count(),
                    'completed_with_deadline' => $completedTasksWithDeadline->count(),
                    'rate' => $completedTasksWithDeadline->isEmpty()
                        ? 0
                        : round(($lateTasks->count() / $completedTasksWithDeadline->count()) * 100, 1),
                    'average_late_days' => $lateTasks->isEmpty() ? 0 : round($lateTasks->avg('late_days'), 1),
                    'items' => $lateTasks->take(20)->values(),
                ],
                'projects' => [
                    'total' => $lateProjects->count(),
                    'completed_with_deadline' => $completedProjectsWithDeadline->count(),
                    'rate' => $completedProjectsWithDeadline->isEmpty()
                        ? 0
                        : round(($lateProjects->count() / $completedProjectsWithDeadline->count()) * 100, 1),
                    'average_late_days' => $lateProjects->isEmpty() ? 0 : round($lateProjects->avg('late_days'), 1),
                    'items' => $lateProjects->take(20)->values(),
                ],
            ],
        ]);
    }

    private function visibleTasks(Request $request): Builder
    {
        if (AccessService::isAdmin($request->user())) {
            return Task::query();
        }

        $projectCodes = AccessService::scopeManagedProjects(Project::query(), $request->user())->pluck('project_code');
        $memberCode = AccessService::userCode($request->user());

        return Task::where(function (Builder $query) use ($projectCodes, $memberCode) {
            $query->whereIn('task_project_code', $projectCodes);
            if ($memberCode) {
                $query->orWhere(function (Builder $created) use ($memberCode) {
                    $created->whereNull('task_project_code')->where('task_created_by', $memberCode);
                });
            }
        });
    }
}
