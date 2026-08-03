<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\DeadlineExtension;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TaskWatcher;
use App\Models\User;
use App\Services\AccessService;
use App\Services\ActivityService;
use App\Services\AutomationService;
use App\Services\ProjectProgressService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
            'deadlineExtensions.actor:user_code,user_name,user_avatar',
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
            ->map(fn (Task $task) => $this->trashItem(
                $task,
                AccessService::canManageTask($request->user(), $task)
            ));

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

        // Nhân viên được chủ động tạo việc, nhưng không được giao việc cho người khác.
        // Mọi nhiệm vụ do nhân viên tạo đều gắn trách nhiệm trực tiếp với chính họ.
        if (AccessService::role($request->user()) === 'member') {
            if (($validated['assignee_code'] ?? null)
                && $validated['assignee_code'] !== $request->user()->user_code) {
                throw ValidationException::withMessages([
                    'assignee_code' => 'Nhân viên chỉ có thể tạo nhiệm vụ do chính mình phụ trách.',
                ]);
            }
            $validated['assignee_code'] = $request->user()->user_code;
        }

        if ($validated['project_code'] ?? null) {
            $project = Project::findOrFail($validated['project_code']);
            AccessService::authorize(AccessService::canCreateTaskInProject($request->user(), $project), 'Bạn chỉ có thể thêm nhiệm vụ vào dự án mình được tham gia.');
            if ($project->project_status === 'completed') {
                return response()->json(['message' => 'Hãy mở lại dự án trước khi tạo nhiệm vụ mới.'], 409);
            }
            if (($validated['milestone_code'] ?? null) && ! $project->milestones()->whereKey($validated['milestone_code'])->exists()) {
                return response()->json(['message' => 'Cột mốc không thuộc dự án đã chọn.'], 422);
            }
        } else {
            AccessService::authorize(AccessService::canCreateTasks($request->user()), 'Bạn không có quyền tạo nhiệm vụ.');
        }

        $taskData = Task::mapToDbAttributes($validated);
        $taskData['task_created_by'] = $request->user()->user_code;
        $task = Task::create($taskData);
        $task->unsetRelation('project');
        $task->load('project');
        $addedAssigneeToProject = false;
        if ($task->task_project_code && $task->task_assignee_code) {
            $addedAssigneeToProject = ! $task->project?->members()
                ->where('users.user_code', $task->task_assignee_code)
                ->exists();
            $task->project?->members()->syncWithoutDetaching([$task->task_assignee_code]);
        }
        ProjectProgressService::sync($task->task_project_code);
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
        if ($addedAssigneeToProject) {
            ActivityService::log(
                $userCode,
                'thêm thành viên vào dự án',
                'Project',
                $task->task_project_code,
                'Tự động thêm '.($task->assignee?->user_name ?? $task->task_assignee_code).' vào dự án vì được giao nhiệm vụ '.$task->task_title.'.'
            );
        }

        if ($task->task_assignee_code) {
            $assignedUser = User::find($task->task_assignee_code);
            if ($assignedUser && (($assignedUser->user_notification_preferences['assignment'] ?? true) !== false)) {
                ActivityService::notify(
                    $task->task_assignee_code,
                    'Nhiệm vụ mới',
                    'Bạn đã được phân công nhiệm vụ: '.$task->task_title,
                    'info',
                    'Task',
                    $task->task_code
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
        AccessService::authorize(
            AccessService::canManageTask($request->user(), $task),
            'Chỉ quản trị viên hoặc người quản lý dự án mới được sửa thông tin giao việc.'
        );

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
            'delay_reason' => 'nullable|string|max:5000',
            'recovery_plan' => 'nullable|string|max:10000',
            'extension_reason' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();
        $extensionReason = trim((string) ($validated['extension_reason'] ?? ''));
        unset($validated['extension_reason']);
        $updateDetail = $this->describeTaskChanges($task, $validated);
        $oldDueDate = $task->task_due_date?->toDateString();
        $newDueDate = array_key_exists('due_date', $validated) && $validated['due_date']
            ? Carbon::parse($validated['due_date'])->toDateString()
            : null;
        $isExtension = $oldDueDate && $newDueDate && $newDueDate > $oldDueDate;

        if ($isExtension) {
            AccessService::authorize(
                AccessService::canCreateProjects($request->user()),
                'Chỉ quản trị viên hoặc quản lý dự án mới được gia hạn nhiệm vụ.'
            );
        }
        if ($isExtension && $extensionReason === '') {
            throw ValidationException::withMessages([
                'extension_reason' => ['Vui lòng nhập lý do gia hạn để lưu vào lịch sử.'],
            ]);
        }
        if ($isExtension && $newDueDate < now()->toDateString()) {
            throw ValidationException::withMessages([
                'due_date' => ['Hạn chót sau gia hạn không được nằm trong quá khứ.'],
            ]);
        }
        if ($isExtension && $task->deadline_state === 'overdue') {
            $delayReason = trim((string) ($validated['delay_reason'] ?? $task->task_delay_reason));
            $recoveryPlan = trim((string) ($validated['recovery_plan'] ?? $task->task_recovery_plan));
            if ($delayReason === '' || $recoveryPlan === '') {
                throw ValidationException::withMessages([
                    'delay_reason' => ['Nhiệm vụ quá hạn phải có lý do chậm.'],
                    'recovery_plan' => ['Nhiệm vụ quá hạn phải có kế hoạch khắc phục trước khi gia hạn.'],
                ]);
            }
        }

        $previousStatus = $task->task_status;
        $previousAssigneeCode = $task->task_assignee_code;
        $previousProjectCode = $task->task_project_code;

        if (array_key_exists('project_code', $validated) && $validated['project_code'] !== $task->task_project_code) {
            if ($validated['project_code']) {
                $targetProject = Project::findOrFail($validated['project_code']);
                AccessService::authorize(AccessService::canManageProject($request->user(), $targetProject));
                $targetStatus = $validated['status'] ?? $task->task_status;
                if ($targetProject->project_status === 'completed' && $targetStatus !== 'done') {
                    return response()->json(['message' => 'Không thể chuyển nhiệm vụ chưa hoàn thành vào dự án đã đóng.'], 409);
                }
            } else {
                $validated['created_by'] = $request->user()->user_code;
            }
        }

        $targetProjectCode = $validated['project_code'] ?? $task->task_project_code;
        if (($validated['milestone_code'] ?? null) && ! Project::whereKey($targetProjectCode)
            ->whereHas('milestones', fn (Builder $query) => $query->whereKey($validated['milestone_code']))
            ->exists()) {
            return response()->json(['message' => 'Cột mốc không thuộc dự án đã chọn.'], 422);
        }

        if (array_key_exists('blocked_override', $validated) && $validated['blocked_override']) {
            AccessService::authorize(AccessService::canManageTask($request->user(), $task), 'Chỉ người quản lý nhiệm vụ mới được bỏ qua trạng thái bị chặn.');
        }
        if (isset($validated['status']) && in_array($validated['status'], ['in_progress', 'done'], true) && $task->is_blocked) {
            $canOverride = ($validated['blocked_override'] ?? false) && AccessService::canManageTask($request->user(), $task);
            if (! $canOverride) {
                return response()->json(['message' => 'Nhiệm vụ đang bị chặn bởi công việc chưa hoàn thành.'], 409);
            }
        }
        if (($validated['status'] ?? null) === 'done' && $task->checklists()->where('checklist_is_completed', false)->exists()) {
            return response()->json(['message' => 'Hãy hoàn thành toàn bộ công việc con trước khi đóng nhiệm vụ.'], 409);
        }
        if (($validated['status'] ?? null) !== null && $validated['status'] !== 'done' && $task->project?->project_status === 'completed') {
            return response()->json(['message' => 'Hãy mở lại dự án trước khi mở lại nhiệm vụ.'], 409);
        }
        DB::transaction(function () use ($task, $validated, $isExtension, $oldDueDate, $newDueDate, $extensionReason, $request) {
            $task->update(Task::mapToDbAttributes($validated));
            if ($isExtension) {
                DeadlineExtension::create([
                    'extension_target_type' => 'Task',
                    'extension_target_code' => $task->task_code,
                    'extension_old_due_date' => $oldDueDate,
                    'extension_new_due_date' => $newDueDate,
                    'extension_reason' => $extensionReason,
                    'extension_created_by' => $request->user()->user_code,
                ]);
            }
        });
        $task->unsetRelation('project');
        $task->load('project');
        if (array_key_exists('status', $validated)) {
            $task->task_completed_at = $validated['status'] === 'done' ? ($task->task_completed_at ?: now()) : null;
            if ($validated['status'] === 'done') {
                $task->task_progress = 100;
            }
            $task->save();
            if ($previousStatus !== 'done' && $validated['status'] === 'done') {
                $this->createRecurringTaskIfNeeded($task);
            }
            AutomationService::taskStatusChanged($task->loadMissing(['project.automations', 'project.manager']), $previousStatus);
        }
        $addedAssigneeToProject = false;
        if ($task->task_project_code && $task->task_assignee_code) {
            $addedAssigneeToProject = ! $task->project?->members()
                ->where('users.user_code', $task->task_assignee_code)
                ->exists();
            $task->project?->members()->syncWithoutDetaching([$task->task_assignee_code]);
        }
        if ($task->task_assignee_code && $task->task_assignee_code !== $previousAssigneeCode) {
            $assignedUser = User::find($task->task_assignee_code);
            if ($assignedUser && (($assignedUser->user_notification_preferences['assignment'] ?? true) !== false)) {
                ActivityService::notify(
                    $assignedUser->user_code,
                    'Nhiệm vụ mới được phân công',
                    'Bạn đã được phân công nhiệm vụ: '.$task->task_title,
                    'info',
                    'Task',
                    $task->task_code
                );
            }
        }
        ProjectProgressService::sync($previousProjectCode);
        if ($task->task_project_code !== $previousProjectCode) {
            ProjectProgressService::sync($task->task_project_code);
        }
        $task->load($this->taskRelations());

        $userCode = $request->user()->user_code;
        ActivityService::log(
            $userCode,
            'cập nhật nhiệm vụ',
            'Task',
            $task->task_code,
            $updateDetail
        );
        if ($addedAssigneeToProject) {
            ActivityService::log(
                $userCode,
                'thêm thành viên vào dự án',
                'Project',
                $task->task_project_code,
                'Tự động thêm '.($task->assignee?->user_name ?? $task->task_assignee_code).' vào dự án vì được giao nhiệm vụ '.$task->task_title.'.'
            );
        }
        if ($isExtension) {
            ActivityService::log(
                $userCode,
                'gia hạn nhiệm vụ',
                'Task',
                $task->task_code,
                "Đã đổi hạn từ {$oldDueDate} sang {$newDueDate}. Lý do: {$extensionReason}"
            );
        }
        if ($previousProjectCode && $previousProjectCode !== $task->task_project_code) {
            ActivityService::log(
                $userCode,
                'chuyển nhiệm vụ khỏi dự án',
                'Task',
                $task->task_code,
                'Nhiệm vụ đã được chuyển sang dự án khác hoặc chuyển thành nhiệm vụ độc lập.',
                $previousProjectCode
            );
        }

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
        AccessService::authorize(
            AccessService::canContributeToTask($request->user(), $task),
            'Bạn không phải người thực hiện hoặc người quản lý nhiệm vụ này.'
        );
        $previousStatus = $task->task_status;
        if ($validated['status'] !== 'done' && $task->project?->project_status === 'completed') {
            return response()->json(['message' => 'Hãy mở lại dự án trước khi mở lại nhiệm vụ.'], 409);
        }

        if ($task->is_blocked && in_array($validated['status'], ['in_progress', 'done'], true)) {
            $canOverride = ($validated['override_blocked'] ?? false) && AccessService::canManageTask($request->user(), $task);
            if (! $canOverride) {
                return response()->json(['message' => 'Nhiệm vụ đang bị chặn bởi công việc chưa hoàn thành.'], 409);
            }
            $task->task_blocked_override = true;
        }
        if ($validated['status'] === 'done' && $task->checklists()->where('checklist_is_completed', false)->exists()) {
            return response()->json(['message' => 'Hãy hoàn thành toàn bộ công việc con trước khi đóng nhiệm vụ.'], 409);
        }

        $task->task_status = $validated['status'];
        $task->task_completed_at = $validated['status'] === 'done' ? now() : null;
        if ($validated['status'] === 'done') {
            $task->task_progress = 100;
        }
        $task->save();
        if ($previousStatus !== 'done' && $validated['status'] === 'done') {
            $this->createRecurringTaskIfNeeded($task);
        }
        ProjectProgressService::sync($task->task_project_code);
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
        AccessService::authorize(AccessService::canManageTask($request->user(), $task));
        $taskTitle = $task->task_title;
        $taskCode = $task->task_code;

        $task->delete();
        ProjectProgressService::sync($task->task_project_code);

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
        $project = $task->task_project_code ? Project::withTrashed()->find($task->task_project_code) : null;
        AccessService::authorize(
            AccessService::isAdmin($request->user())
            || ($project && AccessService::canManageProject($request->user(), $project))
            || (! $task->task_project_code && $task->task_created_by === $request->user()->user_code)
        );
        $restoreUntil = $task->task_deleted_at->copy()->addDays(30);

        if (now()->greaterThan($restoreUntil)) {
            return response()->json([
                'message' => 'Nhiệm vụ đã bị xóa quá 30 ngày và không thể khôi phục.',
            ], 410);
        }

        if ($task->task_project_code) {
            if ($project?->trashed()) {
                return response()->json([
                    'message' => 'Hãy khôi phục dự án chứa nhiệm vụ này trước.',
                ], 409);
            }
            if ($project?->project_status === 'completed' && $task->task_status !== 'done') {
                return response()->json([
                    'message' => 'Hãy mở lại dự án trước khi khôi phục nhiệm vụ chưa hoàn thành.',
                ], 409);
            }
        }

        $task->restore();
        ProjectProgressService::sync($task->task_project_code);

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
        $attachmentCodes = Attachment::where('attachment_target_type', 'TaskComment')
            ->where('attachment_target_code', $taskCode)
            ->pluck('attachment_code', 'attachment_file_path');
        $comments->each(function (TaskComment $comment) use ($attachmentCodes) {
            $comment->setAttribute('attachment_code', $attachmentCodes[$comment->comment_file_url] ?? null);
        });

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
            'attachment_code' => 'nullable|string|exists:attachments,attachment_code',
        ]);

        $commentAttachment = null;
        if (! empty($validated['attachment_code'])) {
            $commentAttachment = Attachment::whereKey($validated['attachment_code'])
                ->where('attachment_target_type', 'TaskComment')
                ->where('attachment_target_code', $taskCode)
                ->where('attachment_uploaded_by', $request->user()->user_code)
                ->firstOrFail();
        }

        // Đảm bảo ít nhất có text hoặc file
        $text = $validated['text'] ?? '';
        $fileUrl = $commentAttachment?->attachment_file_path ?? ($validated['file_url'] ?? null);
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
            'comment_file_name' => $commentAttachment?->attachment_file_name ?? ($validated['file_name'] ?? null),
        ]);

        $comment->load('user:user_code,user_name,user_avatar');
        $comment->setAttribute('attachment_code', $commentAttachment?->attachment_code);

        ActivityService::log(
            $userCode,
            'bình luận',
            'Task',
            $taskCode,
            'Đã thêm bình luận vào nhiệm vụ'.($commentAttachment ? ' kèm tệp '.$commentAttachment->attachment_file_name : '')
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
                $visible->orWhere('task_assignee_code', $memberCode)
                    ->orWhere(function (Builder $created) use ($memberCode) {
                        $created->whereNull('task_project_code')->where('task_created_by', $memberCode);
                    });
            }
        });
    }

    private function describeTaskChanges(Task $task, array $validated): string
    {
        $labels = [
            'title' => 'Tên nhiệm vụ',
            'description' => 'Mô tả',
            'type' => 'Loại nhiệm vụ',
            'status' => 'Trạng thái',
            'priority' => 'Độ ưu tiên',
            'start_date' => 'Ngày bắt đầu',
            'due_date' => 'Hạn hoàn thành',
            'progress' => 'Tiến độ',
            'estimated_hours' => 'Thời gian ước tính',
            'assignee_code' => 'Người phụ trách',
            'project_code' => 'Dự án',
            'milestone_code' => 'Cột mốc',
            'tags' => 'Nhãn',
            'blocked_reason' => 'Lý do bị chặn',
            'recurrence' => 'Lặp lại',
            'recurrence_until' => 'Ngày kết thúc lặp',
            'delay_reason' => 'Lý do chậm',
            'recovery_plan' => 'Kế hoạch khắc phục',
        ];
        $dbData = Task::mapToDbAttributes($validated);
        $changes = [];

        foreach ($validated as $field => $newValue) {
            if (! isset($labels[$field])) {
                continue;
            }
            $dbField = array_key_first(Task::mapToDbAttributes([$field => $newValue]));
            $oldValue = $task->getAttribute($dbField);
            if ($this->normalizeAuditValue($oldValue) === $this->normalizeAuditValue($dbData[$dbField] ?? null)) {
                continue;
            }
            if (in_array($field, ['description', 'blocked_reason', 'delay_reason', 'recovery_plan'], true)) {
                $changes[] = $labels[$field];

                continue;
            }
            $changes[] = $labels[$field].': '.$this->displayTaskAuditValue($field, $oldValue).' → '.$this->displayTaskAuditValue($field, $newValue);
        }

        return $changes
            ? 'Đã thay đổi '.implode('; ', $changes).'.'
            : 'Đã lưu nhiệm vụ nhưng không có dữ liệu nào thay đổi.';
    }

    private function normalizeAuditValue(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return trim((string) ($value ?? ''));
    }

    private function displayTaskAuditValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Chưa đặt';
        }
        if ($value instanceof \DateTimeInterface) {
            $value = $value->format('Y-m-d');
        }

        $mapped = match ($field) {
            'status' => ['todo' => 'Cần làm', 'in_progress' => 'Đang làm', 'done' => 'Hoàn thành'][$value] ?? $value,
            'priority' => ['low' => 'Thấp', 'medium' => 'Trung bình', 'high' => 'Cao'][$value] ?? $value,
            'progress' => $value.'%',
            'estimated_hours' => $value.' giờ',
            'assignee_code' => User::whereKey($value)->value('user_name') ?: $value,
            'project_code' => Project::whereKey($value)->value('project_name') ?: $value,
            'recurrence' => ['daily' => 'Hằng ngày', 'weekly' => 'Hằng tuần', 'monthly' => 'Hằng tháng'][$value] ?? $value,
            default => $value,
        };

        return mb_strimwidth((string) $mapped, 0, 120, '…');
    }

    private function taskRelations(): array
    {
        return [
            'assignee:user_code,user_name,user_avatar,user_weekly_capacity_hours,user_color,user_job_title',
            'attachments', 'checklists', 'workLogs.reporter', 'dependencies', 'blocking',
            'milestone', 'watchers:user_code,user_name,user_avatar',
            'deadlineExtensions.actor:user_code,user_name,user_avatar',
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
            ->filter(fn (User $user) => ($user->user_notification_preferences['comments'] ?? true) !== false)
            ->each(fn (User $user) => ActivityService::notify(
                $user->user_code,
                $title,
                $message,
                'info',
                'Task',
                $task->task_code
            ));
    }

    private function trashItem(Task $task, bool $canRestoreByUser): array
    {
        $restoreUntil = $task->task_deleted_at->copy()->addDays(30);

        return array_merge($task->toArray(), [
            'restore_until' => $restoreUntil->toISOString(),
            'can_restore' => now()->lessThanOrEqualTo($restoreUntil),
            'can_restore_by_user' => $canRestoreByUser,
        ]);
    }
}
