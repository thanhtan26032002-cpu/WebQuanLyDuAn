<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CoreApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_and_task_workflow_uses_the_deployment_schema(): void
    {
        $user = User::create([
            'user_name' => 'Quản trị viên',
            'user_email' => 'admin@example.com',
            'user_password' => Hash::make('test-password'),
            'user_role' => 'admin',
        ]);

        $member = Member::create([
            'member_name' => 'Thành viên kiểm thử',
            'member_email' => 'member@example.com',
        ]);

        $projectResponse = $this->postJson('/api/projects', [
            'name' => 'Dự án kiểm thử',
            'color' => 'rose',
            'status' => 'active',
            'due_date' => now()->toDateString(),
            'member_ids' => [$member->member_code],
            'user_code' => $user->user_code,
        ]);

        $projectResponse
            ->assertCreated()
            ->assertJsonPath('project.code', 'PJ0001')
            ->assertJsonPath('project.color', 'rose')
            ->assertJsonPath('project.members.0.code', $member->member_code)
            ->assertJsonPath('project.due_date', now()->toDateString());

        $taskResponse = $this->postJson('/api/tasks', [
            'project_code' => 'PJ0001',
            'title' => 'Nhiệm vụ kiểm thử',
            'status' => 'todo',
            'priority' => 'high',
            'due_date' => now()->toDateString(),
            'assignee_code' => $member->member_code,
            'user_code' => $user->user_code,
        ]);

        $taskResponse
            ->assertCreated()
            ->assertJsonPath('task.code', 'TK0001')
            ->assertJsonPath('task.project_code', 'PJ0001')
            ->assertJsonPath('task.assignee.code', 'MB0001');

        $this->patchJson('/api/tasks/TK0001/status', ['status' => 'done'])
            ->assertOk()
            ->assertJsonPath('task.status', 'done');

        $this->postJson('/api/tasks/TK0001/comments', [
            'text' => 'Đã hoàn tất',
            'user_code' => $user->user_code,
        ])->assertCreated();

        $this->getJson('/api/projects/PJ0001')
            ->assertOk()
            ->assertJsonPath('color', 'rose')
            ->assertJsonCount(1, 'tasks');

        $this->putJson('/api/projects/PJ0001/members', [
            'member_ids' => [$member->member_code],
        ])->assertOk()->assertJsonPath('project.members.0.code', 'MB0001');

        $this->getJson('/api/activities')
            ->assertOk()
            ->assertJsonCount(4)
            ->assertJsonPath('0.user.code', $user->user_code)
            ->assertJsonPath('0.user.name', 'Quản trị viên');

        $this->assertDatabaseHas('tasks', [
            'task_code' => 'TK0001',
            'task_status' => 'done',
        ]);
    }

    public function test_team_and_attachment_changes_are_persisted(): void
    {
        Storage::fake('public');

        User::create([
            'user_name' => 'Quản trị viên',
            'user_email' => 'admin@example.com',
            'user_password' => Hash::make('test-password'),
        ]);
        $member = Member::create([
            'member_name' => 'Thành viên',
            'member_email' => 'member@example.com',
        ]);

        $this->putJson('/api/members/'.$member->member_code, [
            'name' => 'Thành viên đã sửa',
            'email' => 'member@example.com',
            'role' => 'developer',
        ])->assertOk()->assertJsonPath('member.name', 'Thành viên đã sửa');

        $group = $this->postJson('/api/groups', [
            'name' => 'Nhóm phát triển',
            'icon' => '🚀',
            'color' => 'violet',
        ])->assertCreated()->json('group');

        $this->putJson('/api/groups/members/'.$member->member_code, [
            'group_code' => $group['code'],
        ])->assertOk()->assertJsonPath('0.member_ids.0', $member->member_code);

        $this->postJson('/api/projects', [
            'name' => 'Dự án có tệp',
            'user_code' => 'US0001',
        ])->assertCreated();

        $upload = $this->post('/api/upload', [
            'file' => UploadedFile::fake()->create('tai-lieu.txt', 10, 'text/plain'),
            'target_type' => 'Project',
            'target_code' => 'PJ0001',
        ])->assertCreated();

        $attachmentCode = $upload->json('attachment.code');
        $storedPath = ltrim(str_replace('/storage/', '', $upload->json('attachment.file_path')), '/');
        Storage::disk('public')->assertExists($storedPath);

        $this->deleteJson('/api/attachments/'.$attachmentCode)->assertOk();
        Storage::disk('public')->assertMissing($storedPath);
    }

    public function test_deadline_is_optional_but_past_deadline_is_rejected(): void
    {
        $user = User::create([
            'user_name' => 'Quản trị viên',
            'user_email' => 'admin@example.com',
            'user_password' => Hash::make('test-password'),
        ]);

        $this->postJson('/api/projects', [
            'name' => 'Không có hạn chót',
            'user_code' => $user->user_code,
        ])->assertCreated()->assertJsonPath('project.due_date', null);

        $this->postJson('/api/projects', [
            'name' => 'Hạn chót để trống',
            'due_date' => '',
            'user_code' => $user->user_code,
        ])->assertCreated()->assertJsonPath('project.due_date', null);

        $this->putJson('/api/projects/PJ0002', [
            'due_date' => '',
        ])->assertOk()->assertJsonPath('project.due_date', null);

        $this->postJson('/api/tasks', [
            'title' => 'Hạn chót đã qua',
            'due_date' => now()->subDay()->toDateString(),
            'user_code' => $user->user_code,
        ])->assertUnprocessable()->assertJsonValidationErrors('due_date');
    }

    public function test_project_creation_remains_compatible_before_color_migration_is_applied(): void
    {
        User::create([
            'user_name' => 'Quản trị viên',
            'user_email' => 'admin@example.com',
            'user_password' => Hash::make('test-password'),
        ]);

        Schema::table('projects', function ($table) {
            $table->dropColumn('project_color');
        });

        $this->postJson('/api/projects', [
            'name' => 'Dự án trên schema cũ',
            'color' => 'rose',
            'due_date' => '',
            'user_code' => 'US0001',
        ])->assertCreated()
            ->assertJsonPath('project.due_date', null)
            ->assertJsonMissingPath('project.color');
    }

    public function test_task_can_be_created_and_updated_without_an_assignee(): void
    {
        $user = User::create([
            'user_name' => 'Quản trị viên',
            'user_email' => 'admin@example.com',
            'user_password' => Hash::make('test-password'),
        ]);

        $this->postJson('/api/tasks', [
            'title' => 'Nhiệm vụ chưa phân công',
            'assignee_code' => null,
            'due_date' => '',
            'user_code' => $user->user_code,
        ])->assertCreated()
            ->assertJsonPath('task.assignee_code', null)
            ->assertJsonPath('task.assignee', null);

        $this->putJson('/api/tasks/TK0001', [
            'assignee_code' => '',
        ])->assertOk()
            ->assertJsonPath('task.assignee_code', null)
            ->assertJsonPath('task.assignee', null);

        $this->assertDatabaseHas('tasks', [
            'task_code' => 'TK0001',
            'task_assignee_code' => null,
        ]);
    }

    public function test_member_group_can_be_selected_in_forms_and_removed_by_drag_api(): void
    {
        $firstGroup = $this->postJson('/api/groups', [
            'name' => 'Nhóm thứ nhất',
        ])->assertCreated()->json('group');

        $secondGroup = $this->postJson('/api/groups', [
            'name' => 'Nhóm thứ hai',
        ])->assertCreated()->json('group');

        $memberResponse = $this->postJson('/api/members', [
            'name' => 'Thành viên có nhóm',
            'email' => 'grouped@example.com',
            'group_code' => $firstGroup['code'],
        ])->assertCreated()
            ->assertJsonPath('groups.0.member_ids.0', 'MB0001');

        $memberCode = $memberResponse->json('user.code');

        $this->putJson('/api/members/'.$memberCode, [
            'group_code' => $secondGroup['code'],
        ])->assertOk()
            ->assertJsonCount(0, 'groups.0.member_ids')
            ->assertJsonPath('groups.1.member_ids.0', $memberCode);

        $this->putJson('/api/groups/members/'.$memberCode, [
            'group_code' => null,
        ])->assertOk()
            ->assertJsonCount(0, '0.member_ids')
            ->assertJsonCount(0, '1.member_ids');

        $this->getJson('/api/groups')->assertOk()
            ->assertJsonCount(0, '0.member_ids')
            ->assertJsonCount(0, '1.member_ids');
    }
}
