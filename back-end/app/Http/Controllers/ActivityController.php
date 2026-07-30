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
        $query = Activity::with('user:user_code,user_name,user_avatar');
        if (! AccessService::isAdmin($request->user())) {
            $projectCodes = AccessService::scopeProjects(Project::query(), $request->user())->pluck('project_code');
            $taskCodes = Task::whereIn('task_project_code', $projectCodes)->pluck('task_code');
            $query->where(function ($visible) use ($projectCodes, $taskCodes, $request) {
                $visible->where('activity_user_code', $request->user()->user_code)
                    ->orWhere(fn ($projects) => $projects->where('activity_target_type', 'Project')->whereIn('activity_target_code', $projectCodes))
                    ->orWhere(fn ($tasks) => $tasks->where('activity_target_type', 'Task')->whereIn('activity_target_code', $taskCodes));
            });
        }
        $activities = $query
            ->orderBy('activity_created_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json($activities);
    }
}
