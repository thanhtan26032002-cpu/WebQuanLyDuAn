<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\ActivityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NotificationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifications_include_safe_navigation_targets_and_support_pagination(): void
    {
        [$manager, $managerToken] = $this->user('project_manager', 'notification-manager@example.com');
        [$employee, $employeeToken] = $this->user('member', 'notification-employee@example.com');
        [, $outsiderToken] = $this->user('member', 'notification-outsider@example.com');

        $project = Project::create([
            'project_name' => 'Dự án có thông báo',
            'project_manager_code' => $manager->user_code,
            'project_created_by' => $manager->user_code,
        ]);
        $project->members()->sync([$manager->user_code, $employee->user_code]);
        $task = Task::create([
            'task_project_code' => $project->project_code,
            'task_title' => 'Nhiệm vụ cần mở từ thông báo',
            'task_assignee_code' => $employee->user_code,
            'task_created_by' => $manager->user_code,
        ]);

        $taskNotification = ActivityService::notify(
            $employee->user_code,
            'Bạn có nhiệm vụ mới',
            $task->task_title,
            'info',
            'Task',
            $task->task_code
        );
        ActivityService::notify(
            $employee->user_code,
            'Dự án cần chú ý',
            $project->project_name,
            'warning',
            'Project',
            $project->project_code
        );
        ActivityService::notify($employee->user_code, 'Thông báo hệ thống', 'Không có liên kết', 'info');

        $this->withToken($employeeToken)->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonCount(3)
            ->assertJsonFragment([
                'id' => $taskNotification->notif_code,
                'targetType' => 'Task',
                'targetCode' => $task->task_code,
                'read' => false,
            ])
            ->assertJsonFragment([
                'targetType' => 'Project',
                'targetCode' => $project->project_code,
            ])
            ->assertJsonFragment([
                'title' => 'Thông báo hệ thống',
                'targetType' => null,
                'targetCode' => null,
            ]);

        $this->withToken($employeeToken)->getJson('/api/notifications?paginate=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonCount(3, 'data');

        $this->withToken($employeeToken)->putJson('/api/notifications/'.$taskNotification->notif_code.'/read')
            ->assertOk();
        $this->assertDatabaseHas('notifications', [
            'notif_code' => $taskNotification->notif_code,
            'notif_is_read' => true,
        ]);
        $this->withToken($outsiderToken)->putJson('/api/notifications/'.$taskNotification->notif_code.'/read')
            ->assertNotFound();
        $this->withToken($managerToken)->getJson('/api/notifications')->assertOk()->assertJsonCount(0);
    }

    public function test_task_assignment_notification_points_to_the_assigned_task(): void
    {
        [$manager, $managerToken] = $this->user('project_manager', 'assignment-manager@example.com');
        [$employee, $employeeToken] = $this->user('member', 'assignment-employee@example.com');

        $project = $this->withToken($managerToken)->postJson('/api/projects', [
            'name' => 'Dự án phân công',
            'manager_code' => $manager->user_code,
        ])->assertCreated()->json('project');
        $task = $this->withToken($managerToken)->postJson('/api/tasks', [
            'project_code' => $project['code'],
            'title' => 'Nhiệm vụ được phân công',
            'assignee_code' => $employee->user_code,
        ])->assertCreated()->json('task');

        $this->withToken($employeeToken)->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment([
                'title' => 'Nhiệm vụ mới',
                'targetType' => 'Task',
                'targetCode' => $task['code'],
            ])
            ->assertJsonFragment([
                'title' => 'Bạn được thêm vào dự án',
                'targetType' => 'Project',
                'targetCode' => $project['code'],
            ]);
    }

    public function test_project_members_and_new_manager_receive_one_actionable_notification(): void
    {
        [$manager, $managerToken] = $this->user('project_manager', 'project-notify-manager@example.com');
        [$nextManager, $nextManagerToken] = $this->user('project_manager', 'project-notify-next-manager@example.com');
        [$employee, $employeeToken] = $this->user('member', 'project-notify-employee@example.com');

        $project = $this->withToken($managerToken)->postJson('/api/projects', [
            'name' => 'Dự án thông báo thành viên',
            'manager_code' => $manager->user_code,
        ])->assertCreated()->json('project');

        $this->withToken($managerToken)->putJson('/api/projects/'.$project['code'].'/members', [
            'member_ids' => [$manager->user_code, $employee->user_code],
        ])->assertOk();
        $this->withToken($managerToken)->putJson('/api/projects/'.$project['code'].'/members', [
            'member_ids' => [$manager->user_code, $employee->user_code],
        ])->assertOk();

        $this->withToken($employeeToken)->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment([
                'title' => 'Bạn được thêm vào dự án',
                'targetType' => 'Project',
                'targetCode' => $project['code'],
                'read' => false,
            ]);

        $this->withToken($managerToken)->putJson('/api/projects/'.$project['code'], [
            'manager_code' => $nextManager->user_code,
        ])->assertOk();
        $this->withToken($nextManagerToken)->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment([
                'title' => 'Bạn được giao phụ trách dự án',
                'targetType' => 'Project',
                'targetCode' => $project['code'],
            ]);
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
            'user_job_title' => $role === 'member' ? 'Nhân viên' : 'Quản lý dự án',
            'user_join_date' => now()->toDateString(),
            'user_profile_completed_at' => now(),
            'user_api_token' => hash('sha256', $plainToken),
            'user_notification_preferences' => ['assignment' => true, 'deadline' => true, 'comments' => true],
        ]);

        return [$user, $plainToken];
    }
}
