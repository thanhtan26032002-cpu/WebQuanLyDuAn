<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProjectNotesTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_participants_can_create_and_manage_their_notes(): void
    {
        [$manager, $managerToken] = $this->user('project_manager', 'notes-manager@example.com');
        [$member, $memberToken] = $this->user('member', 'notes-member@example.com');

        $project = $this->withToken($managerToken)->postJson('/api/projects', [
            'name' => 'Dự án có ghi chú',
            'manager_code' => $manager->user_code,
            'member_ids' => [$member->user_code],
        ])->assertCreated()->json('project');

        $note = $this->withToken($memberToken)
            ->postJson('/api/projects/'.$project['code'].'/notes', [
                'title' => 'Quyết định quan trọng',
                'content' => 'Thống nhất bàn giao bản thử nghiệm vào thứ Sáu.',
                'is_pinned' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('note.author.code', $member->user_code)
            ->assertJsonPath('note.is_pinned', true)
            ->json('note');

        $this->withToken($memberToken)
            ->putJson('/api/projects/'.$project['code'].'/notes/'.$note['code'], [
                'title' => 'Quyết định đã cập nhật',
                'content' => 'Bàn giao bản thử nghiệm lúc 16:00 thứ Sáu.',
                'is_pinned' => false,
            ])
            ->assertOk()
            ->assertJsonPath('note.title', 'Quyết định đã cập nhật');

        $this->withToken($managerToken)
            ->putJson('/api/projects/'.$project['code'].'/notes/'.$note['code'], [
                'is_pinned' => true,
            ])
            ->assertOk()
            ->assertJsonPath('note.is_pinned', true);

        $this->withToken($memberToken)
            ->getJson('/api/projects/'.$project['code'].'/notes')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.title', 'Quyết định đã cập nhật');

        $this->assertDatabaseHas('project_notes', [
            'note_code' => $note['code'],
            'note_project_code' => $project['code'],
            'note_author_code' => $member->user_code,
            'note_is_pinned' => true,
        ]);
        $this->assertSame(
            3,
            Activity::where('activity_project_code', $project['code'])
                ->whereIn('activity_action', [
                    'thêm ghi chú dự án',
                    'cập nhật ghi chú dự án',
                ])->count()
        );
    }

    public function test_outsider_can_read_notes_but_cannot_create_or_change_them(): void
    {
        [$manager, $managerToken] = $this->user('project_manager', 'notes-owner@example.com');
        [$member, $memberToken] = $this->user('member', 'notes-author@example.com');
        [, $outsiderToken] = $this->user('member', 'notes-outsider@example.com');

        $project = $this->withToken($managerToken)->postJson('/api/projects', [
            'name' => 'Dự án nội bộ minh bạch',
            'manager_code' => $manager->user_code,
            'member_ids' => [$member->user_code],
        ])->assertCreated()->json('project');

        $note = $this->withToken($memberToken)->postJson('/api/projects/'.$project['code'].'/notes', [
            'title' => 'Nội dung cần biết',
            'content' => 'Ghi chú dùng chung trong công ty.',
        ])->assertCreated()->json('note');

        $this->withToken($outsiderToken)
            ->getJson('/api/projects/'.$project['code'].'/notes')
            ->assertOk()
            ->assertJsonPath('0.code', $note['code']);

        $this->withToken($outsiderToken)
            ->postJson('/api/projects/'.$project['code'].'/notes', [
                'title' => 'Không hợp lệ',
                'content' => 'Không phải thành viên.',
            ])->assertForbidden();

        $this->withToken($outsiderToken)
            ->putJson('/api/projects/'.$project['code'].'/notes/'.$note['code'], [
                'content' => 'Không được sửa.',
            ])->assertForbidden();

        $this->withToken($outsiderToken)
            ->deleteJson('/api/projects/'.$project['code'].'/notes/'.$note['code'])
            ->assertForbidden();

        $this->withToken($managerToken)
            ->deleteJson('/api/projects/'.$project['code'].'/notes/'.$note['code'])
            ->assertOk();
        $this->assertDatabaseMissing('project_notes', ['note_code' => $note['code']]);
        $this->assertDatabaseHas('activities', [
            'activity_project_code' => $project['code'],
            'activity_action' => 'xóa ghi chú dự án',
        ]);
    }

    private function user(string $role, string $email): array
    {
        $plainToken = 'token-'.sha1($email);
        $user = User::create([
            'user_name' => strstr($email, '@', true),
            'user_email' => $email,
            'user_password' => Hash::make('secure-password'),
            'user_role' => $role,
            'user_department' => 'Nội bộ',
            'user_job_title' => $role === 'member' ? 'Nhân viên' : 'Quản lý dự án',
            'user_join_date' => now()->toDateString(),
            'user_profile_completed_at' => now(),
            'user_api_token' => hash('sha256', $plainToken),
        ]);

        return [$user, $plainToken];
    }
}
