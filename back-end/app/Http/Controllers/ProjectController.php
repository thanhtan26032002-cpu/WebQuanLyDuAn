<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\ActivityService;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    // Lấy danh sách dự án
    public function index()
    {
        $projects = Project::withCount('tasks')
            ->with([
                'members' => function($q) {
                    $q->select('members.code', 'members.name', 'members.avatar');
                },
                'attachments'
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($projects);
    }

    // Lấy chi tiết 1 dự án
    public function show($code)
    {
        $project = Project::with([
            'tasks.assignee',
            'members'
        ])->findOrFail($code);

        return response()->json($project);
    }

    // Tạo dự án mới
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string|in:planning,active,on_hold,completed',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:today',
            'progress' => 'nullable|integer|min:0|max:100',
        ]);

        $validated['created_by'] = $request->input('user_code', 'US0001');

        $project = Project::create($validated);

        ActivityService::log(
            $validated['created_by'],
            'tạo dự án',
            'Project',
            $project->code,
            "Đã tạo dự án mới: {$project->name}"
        );

        return response()->json([
            'message' => 'Tạo dự án thành công',
            'project' => $project
        ], 201);
    }

    // Cập nhật dự án
    public function update(Request $request, $code)
    {
        $project = Project::findOrFail($code);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string|in:planning,active,on_hold,completed',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'progress' => 'nullable|integer|min:0|max:100',
        ]);

        $project->update($validated);

        $userCode = $request->input('user_code', 'US0001');
        ActivityService::log(
            $userCode,
            'cập nhật dự án',
            'Project',
            $project->code,
            "Đã cập nhật thông tin dự án: {$project->name}"
        );

        return response()->json([
            'message' => 'Đã cập nhật dự án',
            'project' => $project
        ]);
    }

    // Xóa dự án
    public function destroy(Request $request, $code)
    {
        $project = Project::findOrFail($code);
        $projectName = $project->name;
        $projectCode = $project->code;

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
            'message' => 'Đã xóa dự án thành công'
        ]);
    }
}


