<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AccessService
{
    public static function role(User $user): string
    {
        $role = mb_strtolower(trim((string) $user->user_role));

        return match (true) {
            in_array($role, ['admin', 'administrator', 'quản trị viên'], true) => 'admin',
            in_array($role, ['project_manager', 'manager', 'quản lý', 'quản lý dự án'], true) => 'project_manager',
            in_array($role, ['viewer', 'guest', 'client', 'người xem', 'khách hàng'], true) => 'viewer',
            default => 'member',
        };
    }

    public static function isAdmin(User $user): bool
    {
        return self::role($user) === 'admin';
    }

    public static function canManagePeople(User $user): bool
    {
        return in_array(self::role($user), ['admin', 'project_manager'], true);
    }

    public static function memberCode(User $user): ?string
    {
        if ($user->user_member_code) {
            return $user->user_member_code;
        }

        return $user->member()->value('member_code')
            ?: Member::whereRaw('LOWER(member_email) = ?', [mb_strtolower($user->user_email)])
                ->value('member_code');
    }

    public static function scopeProjects(Builder $query, User $user): Builder
    {
        if (self::isAdmin($user)) {
            return $query;
        }

        $memberCode = self::memberCode($user);

        return $query->where(function (Builder $visible) use ($user, $memberCode) {
            $visible->where('project_created_by', $user->user_code);
            if ($memberCode) {
                $visible->orWhere('project_manager_code', $memberCode)
                    ->orWhereHas('members', fn (Builder $members) => $members->where('members.member_code', $memberCode));
            }
        });
    }

    public static function canViewProject(User $user, Project $project): bool
    {
        return self::scopeProjects(Project::query()->whereKey($project->getKey()), $user)->exists();
    }

    public static function canManageProject(User $user, Project $project): bool
    {
        if (self::isAdmin($user) || $project->project_created_by === $user->user_code) {
            return true;
        }

        if (self::role($user) !== 'project_manager') {
            return false;
        }

        $memberCode = self::memberCode($user);

        return $memberCode !== null && (
            $project->project_manager_code === $memberCode
            || $project->members()->where('members.member_code', $memberCode)->exists()
        );
    }

    public static function canViewTask(User $user, Task $task): bool
    {
        if (self::isAdmin($user)) {
            return true;
        }

        $memberCode = self::memberCode($user);
        if ($task->task_project_code) {
            $project = $task->project;

            return $project && self::canViewProject($user, $project);
        }

        return $memberCode !== null && $task->task_assignee_code === $memberCode;
    }

    public static function canEditTask(User $user, Task $task): bool
    {
        if (self::isAdmin($user)) {
            return true;
        }

        if ($task->task_project_code && $task->project && self::canManageProject($user, $task->project)) {
            return true;
        }

        return self::role($user) === 'member'
            && self::memberCode($user) !== null
            && $task->task_assignee_code === self::memberCode($user);
    }

    public static function authorize(bool $allowed, string $message = 'Bạn không có quyền thực hiện thao tác này.'): void
    {
        abort_unless($allowed, 403, $message);
    }
}
