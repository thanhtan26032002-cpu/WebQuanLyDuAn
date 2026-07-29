<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\ActivityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ProjectController extends Controller
{
    // Lấy danh sách dự án
    public function index()
    {
        $projects = Project::withCount('tasks')
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

    // Lấy chi tiết 1 dự án
    public function show($code)
    {
        $project = Project::with([
            'customer',
            'manager',
            'tasks.assignee',
            'members',
            'attachments',
        ])->findOrFail($code);

        return response()->json($project);
    }

    // Tạo dự án mới
    public function store(Request $request)
    {
        $this->normalizeOptionalDates($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'customer_code' => 'nullable|string|exists:customers,customer_code',
            'manager_code' => 'nullable|string|exists:members,member_code',
            'color' => 'sometimes|string|in:indigo,emerald,amber,rose,sky,violet,orange,purple,green,pink,blue',
            'status' => 'nullable|string|in:planning,active,on_hold,completed',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:today',
            'progress' => 'nullable|integer|min:0|max:100',
            'member_ids' => 'sometimes|array',
            'member_ids.*' => 'string|distinct|exists:members,member_code',
        ]);
        $validated = $this->discardFieldsMissingFromLegacySchema($validated);

        $memberIds = $validated['member_ids'] ?? [];
        unset($validated['member_ids']);

        $dbData = Project::mapToDbAttributes($validated);
        $dbData['project_created_by'] = $request->input('user_code', 'US0001');

        $project = Project::create($dbData);
        $project->members()->sync($memberIds);

        ActivityService::log(
            $dbData['project_created_by'],
            'tạo dự án',
            'Project',
            $project->project_code,
            "Đã tạo dự án mới: {$project->project_name}"
        );

        $project->load('customer', 'manager', 'members', 'attachments');

        return response()->json([
            'message' => 'Tạo dự án thành công',
            'project' => $project,
        ], 201);
    }

    // Cập nhật dự án
    public function update(Request $request, $code)
    {
        $project = Project::findOrFail($code);
        $this->normalizeOptionalDates($request);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'customer_code' => 'nullable|string|exists:customers,customer_code',
            'manager_code' => 'nullable|string|exists:members,member_code',
            'color' => 'sometimes|string|in:indigo,emerald,amber,rose,sky,violet,orange,purple,green,pink,blue',
            'status' => 'nullable|string|in:planning,active,on_hold,completed',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'progress' => 'nullable|integer|min:0|max:100',
        ]);
        $validated = $this->discardFieldsMissingFromLegacySchema($validated);

        $project->update(Project::mapToDbAttributes($validated));
        $project->load('customer', 'manager', 'members', 'attachments');

        $userCode = $request->input('user_code', 'US0001');
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
        $projectName = $project->project_name;
        $projectCode = $project->project_code;

        $project->delete();

        $userCode = $request->input('user_code', 'US0001');
        ActivityService::log(
            $userCode,
            'xóa dự án',
            'Project',
            $projectCode,
            "Đã xóa dự án: {$projectName}"
        );

        return response()->json([
            'message' => 'Đã xóa dự án thành công',
        ]);
    }

    public function syncMembers(Request $request, string $code)
    {
        $project = Project::findOrFail($code);
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
}
