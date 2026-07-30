<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('user_color', 30)->default('blue')->after('user_avatar');
            $table->string('user_job_title')->nullable()->after('user_role');
            $table->date('user_join_date')->nullable()->after('user_department');
            $table->text('user_bio')->nullable()->after('user_join_date');
            $table->boolean('user_online')->default(true)->after('user_bio');
            $table->decimal('user_weekly_capacity_hours', 6, 2)->default(40)->after('user_online');
            $table->timestamp('user_profile_completed_at')->nullable()->after('user_weekly_capacity_hours');
        });

        $this->dropForeignForColumn('projects', 'project_manager_code');
        $this->dropForeignForColumn('project_members', 'pm_member_code');
        $this->dropForeignForColumn('tasks', 'task_assignee_code');
        $this->dropForeignForColumn('task_work_logs', 'worklog_reporter_code');

        $nextUserNumber = $this->nextUserNumber();
        $memberToUser = [];

        DB::table('members')->orderBy('member_code')->get()->each(function ($member) use (&$nextUserNumber, &$memberToUser) {
            $user = DB::table('users')
                ->where('user_member_code', $member->member_code)
                ->orWhereRaw('LOWER(user_email) = ?', [mb_strtolower($member->member_email)])
                ->first();

            if (! $user) {
                $userCode = 'US'.str_pad((string) $nextUserNumber++, 4, '0', STR_PAD_LEFT);
                DB::table('users')->insert([
                    'user_code' => $userCode,
                    'user_name' => $member->member_name,
                    'user_email' => mb_strtolower($member->member_email),
                    'user_password' => Hash::make(Str::random(64)),
                    'user_role' => 'member',
                    'user_created_at' => $member->member_created_at ?? now(),
                    'user_updated_at' => $member->member_updated_at ?? now(),
                ]);
                $user = DB::table('users')->where('user_code', $userCode)->first();
            }

            $memberToUser[$member->member_code] = $user->user_code;
            $profileComplete = filled($member->member_phone)
                && filled($member->member_department)
                && filled($member->member_name);

            DB::table('users')->where('user_code', $user->user_code)->update([
                'user_name' => $user->user_name ?: $member->member_name,
                'user_avatar' => $user->user_avatar ?: $member->member_avatar,
                'user_phone' => $user->user_phone ?: $member->member_phone,
                'user_department' => $user->user_department ?: $member->member_department,
                'user_color' => $member->member_color ?: 'blue',
                'user_job_title' => $this->jobTitle($member->member_role),
                'user_join_date' => $member->member_join_date,
                'user_bio' => $member->member_bio,
                'user_online' => (bool) $member->member_online,
                'user_weekly_capacity_hours' => $member->member_weekly_capacity_hours ?? 40,
                'user_profile_completed_at' => $profileComplete ? now() : null,
            ]);
        });

        foreach ($memberToUser as $memberCode => $userCode) {
            DB::table('projects')->where('project_manager_code', $memberCode)->update(['project_manager_code' => $userCode]);
            DB::table('project_members')->where('pm_member_code', $memberCode)->update(['pm_member_code' => $userCode]);
            DB::table('tasks')->where('task_assignee_code', $memberCode)->update(['task_assignee_code' => $userCode]);
            DB::table('task_work_logs')->where('worklog_reporter_code', $memberCode)->update(['worklog_reporter_code' => $userCode]);
        }

        DB::table('groups')->orderBy('group_code')->get()->each(function ($group) use ($memberToUser) {
            $ids = json_decode($group->group_member_ids ?: '[]', true) ?: [];
            $userIds = collect($ids)
                ->map(fn ($id) => $memberToUser[$id] ?? $id)
                ->filter()
                ->unique()
                ->values()
                ->all();
            DB::table('groups')->where('group_code', $group->group_code)->update([
                'group_member_ids' => json_encode($userIds),
            ]);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreign('project_manager_code')->references('user_code')->on('users')->nullOnDelete();
        });
        Schema::table('project_members', function (Blueprint $table) {
            $table->foreign('pm_member_code')->references('user_code')->on('users')->cascadeOnDelete();
        });
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreign('task_assignee_code')->references('user_code')->on('users')->nullOnDelete();
        });
        Schema::table('task_work_logs', function (Blueprint $table) {
            $table->foreign('worklog_reporter_code')->references('user_code')->on('users')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['user_member_code']);
            $table->dropColumn('user_member_code');
        });
        Schema::dropIfExists('members');
    }

    public function down(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->string('member_code', 50)->primary();
            $table->string('member_name');
            $table->string('member_email')->unique();
            $table->string('member_avatar')->nullable();
            $table->string('member_color', 30)->default('blue');
            $table->string('member_role')->default('member');
            $table->string('member_phone', 50)->nullable();
            $table->string('member_department')->nullable();
            $table->date('member_join_date')->nullable();
            $table->text('member_bio')->nullable();
            $table->boolean('member_online')->default(true);
            $table->decimal('member_weekly_capacity_hours', 6, 2)->default(40);
            $table->timestamp('member_created_at')->nullable();
            $table->timestamp('member_updated_at')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('user_member_code', 50)->nullable()->after('user_code')->index();
        });

        DB::table('users')->orderBy('user_code')->get()->each(function ($user) {
            DB::table('members')->insert([
                'member_code' => $user->user_code,
                'member_name' => $user->user_name,
                'member_email' => $user->user_email,
                'member_avatar' => $user->user_avatar,
                'member_color' => $user->user_color,
                'member_role' => $user->user_job_title ?: 'member',
                'member_phone' => $user->user_phone,
                'member_department' => $user->user_department,
                'member_join_date' => $user->user_join_date,
                'member_bio' => $user->user_bio,
                'member_online' => $user->user_online,
                'member_weekly_capacity_hours' => $user->user_weekly_capacity_hours,
                'member_created_at' => $user->user_created_at,
                'member_updated_at' => $user->user_updated_at,
            ]);
            DB::table('users')->where('user_code', $user->user_code)->update(['user_member_code' => $user->user_code]);
        });

        $this->dropForeignForColumn('projects', 'project_manager_code');
        $this->dropForeignForColumn('project_members', 'pm_member_code');
        $this->dropForeignForColumn('tasks', 'task_assignee_code');
        $this->dropForeignForColumn('task_work_logs', 'worklog_reporter_code');

        Schema::table('projects', fn (Blueprint $table) => $table->foreign('project_manager_code')->references('member_code')->on('members')->nullOnDelete());
        Schema::table('project_members', fn (Blueprint $table) => $table->foreign('pm_member_code')->references('member_code')->on('members')->cascadeOnDelete());
        Schema::table('tasks', fn (Blueprint $table) => $table->foreign('task_assignee_code')->references('member_code')->on('members')->nullOnDelete());
        Schema::table('task_work_logs', fn (Blueprint $table) => $table->foreign('worklog_reporter_code')->references('member_code')->on('members')->nullOnDelete());

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'user_color',
                'user_job_title',
                'user_join_date',
                'user_bio',
                'user_online',
                'user_weekly_capacity_hours',
                'user_profile_completed_at',
            ]);
        });
    }

    private function nextUserNumber(): int
    {
        $lastCode = DB::table('users')->orderByDesc('user_code')->value('user_code');

        return $lastCode ? ((int) preg_replace('/\D/', '', $lastCode)) + 1 : 1;
    }

    private function dropForeignForColumn(string $table, string $column): void
    {
        if (DB::getDriverName() !== 'mysql') {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropForeign([$column]));

            return;
        }

        $constraint = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->value('CONSTRAINT_NAME');

        if ($constraint) {
            DB::statement(sprintf('ALTER TABLE `%s` DROP FOREIGN KEY `%s`', $table, $constraint));
        }
    }

    private function jobTitle(?string $memberRole): string
    {
        $role = trim((string) $memberRole);

        return in_array(mb_strtolower($role), ['', 'member', 'admin', 'project_manager', 'viewer'], true)
            ? 'Nhân viên'
            : $role;
    }
};
