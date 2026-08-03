<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectActivityTimelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_timeline_records_and_displays_all_core_project_actions(): void
    {
        Storage::fake('public');
        [$manager, $managerToken] = $this->user('project_manager', 'activity-manager@example.com', 'Quản lý nhật ký');
        [$employee, $employeeToken] = $this->user('member', 'activity-employee@example.com', 'Nhân viên nhật ký');

        $project = $this->withToken($managerToken)->postJson('/api/projects', [
            'name' => 'Dự án nhật ký đầy đủ',
            'manager_code' => $manager->user_code,
        ])->assertCreated()->json('project');

        $this->withToken($managerToken)->putJson('/api/projects/'.$project['code'], [
            'name' => 'Dự án nhật ký đã cập nhật',
            'health' => 'at_risk',
        ])->assertOk();

        $this->withToken($managerToken)->putJson('/api/projects/'.$project['code'].'/members', [
            'member_ids' => [$manager->user_code, $employee->user_code],
        ])->assertOk();

        $task = $this->withToken($managerToken)->postJson('/api/tasks', [
            'project_code' => $project['code'],
            'title' => 'Chuẩn bị tài liệu nghiệm thu',
            'assignee_code' => $employee->user_code,
        ])->assertCreated()->json('task');

        $upload = $this->withToken($managerToken)->post('/api/upload', [
            'file' => UploadedFile::fake()->create('ke-hoach.txt', 10, 'text/plain'),
            'target_type' => 'Project',
            'target_code' => $project['code'],
        ])->assertCreated();

        $attachmentCode = $upload->json('attachment.code');
        $this->withToken($employeeToken)
            ->get('/api/attachments/'.$attachmentCode.'/download')
            ->assertOk()
            ->assertDownload('ke-hoach.txt');

        $timeline = $this->withToken($employeeToken)
            ->getJson('/api/projects/'.$project['code'].'/activities?per_page=10')
            ->assertOk()
            ->assertJsonPath('total', 6)
            ->assertJsonFragment(['action' => 'tạo dự án'])
            ->assertJsonFragment(['action' => 'cập nhật dự án'])
            ->assertJsonFragment(['action' => 'thêm thành viên vào dự án'])
            ->assertJsonFragment(['action' => 'tạo nhiệm vụ', 'target_label' => $task['title']])
            ->assertJsonFragment(['action' => 'tải tệp lên'])
            ->assertJsonFragment(['action' => 'tải tệp xuống'])
            ->assertJsonFragment(['detail' => 'Thêm: Nhân viên nhật ký. Hiện có 2 thành viên trong dự án.'])
            ->assertJsonFragment(['detail' => 'Đã tải xuống tệp: ke-hoach.txt']);

        foreach ($timeline->json('data') as $activity) {
            $this->assertSame($project['code'], $activity['project_code']);
            $this->assertNotEmpty($activity['user']['name']);
            $this->assertNotEmpty($activity['created_at']);
        }

        $this->assertDatabaseHas('activities', [
            'activity_project_code' => $project['code'],
            'activity_user_code' => $employee->user_code,
            'activity_action' => 'tải tệp xuống',
        ]);
        $this->assertSame(6, Activity::where('activity_project_code', $project['code'])->count());
    }

    public function test_user_outside_project_cannot_download_or_create_a_download_activity(): void
    {
        Storage::fake('public');
        [$manager, $managerToken] = $this->user('project_manager', 'secure-manager@example.com', 'Quản lý bảo mật');
        [, $outsiderToken] = $this->user('member', 'activity-outsider@example.com', 'Nhân viên bên ngoài');

        $project = $this->withToken($managerToken)->postJson('/api/projects', [
            'name' => 'Dự án nội bộ',
            'manager_code' => $manager->user_code,
        ])->assertCreated()->json('project');
        $attachmentCode = $this->withToken($managerToken)->post('/api/upload', [
            'file' => UploadedFile::fake()->create('noi-bo.txt', 5, 'text/plain'),
            'target_type' => 'Project',
            'target_code' => $project['code'],
        ])->assertCreated()->json('attachment.code');

        $before = Activity::where('activity_project_code', $project['code'])->count();
        $this->withToken($outsiderToken)
            ->get('/api/attachments/'.$attachmentCode.'/download')
            ->assertForbidden();

        $this->assertSame($before, Activity::where('activity_project_code', $project['code'])->count());
    }

    private function user(string $role, string $email, string $name): array
    {
        $plainToken = 'token-'.sha1($email);
        $user = User::create([
            'user_name' => $name,
            'user_email' => $email,
            'user_password' => Hash::make('secure-password'),
            'user_role' => $role,
            'user_phone' => '0901234567',
            'user_department' => 'Nội bộ',
            'user_job_title' => $role === 'member' ? 'Nhân viên' : 'Quản lý dự án',
            'user_join_date' => now()->toDateString(),
            'user_profile_completed_at' => now(),
            'user_api_token' => hash('sha256', $plainToken),
        ]);

        return [$user, $plainToken];
    }
}
