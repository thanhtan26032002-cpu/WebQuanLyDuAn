<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Services\ActivityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TaskController extends Controller
{
    // Lấy danh sách tasks
    public function index()
    {
        $tasks = Task::with(['assignee:member_code,member_name,member_avatar', 'attachments'])->get();

        return response()->json($tasks);
    }

    // Tạo Task mới
    public function store(Request $request)
    {
        $input = $this->normalizeOptionalFields($request->all());

        $validator = Validator::make($input, [
            'project_code' => 'nullable|exists:projects,project_code',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'nullable|string|in:task,analysis,ui_ux,frontend,backend,api,database,devops,testing,security,documentation,research,maintenance,bug,feature,milestone',
            'status' => 'nullable|string|in:todo,in_progress,done',
            'priority' => 'nullable|string|in:low,medium,high',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:today|after_or_equal:start_date',
            'progress' => 'nullable|integer|min:0|max:100',
            'estimated_hours' => 'nullable|numeric|min:0|max:99999.99',
            'assignee_code' => 'nullable|exists:members,member_code',
            'tags' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();

        $task = Task::create(Task::mapToDbAttributes($validated));
        $task->load(['assignee:member_code,member_name,member_avatar', 'attachments']);

        $userCode = $request->input('user_code', 'US0001');
        ActivityService::log(
            $userCode,
            'tạo nhiệm vụ',
            'Task',
            $task->task_code,
            "Đã tạo nhiệm vụ mới: {$task->task_title}"
        );

        if ($task->task_assignee_code) {
            $assigneeEmail = Member::whereKey($task->task_assignee_code)->value('member_email');
            $recipientCode = $assigneeEmail
                ? User::where('user_email', $assigneeEmail)->value('user_code')
                : null;

            if ($recipientCode) {
                ActivityService::notify(
                    $recipientCode,
                    'Nhiệm vụ mới',
                    'Bạn đã được phân công nhiệm vụ: '.$task->task_title,
                    'info'
                );
            }
        }

        return response()->json([
            'message' => 'Tạo nhiệm vụ thành công',
            'task' => $task,
        ], 201);
    }

    // Cập nhật Task (toàn bộ thông tin)
    public function update(Request $request, $code)
    {
        $task = Task::findOrFail($code);

        $input = $this->normalizeOptionalFields($request->all());

        $validator = Validator::make($input, [
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'type' => 'nullable|string|in:task,analysis,ui_ux,frontend,backend,api,database,devops,testing,security,documentation,research,maintenance,bug,feature,milestone',
            'status' => 'nullable|string|in:todo,in_progress,done',
            'priority' => 'nullable|string|in:low,medium,high',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'progress' => 'nullable|integer|min:0|max:100',
            'estimated_hours' => 'nullable|numeric|min:0|max:99999.99',
            'assignee_code' => 'nullable|exists:members,member_code',
            'project_code' => 'nullable|exists:projects,project_code',
            'tags' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();

        $task->update(Task::mapToDbAttributes($validated));
        $task->load(['assignee:member_code,member_name,member_avatar', 'attachments']);

        $userCode = $request->input('user_code', 'US0001');
        ActivityService::log(
            $userCode,
            'cập nhật nhiệm vụ',
            'Task',
            $task->task_code,
            "Đã cập nhật nhiệm vụ: {$task->task_title}"
        );

        return response()->json([
            'message' => 'Đã cập nhật nhiệm vụ',
            'task' => $task,
        ]);
    }

    // Cập nhật trạng thái Task (Dành cho Kéo thả Kanban)
    public function updateStatus(Request $request, $code)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:todo,in_progress,done',
        ]);

        $task = Task::findOrFail($code);
        $task->update(['task_status' => $validated['status']]);

        $userCode = $request->input('user_code', 'US0001');
        ActivityService::log(
            $userCode,
            'chuyển trạng thái',
            'Task',
            $task->task_code,
            "Đã chuyển nhiệm vụ {$task->task_title} sang trạng thái: {$validated['status']}"
        );

        return response()->json([
            'message' => 'Đã cập nhật trạng thái',
            'task' => $task,
        ]);
    }

    // Xóa Task
    public function destroy(Request $request, $code)
    {
        $task = Task::findOrFail($code);
        $taskTitle = $task->task_title;
        $taskCode = $task->task_code;

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
            'message' => 'Đã xóa nhiệm vụ',
        ]);
    }

    // Lấy danh sách bình luận
    public function comments($taskCode)
    {
        $comments = TaskComment::where('comment_task_code', $taskCode)
            ->with('user:user_code,user_name,user_avatar')
            ->orderBy('comment_created_at', 'desc')
            ->get();

        return response()->json($comments);
    }

    // Thêm bình luận mới vào Task
    public function storeComment(Request $request, $taskCode)
    {
        $validated = $request->validate([
            'text' => 'nullable|string',
            'user_code' => 'nullable|exists:users,user_code',
            'file_url' => 'nullable|string',
            'file_name' => 'nullable|string',
        ]);

        // Đảm bảo ít nhất có text hoặc file
        $text = $validated['text'] ?? '';
        $fileUrl = $validated['file_url'] ?? null;
        if (empty(trim($text)) && empty($fileUrl)) {
            return response()->json([
                'message' => 'Vui lòng nhập nội dung hoặc đính kèm tệp.',
            ], 422);
        }

        $userCode = $validated['user_code'] ?? 'US0001';

        $comment = TaskComment::create([
            'comment_task_code' => $taskCode,
            'comment_user_code' => $userCode,
            'comment_text' => $text,
            'comment_file_url' => $fileUrl,
            'comment_file_name' => $validated['file_name'] ?? null,
        ]);

        $comment->load('user:user_code,user_name,user_avatar');

        ActivityService::log(
            $userCode,
            'bình luận',
            'Task',
            $taskCode,
            'Đã thêm bình luận vào nhiệm vụ'
        );

        return response()->json([
            'message' => 'Đã gửi bình luận',
            'comment' => $comment,
        ], 201);
    }

    private function normalizeOptionalFields(array $input): array
    {
        foreach (['project_code', 'assignee_code', 'start_date', 'due_date', 'estimated_hours', 'tags'] as $field) {
            if (array_key_exists($field, $input) && trim((string) ($input[$field] ?? '')) === '') {
                $input[$field] = null;
            }
        }

        return $input;
    }
}
