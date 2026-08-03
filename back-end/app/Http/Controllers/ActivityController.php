<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Project;
use App\Models\Task;
use App\Services\AccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    // Lấy danh sách nhật ký hệ thống
    public function index(Request $request)
    {
        $query = Activity::with('user:user_code,user_name,user_avatar,user_color');
        if (! AccessService::isAdmin($request->user())) {
            $projectCodes = AccessService::scopeProjects(Project::query(), $request->user())->pluck('project_code');
            $query->where(function ($visible) use ($projectCodes, $request) {
                $visible->where('activity_user_code', $request->user()->user_code)
                    ->orWhereIn('activity_project_code', $projectCodes);
            });
        }

        $type = $request->string('type')->toString();
        if (in_array($type, ['Project', 'Task', 'TaskComment'], true)) {
            $query->where('activity_target_type', $type);
        }

        $keyword = trim(mb_substr($request->string('search')->toString(), 0, 100));
        if ($keyword !== '') {
            $this->applySearch($query, $keyword);
        }

        $query->orderByDesc('activity_created_at');
        if ($request->boolean('paginated')) {
            $perPage = min(100, max(10, $request->integer('per_page', 30)));
            $activities = $query->paginate($perPage);
            $activities->setCollection($this->withTargetLabels($activities->getCollection()));

            return response()->json($activities);
        }

        return response()->json(
            $this->withTargetLabels($query->limit(50)->get())
        );
    }

    private function applySearch(Builder $query, string $keyword): void
    {
        $like = '%'.$keyword.'%';
        $projectCodes = Project::withTrashed()
            ->where('project_name', 'like', $like)
            ->pluck('project_code');
        $taskCodes = Task::withTrashed()
            ->where('task_title', 'like', $like)
            ->pluck('task_code');

        $query->where(function (Builder $search) use ($like, $projectCodes, $taskCodes) {
            $search->where('activity_action', 'like', $like)
                ->orWhere('activity_detail', 'like', $like)
                ->orWhere('activity_target_code', 'like', $like)
                ->orWhereIn('activity_target_code', $projectCodes)
                ->orWhereIn('activity_target_code', $taskCodes)
                ->orWhereHas('user', fn (Builder $user) => $user->where('user_name', 'like', $like));
        });
    }

    private function withTargetLabels(Collection $activities): Collection
    {
        $projectLabels = Project::withTrashed()->pluck('project_name', 'project_code');
        $taskLabels = Task::withTrashed()->pluck('task_title', 'task_code');

        return $activities->map(function (Activity $activity) use ($projectLabels, $taskLabels) {
            $targetLabel = match ($activity->activity_target_type) {
                'Project' => $projectLabels[$activity->activity_target_code] ?? $activity->activity_target_code,
                'Task', 'TaskComment' => $taskLabels[$activity->activity_target_code] ?? $activity->activity_target_code,
                default => $activity->activity_target_code,
            };
            $activity->setAttribute('target_label', $targetLabel);

            return $activity;
        });
    }

    public function project(Request $request, string $code)
    {
        $project = Project::findOrFail($code);
        AccessService::authorize(AccessService::canViewProject($request->user(), $project));

        $taskLabels = Task::withTrashed()
            ->where('task_project_code', $project->project_code)
            ->pluck('task_title', 'task_code');
        $perPage = min(100, max(10, $request->integer('per_page', 50)));
        $activities = Activity::with('user:user_code,user_name,user_avatar,user_color')
            ->where('activity_project_code', $project->project_code)
            ->orderByDesc('activity_created_at')
            ->paginate($perPage);

        $activities->getCollection()->transform(function (Activity $activity) use ($project, $taskLabels) {
            $targetLabel = $activity->activity_target_type === 'Project'
                ? $project->project_name
                : ($taskLabels[$activity->activity_target_code] ?? $activity->activity_target_code);
            $activity->setAttribute('target_label', $targetLabel);

            return $activity;
        });

        return response()->json($activities);
    }
}
