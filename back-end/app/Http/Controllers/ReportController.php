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
        $query = $this->visibleTasks($request)->with(['project', 'assignee', 'dependencies']);
        if ($memberCode) {
            $query->where('task_assignee_code', $memberCode);
        } else {
            $query->whereRaw('1 = 0');
        }

        $tasks = $query->get();
        $today = now()->toDateString();
        $upcoming = now()->addDays(7)->toDateString();

        return response()->json([
            'overdue' => $tasks->filter(fn (Task $task) => $task->task_status !== 'done' && $task->task_due_date && $task->task_due_date < $today)->values(),
            'today' => $tasks->filter(fn (Task $task) => $task->task_status !== 'done' && $task->task_due_date === $today)->values(),
            'upcoming' => $tasks->filter(fn (Task $task) => $task->task_status !== 'done' && $task->task_due_date > $today && $task->task_due_date <= $upcoming)->values(),
            'blocked' => $tasks->filter(fn (Task $task) => $task->is_blocked)->values(),
            'unassigned' => AccessService::canManagePeople($request->user())
                ? $this->visibleTasks($request)->whereNull('task_assignee_code')->with('project')->get()
                : [],
        ]);
    }

    public function index(Request $request)
    {
        AccessService::authorize(AccessService::canManagePeople($request->user()), 'Chỉ quản lý mới được xem báo cáo tổng hợp.');
        $tasks = $this->visibleTasks($request)->with(['project', 'assignee', 'workLogs'])->get();
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
            ->filter(fn (Task $task) => $task->task_status !== 'done' && $task->task_due_date && $task->task_due_date < $today)
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
        ]);
    }

    private function visibleTasks(Request $request): Builder
    {
        if (AccessService::isAdmin($request->user())) {
            return Task::query();
        }

        $projectCodes = AccessService::scopeProjects(Project::query(), $request->user())->pluck('project_code');
        $memberCode = AccessService::userCode($request->user());

        return Task::where(function (Builder $query) use ($projectCodes, $memberCode) {
            $query->whereIn('task_project_code', $projectCodes);
            if ($memberCode) {
                $query->orWhere('task_assignee_code', $memberCode);
            }
        });
    }
}
