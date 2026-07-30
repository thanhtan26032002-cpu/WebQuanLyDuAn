<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfessionalFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->token = $this->postJson('/api/register', [
            'name' => 'Quản trị hệ thống',
            'email' => 'admin@ringnet.test',
            'password' => 'secure-password',
        ])->assertCreated()->json('token');

        User::where('user_email', 'admin@ringnet.test')->update(['user_role' => 'admin']);

        $this->withToken($this->token);
    }

    public function test_templates_planning_dependencies_automations_and_saved_views_work_together(): void
    {
        $project = $this->postJson('/api/projects', [
            'name' => 'Nền tảng mới',
            'template' => 'software',
            'status' => 'planning',
        ])->assertCreated()
            ->assertJsonCount(4, 'project.tasks')
            ->json('project');

        $this->postJson("/api/projects/{$project['code']}/updates", [
            'health' => 'at_risk',
            'completed' => 'Đã hoàn thành khảo sát.',
            'risks' => 'Thiếu dữ liệu đầu vào.',
            'next_steps' => 'Chốt phạm vi.',
        ])->assertCreated()->assertJsonPath('update.health', 'at_risk');

        $milestone = $this->postJson("/api/projects/{$project['code']}/milestones", [
            'name' => 'MVP',
            'target_date' => now()->addMonth()->toDateString(),
        ])->assertCreated()->json('milestone');

        $this->putJson('/api/tasks/TK0001', [
            'milestone_code' => $milestone['code'],
        ])->assertOk()->assertJsonPath('task.milestone.code', $milestone['code']);

        $this->putJson('/api/tasks/TK0002/dependencies', [
            'dependency_ids' => ['TK0001'],
        ])->assertOk()->assertJsonPath('dependencies.0.code', 'TK0001');

        $this->patchJson('/api/tasks/TK0002/status', ['status' => 'in_progress'])
            ->assertStatus(409);

        $this->patchJson('/api/tasks/TK0001/status', ['status' => 'done'])->assertOk();
        $this->patchJson('/api/tasks/TK0002/status', ['status' => 'in_progress'])->assertOk();

        $this->postJson("/api/projects/{$project['code']}/automations", [
            'rule' => 'completion_notify_manager',
            'enabled' => true,
            'config' => [],
        ])->assertCreated()->assertJsonPath('automation.enabled', true);

        $this->postJson('/api/saved-views', [
            'name' => 'Ưu tiên cao',
            'scope' => 'tasks',
            'filters' => ['priority' => 'high', 'status' => 'all'],
        ])->assertCreated();
        $this->getJson('/api/saved-views')
            ->assertOk()
            ->assertJsonPath('0.name', 'Ưu tiên cao');
    }

    public function test_recurring_tasks_and_actual_work_duration_are_persisted(): void
    {
        $member = User::create([
            'user_name' => 'Người thực hiện',
            'user_email' => 'worker@ringnet.test',
            'user_password' => Hash::make('test-password'),
            'user_role' => 'member',
            'user_phone' => '0901234567',
            'user_department' => 'Phát triển',
            'user_job_title' => 'Developer',
            'user_profile_completed_at' => now(),
        ]);

        $this->postJson('/api/tasks', [
            'title' => 'Báo cáo tuần',
            'assignee_code' => $member->user_code,
            'due_date' => now()->toDateString(),
            'estimated_hours' => 2,
            'recurrence' => 'weekly',
        ])->assertCreated();

        $this->postJson('/api/tasks/TK0001/work-logs', [
            'time' => '10:30',
            'duration_minutes' => 90,
            'note' => 'Đã tổng hợp số liệu.',
        ])->assertCreated()->assertJsonPath('work_log.duration_minutes', 90);

        $this->patchJson('/api/tasks/TK0001/status', ['status' => 'done'])->assertOk();

        $this->assertDatabaseCount('tasks', 2);
        $this->assertDatabaseHas('tasks', [
            'task_code' => 'TK0002',
            'task_status' => 'todo',
            'task_due_date' => now()->addWeek()->toDateString(),
        ]);
        $this->getJson('/api/reports')
            ->assertOk()
            ->assertJsonPath('estimate_vs_actual.0.actual_hours', 1.5);
    }

    public function test_non_manager_cannot_read_an_unrelated_project(): void
    {
        $project = $this->postJson('/api/projects', ['name' => 'Dự án riêng'])
            ->assertCreated()
            ->json('project');

        $memberToken = $this->postJson('/api/register', [
            'name' => 'Thành viên ngoài dự án',
            'email' => 'outsider@ringnet.test',
            'password' => 'secure-password',
        ])->assertCreated()->json('token');

        $this->withToken($memberToken)
            ->getJson("/api/projects/{$project['code']}")
            ->assertForbidden();
    }
}
