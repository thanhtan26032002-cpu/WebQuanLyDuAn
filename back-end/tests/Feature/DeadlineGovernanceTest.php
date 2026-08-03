<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\AutomationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DeadlineGovernanceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_only_project_managers_and_admins_can_extend_deadlines_with_audited_recovery_context(): void
    {
        Carbon::setTestNow('2026-08-03 08:00:00');
        [$manager, $managerToken] = $this->user('project_manager', 'deadline-manager@example.com');
        [$employee, $employeeToken] = $this->user('member', 'deadline-employee@example.com');
        [, $otherManagerToken] = $this->user('project_manager', 'deadline-other@example.com');

        $project = Project::create([
            'project_name' => 'Dự án quá hạn cần phục hồi',
            'project_status' => 'active',
            'project_due_date' => now()->subDays(4)->toDateString(),
            'project_manager_code' => $manager->user_code,
            'project_created_by' => $manager->user_code,
        ]);
        $project->members()->sync([$manager->user_code, $employee->user_code]);
        $task = Task::create([
            'task_project_code' => $project->project_code,
            'task_title' => 'Nhiệm vụ quá hạn cần phục hồi',
            'task_status' => 'in_progress',
            'task_due_date' => now()->subDays(3)->toDateString(),
            'task_assignee_code' => $employee->user_code,
            'task_created_by' => $manager->user_code,
        ]);

        $newProjectDueDate = now()->addDays(10)->toDateString();
        $newTaskDueDate = now()->addDays(5)->toDateString();

        $this->withToken($employeeToken)->putJson('/api/tasks/'.$task->task_code, [
            'due_date' => $newTaskDueDate,
            'extension_reason' => 'Nhân viên không được tự gia hạn',
        ])->assertForbidden();
        $this->withToken($otherManagerToken)->putJson('/api/projects/'.$project->project_code, [
            'due_date' => $newProjectDueDate,
            'extension_reason' => 'Quản lý ngoài dự án không được gia hạn',
        ])->assertForbidden();

        $this->withToken($managerToken)->putJson('/api/projects/'.$project->project_code, [
            'due_date' => $newProjectDueDate,
        ])->assertUnprocessable()->assertJsonValidationErrors('extension_reason');
        $this->withToken($managerToken)->putJson('/api/tasks/'.$task->task_code, [
            'due_date' => $newTaskDueDate,
            'extension_reason' => 'Cần thêm thời gian',
        ])->assertUnprocessable()->assertJsonValidationErrors(['delay_reason', 'recovery_plan']);

        $this->withToken($managerToken)->putJson('/api/projects/'.$project->project_code, [
            'due_date' => $newProjectDueDate,
            'delay_reason' => 'Thiếu đầu vào đã cam kết',
            'recovery_plan' => 'Bổ sung nguồn lực và rà soát hằng ngày',
            'extension_reason' => 'Điều chỉnh theo kế hoạch phục hồi đã thống nhất',
        ])->assertOk()
            ->assertJsonPath('project.deadline_extensions.0.old_due_date', now()->subDays(4)->toDateString())
            ->assertJsonPath('project.deadline_extensions.0.new_due_date', $newProjectDueDate)
            ->assertJsonPath('project.delay_reason', 'Thiếu đầu vào đã cam kết');

        $this->withToken($managerToken)->putJson('/api/tasks/'.$task->task_code, [
            'due_date' => $newTaskDueDate,
            'delay_reason' => 'Phụ thuộc đầu vào của nhóm khác',
            'recovery_plan' => 'Chia nhỏ phần việc và kiểm tra mỗi ngày',
            'extension_reason' => 'Gia hạn theo đường găng mới',
        ])->assertOk()
            ->assertJsonPath('task.deadline_extensions.0.old_due_date', now()->subDays(3)->toDateString())
            ->assertJsonPath('task.deadline_extensions.0.new_due_date', $newTaskDueDate)
            ->assertJsonPath('task.recovery_plan', 'Chia nhỏ phần việc và kiểm tra mỗi ngày');

        $this->withToken($managerToken)->getJson('/api/projects/'.$project->project_code.'/activities')
            ->assertOk()
            ->assertJsonFragment(['action' => 'gia hạn dự án'])
            ->assertJsonFragment(['action' => 'gia hạn nhiệm vụ']);
    }

    public function test_overdue_alerts_escalate_once_at_one_three_and_seven_days(): void
    {
        Carbon::setTestNow('2026-08-03 08:00:00');
        [$admin] = $this->user('admin', 'alert-admin@example.com');
        [$manager] = $this->user('project_manager', 'alert-manager@example.com');
        [$employee] = $this->user('member', 'alert-employee@example.com');

        foreach ([1, 3, 7] as $days) {
            $project = Project::create([
                'project_name' => 'Dự án chứa nhiệm vụ mức '.$days,
                'project_status' => 'active',
                'project_manager_code' => $manager->user_code,
                'project_created_by' => $manager->user_code,
            ]);
            Task::create([
                'task_project_code' => $project->project_code,
                'task_title' => 'Nhiệm vụ cảnh báo mức '.$days,
                'task_status' => 'in_progress',
                'task_due_date' => now()->subDays($days)->toDateString(),
                'task_assignee_code' => $employee->user_code,
                'task_created_by' => $manager->user_code,
            ]);
            Project::create([
                'project_name' => 'Dự án cảnh báo mức '.$days,
                'project_status' => 'active',
                'project_due_date' => now()->subDays($days)->toDateString(),
                'project_manager_code' => $manager->user_code,
                'project_created_by' => $manager->user_code,
            ]);
        }

        $this->assertSame(10, AutomationService::sendDeadlineReminders());
        $this->assertSame(0, AutomationService::sendDeadlineReminders());
        $this->assertSame(10, Notification::count());
        $this->assertSame(3, Notification::where('notif_user_code', $employee->user_code)->count());
        $this->assertSame(5, Notification::where('notif_user_code', $manager->user_code)->count());
        $this->assertSame(2, Notification::where('notif_user_code', $admin->user_code)->count());
    }

    public function test_reports_include_overdue_projects_and_late_completion_metrics(): void
    {
        Carbon::setTestNow('2026-08-03 08:00:00');
        [$admin, $adminToken] = $this->user('admin', 'report-admin@example.com');
        [$manager] = $this->user('project_manager', 'report-manager@example.com');

        Project::create([
            'project_name' => 'Dự án vẫn đang quá hạn',
            'project_status' => 'active',
            'project_due_date' => now()->subDays(6)->toDateString(),
            'project_manager_code' => $manager->user_code,
            'project_created_by' => $manager->user_code,
        ]);
        Project::create([
            'project_name' => 'Dự án hoàn thành trễ',
            'project_status' => 'completed',
            'project_progress' => 100,
            'project_due_date' => now()->subDays(10)->toDateString(),
            'project_completed_at' => now()->subDays(2),
            'project_manager_code' => $manager->user_code,
            'project_created_by' => $manager->user_code,
        ]);
        Task::create([
            'task_title' => 'Nhiệm vụ hoàn thành trễ',
            'task_status' => 'done',
            'task_progress' => 100,
            'task_due_date' => now()->subDays(5)->toDateString(),
            'task_completed_at' => now()->subDays(2),
            'task_created_by' => $admin->user_code,
        ]);
        Task::create([
            'task_title' => 'Nhiệm vụ hoàn thành đúng hạn',
            'task_status' => 'done',
            'task_progress' => 100,
            'task_due_date' => now()->subDays(2)->toDateString(),
            'task_completed_at' => now()->subDays(3),
            'task_created_by' => $admin->user_code,
        ]);

        $this->withToken($adminToken)->getJson('/api/reports')
            ->assertOk()
            ->assertJsonPath('overdue_projects.total', 1)
            ->assertJsonPath('overdue_projects.items.0.overdue_days', 6)
            ->assertJsonPath('late_completion.tasks.total', 1)
            ->assertJsonPath('late_completion.tasks.rate', 50)
            ->assertJsonPath('late_completion.tasks.items.0.late_days', 3)
            ->assertJsonPath('late_completion.projects.total', 1)
            ->assertJsonPath('late_completion.projects.items.0.late_days', 8);
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
            'user_notification_preferences' => ['deadline' => true],
        ]);

        return [$user, $plainToken];
    }
}
