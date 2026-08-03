<?php

namespace App\Http\Controllers;

use App\Models\DeadlineExtension;
use App\Models\Project;
use App\Models\Task;
use App\Services\AccessService;
use App\Services\ActivityService;
use App\Services\ProjectProgressService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProjectController extends Controller
{
    // Lấy danh sách dự án
    public function index(Request $request)
    {
        $projects = AccessService::scopeProjects(Project::query(), $request->user())
            ->withCount('tasks')
            ->with([
                'customer',
                'manager:user_code,user_name,user_avatar,user_color,user_job_title,user_department',
                'members' => function ($q) {
                    $q->select('users.user_code', 'users.user_name', 'users.user_avatar', 'users.user_color', 'users.user_job_title', 'users.user_department');
                },
                'attachments',
                'deadlineExtensions.actor:user_code,user_name,user_avatar',
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
            ->map(fn (Project $project) => $this->trashItem(
                $project,
                AccessService::canManageProject($request->user(), $project)
            ));

        return response()->json($projects);
    }

    // Lấy chi tiết 1 dự án
    public function show(Request $request, $code)
    {
        $project = Project::with([
            'customer',
            'manager:user_code,user_name,user_avatar,user_color,user_job_title,user_department',
            'tasks.assignee:user_code,user_name,user_avatar,user_color,user_job_title,user_department',
            'tasks.checklists',
            'tasks.workLogs.reporter:user_code,user_name,user_avatar',
            'members' => function ($query) {
                $query->select('users.user_code', 'users.user_name', 'users.user_avatar', 'users.user_color', 'users.user_job_title', 'users.user_department');
            },
            'attachments',
            'updates.author:user_code,user_name,user_avatar',
            'milestones.tasks',
            'automations',
            'deadlineExtensions.actor:user_code,user_name,user_avatar',
        ])->findOrFail($code);
        AccessService::authorize(AccessService::canViewProject($request->user(), $project));

        return response()->json($project);
    }

    // Tạo dự án mới
    public function store(Request $request)
    {
        AccessService::authorize(AccessService::canCreateProjects($request->user()), 'Chỉ quản trị viên hoặc quản lý dự án mới được tạo dự án.');
        $this->normalizeOptionalDates($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'customer_code' => 'nullable|string|exists:customers,customer_code',
            'manager_code' => [
                'nullable',
                'string',
                Rule::exists('users', 'user_code')->where(fn ($query) => $query->whereIn('user_role', ['admin', 'project_manager'])),
            ],
            'color' => 'sometimes|string|in:indigo,emerald,amber,rose,sky,violet,orange,purple,green,pink,blue',
            'status' => 'nullable|string|in:planning,active,on_hold,completed',
            'health' => 'nullable|string|in:on_track,at_risk,off_track',
            'update_cadence' => 'nullable|string|in:weekly,biweekly,monthly,never',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:today|after_or_equal:start_date',
            'progress' => 'nullable|integer|min:0|max:100',
            'member_ids' => 'sometimes|array',
            'member_ids.*' => 'string|distinct|exists:users,user_code',
            'template' => 'nullable|in:blank,software,marketing,operations',
        ]);
        $validated = $this->discardFieldsMissingFromLegacySchema($validated);

        $memberIds = collect($validated['member_ids'] ?? [])
            ->when($validated['manager_code'] ?? null, fn ($members, $managerCode) => $members->push($managerCode))
            ->unique()
            ->values()
            ->all();
        $template = $validated['template'] ?? 'blank';
        if (($validated['status'] ?? null) === 'completed' && $template !== 'blank') {
            return response()->json([
                'message' => 'Không thể tạo dự án đã hoàn thành từ mẫu còn chứa nhiệm vụ chưa thực hiện.',
            ], 422);
        }
        if (($validated['status'] ?? null) === 'completed') {
            $validated['progress'] = 100;
        }
        unset($validated['member_ids'], $validated['template']);

        $dbData = Project::mapToDbAttributes($validated);
        $dbData['project_created_by'] = $request->user()->user_code;
        if (($validated['status'] ?? null) === 'completed') {
            $dbData['project_completed_at'] = now();
        }

        $project = Project::create($dbData);
        $project->members()->sync($memberIds);
        $this->createTemplateTasks($project, $template);
        if ($template !== 'blank') {
            ProjectProgressService::sync($project->project_code);
            $project->refresh();
        }

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
                'task_created_by' => $project->project_created_by,
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
            'manager_code' => [
                'nullable',
                'string',
                Rule::exists('users', 'user_code')->where(fn ($query) => $query->whereIn('user_role', ['admin', 'project_manager'])),
            ],
            'color' => 'sometimes|string|in:indigo,emerald,amber,rose,sky,violet,orange,purple,green,pink,blue',
            'status' => 'nullable|string|in:planning,active,on_hold,completed',
            'health' => 'nullable|string|in:on_track,at_risk,off_track',
            'update_cadence' => 'nullable|string|in:weekly,biweekly,monthly,never',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:start_date',
            'progress' => 'nullable|integer|min:0|max:100',
            'delay_reason' => 'nullable|string|max:5000',
            'recovery_plan' => 'nullable|string|max:10000',
            'extension_reason' => 'nullable|string|max:2000',
        ]);
        $validated = $this->discardFieldsMissingFromLegacySchema($validated);
        $extensionReason = trim((string) ($validated['extension_reason'] ?? ''));
        unset($validated['extension_reason']);
        $oldDueDate = $project->project_due_date?->toDateString();
        $newDueDate = array_key_exists('due_date', $validated) && $validated['due_date']
            ? Carbon::parse($validated['due_date'])->toDateString()
            : null;
        $isExtension = $oldDueDate && $newDueDate && $newDueDate > $oldDueDate;

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
        if ($isExtension && $project->deadline_state === 'overdue') {
            $delayReason = trim((string) ($validated['delay_reason'] ?? $project->project_delay_reason));
            $recoveryPlan = trim((string) ($validated['recovery_plan'] ?? $project->project_recovery_plan));
            if ($delayReason === '' || $recoveryPlan === '') {
                throw ValidationException::withMessages([
                    'delay_reason' => ['Dự án quá hạn phải có lý do chậm.'],
                    'recovery_plan' => ['Dự án quá hạn phải có kế hoạch khắc phục trước khi gia hạn.'],
                ]);
            }
        }

        if (
            ($validated['status'] ?? null) === 'completed'
            && $project->tasks()->where('task_status', '!=', 'done')->exists()
        ) {
            return response()->json([
                'message' => 'Không thể hoàn thành dự án khi vẫn còn nhiệm vụ chưa hoàn thành.',
            ], 409);
        }
        if (($validated['status'] ?? null) === 'completed') {
            $validated['progress'] = 100;
        }

        DB::transaction(function () use ($project, $validated, $isExtension, $oldDueDate, $newDueDate, $extensionReason, $request) {
            $projectData = Project::mapToDbAttributes($validated);
            if (array_key_exists('status', $validated)) {
                $projectData['project_completed_at'] = $validated['status'] === 'completed'
                    ? ($project->project_completed_at ?: now())
                    : null;
            }
            $project->update($projectData);

            if ($isExtension) {
                DeadlineExtension::create([
                    'extension_target_type' => 'Project',
                    'extension_target_code' => $project->project_code,
                    'extension_old_due_date' => $oldDueDate,
                    'extension_new_due_date' => $newDueDate,
                    'extension_reason' => $extensionReason,
                    'extension_created_by' => $request->user()->user_code,
                ]);
            }
        });
        if (isset($validated['status']) && $validated['status'] !== 'completed') {
            ProjectProgressService::sync($project->project_code);
            $project->refresh();
        }
        if (! empty($validated['manager_code'])) {
            $project->members()->syncWithoutDetaching([$validated['manager_code']]);
        }
        $project->load('customer', 'manager', 'members', 'attachments', 'deadlineExtensions.actor');

        $userCode = $request->user()->user_code;
        ActivityService::log(
            $userCode,
            'cập nhật dự án',
            'Project',
            $project->project_code,
            "Đã cập nhật thông tin dự án: {$project->project_name}"
        );
        if ($isExtension) {
            ActivityService::log(
                $userCode,
                'gia hạn dự án',
                'Project',
                $project->project_code,
                "Đã đổi hạn từ {$oldDueDate} sang {$newDueDate}. Lý do: {$extensionReason}"
            );
        }

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
        AccessService::authorize(AccessService::canManageProject($request->user(), $project));
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
            'member_ids.*' => 'string|distinct|exists:users,user_code',
        ]);

        $memberIds = collect($validated['member_ids'])
            ->when($project->project_manager_code, fn ($members, $managerCode) => $members->push($managerCode))
            ->unique()
            ->values()
            ->all();
        $project->members()->sync($memberIds);
        $project->load('members');

        ActivityService::log(
            $request->user()->user_code,
            'cập nhật thành viên dự án',
            'Project',
            $project->project_code,
            'Danh sách thành viên hiện có '.count($memberIds).' người.'
        );

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

    private function trashItem(Project $project, bool $canRestoreByUser): array
    {
        $restoreUntil = $project->project_deleted_at->copy()->addDays(30);

        return array_merge($project->toArray(), [
            'restore_until' => $restoreUntil->toISOString(),
            'can_restore' => now()->lessThanOrEqualTo($restoreUntil),
            'can_restore_by_user' => $canRestoreByUser,
        ]);
    }
}
