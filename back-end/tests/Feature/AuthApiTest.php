<?php

namespace Tests\Feature;

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
}
