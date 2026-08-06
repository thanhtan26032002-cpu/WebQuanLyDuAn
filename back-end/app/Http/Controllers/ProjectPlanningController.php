<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectAutomation;
use App\Models\ProjectMilestone;
use App\Models\ProjectUpdate;
use App\Services\AccessService;
use App\Services\ActivityService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectPlanningController extends Controller
{
    public function storeUpdate(Request $request, string $projectCode)
    {
        $project = Project::findOrFail($projectCode);
        AccessService::authorize(AccessService::isProjectParticipant($request->user(), $project));

        $validated = $request->validate([
            'health' => 'required|in:on_track,at_risk,off_track',
            'completed' => 'nullable|string|max:10000',
            'risks' => 'nullable|string|max:10000',
            'next_steps' => 'nullable|string|max:10000',
        ]);

        $update = ProjectUpdate::create([
            'update_project_code' => $project->project_code,
            'update_author_code' => $request->user()->user_code,
            'update_health' => $validated['health'],
            'update_completed' => $validated['completed'] ?? null,
            'update_risks' => $validated['risks'] ?? null,
            'update_next_steps' => $validated['next_steps'] ?? null,
        ]);
        $project->update(['project_health' => $validated['health']]);

        ActivityService::log($request->user()->user_code, 'cập nhật sức khỏe dự án', 'Project', $projectCode, 'Đã đăng cập nhật định kỳ cho dự án.');

        return response()->json(['message' => 'Đã đăng cập nhật dự án.', 'update' => $update->load('author')], 201);
    }

    public function storeMilestone(Request $request, string $projectCode)
    {
        $project = Project::findOrFail($projectCode);
        AccessService::authorize(AccessService::canManageProject($request->user(), $project));

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'target_date' => 'nullable|date',
        ]);

        $milestone = $project->milestones()->create([
            'milestone_name' => trim($validated['name']),
            'milestone_description' => $validated['description'] ?? null,
            'milestone_target_date' => $validated['target_date'] ?? null,
            'milestone_sort_order' => ((int) $project->milestones()->max('milestone_sort_order')) + 1,
        ]);

        ActivityService::log($request->user()->user_code, 'tạo cột mốc', 'Project', $projectCode, 'Đã tạo cột mốc: '.$milestone->milestone_name);

        return response()->json(['message' => 'Đã tạo cột mốc.', 'milestone' => $milestone->load('tasks')], 201);
    }

    public function updateMilestone(Request $request, string $projectCode, string $milestoneCode)
    {
        $project = Project::findOrFail($projectCode);
        AccessService::authorize(AccessService::canManageProject($request->user(), $project));
        $milestone = $project->milestones()->whereKey($milestoneCode)->firstOrFail();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'target_date' => 'nullable|date',
            'sort_order' => 'nullable|integer|min:0',
        ]);
        $milestone->update(ProjectMilestone::mapToDbAttributes($validated));

        ActivityService::log($request->user()->user_code, 'cập nhật cột mốc', 'Project', $projectCode, 'Đã cập nhật cột mốc: '.$milestone->milestone_name);

        return response()->json(['message' => 'Đã cập nhật cột mốc.', 'milestone' => $milestone->fresh()->load('tasks')]);
    }

    public function destroyMilestone(Request $request, string $projectCode, string $milestoneCode)
    {
        $project = Project::findOrFail($projectCode);
        AccessService::authorize(AccessService::canManageProject($request->user(), $project));
        $milestone = $project->milestones()->whereKey($milestoneCode)->firstOrFail();
        $milestoneName = $milestone->milestone_name;
        $milestone->tasks()->update(['task_milestone_code' => null]);
        $milestone->delete();

        ActivityService::log($request->user()->user_code, 'xóa cột mốc', 'Project', $projectCode, 'Đã xóa cột mốc: '.$milestoneName);

        return response()->json(['message' => 'Đã xóa cột mốc.']);
    }

    public function automations(Request $request, string $projectCode)
    {
        $project = Project::findOrFail($projectCode);
        AccessService::authorize(AccessService::canViewProject($request->user(), $project));

        return response()->json($project->automations()->get());
    }

    public function storeAutomation(Request $request, string $projectCode)
    {
        $project = Project::findOrFail($projectCode);
        AccessService::authorize(AccessService::canManageProject($request->user(), $project));

        $validated = $request->validate([
            'rule' => ['required', Rule::in(['deadline_reminder', 'completion_notify_manager', 'status_handover'])],
            'enabled' => 'sometimes|boolean',
            'config' => 'nullable|array',
        ]);

        $automation = ProjectAutomation::updateOrCreate(
            ['automation_project_code' => $projectCode, 'automation_rule' => $validated['rule']],
            ['automation_enabled' => $validated['enabled'] ?? true, 'automation_config' => $validated['config'] ?? []]
        );

        ActivityService::log(
            $request->user()->user_code,
            ($automation->automation_enabled ? 'bật' : 'tắt').' tự động hóa',
            'Project',
            $projectCode,
            'Quy tắc: '.$automation->automation_rule
        );

        return response()->json(['message' => 'Đã lưu tự động hóa.', 'automation' => $automation], 201);
    }
}
