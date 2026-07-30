<?php

namespace Tests\Feature;

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

        User::whereKey($admin['user']['code'])->update(['user_role' => 'admin']);

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

        User::whereKey($admin['user']['code'])->update(['user_role' => 'admin']);

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

        $this->withToken($admin['token'])->getJson('/api/my-work')
            ->assertOk()
            ->assertJsonCount(1, 'today')
            ->assertJsonPath('today.0.title', 'Việc của quản trị viên');

        $this->withToken($employee['token'])->getJson('/api/my-work')
            ->assertOk()
            ->assertJsonCount(1, 'today')
            ->assertJsonPath('today.0.title', 'Việc của nhân viên');

        $this->withToken($employee['token'])->getJson('/api/tasks')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.title', 'Việc của nhân viên');
    }
}
