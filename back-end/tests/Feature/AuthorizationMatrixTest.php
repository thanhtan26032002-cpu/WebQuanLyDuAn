<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use App\Services\AutomationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthorizationMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_incomplete_profiles_can_only_use_account_endpoints(): void
    {
        $account = $this->postJson('/api/register', [
            'name' => 'Nhân viên mới',
            'email' => 'onboarding@example.com',
            'password' => 'secure-password',
        ])->assertCreated()->json();

        $this->withToken($account['token'])->getJson('/api/user')->assertOk();
        $this->withToken($account['token'])->getJson('/api/projects')
            ->assertStatus(428)
            ->assertJsonPath('requires_profile_completion', true);

        $this->withToken($account['token'])->postJson('/api/profile/complete', [
            'phone' => '0901234567',
            'department' => 'Kỹ thuật',
            'job_title' => 'Nhân viên',
        ])->assertOk();

        $this->withToken($account['token'])->getJson('/api/projects')->assertOk();
    }

    public function test_company_roles_are_enforced_on_the_server_not_only_in_the_interface(): void
    {
        [$admin, $adminToken] = $this->user('admin', 'admin-matrix@example.com');
        [$manager, $managerToken] = $this->user('project_manager', 'manager-matrix@example.com');
        [$otherManager, $otherManagerToken] = $this->user('project_manager', 'other-manager@example.com');
        [$employee, $employeeToken] = $this->user('member', 'employee-matrix@example.com');

        $project = $this->withToken($managerToken)->postJson('/api/projects', [
            'name' => 'Dự án có phân quyền',
            'manager_code' => $manager->user_code,
            'member_ids' => [$otherManager->user_code, $employee->user_code],
        ])->assertCreated()->json('project');

        $this->withToken($employeeToken)->getJson('/api/members')
            ->assertOk()
            ->assertJsonFragment([
                'code' => $manager->user_code,
                'name' => $manager->user_name,
                'role' => 'project_manager',
                'job_title' => 'Quản lý',
                'department' => 'Nội bộ',
                'join_date' => now()->toDateString(),
                'profile_limited' => true,
            ]);

        foreach ([
            ['/api/projects', ['name' => 'Không được tạo']],
            ['/api/customers', ['name' => 'Không được tạo']],
            ['/api/members', ['name' => 'Không được tạo', 'email' => 'blocked@example.com', 'phone' => '0901234567']],
            ['/api/groups', ['name' => 'Không được tạo']],
        ] as [$endpoint, $payload]) {
            $this->withToken($employeeToken)->postJson($endpoint, $payload)->assertForbidden();
        }
        $this->withToken($employeeToken)->getJson('/api/reports')->assertForbidden();

        $employeeTask = $this->withToken($employeeToken)->postJson('/api/tasks', [
            'project_code' => $project['code'],
            'title' => 'Nhân viên chủ động tạo nhiệm vụ',
        ])->assertCreated()->json('task');
        $this->assertSame($employee->user_code, $employeeTask['assignee_code']);
        $this->assertSame($employee->user_code, $employeeTask['created_by']);

        $this->withToken($employeeToken)->postJson('/api/tasks', [
            'project_code' => $project['code'],
            'title' => 'Không được giao việc cho người khác',
            'assignee_code' => $manager->user_code,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('assignee_code');

        $this->withToken($employeeToken)->postJson('/api/tasks', [
            'title' => 'Nhiệm vụ độc lập của nhân viên',
        ])->assertCreated()
            ->assertJsonPath('task.assignee_code', $employee->user_code);

        $this->withToken($managerToken)->postJson('/api/groups', ['name' => 'Nhóm vượt quyền'])->assertForbidden();
        $this->withToken($managerToken)->putJson('/api/members/'.$employee->user_code, [
            'name' => 'Không được sửa người khác',
        ])->assertForbidden();

        $this->withToken($otherManagerToken)->putJson('/api/projects/'.$project['code'], [
            'name' => 'Thành viên không được quản trị cả dự án',
        ])->assertForbidden();
        $this->withToken($managerToken)->putJson('/api/projects/'.$project['code'], [
            'name' => 'Quản lý được phân công',
        ])->assertOk();

        $this->withToken($adminToken)->postJson('/api/groups', ['name' => 'Nhóm hệ thống'])
            ->assertCreated();
        $this->withToken($adminToken)->putJson('/api/members/'.$employee->user_code, [
            'system_role' => 'project_manager',
        ])->assertOk()->assertJsonPath('member.role', 'project_manager');

        $this->assertSame('admin', $admin->user_role);
    }

    public function test_company_overview_is_shared_while_operational_lists_remain_scoped(): void
    {
        [, $adminToken] = $this->user('admin', 'overview-admin@example.com');
        [$firstManager, $firstManagerToken] = $this->user('project_manager', 'overview-manager-a@example.com');
        [$secondManager, $secondManagerToken] = $this->user('project_manager', 'overview-manager-b@example.com');
        [, $employeeToken] = $this->user('member', 'overview-employee@example.com');

        $firstProject = $this->withToken($firstManagerToken)->postJson('/api/projects', [
            'name' => 'Dự án phòng Kỹ thuật',
            'manager_code' => $firstManager->user_code,
        ])->assertCreated()->json('project');
        $secondProject = $this->withToken($secondManagerToken)->postJson('/api/projects', [
            'name' => 'Dự án phòng Vận hành',
            'manager_code' => $secondManager->user_code,
        ])->assertCreated()->json('project');

        $this->withToken($firstManagerToken)->postJson('/api/tasks', [
            'project_code' => $firstProject['code'],
            'title' => 'Nhiệm vụ của phòng Kỹ thuật',
        ])->assertCreated();
        $this->withToken($secondManagerToken)->postJson('/api/tasks', [
            'project_code' => $secondProject['code'],
            'title' => 'Nhiệm vụ của phòng Vận hành',
        ])->assertCreated();

        $this->withToken($adminToken)->getJson('/api/projects')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment(['name' => 'Dự án phòng Kỹ thuật'])
            ->assertJsonFragment(['name' => 'Dự án phòng Vận hành']);
        $this->withToken($adminToken)->getJson('/api/tasks')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment(['title' => 'Nhiệm vụ của phòng Kỹ thuật'])
            ->assertJsonFragment(['title' => 'Nhiệm vụ của phòng Vận hành']);
        $this->withToken($adminToken)->getJson('/api/activities')
            ->assertOk()
            ->assertJsonFragment(['detail' => 'Đã tạo nhiệm vụ mới: Nhiệm vụ của phòng Kỹ thuật'])
            ->assertJsonFragment(['detail' => 'Đã tạo nhiệm vụ mới: Nhiệm vụ của phòng Vận hành']);
        $this->withToken($adminToken)->getJson('/api/activities?paginated=1&per_page=10')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['code', 'action', 'target_type', 'target_code', 'target_label', 'created_at'],
                ],
                'current_page',
                'last_page',
                'per_page',
                'total',
            ])
            ->assertJsonFragment(['target_label' => 'Nhiệm vụ của phòng Kỹ thuật'])
            ->assertJsonFragment(['target_label' => 'Nhiệm vụ của phòng Vận hành']);
        $this->withToken($adminToken)->getJson('/api/activities?paginated=1&type=Task&search=Vận hành')
            ->assertOk()
            ->assertJsonFragment(['target_label' => 'Nhiệm vụ của phòng Vận hành'])
            ->assertJsonMissing(['target_label' => 'Dự án phòng Kỹ thuật']);

        $this->withToken($firstManagerToken)->getJson('/api/projects')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['code' => $firstProject['code']]);
        $this->withToken($firstManagerToken)->getJson('/api/tasks')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['title' => 'Nhiệm vụ của phòng Kỹ thuật']);

        foreach ([$adminToken, $firstManagerToken, $employeeToken] as $token) {
            $this->withToken($token)->getJson('/api/company-overview')
                ->assertOk()
                ->assertJsonCount(2, 'projects')
                ->assertJsonCount(2, 'tasks')
                ->assertJsonFragment(['name' => 'Dự án phòng Kỹ thuật'])
                ->assertJsonFragment(['name' => 'Dự án phòng Vận hành'])
                ->assertJsonFragment(['title' => 'Nhiệm vụ của phòng Kỹ thuật'])
                ->assertJsonFragment(['title' => 'Nhiệm vụ của phòng Vận hành']);
        }

        $adminOverviewTasks = collect(
            $this->withToken($adminToken)->getJson('/api/company-overview')
                ->assertOk()
                ->json('tasks')
        );
        $this->assertTrue($adminOverviewTasks->every(
            fn (array $task) => $task['can_contribute'] === true
        ));

        $managerOverviewTasks = collect(
            $this->withToken($firstManagerToken)->getJson('/api/company-overview')
                ->assertOk()
                ->json('tasks')
        )->keyBy('title');
        $this->assertTrue($managerOverviewTasks['Nhiệm vụ của phòng Kỹ thuật']['can_contribute']);
        $this->assertFalse($managerOverviewTasks['Nhiệm vụ của phòng Vận hành']['can_contribute']);

        $employeeOverviewTasks = collect(
            $this->withToken($employeeToken)->getJson('/api/company-overview')
                ->assertOk()
                ->json('tasks')
        );
        $this->assertTrue($employeeOverviewTasks->every(
            fn (array $task) => $task['can_contribute'] === false
        ));

        $this->withToken($employeeToken)->postJson('/api/tasks', [
            'project_code' => $firstProject['code'],
            'title' => 'Không được thêm vào dự án chỉ nhìn thấy ở Tổng quan',
        ])->assertForbidden();
    }

    public function test_task_accountability_completion_and_restore_rules_are_consistent(): void
    {
        [$manager, $managerToken] = $this->user('project_manager', 'task-manager@example.com');
        [$employee, $employeeToken] = $this->user('member', 'task-worker@example.com');

        $project = $this->withToken($managerToken)->postJson('/api/projects', [
            'name' => 'Dự án kiểm soát tiến độ',
            'manager_code' => $manager->user_code,
        ])->assertCreated()->json('project');
        $task = $this->withToken($managerToken)->postJson('/api/tasks', [
            'project_code' => $project['code'],
            'title' => 'Nhiệm vụ có người chịu trách nhiệm',
            'assignee_code' => $employee->user_code,
        ])->assertCreated()->json('task');

        $this->withToken($managerToken)->postJson('/api/tasks/'.$task['code'].'/work-logs', [
            'time' => '09:00',
            'note' => 'Không được báo cáo thay nhân viên',
        ])->assertForbidden();
        $this->withToken($employeeToken)->putJson('/api/tasks/'.$task['code'], [
            'title' => 'Nhân viên không được đổi yêu cầu giao việc',
        ])->assertForbidden();

        $checklist = $this->withToken($employeeToken)->postJson('/api/tasks/'.$task['code'].'/checklists', [
            'text' => 'Hoàn tất phần việc con',
        ])->assertCreated()->json('checklist');
        $this->withToken($employeeToken)->patchJson('/api/tasks/'.$task['code'].'/status', [
            'status' => 'done',
        ])->assertStatus(409);

        $this->withToken($employeeToken)->postJson('/api/tasks/'.$task['code'].'/work-logs', [
            'time' => '10:00',
            'duration_minutes' => 60,
            'note' => 'Đã hoàn thành',
            'checklist_ids' => [$checklist['code']],
        ])->assertCreated()->assertJsonPath('progress', 100);
        $this->withToken($employeeToken)->patchJson('/api/tasks/'.$task['code'].'/status', [
            'status' => 'done',
        ])->assertOk()->assertJsonPath('task.progress', 100);

        $openTask = $this->withToken($managerToken)->postJson('/api/tasks', [
            'project_code' => $project['code'],
            'title' => 'Việc còn mở',
        ])->assertCreated()->json('task');
        $this->withToken($managerToken)->getJson('/api/projects/'.$project['code'])
            ->assertOk()->assertJsonPath('progress', 50);
        $this->withToken($managerToken)->putJson('/api/projects/'.$project['code'], [
            'status' => 'completed',
        ])->assertStatus(409);
        $this->withToken($managerToken)->patchJson('/api/tasks/'.$openTask['code'].'/status', [
            'status' => 'done',
        ])->assertOk();
        $this->withToken($managerToken)->putJson('/api/projects/'.$project['code'], [
            'status' => 'completed',
        ])->assertOk()->assertJsonPath('project.progress', 100);
        $this->withToken($managerToken)->postJson('/api/tasks', [
            'project_code' => $project['code'],
            'title' => 'Không được thêm vào dự án đã đóng',
        ])->assertStatus(409);
        $this->withToken($employeeToken)->patchJson('/api/tasks/'.$task['code'].'/status', [
            'status' => 'in_progress',
        ])->assertStatus(409);

        $this->withToken($employeeToken)->deleteJson('/api/tasks/'.$task['code'])->assertForbidden();
        $this->withToken($managerToken)->deleteJson('/api/tasks/'.$task['code'])->assertOk();
        $this->withToken($employeeToken)->getJson('/api/tasks-trash')
            ->assertOk()
            ->assertJsonPath('0.can_restore_by_user', false);
        $this->withToken($employeeToken)->postJson('/api/tasks/'.$task['code'].'/restore')->assertForbidden();
        $this->withToken($managerToken)->postJson('/api/tasks/'.$task['code'].'/restore')->assertOk();

        $this->withToken($employeeToken)->getJson('/api/projects/'.$project['code'].'/activities')
            ->assertOk()
            ->assertJsonPath('total', fn ($total) => $total >= 8)
            ->assertJsonFragment(['action' => 'tạo nhiệm vụ'])
            ->assertJsonFragment(['action' => 'thêm công việc con'])
            ->assertJsonFragment(['action' => 'báo cáo tiến độ'])
            ->assertJsonFragment(['action' => 'chuyển trạng thái'])
            ->assertJsonFragment(['action' => 'xóa nhiệm vụ'])
            ->assertJsonFragment(['action' => 'khôi phục nhiệm vụ']);
    }

    public function test_standalone_tasks_have_an_owner_and_deadline_automation_respects_preferences(): void
    {
        [, $adminToken] = $this->user('admin', 'automation-admin@example.com');
        [$manager, $managerToken] = $this->user('project_manager', 'automation-manager@example.com');
        [, $otherManagerToken] = $this->user('project_manager', 'automation-other@example.com');
        [$employee] = $this->user('member', 'automation-worker@example.com');

        $standalone = $this->withToken($managerToken)->postJson('/api/tasks', [
            'title' => 'Nhiệm vụ độc lập có chủ sở hữu',
        ])->assertCreated()->json('task');
        $this->withToken($otherManagerToken)->putJson('/api/tasks/'.$standalone['code'], [
            'title' => 'Không được chiếm quyền sở hữu',
        ])->assertForbidden();
        $this->withToken($adminToken)->putJson('/api/tasks/'.$standalone['code'], [
            'title' => 'Quản trị viên có quyền kiểm soát',
        ])->assertOk();

        $project = $this->withToken($managerToken)->postJson('/api/projects', [
            'name' => 'Dự án có nhắc hạn',
            'manager_code' => $manager->user_code,
        ])->assertCreated()->json('project');
        $this->withToken($managerToken)->postJson('/api/projects/'.$project['code'].'/automations', [
            'rule' => 'deadline_reminder',
            'enabled' => true,
        ])->assertCreated();
        $this->withToken($managerToken)->postJson('/api/tasks', [
            'project_code' => $project['code'],
            'title' => 'Việc đến hạn hôm nay',
            'assignee_code' => $employee->user_code,
            'due_date' => now()->toDateString(),
        ])->assertCreated();

        $this->assertSame(1, AutomationService::sendDeadlineReminders());
        $this->assertSame(0, AutomationService::sendDeadlineReminders());
        $this->assertTrue(Notification::where('notif_user_code', $employee->user_code)
            ->where('notif_title', 'Nhắc hạn nhiệm vụ')
            ->exists());

        $employee->update(['user_notification_preferences' => ['deadline' => false]]);
        $this->withToken($managerToken)->postJson('/api/tasks', [
            'project_code' => $project['code'],
            'title' => 'Việc không muốn nhận nhắc hạn',
            'assignee_code' => $employee->user_code,
            'due_date' => now()->addDay()->toDateString(),
        ])->assertCreated();
        $this->assertSame(0, AutomationService::sendDeadlineReminders());
    }

    private function user(string $role, string $email): array
    {
        $plainToken = 'token-'.sha1($email);
        $user = User::create([
            'user_name' => ucfirst(str_replace(['@example.com', '-', '.'], [' ', ' ', ' '], $email)),
            'user_email' => $email,
            'user_password' => Hash::make('secure-password'),
            'user_role' => $role,
            'user_phone' => '0901234567',
            'user_department' => 'Nội bộ',
            'user_job_title' => $role === 'member' ? 'Nhân viên' : 'Quản lý',
            'user_join_date' => now()->toDateString(),
            'user_profile_completed_at' => now(),
            'user_api_token' => hash('sha256', $plainToken),
        ]);

        return [$user, $plainToken];
    }
}
