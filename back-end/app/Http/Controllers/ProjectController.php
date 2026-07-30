<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Services\AccessService;
use App\Services\ActivityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ProjectController extends Controller
{
    // Lấy danh sách dự án
    public function index(Request $request)
    {
        $projects = AccessService::scopeProjects(Project::query(), $request->user())
            ->withCount('tasks')
            ->with([
                'customer',
                'manager',
                'members' => function ($q) {
                    $q->select('members.member_code', 'members.member_name', 'members.member_avatar');
                },
                'attachments',
            ])
            ->orderBy('project_created_at', 'desc')
            ->get();

        return response()->json($projects);
    }

    public function trash(Request $request)
    {
        $projects = AccessService::scopeProjects(Project::onlyTrashed(), $request->user())
            ->where('project_deleted_at', '>=', now()->subDays(30))
            ->withCount('tasks')
            ->with(['customer', 'manager'])
            ->orderBy('project_deleted_at', 'desc')
            ->get()
            ->map(fn (Project $project) => $this->trashItem($project));

        return response()->json($projects);
    }

    // Lấy chi tiết 1 dự án
    public function show(Request $request, $code)
    {
        $project = Project::with([
            'customer',
            'manager',
            'tasks.assignee',
            'tasks.checklists',
            'tasks.workLogs.reporter',
            'members',
            'attachments',
            'updates.author',
            'milestones.tasks',
            'automations',
        ])->findOrFail($code);
        AccessService::authorize(AccessService::canViewProject($request->user(), $project));

        return response()->json($project);
    }

    // Tạo dự án mới
    public function store(Request $request)
    {
        AccessService::authorize(AccessService::canManagePeople($request->user()), 'Chỉ quản lý mới được tạo dự án.');
        $this->normalizeOptionalDates($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'customer_code' => 'nullable|string|exists:customers,customer_code',
            'manager_code' => 'nullable|string|exists:members,member_code',
            'color' => 'sometimes|string|in:indigo,emerald,amber,rose,sky,violet,orange,purple,green,pink,blue',
            'status' => 'nullable|string|in:planning,active,on_hold,completed',
            'health' => 'nullable|string|in:on_track,at_risk,off_track',
            'update_cadence' => 'nullable|string|in:weekly,biweekly,monthly,never',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:today',
            'progress' => 'nullable|integer|min:0|max:100',
            'member_ids' => 'sometimes|array',
            'member_ids.*' => 'string|distinct|exists:members,member_code',
            'template' => 'nullable|in:blank,software,marketing,operations',
        ]);
        $validated = $this->discardFieldsMissingFromLegacySchema($validated);

        $memberIds = $validated['member_ids'] ?? [];
        $template = $validated['template'] ?? 'blank';
        unset($validated['member_ids'], $validated['template']);

        $dbData = Project::mapToDbAttributes($validated);
        $dbData['project_created_by'] = $request->user()->user_code;

        $project = Project::create($dbData);
        $project->members()->sync($memberIds);
        $this->createTemplateTasks($project, $template);

        ActivityService::log(
            $dbData['project_created_by'],
            'tạo dự án',
            'Project',
            $project->project_code,
            "Đã tạo dự án mới: {$project->project_name}"
        );

        $project->load('customer', 'manager', 'members', 'attachments', 'tasks');

        return response()->json([
            'message' => 'Tạo dự án thành công',
            'project' => $project,
        ], 201);
    }

    private function createTemplateTasks(Project $project, string $template): void
    {
        $templates = [
            'software' => [
                ['Phân tích yêu cầu', 'analysis', 8],
                ['Thiết kế trải nghiệm và giao diện', 'ui_ux', 16],
                ['Phát triển và tích hợp', 'feature', 40],
                ['Kiểm thử và phát hành', 'testing', 16],
            ],
            'marketing' => [
                ['Xác định mục tiêu và chân dung khách hàng', 'research', 6],
                ['Lập kế hoạch nội dung và kênh', 'documentation', 8],
                ['Triển khai chiến dịch', 'task', 24],
                ['Đo lường và tối ưu', 'analysis', 8],
            ],
            'operations' => [
                ['Khảo sát quy trình hiện tại', 'analysis', 8],
                ['Chuẩn hóa quy trình', 'documentation', 12],
                ['Đào tạo và bàn giao', 'task', 8],
                ['Đánh giá sau triển khai', 'analysis', 4],
            ],
        ];

        foreach ($templates[$template] ?? [] as [$title, $type, $estimate]) {
            Task::create([
                'task_project_code' => $project->project_code,
                'task_title' => $title,
                'task_type' => $type,
                'task_status' => 'todo',
                'task_priority' => 'medium',
                'task_progress' => 0,
                'task_estimated_hours' => $estimate,
            ]);
        }
    }

    // Cập nhật dự án
    public function update(Request $request, $code)
    {
        $project = Project::findOrFail($code);
        AccessService::authorize(AccessService::canManageProject($request->user(), $project));
        $this->normalizeOptionalDates($request);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'customer_code' => 'nullable|string|exists:customers,customer_code',
            'manager_code' => 'nullable|string|exists:members,member_code',
            'color' => 'sometimes|string|in:indigo,emerald,amber,rose,sky,violet,orange,purple,green,pink,blue',
            'status' => 'nullable|string|in:planning,active,on_hold,completed',
            'health' => 'nullable|string|in:on_track,at_risk,off_track',
            'update_cadence' => 'nullable|string|in:weekly,biweekly,monthly,never',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'progress' => 'nullable|integer|min:0|max:100',
        ]);
        $validated = $this->discardFieldsMissingFromLegacySchema($validated);

        $project->update(Project::mapToDbAttributes($validated));
        $project->load('customer', 'manager', 'members', 'attachments');

        $userCode = $request->user()->user_code;
        ActivityService::log(
            $userCode,
            'cập nhật dự án',
            'Project',
            $project->project_code,
            "Đã cập nhật thông tin dự án: {$project->project_name}"
        );

        return response()->json([
            'message' => 'Đã cập nhật dự án',
            'project' => $project,
        ]);
    }

    // Xóa dự án
    public function destroy(Request $request, $code)
    {
        $project = Project::findOrFail($code);
        AccessService::authorize(AccessService::canManageProject($request->user(), $project));
        $projectName = $project->project_name;
        $projectCode = $project->project_code;

        $project->delete();

        $userCode = $request->user()->user_code;
        ActivityService::log(
            $userCode,
            'xóa dự án',
            'Project',
            $projectCode,
            "Đã xóa dự án: {$projectName}"
        );

        return response()->json([
            'message' => 'Đã chuyển dự án vào thùng rác. Có thể khôi phục trong 30 ngày.',
        ]);
    }

    public function restore(Request $request, $code)
    {
        $project = Project::onlyTrashed()->findOrFail($code);
        AccessService::authorize(
            AccessService::isAdmin($request->user()) || $project->project_created_by === $request->user()->user_code
        );
        $restoreUntil = $project->project_deleted_at->copy()->addDays(30);

        if (now()->greaterThan($restoreUntil)) {
            return response()->json([
                'message' => 'Dự án đã bị xóa quá 30 ngày và không thể khôi phục.',
            ], 410);
        }

        $project->restore();

        ActivityService::log(
            $request->user()->user_code,
            'khôi phục dự án',
            'Project',
            $project->project_code,
            'Đã khôi phục dự án: '.$project->project_name
        );

        $project->load('customer', 'manager', 'members', 'attachments');

        return response()->json([
            'message' => 'Đã khôi phục dự án.',
            'project' => $project,
        ]);
    }

    public function syncMembers(Request $request, string $code)
    {
        $project = Project::findOrFail($code);
        AccessService::authorize(AccessService::canManageProject($request->user(), $project));
        $validated = $request->validate([
            'member_ids' => 'present|array',
            'member_ids.*' => 'string|distinct|exists:members,member_code',
        ]);

        $project->members()->sync($validated['member_ids']);
        $project->load('members');

        return response()->json([
            'message' => 'Đã cập nhật thành viên dự án',
            'project' => $project,
        ]);
    }

    private function normalizeOptionalDates(Request $request): void
    {
        foreach (['start_date', 'due_date', 'customer_code', 'manager_code'] as $field) {
            if (array_key_exists($field, $request->all()) && trim((string) $request->input($field)) === '') {
                $request->merge([$field => null]);
            }
        }
    }

    private function discardFieldsMissingFromLegacySchema(array $validated): array
    {
        if (! Schema::hasColumn('projects', 'project_color')) {
            unset($validated['color']);
        }

        return $validated;
    }

    private function trashItem(Project $project): array
    {
        $restoreUntil = $project->project_deleted_at->copy()->addDays(30);

        return array_merge($project->toArray(), [
            'restore_until' => $restoreUntil->toISOString(),
            'can_restore' => now()->lessThanOrEqualTo($restoreUntil),
        ]);
    }
}
