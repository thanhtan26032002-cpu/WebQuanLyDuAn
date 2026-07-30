<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Project;
use App\Models\Task;
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

    private User $apiUser;

    protected function setUp(): void
    {
        parent::setUp();

        $plainToken = 'core-api-test-token';
        $this->apiUser = User::create([
            'user_name' => 'Quản trị viên',
            'user_email' => 'authenticated-admin@example.com',
            'user_password' => Hash::make('test-password'),
            'user_role' => 'admin',
            'user_api_token' => hash('sha256', $plainToken),
        ]);
        $this->withToken($plainToken);
    }

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
            ->assertJsonPath('0.user.code', $this->apiUser->user_code)
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
            'phone' => '0901234567',
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

    public function test_customer_project_and_task_planning_fields_are_persisted(): void
    {
        $user = User::create([
            'user_name' => 'Quản trị viên',
            'user_email' => 'planning@example.com',
            'user_password' => Hash::make('test-password'),
        ]);
        $manager = Member::create([
            'member_name' => 'Quản lý dự án',
            'member_email' => 'manager@example.com',
        ]);

        $customer = $this->postJson('/api/customers', [
            'name' => 'Nguyễn Văn Khách',
            'company' => 'Công ty Khách hàng',
            'email' => 'customer@example.com',
            'phone' => '0901234567',
        ])->assertCreated()->json('customer');

        $project = $this->postJson('/api/projects', [
            'name' => 'Dự án có khách hàng',
            'customer_code' => $customer['code'],
            'manager_code' => $manager->member_code,
            'start_date' => now()->toDateString(),
            'due_date' => now()->addMonth()->toDateString(),
            'user_code' => $user->user_code,
        ])->assertCreated()
            ->assertJsonPath('project.customer.code', 'KH0001')
            ->assertJsonPath('project.manager.code', 'MB0001')
            ->json('project');

        $this->postJson('/api/tasks', [
            'project_code' => $project['code'],
            'title' => 'Thiết kế tính năng',
            'type' => 'devops',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addWeek()->toDateString(),
            'estimated_hours' => 16.5,
            'user_code' => $user->user_code,
        ])->assertCreated()
            ->assertJsonPath('task.type', 'devops')
            ->assertJsonPath('task.start_date', now()->toDateString())
            ->assertJsonPath('task.estimated_hours', 16.5);

        $this->getJson('/api/projects/'.$project['code'])
            ->assertOk()
            ->assertJsonPath('customer.company', 'Công ty Khách hàng')
            ->assertJsonCount(1, 'tasks');
    }

    public function test_customer_email_and_phone_are_validated(): void
    {
        $this->postJson('/api/customers', [
            'name' => 'Khách hàng dữ liệu sai',
            'email' => 'email-khong-hop-le',
            'phone' => '--------',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'phone'])
            ->assertJsonPath('errors.email.0', 'Email khách hàng không đúng định dạng.')
            ->assertJsonPath('errors.phone.0', 'Số điện thoại phải có từ 8 đến 15 chữ số và chỉ chứa khoảng trắng hoặc các ký tự + ( ) . -.');

        $this->postJson('/api/customers', [
            'name' => 'Khách hàng hợp lệ',
            'email' => '  Customer@Example.COM ',
            'phone' => '+84 (90) 123-4567',
        ])->assertCreated()
            ->assertJsonPath('customer.email', 'customer@example.com')
            ->assertJsonPath('customer.phone', '+84 (90) 123-4567');
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
            'phone' => '0901234567',
            'group_code' => $firstGroup['code'],
        ])->assertCreated()
            ->assertJsonPath('groups.0.member_ids.0', 'MB0001');

        $memberCode = $memberResponse->json('user.code');

        $this->putJson('/api/members/'.$memberCode, [
            'phone' => '0901234567',
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

    public function test_member_phone_is_required_and_validated_before_saving(): void
    {
        $this->postJson('/api/members', [
            'name' => 'Thiếu số điện thoại',
            'email' => 'missing-phone@example.com',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('phone')
            ->assertJsonPath('errors.phone.0', 'Vui lòng nhập số điện thoại.');

        $this->postJson('/api/members', [
            'name' => 'Sai số điện thoại',
            'email' => 'invalid-phone@example.com',
            'phone' => '09abc',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('phone');

        $memberCode = $this->postJson('/api/members', [
            'name' => 'Số điện thoại hợp lệ',
            'email' => 'valid-phone@example.com',
            'phone' => '+84901234567',
        ])->assertCreated()->json('user.code');

        $this->putJson('/api/members/'.$memberCode, [
            'name' => 'Không được lưu',
            'phone' => '',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.phone.0', 'Vui lòng nhập số điện thoại.');

        $this->assertDatabaseHas('members', [
            'member_code' => $memberCode,
            'member_name' => 'Số điện thoại hợp lệ',
            'member_phone' => '+84901234567',
        ]);
    }

    public function test_projects_and_tasks_are_soft_deleted_and_restorable_for_30_days(): void
    {
        User::create([
            'user_name' => 'Admin',
            'user_email' => 'soft-delete@example.com',
            'user_password' => Hash::make('test-password'),
        ]);

        $this->postJson('/api/projects', [
            'name' => 'Restorable project',
            'user_code' => 'US0001',
        ])->assertCreated();

        $this->postJson('/api/tasks', [
            'project_code' => 'PJ0001',
            'title' => 'Restorable task',
            'user_code' => 'US0001',
        ])->assertCreated();

        $this->deleteJson('/api/tasks/TK0001')->assertOk();
        $this->getJson('/api/tasks')->assertJsonCount(0);
        $this->getJson('/api/tasks-trash')
            ->assertOk()
            ->assertJsonPath('0.code', 'TK0001')
            ->assertJsonPath('0.can_restore', true);

        $this->postJson('/api/tasks/TK0001/restore', ['user_code' => 'US0001'])
            ->assertOk()
            ->assertJsonPath('task.code', 'TK0001');

        $this->deleteJson('/api/projects/PJ0001')->assertOk();
        $this->getJson('/api/projects')->assertJsonCount(0);
        $this->getJson('/api/tasks')->assertJsonCount(0);
        $this->getJson('/api/projects-trash')
            ->assertOk()
            ->assertJsonPath('0.code', 'PJ0001')
            ->assertJsonPath('0.can_restore', true);

        $this->assertNotNull(Project::withTrashed()->find('PJ0001')->project_deleted_at);
        $this->assertNull(Task::find('TK0001')->task_deleted_at);

        $this->postJson('/api/projects/PJ0001/restore', ['user_code' => 'US0001'])
            ->assertOk()
            ->assertJsonPath('project.code', 'PJ0001');

        $this->getJson('/api/tasks')->assertJsonCount(1);
    }

    public function test_restore_is_rejected_after_30_days(): void
    {
        User::create([
            'user_name' => 'Admin',
            'user_email' => 'expired-delete@example.com',
            'user_password' => Hash::make('test-password'),
        ]);

        $this->postJson('/api/projects', [
            'name' => 'Expired project',
            'user_code' => 'US0001',
        ])->assertCreated();
        $this->deleteJson('/api/projects/PJ0001')->assertOk();

        Project::onlyTrashed()->whereKey('PJ0001')->update([
            'project_deleted_at' => now()->subDays(31),
        ]);

        $this->getJson('/api/projects-trash')
            ->assertOk()
            ->assertJsonCount(0);
        $this->postJson('/api/projects/PJ0001/restore')
            ->assertStatus(410);

        $this->assertTrue(Project::withTrashed()->find('PJ0001')->trashed());

        $this->postJson('/api/tasks', [
            'title' => 'Expired task',
            'user_code' => 'US0001',
        ])->assertCreated();
        $this->deleteJson('/api/tasks/TK0001')->assertOk();
        Task::onlyTrashed()->whereKey('TK0001')->update([
            'task_deleted_at' => now()->subDays(31),
        ]);

        $this->getJson('/api/tasks-trash')
            ->assertOk()
            ->assertJsonCount(0);
        $this->postJson('/api/tasks/TK0001/restore')
            ->assertStatus(410);

        $this->assertTrue(Task::withTrashed()->find('TK0001')->trashed());
    }

    public function test_checklists_and_work_logs_are_persisted_and_require_an_assignee(): void
    {
        User::create([
            'user_name' => 'Admin',
            'user_email' => 'progress@example.com',
            'user_password' => Hash::make('test-password'),
        ]);

        $this->postJson('/api/tasks', [
            'title' => 'Persistent progress task',
            'user_code' => 'US0001',
        ])->assertCreated();

        $this->postJson('/api/tasks/TK0001/checklists', [
            'text' => 'Complete API integration',
        ])->assertCreated()
            ->assertJsonPath('checklist.code', 'CK0001')
            ->assertJsonPath('checklist.completed', false)
            ->assertJsonPath('progress', 0);

        $this->getJson('/api/tasks')
            ->assertOk()
            ->assertJsonPath('0.checklists.0.text', 'Complete API integration')
            ->assertJsonCount(0, '0.work_logs');

        $this->postJson('/api/tasks/TK0001/work-logs', [
            'time' => '14:30',
            'note' => 'Must be rejected without assignee',
            'checklist_ids' => ['CK0001'],
            'user_code' => 'US0001',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('assignee_code');

        $member = Member::create([
            'member_name' => 'Assigned member',
            'member_email' => 'assigned-progress@example.com',
        ]);

        $this->putJson('/api/tasks/TK0001', [
            'assignee_code' => $member->member_code,
        ])->assertOk();

        $this->postJson('/api/tasks/TK0001/work-logs', [
            'time' => '14:30',
            'note' => 'Completed API integration',
            'checklist_ids' => ['CK0001'],
            'files' => [[
                'code' => 'AT0001',
                'name' => 'evidence.png',
                'url' => '/storage/attachments/evidence.png',
            ]],
            'user_code' => 'US0001',
        ])->assertCreated()
            ->assertJsonPath('work_log.code', 'WL0001')
            ->assertJsonPath('work_log.reporter_code', $member->member_code)
            ->assertJsonPath('work_log.completed_items.0.id', 'CK0001')
            ->assertJsonPath('checklists.0.completed', true)
            ->assertJsonPath('progress', 100);

        $this->getJson('/api/tasks')
            ->assertOk()
            ->assertJsonPath('0.progress', 100)
            ->assertJsonPath('0.checklists.0.completed', true)
            ->assertJsonPath('0.work_logs.0.note', 'Completed API integration')
            ->assertJsonPath('0.work_logs.0.files.0.name', 'evidence.png');

        $this->assertDatabaseHas('task_checklists', [
            'checklist_code' => 'CK0001',
            'checklist_is_completed' => true,
        ]);
        $this->assertDatabaseHas('task_work_logs', [
            'worklog_code' => 'WL0001',
            'worklog_task_code' => 'TK0001',
            'worklog_reporter_code' => $member->member_code,
        ]);
    }
}
