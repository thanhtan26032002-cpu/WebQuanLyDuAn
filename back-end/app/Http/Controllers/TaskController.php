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
        $tasks = Task::with(['assignee:code,name,avatar', 'attachments'])->get();
        return response()->json($tasks);
    }

    // Tạo Task mới
    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_code' => 'nullable|exists:projects,code',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string|in:todo,in_progress,done',
            'priority' => 'nullable|string|in:low,medium,high',
            'due_date' => 'nullable|date|after_or_equal:today',
            'progress' => 'nullable|integer|min:0|max:100',
            'assignee_code' => 'nullable|exists:members,code',
        ]);

        $task = Task::create($validated);
        $task->load(['assignee:code,name,avatar', 'attachments']);

        $userCode = $request->input('user_code', 'US0001');
        ActivityService::log(
            $userCode, 
            'tạo nhiệm vụ', 
            'Task', 
            $task->code, 
            "Đã tạo nhiệm vụ mới: {$task->title}"
        );

        if ($task->assignee_code) {
            ActivityService::notify(
                $userCode, 
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
            'status' => 'nullable|string|in:todo,in_progress,done',
            'priority' => 'nullable|string|in:low,medium,high',
            'due_date' => 'nullable|date',
            'progress' => 'nullable|integer|min:0|max:100',
            'assignee_code' => 'nullable|exists:members,code',
        ]);

        $task->update($validated);
        $task->load(['assignee:code,name,avatar', 'attachments']);

        $userCode = $request->input('user_code', 'US0001');
        ActivityService::log(
            $userCode, 
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
            'status' => 'required|string|in:todo,in_progress,done'
        ]);

        $task = Task::findOrFail($code);
        $task->update(['status' => $validated['status']]);

        $userCode = $request->input('user_code', 'US0001');
        ActivityService::log(
            $userCode, 
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
    public function destroy(Request $request, $code)
    {
        $task = Task::findOrFail($code);
        $taskTitle = $task->title;
        $taskCode = $task->code;
        
        $task->delete();

        $userCode = $request->input('user_code', 'US0001');
        ActivityService::log(
            $userCode, 
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
            ->with('user:code,name,avatar')
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json($comments);
    }

    // Thêm bình luận mới vào Task
    public function storeComment(Request $request, $taskCode)
    {
        $validated = $request->validate([
            'text' => 'nullable|string',
            'user_code' => 'nullable|exists:users,code',
            'file_url' => 'nullable|string',
            'file_name' => 'nullable|string',
        ]);

        // Đảm bảo ít nhất có text hoặc file
        $text = $validated['text'] ?? '';
        $fileUrl = $validated['file_url'] ?? null;
        if (empty(trim($text)) && empty($fileUrl)) {
            return response()->json([
                'message' => 'Vui lòng nhập nội dung hoặc đính kèm tệp.'
            ], 422);
        }

        $userCode = $validated['user_code'] ?? 'US0001';

        $comment = TaskComment::create([
            'task_code' => $taskCode,
            'user_code' => $userCode,
            'text' => $text,
            'file_url' => $fileUrl,
            'file_name' => $validated['file_name'] ?? null,
        ]);

        $comment->load('user:code,name,avatar');

        ActivityService::log(
            $userCode, 
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


