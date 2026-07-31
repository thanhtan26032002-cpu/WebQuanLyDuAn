<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Project;
use App\Models\Task;
use App\Services\AccessService;
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
        $activities = $query
            ->orderBy('activity_created_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json($activities);
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
