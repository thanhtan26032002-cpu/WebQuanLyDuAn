<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TaskDependency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_authentication_cycle(): void
    {
        $register = $this->postJson('/api/register', [
            'name' => 'Người dùng',
            'email' => 'user@example.com',
            'password' => 'mat-khau-an-toan',
        ])->assertCreated();

        $register->assertJsonPath('user.role', 'member');

        $token = $register->json('token');
        $this->assertIsString($token);
        $this->assertGreaterThan(40, strlen($token));

        $this->withToken($token)->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('email', 'user@example.com')
            ->assertJsonMissingPath('api_token')
            ->assertJsonMissingPath('password');

        $this->withToken($token)->postJson('/api/logout')->assertOk();
        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();

        $loginToken = $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'password' => 'mat-khau-an-toan',
        ])->assertOk()->json('token');

        $this->withToken($loginToken)->getJson('/api/user')->assertOk();
    }

    public function test_protected_auth_routes_reject_missing_token(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
        $this->postJson('/api/logout')->assertUnauthorized();
    }

    public function test_employee_completes_profile_and_can_only_edit_their_own_member_card(): void
    {
        $admin = $this->postJson('/api/register', [
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'secure-password',
        ])->assertCreated()->json();

        User::whereKey($admin['user']['code'])->update([
            'user_role' => 'admin',
            'user_profile_completed_at' => now(),
        ]);

        $employee = $this->postJson('/api/register', [
            'name' => 'Employee',
            'email' => 'employee@example.com',
            'password' => 'secure-password',
        ])->assertCreated()
            ->assertJsonPath('requires_profile_completion', true)
            ->assertJsonPath('user.role', 'member')
            ->json();

        $this->withToken($employee['token'])->postJson('/api/profile/complete', [
            'phone' => '0901234567',
            'department' => 'Development',
            'job_title' => 'Developer',
            'bio' => 'API tester',
            'color' => 'emerald',
        ])->assertOk()
            ->assertJsonPath('user.profile_completed_at', fn ($value) => filled($value));

        $this->withToken($employee['token'])->getJson('/api/members')
            ->assertOk()
            ->assertJsonPath('0.profile_limited', true)
            ->assertJsonMissingPath('0.email')
            ->assertJsonPath('1.email', 'employee@example.com');

        $this->withToken($employee['token'])->putJson('/api/members/'.$admin['user']['code'], [
            'name' => 'Not allowed',
        ])->assertForbidden();

        $this->withToken($employee['token'])->postJson('/api/users/'.$employee['user']['code'], [
            'name' => 'Employee Updated',
            'role' => 'admin',
            'phone' => '0901234567',
            'department' => 'Development',
            'job_title' => 'Senior Developer',
        ])->assertOk()
            ->assertJsonPath('user.name', 'Employee Updated')
            ->assertJsonPath('user.role', 'member');

        $this->assertFalse(Schema::hasTable('members'));
        $this->assertDatabaseHas('users', [
            'user_code' => $employee['user']['code'],
            'user_job_title' => 'Senior Developer',
            'user_role' => 'member',
        ]);
    }

    public function test_admin_can_assign_a_system_role_but_cannot_demote_themselves(): void
    {
        $admin = $this->postJson('/api/register', [
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'secure-password',
        ])->assertCreated()->json();

        User::whereKey($admin['user']['code'])->update([
            'user_role' => 'admin',
            'user_profile_completed_at' => now(),
        ]);

        $employee = $this->postJson('/api/register', [
            'name' => 'Employee',
            'email' => 'employee@example.com',
            'password' => 'secure-password',
        ])->assertCreated()->json();

        $this->withToken($admin['token'])->putJson('/api/members/'.$employee['user']['code'], [
            'system_role' => 'project_manager',
        ])->assertOk()
            ->assertJsonPath('member.role', 'project_manager');

        $this->withToken($admin['token'])->putJson('/api/members/'.$employee['user']['code'], [
            'system_role' => 'viewer',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('system_role');

        $this->withToken($admin['token'])->putJson('/api/members/'.$admin['user']['code'], [
            'system_role' => 'member',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('system_role');

        $this->assertDatabaseHas('users', [
            'user_code' => $admin['user']['code'],
            'user_role' => 'admin',
        ]);
    }

    public function test_user_can_change_password_with_their_current_password(): void
    {
        $account = $this->postJson('/api/register', [
            'name' => 'Password User',
            'email' => 'password@example.com',
            'password' => 'old-password',
        ])->assertCreated()->json();

        $this->withToken($account['token'])->putJson('/api/profile/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->withToken($account['token'])->putJson('/api/profile/password', [
            'current_password' => 'old-password',
            'password' => 'new-password',
            'password_confirmation' => 'not-matching',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->withToken($account['token'])->putJson('/api/profile/password', [
            'current_password' => 'old-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertOk();

        $this->postJson('/api/login', [
            'email' => 'password@example.com',
            'password' => 'old-password',
        ])->assertUnprocessable();

        $this->postJson('/api/login', [
            'email' => 'password@example.com',
            'password' => 'new-password',
        ])->assertOk();
    }

    public function test_my_work_only_returns_tasks_assigned_to_the_logged_in_user(): void
    {
        $admin = $this->postJson('/api/register', [
            'name' => 'Admin',
            'email' => 'my-work-admin@example.com',
            'password' => 'secure-password',
        ])->assertCreated()->json();
        User::whereKey($admin['user']['code'])->update(['user_role' => 'admin']);

        $employee = $this->postJson('/api/register', [
            'name' => 'Employee',
            'email' => 'my-work-employee@example.com',
            'password' => 'secure-password',
        ])->assertCreated()->json();

        $outsider = $this->postJson('/api/register', [
            'name' => 'Outsider',
            'email' => 'my-work-outsider@example.com',
            'password' => 'secure-password',
        ])->assertCreated()->json();

        $manager = $this->postJson('/api/register', [
            'name' => 'Project Manager',
            'email' => 'my-work-manager@example.com',
            'password' => 'secure-password',
        ])->assertCreated()->json();
        User::whereKey($manager['user']['code'])->update(['user_role' => 'project_manager']);
        User::whereIn('user_code', [
            $admin['user']['code'],
            $employee['user']['code'],
            $outsider['user']['code'],
            $manager['user']['code'],
        ])->update(['user_profile_completed_at' => now()]);

        $project = $this->withToken($admin['token'])->postJson('/api/projects', [
            'name' => 'Dự án chỉ quản trị viên quản lý',
        ])->assertCreated()->json('project');

        $this->withToken($admin['token'])->postJson('/api/tasks', [
            'title' => 'Việc của quản trị viên',
            'assignee_code' => $admin['user']['code'],
            'due_date' => now()->toDateString(),
        ])->assertCreated();

        $this->withToken($admin['token'])->postJson('/api/tasks', [
            'title' => 'Việc của nhân viên',
            'project_code' => $project['code'],
            'assignee_code' => $employee['user']['code'],
            'due_date' => now()->toDateString(),
        ])->assertCreated();

        $managerTask = $this->withToken($admin['token'])->postJson('/api/tasks', [
            'title' => 'Việc được giao cho quản lý',
            'project_code' => $project['code'],
            'assignee_code' => $manager['user']['code'],
            'due_date' => now()->toDateString(),
        ])->assertCreated()->json('task');

        $this->withToken($admin['token'])->getJson('/api/my-work')
            ->assertOk()
            ->assertJsonPath('summary.total_assigned', 1)
            ->assertJsonPath('projects.summary.total', 1)
            ->assertJsonPath('projects.items.0.participation_role', 'creator')
            ->assertJsonCount(1, 'sections.today')
            ->assertJsonPath('sections.today.0.title', 'Việc của quản trị viên');

        $this->withToken($employee['token'])->getJson('/api/my-work')
            ->assertOk()
            ->assertJsonPath('summary.total_assigned', 1)
            ->assertJsonPath('projects.summary.total', 1)
            ->assertJsonPath('projects.items.0.participation_role', 'member')
            ->assertJsonPath('projects.items.0.assigned_task_count', 1)
            ->assertJsonCount(1, 'sections.today')
            ->assertJsonPath('sections.today.0.title', 'Việc của nhân viên');

        $this->withToken($manager['token'])->getJson('/api/my-work')
            ->assertOk()
            ->assertJsonPath('summary.total_assigned', 1)
            ->assertJsonPath('projects.summary.total', 1)
            ->assertJsonPath('sections.today.0.title', 'Việc được giao cho quản lý');

        $this->withToken($manager['token'])->patchJson("/api/tasks/{$managerTask['code']}/status", [
            'status' => 'in_progress',
        ])->assertOk();

        $this->withToken($manager['token'])->putJson("/api/tasks/{$managerTask['code']}", [
            'title' => 'Không được sửa dự án ngoài phạm vi quản lý',
        ])->assertForbidden();

        $employeeTasksUrl = '/api/tasks?assignee_code='.$employee['user']['code'];
        $this->withToken($employee['token'])->getJson($employeeTasksUrl)
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.title', 'Việc của nhân viên');

        $employeeTaskCode = $this->withToken($employee['token'])->getJson($employeeTasksUrl)->json('0.code');
        $this->withToken($employee['token'])->patchJson("/api/tasks/{$employeeTaskCode}/status", [
            'status' => 'in_progress',
        ])->assertOk();

        $this->withToken($employee['token'])->postJson("/api/tasks/{$employeeTaskCode}/checklists", [
            'text' => 'Phần việc cá nhân',
        ])->assertCreated();

        $this->withToken($employee['token'])->putJson("/api/tasks/{$employeeTaskCode}", [
            'title' => 'Nhân viên không được tự đổi yêu cầu',
            'assignee_code' => $admin['user']['code'],
        ])->assertForbidden();

        $this->withToken($outsider['token'])->patchJson("/api/tasks/{$employeeTaskCode}/status", [
            'status' => 'done',
        ])->assertForbidden();

        $this->withToken($outsider['token'])->getJson('/api/my-work')
            ->assertOk()
            ->assertJsonPath('summary.total_assigned', 0)
            ->assertJsonPath('summary.active', 0)
            ->assertJsonPath('projects.summary.total', 0)
            ->assertJsonCount(0, 'projects.items');

        $this->assertDatabaseHas('tasks', [
            'task_code' => $employeeTaskCode,
            'task_title' => 'Việc của nhân viên',
            'task_assignee_code' => $employee['user']['code'],
            'task_status' => 'in_progress',
        ]);
    }

    public function test_my_work_groups_every_assigned_task_once_and_never_includes_team_backlog(): void
    {
        $employee = $this->postJson('/api/register', [
            'name' => 'Nhân viên phân nhóm',
            'email' => 'grouped-work@example.com',
            'password' => 'secure-password',
        ])->assertCreated()->json();
        User::whereKey($employee['user']['code'])->update(['user_profile_completed_at' => now()]);

        $other = User::create([
            'user_name' => 'Người khác',
            'user_email' => 'other-work@example.com',
            'user_password' => 'secure-password',
            'user_role' => 'member',
        ]);

        $createTask = fn (string $title, ?string $dueDate, string $status = 'todo') => Task::create([
            'task_title' => $title,
            'task_assignee_code' => $employee['user']['code'],
            'task_due_date' => $dueDate,
            'task_status' => $status,
            'task_priority' => 'medium',
            'task_completed_at' => $status === 'done' ? now() : null,
        ]);

        $overdue = $createTask('Việc quá hạn', now()->subDay()->toDateString());
        $today = $createTask('Việc hôm nay đang bị chặn', now()->toDateString());
        $upcoming = $createTask('Việc ba ngày tới', now()->addDays(3)->toDateString());
        $later = $createTask('Việc dài hạn', now()->addDays(10)->toDateString());
        $noDeadline = $createTask('Việc chưa có hạn', null);
        $completed = $createTask('Việc đã xong', now()->subDays(2)->toDateString(), 'done');

        $blocker = Task::create([
            'task_title' => 'Việc của người khác đang chặn',
            'task_assignee_code' => $other->user_code,
            'task_status' => 'todo',
        ]);
        TaskDependency::create([
            'dependency_task_code' => $today->task_code,
            'dependency_depends_on_code' => $blocker->task_code,
        ]);
        Task::create([
            'task_title' => 'Việc chưa phân công của nhóm',
            'task_status' => 'todo',
        ]);

        $response = $this->withToken($employee['token'])->getJson('/api/my-work')
            ->assertOk()
            ->assertJsonPath('owner.code', $employee['user']['code'])
            ->assertJsonPath('summary.total_assigned', 6)
            ->assertJsonPath('summary.active', 5)
            ->assertJsonPath('summary.blocked', 1)
            ->assertJsonPath('summary.completed', 1)
            ->assertJsonCount(1, 'sections.overdue')
            ->assertJsonCount(1, 'sections.today')
            ->assertJsonCount(1, 'sections.upcoming')
            ->assertJsonCount(1, 'sections.later')
            ->assertJsonCount(1, 'sections.no_deadline')
            ->assertJsonCount(1, 'sections.recently_completed');

        $activeCodes = collect(['overdue', 'today', 'upcoming', 'later', 'no_deadline'])
            ->flatMap(fn (string $section) => collect($response->json("sections.{$section}"))->pluck('code'));

        $this->assertCount(5, $activeCodes);
        $this->assertCount(5, $activeCodes->unique());
        $this->assertEqualsCanonicalizing([
            $overdue->task_code,
            $today->task_code,
            $upcoming->task_code,
            $later->task_code,
            $noDeadline->task_code,
        ], $activeCodes->all());
        $this->assertSame($completed->task_code, $response->json('sections.recently_completed.0.code'));
    }
}
