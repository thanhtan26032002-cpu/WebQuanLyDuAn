<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
