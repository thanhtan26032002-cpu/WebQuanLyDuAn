<?php

namespace App\Http\Controllers;

use App\Models\DeadlineExtension;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
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
        $this->notifyProjectAssignments($project, $memberIds, $request->user()->user_code);

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
        $previousManagerCode = $project->project_manager_code;
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
        $updateDetail = $this->describeProjectChanges($project, $validated);
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
            $managerWasMember = $project->members()
                ->where('users.user_code', $validated['manager_code'])
                ->exists();
            $project->members()->syncWithoutDetaching([$validated['manager_code']]);
            if (! $managerWasMember) {
                ActivityService::log(
                    $request->user()->user_code,
                    'thêm thành viên vào dự án',
                    'Project',
                    $project->project_code,
                    'Tự động thêm '.(User::whereKey($validated['manager_code'])->value('user_name') ?: $validated['manager_code']).' vào dự án với vai trò quản lý dự án.'
                );
            }
        }
        $project->load('customer', 'manager', 'members', 'attachments', 'deadlineExtensions.actor');

        $userCode = $request->user()->user_code;
        ActivityService::log(
            $userCode,
            'cập nhật dự án',
            'Project',
            $project->project_code,
            $updateDetail
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
        if (($validated['manager_code'] ?? null)
            && $validated['manager_code'] !== $previousManagerCode) {
            $this->notifyProjectAssignments(
                $project,
                [$validated['manager_code']],
                $request->user()->user_code
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
        $previousMemberIds = $project->members()->pluck('users.user_code')->all();
        $addedMemberIds = array_values(array_diff($memberIds, $previousMemberIds));
        $removedMemberIds = array_values(array_diff($previousMemberIds, $memberIds));
        $project->members()->sync($memberIds);
        $project->load('members');

        $memberNames = User::whereIn('user_code', array_values(array_unique(array_merge($addedMemberIds, $removedMemberIds))))
            ->pluck('user_name', 'user_code');
        $addedNames = collect($addedMemberIds)->map(fn ($id) => $memberNames[$id] ?? $id)->implode(', ');
        $removedNames = collect($removedMemberIds)->map(fn ($id) => $memberNames[$id] ?? $id)->implode(', ');
        $memberChanges = collect([
            $addedNames !== '' ? 'Thêm: '.$addedNames : null,
            $removedNames !== '' ? 'Loại khỏi dự án: '.$removedNames : null,
        ])->filter()->implode('. ');

        ActivityService::log(
            $request->user()->user_code,
            $addedMemberIds && ! $removedMemberIds
                ? 'thêm thành viên vào dự án'
                : (! $addedMemberIds && $removedMemberIds ? 'xóa thành viên khỏi dự án' : 'cập nhật thành viên dự án'),
            'Project',
            $project->project_code,
            ($memberChanges !== '' ? $memberChanges.'. ' : 'Danh sách thành viên không thay đổi. ')
                .'Hiện có '.count($memberIds).' thành viên trong dự án.'
        );
        $this->notifyProjectAssignments($project, $addedMemberIds, $request->user()->user_code);

        return response()->json([
            'message' => 'Đã cập nhật thành viên dự án',
            'project' => $project,
        ]);
    }

    private function notifyProjectAssignments(Project $project, array $userCodes, string $actorCode): void
    {
        User::whereIn('user_code', array_values(array_unique($userCodes)))
            ->get()
            ->each(function (User $user) use ($project, $actorCode) {
                if ($user->user_code === $actorCode
                    || (($user->user_notification_preferences['assignment'] ?? true) === false)) {
                    return;
                }

                $isManager = $user->user_code === $project->project_manager_code;
                ActivityService::notify(
                    $user->user_code,
                    $isManager ? 'Bạn được giao phụ trách dự án' : 'Bạn được thêm vào dự án',
                    $isManager
                        ? 'Bạn đã được phân công phụ trách dự án: '.$project->project_name
                        : 'Bạn đã được thêm vào dự án: '.$project->project_name,
                    'info',
                    'Project',
                    $project->project_code
                );
            });
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

    private function describeProjectChanges(Project $project, array $validated): string
    {
        $labels = [
            'name' => 'Tên dự án',
            'description' => 'Mô tả',
            'customer_code' => 'Khách hàng',
            'manager_code' => 'Quản lý dự án',
            'color' => 'Màu nhận diện',
            'status' => 'Trạng thái',
            'health' => 'Sức khỏe dự án',
            'update_cadence' => 'Chu kỳ cập nhật',
            'start_date' => 'Ngày bắt đầu',
            'due_date' => 'Hạn hoàn thành',
            'progress' => 'Tiến độ',
            'delay_reason' => 'Lý do chậm',
            'recovery_plan' => 'Kế hoạch khắc phục',
        ];
        $dbData = Project::mapToDbAttributes($validated);
        $changes = [];

        foreach ($validated as $field => $newValue) {
            if (! isset($labels[$field])) {
                continue;
            }
            $dbField = array_key_first(Project::mapToDbAttributes([$field => $newValue]));
            $oldValue = $project->getAttribute($dbField);
            if ($this->normalizeAuditValue($oldValue) === $this->normalizeAuditValue($dbData[$dbField] ?? null)) {
                continue;
            }
            if (in_array($field, ['description', 'delay_reason', 'recovery_plan'], true)) {
                $changes[] = $labels[$field];

                continue;
            }
            $changes[] = $labels[$field].': '.$this->displayAuditValue($field, $oldValue).' → '.$this->displayAuditValue($field, $newValue);
        }

        return $changes
            ? 'Đã thay đổi '.implode('; ', $changes).'.'
            : 'Đã lưu thông tin dự án nhưng không có dữ liệu nào thay đổi.';
    }

    private function normalizeAuditValue(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return trim((string) ($value ?? ''));
    }

    private function displayAuditValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Chưa đặt';
        }
        if ($value instanceof \DateTimeInterface) {
            $value = $value->format('Y-m-d');
        }

        $mapped = match ($field) {
            'status' => ['planning' => 'Lập kế hoạch', 'active' => 'Đang triển khai', 'on_hold' => 'Tạm dừng', 'completed' => 'Hoàn thành'][$value] ?? $value,
            'health' => ['on_track' => 'Đúng hướng', 'at_risk' => 'Có rủi ro', 'off_track' => 'Chệch hướng'][$value] ?? $value,
            'update_cadence' => ['weekly' => 'Hằng tuần', 'biweekly' => 'Hai tuần', 'monthly' => 'Hằng tháng', 'never' => 'Không nhắc'][$value] ?? $value,
            'progress' => $value.'%',
            'manager_code' => User::whereKey($value)->value('user_name') ?: $value,
            default => $value,
        };

        return mb_strimwidth((string) $mapped, 0, 120, '…');
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
