<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskComment;
use App\Services\ActivityService;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    // Lấy danh sách tasks
    public function index()
    {
        $tasks = Task::with('assignee:code,name,avatar')->get();
        return response()->json($tasks);
    }

    // Tạo Task mới
    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_code' => 'required|exists:projects,code',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string',
            'priority' => 'nullable|string',
            'due_date' => 'nullable|date',
            'progress' => 'nullable|integer|min:0|max:100',
            'assignee_code' => 'nullable|exists:members,code',
        ]);

        $task = Task::create($validated);
        $task->load('assignee:code,name,avatar');

        ActivityService::log(
            'US0001', 
            'tạo nhiệm vụ', 
            'Task', 
            $task->code, 
            "Đã tạo nhiệm vụ mới: {$task->title}"
        );

        if ($task->assignee_code) {
            ActivityService::notify(
                'US0001', 
                'Nhiệm vụ mới', 
                "Bạn đã được phân công nhiệm vụ: {$task->title}",
                'info'
            );
        }

        return response()->json([
            'message' => 'Tạo nhiệm vụ thành công',
            'task' => $task
        ], 201);
    }

    // Cập nhật Task (toàn bộ thông tin)
    public function update(Request $request, $code)
    {
        $task = Task::findOrFail($code);

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string',
            'priority' => 'nullable|string',
            'due_date' => 'nullable|date',
            'progress' => 'nullable|integer|min:0|max:100',
            'assignee_code' => 'nullable|exists:members,code',
        ]);

        $task->update($validated);
        $task->load('assignee:code,name,avatar');

        ActivityService::log(
            'US0001', 
            'cập nhật nhiệm vụ', 
            'Task', 
            $task->code, 
            "Đã cập nhật nhiệm vụ: {$task->title}"
        );

        return response()->json([
            'message' => 'Đã cập nhật nhiệm vụ',
            'task' => $task
        ]);
    }

    // Cập nhật trạng thái Task (Dành cho Kéo thả Kanban)
    public function updateStatus(Request $request, $code)
    {
        $validated = $request->validate([
            'status' => 'required|string'
        ]);

        $task = Task::findOrFail($code);
        $task->update(['status' => $validated['status']]);

        ActivityService::log(
            'US0001', 
            'chuyển trạng thái', 
            'Task', 
            $task->code, 
            "Đã chuyển nhiệm vụ {$task->title} sang trạng thái: {$validated['status']}"
        );

        return response()->json([
            'message' => 'Đã cập nhật trạng thái',
            'task' => $task
        ]);
    }

    // Xóa Task
    public function destroy($code)
    {
        $task = Task::findOrFail($code);
        $taskTitle = $task->title;
        $taskCode = $task->code;
        
        $task->delete();

        ActivityService::log(
            'US0001', 
            'xóa nhiệm vụ', 
            'Task', 
            $taskCode, 
            "Đã xóa nhiệm vụ: {$taskTitle}"
        );

        return response()->json([
            'message' => 'Đã xóa nhiệm vụ'
        ]);
    }

    // Lấy danh sách bình luận
    public function comments($taskCode)
    {
        $comments = TaskComment::where('task_code', $taskCode)
            ->with('member:code,name,avatar')
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json($comments);
    }

    // Thêm bình luận mới vào Task
    public function storeComment(Request $request, $taskCode)
    {
        $validated = $request->validate([
            'text' => 'required|string',
            'member_code' => 'nullable|exists:members,code',
            'file_url' => 'nullable|string',
            'file_name' => 'nullable|string',
        ]);

        $comment = TaskComment::create([
            'task_code' => $taskCode,
            'member_code' => $validated['member_code'] ?? 'MB0001',
            'text' => $validated['text'],
            'file_url' => $validated['file_url'] ?? null,
            'file_name' => $validated['file_name'] ?? null,
        ]);

        $comment->load('member:code,name,avatar');

        ActivityService::log(
            'US0001', 
            'bình luận', 
            'Task', 
            $taskCode, 
            "Đã thêm bình luận vào nhiệm vụ"
        );

        return response()->json([
            'message' => 'Đã gửi bình luận',
            'comment' => $comment
        ], 201);
    }
}


