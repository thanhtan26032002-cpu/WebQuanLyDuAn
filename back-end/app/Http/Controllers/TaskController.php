<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TaskWatcher;
use App\Models\User;
use App\Services\AccessService;
use App\Services\ActivityService;
use App\Services\AutomationService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    // Lấy danh sách tasks
    public function index(Request $request)
    {
        $tasks = $this->visibleTasks($request)->where(function ($query) {
            $query->whereNull('task_project_code')->orWhereHas('project');
        })->with([
            'assignee:user_code,user_name,user_avatar,user_color,user_job_title',
            'attachments',
            'checklists',
            'workLogs.reporter:user_code,user_name,user_avatar',
            'dependencies:task_code,task_title,task_status,task_project_code',
            'blocking:task_code,task_title,task_status,task_project_code',
            'milestone',
            'watchers:user_code,user_name,user_avatar',
        ]);

        if ($request->filled('project_code')) {
            $tasks->where('task_project_code', $request->query('project_code'));
        }
        if ($request->filled('status')) {
            $tasks->where('task_status', $request->query('status'));
        }
        if ($request->filled('assignee_code')) {
            $tasks->where('task_assignee_code', $request->query('assignee_code'));
        }
        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $tasks->where(fn (Builder $query) => $query
                ->where('task_title', 'like', "%{$search}%")
                ->orWhere('task_description', 'like', "%{$search}%"));
        }

        $tasks->orderByDesc('task_updated_at');

        if ($request->boolean('paginate')) {
            return response()->json($tasks->paginate(min(100, max(10, $request->integer('per_page', 30)))));
        }

        $tasks = $tasks->get();

        return response()->json($tasks);
    }

    public function trash(Request $request)
    {
        $tasks = $this->visibleTasks($request, true)
            ->where('task_deleted_at', '>=', now()->subDays(30))
            ->with([
                'assignee:user_code,user_name,user_avatar,user_color,user_job_title',
                'project' => fn ($query) => $query->withTrashed(),
            ])
            ->orderBy('task_deleted_at', 'desc')
            ->get()
            ->map(fn (Task $task) => $this->trashItem($task));

        return response()->json($tasks);
    }

    // Tạo Task mới
    public function store(Request $request)
    {
        $input = $this->normalizeOptionalFields($request->all());

        $validator = Validator::make($input, [
            'project_code' => ['nullable', Rule::exists('projects', 'project_code')->whereNull('project_deleted_at')],
            'milestone_code' => 'nullable|exists:project_milestones,milestone_code',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'nullable|string|in:task,analysis,ui_ux,frontend,backend,api,database,devops,testing,security,documentation,research,maintenance,bug,feature,milestone',
            'status' => 'nullable|string|in:todo,in_progress,done',
            'priority' => 'nullable|string|in:low,medium,high',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:today|after_or_equal:start_date',
            'progress' => 'nullable|integer|min:0|max:100',
            'estimated_hours' => 'nullable|numeric|min:0|max:99999.99',
            'assignee_code' => 'nullable|exists:users,user_code',
            'tags' => 'nullable|string|max:500',
            'blocked_reason' => 'nullable|string|max:5000',
            'recurrence' => 'nullable|in:daily,weekly,monthly',
            'recurrence_until' => 'nullable|date|after_or_equal:today',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();

        if ($validated['project_code'] ?? null) {
            $project = Project::findOrFail($validated['project_code']);
            AccessService::authorize(AccessService::canManageProject($request->user(), $project), 'Bạn không có quyền tạo nhiệm vụ trong dự án này.');
            if (($validated['milestone_code'] ?? null) && ! $project->milestones()->whereKey($validated['milestone_code'])->exists()) {
                return response()->json(['message' => 'Cột mốc không thuộc dự án đã chọn.'], 422);
            }
        } else {
            AccessService::authorize(AccessService::canManagePeople($request->user()), 'Chỉ quản lý mới được tạo nhiệm vụ độc lập.');
        }

        $task = Task::create(Task::mapToDbAttributes($validated));
        TaskWatcher::firstOrCreate(['watcher_task_code' => $task->task_code, 'watcher_user_code' => $request->user()->user_code]);
        $task->load($this->taskRelations());

        $userCode = $request->user()->user_code;
        ActivityService::log(
            $userCode,
            'tạo nhiệm vụ',
            'Task',
            $task->task_code,
            "Đã tạo nhiệm vụ mới: {$task->task_title}"
        );

        if ($task->task_assignee_code) {
            if (User::whereKey($task->task_assignee_code)->exists()) {
                ActivityService::notify(
                    $task->task_assignee_code,
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
        $task = Task::with('project')->findOrFail($code);
        AccessService::authorize(AccessService::canEditTask($request->user(), $task));

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
            'assignee_code' => 'nullable|exists:users,user_code',
            'project_code' => ['nullable', Rule::exists('projects', 'project_code')->whereNull('project_deleted_at')],
            'milestone_code' => 'nullable|exists:project_milestones,milestone_code',
            'tags' => 'nullable|string|max:500',
            'blocked_reason' => 'nullable|string|max:5000',
            'blocked_override' => 'nullable|boolean',
            'recurrence' => 'nullable|in:daily,weekly,monthly',
            'recurrence_until' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();

        $previousStatus = $task->task_status;

        if (array_key_exists('project_code', $validated) && $validated['project_code'] !== $task->task_project_code) {
            AccessService::authorize(AccessService::canManagePeople($request->user()));
            if ($validated['project_code']) {
                $targetProject = Project::findOrFail($validated['project_code']);
                AccessService::authorize(AccessService::canManageProject($request->user(), $targetProject));
            }
        }

        $targetProjectCode = $validated['project_code'] ?? $task->task_project_code;
        if (($validated['milestone_code'] ?? null) && ! Project::whereKey($targetProjectCode)
            ->whereHas('milestones', fn (Builder $query) => $query->whereKey($validated['milestone_code']))
            ->exists()) {
            return response()->json(['message' => 'Cột mốc không thuộc dự án đã chọn.'], 422);
        }

        if (array_key_exists('blocked_override', $validated) && $validated['blocked_override']) {
            AccessService::authorize(AccessService::canManagePeople($request->user()), 'Chỉ quản lý mới được bỏ qua trạng thái bị chặn.');
        }
        if (isset($validated['status']) && in_array($validated['status'], ['in_progress', 'done'], true) && $task->is_blocked) {
            $canOverride = ($validated['blocked_override'] ?? false) && AccessService::canManagePeople($request->user());
            if (! $canOverride) {
                return response()->json(['message' => 'Nhiệm vụ đang bị chặn bởi công việc chưa hoàn thành.'], 409);
            }
        }
        $task->update(Task::mapToDbAttributes($validated));
        if (array_key_exists('status', $validated)) {
            $task->task_completed_at = $validated['status'] === 'done' ? ($task->task_completed_at ?: now()) : null;
            $task->save();
            if ($previousStatus !== 'done' && $validated['status'] === 'done') {
                $this->createRecurringTaskIfNeeded($task);
            }
            AutomationService::taskStatusChanged($task->loadMissing(['project.automations', 'project.manager']), $previousStatus);
        }
        $task->load($this->taskRelations());

        $userCode = $request->user()->user_code;
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
            'override_blocked' => 'sometimes|boolean',
        ]);

        $task = Task::with(['project', 'dependencies'])->findOrFail($code);
        AccessService::authorize(AccessService::canEditTask($request->user(), $task));
        $previousStatus = $task->task_status;

        if ($task->is_blocked && in_array($validated['status'], ['in_progress', 'done'], true)) {
            $canOverride = ($validated['override_blocked'] ?? false) && AccessService::canManagePeople($request->user());
            if (! $canOverride) {
                return response()->json(['message' => 'Nhiệm vụ đang bị chặn bởi công việc chưa hoàn thành.'], 409);
            }
            $task->task_blocked_override = true;
        }

        $task->task_status = $validated['status'];
        $task->task_completed_at = $validated['status'] === 'done' ? now() : null;
        $task->save();
        if ($previousStatus !== 'done' && $validated['status'] === 'done') {
            $this->createRecurringTaskIfNeeded($task);
        }
        AutomationService::taskStatusChanged($task->loadMissing(['project.automations', 'project.manager']), $previousStatus);

        $userCode = $request->user()->user_code;
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
        $task = Task::with('project')->findOrFail($code);
        AccessService::authorize(
            AccessService::isAdmin($request->user())
            || ($task->project && AccessService::canManageProject($request->user(), $task->project))
        );
        $taskTitle = $task->task_title;
        $taskCode = $task->task_code;

        $task->delete();

        $userCode = $request->user()->user_code;
        ActivityService::log(
            $userCode,
            'xóa nhiệm vụ',
            'Task',
            $taskCode,
            "Đã xóa nhiệm vụ: {$taskTitle}"
        );

        return response()->json([
            'message' => 'Đã chuyển nhiệm vụ vào thùng rác. Có thể khôi phục trong 30 ngày.',
        ]);
    }

    public function restore(Request $request, $code)
    {
        $task = Task::onlyTrashed()->findOrFail($code);
        AccessService::authorize(AccessService::isAdmin($request->user()));
        $restoreUntil = $task->task_deleted_at->copy()->addDays(30);

        if (now()->greaterThan($restoreUntil)) {
            return response()->json([
                'message' => 'Nhiệm vụ đã bị xóa quá 30 ngày và không thể khôi phục.',
            ], 410);
        }

        if ($task->task_project_code) {
            $project = Project::withTrashed()->find($task->task_project_code);
            if ($project?->trashed()) {
                return response()->json([
                    'message' => 'Hãy khôi phục dự án chứa nhiệm vụ này trước.',
                ], 409);
            }
        }

        $task->restore();

        ActivityService::log(
            $request->user()->user_code,
            'khôi phục nhiệm vụ',
            'Task',
            $task->task_code,
            'Đã khôi phục nhiệm vụ: '.$task->task_title
        );

        $task->load($this->taskRelations());

        return response()->json([
            'message' => 'Đã khôi phục nhiệm vụ.',
            'task' => $task,
        ]);
    }

    // Lấy danh sách bình luận
    public function comments(Request $request, $taskCode)
    {
        $task = Task::with('project')->findOrFail($taskCode);
        AccessService::authorize(AccessService::canViewTask($request->user(), $task));
        $comments = TaskComment::where('comment_task_code', $taskCode)
            ->with('user:user_code,user_name,user_avatar')
            ->orderBy('comment_created_at', 'desc')
            ->get();

        return response()->json($comments);
    }

    // Thêm bình luận mới vào Task
    public function storeComment(Request $request, $taskCode)
    {
        $task = Task::with('project')->findOrFail($taskCode);
        AccessService::authorize(AccessService::canViewTask($request->user(), $task));
        $validated = $request->validate([
            'text' => 'nullable|string',
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

        $userCode = $request->user()->user_code;

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

        $this->notifyTaskWatchers($task, $userCode, 'Bình luận mới', $request->user()->user_name.' đã bình luận trong '.$task->task_title);

        return response()->json([
            'message' => 'Đã gửi bình luận',
            'comment' => $comment,
        ], 201);
    }

    private function normalizeOptionalFields(array $input): array
    {
        foreach (['project_code', 'milestone_code', 'assignee_code', 'start_date', 'due_date', 'estimated_hours', 'tags', 'recurrence', 'recurrence_until'] as $field) {
            if (array_key_exists($field, $input) && trim((string) ($input[$field] ?? '')) === '') {
                $input[$field] = null;
            }
        }

        return $input;
    }

    private function visibleTasks(Request $request, bool $onlyTrashed = false): Builder
    {
        $query = $onlyTrashed ? Task::onlyTrashed() : Task::query();
        if (AccessService::isAdmin($request->user())) {
            return $query;
        }

        $projectCodes = AccessService::scopeProjects(Project::query(), $request->user())->pluck('project_code');
        $memberCode = AccessService::userCode($request->user());

        return $query->where(function (Builder $visible) use ($projectCodes, $memberCode) {
            $visible->whereIn('task_project_code', $projectCodes);
            if ($memberCode) {
                $visible->orWhere(fn (Builder $standalone) => $standalone
                    ->whereNull('task_project_code')
                    ->where('task_assignee_code', $memberCode));
            }
        });
    }

    private function taskRelations(): array
    {
        return [
            'assignee:user_code,user_name,user_avatar,user_weekly_capacity_hours,user_color,user_job_title',
            'attachments', 'checklists', 'workLogs.reporter', 'dependencies', 'blocking',
            'milestone', 'watchers:user_code,user_name,user_avatar',
        ];
    }

    private function createRecurringTaskIfNeeded(Task $task): void
    {
        if ($task->task_status !== 'done' || ! $task->task_recurrence || ! $task->task_due_date) {
            return;
        }

        $nextDue = match ($task->task_recurrence) {
            'daily' => Carbon::parse($task->task_due_date)->addDay(),
            'weekly' => Carbon::parse($task->task_due_date)->addWeek(),
            'monthly' => Carbon::parse($task->task_due_date)->addMonthNoOverflow(),
        };
        if ($task->task_recurrence_until && $nextDue->gt($task->task_recurrence_until)) {
            return;
        }
        if (Task::where('task_title', $task->task_title)->whereDate('task_due_date', $nextDue)->where('task_project_code', $task->task_project_code)->exists()) {
            return;
        }

        $next = $task->replicate(['task_code', 'task_created_at', 'task_updated_at', 'task_deleted_at', 'task_completed_at']);
        $next->task_status = 'todo';
        $next->task_progress = 0;
        $next->task_due_date = $nextDue->toDateString();
        $next->task_completed_at = null;
        $next->save();
    }

    private function notifyTaskWatchers(Task $task, string $actorCode, string $title, string $message): void
    {
        $task->watchers()->where('users.user_code', '!=', $actorCode)->get()
            ->each(fn (User $user) => ActivityService::notify($user->user_code, $title, $message, 'info'));
    }

    private function trashItem(Task $task): array
    {
        $restoreUntil = $task->task_deleted_at->copy()->addDays(30);

        return array_merge($task->toArray(), [
            'restore_until' => $restoreUntil->toISOString(),
            'can_restore' => now()->lessThanOrEqualTo($restoreUntil),
        ]);
    }
}
