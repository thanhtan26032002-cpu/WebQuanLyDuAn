<?php

namespace App\Services;

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
            default => 'member',
        };
    }

    public static function isAdmin(User $user): bool
    {
        return self::role($user) === 'admin';
    }

    public static function canManageOrganization(User $user): bool
    {
        return self::isAdmin($user);
    }

    public static function canCreateProjects(User $user): bool
    {
        return in_array(self::role($user), ['admin', 'project_manager'], true);
    }

    public static function canCreateTasks(User $user): bool
    {
        return in_array(self::role($user), ['admin', 'project_manager', 'member'], true);
    }

    public static function canCreateTaskInProject(User $user, Project $project): bool
    {
        return self::canCreateTasks($user) && self::canViewProject($user, $project);
    }

    public static function canViewPrivateProfiles(User $user): bool
    {
        return self::canCreateProjects($user);
    }

    public static function canViewReports(User $user): bool
    {
        return self::canCreateProjects($user);
    }

    public static function canManagePeople(User $user): bool
    {
        return self::canCreateProjects($user);
    }

    public static function userCode(User $user): string
    {
        return $user->user_code;
    }

    public static function scopeProjects(Builder $query, User $user): Builder
    {
        if (self::isAdmin($user)) {
            return $query;
        }

        $userCode = self::userCode($user);

        return $query->where(function (Builder $visible) use ($user, $userCode) {
            $visible->where('project_created_by', $user->user_code);
            $visible->orWhere('project_manager_code', $userCode)
                ->orWhereHas('members', fn (Builder $members) => $members->where('users.user_code', $userCode));
        });
    }

    public static function canViewProject(User $user, Project $project): bool
    {
        return self::scopeProjects(Project::query()->whereKey($project->getKey()), $user)->exists();
    }

    public static function canManageProject(User $user, Project $project): bool
    {
        if (self::isAdmin($user)) {
            return true;
        }

        if (self::role($user) !== 'project_manager') {
            return false;
        }

        $userCode = self::userCode($user);

        return $project->project_created_by === $userCode
            || $project->project_manager_code === $userCode;
    }

    public static function scopeManagedProjects(Builder $query, User $user): Builder
    {
        if (self::isAdmin($user)) {
            return $query;
        }

        $userCode = self::userCode($user);

        return $query->where(function (Builder $managed) use ($userCode) {
            $managed->where('project_created_by', $userCode)
                ->orWhere(function (Builder $assigned) use ($userCode) {
                    $assigned->where('project_manager_code', $userCode)
                        ->whereHas('manager', fn (Builder $manager) => $manager->where('user_role', 'project_manager'));
                });
        });
    }

    public static function canViewTask(User $user, Task $task): bool
    {
        if (self::isAdmin($user)) {
            return true;
        }

        if ($task->task_assignee_code === self::userCode($user)) {
            return true;
        }

        if (! $task->task_project_code && $task->task_created_by === self::userCode($user)) {
            return true;
        }

        if ($task->task_project_code) {
            $project = $task->project;

            return $project && self::canViewProject($user, $project);
        }

        return false;
    }

    public static function canManageTask(User $user, Task $task): bool
    {
        if (self::isAdmin($user)) {
            return true;
        }

        if ($task->task_project_code) {
            return $task->project && self::canManageProject($user, $task->project);
        }

        return $task->task_created_by === self::userCode($user);
    }

    public static function canContributeToTask(User $user, Task $task): bool
    {
        return self::canManageTask($user, $task)
            || $task->task_assignee_code === self::userCode($user);
    }

    public static function authorize(bool $allowed, string $message = 'Bạn không có quyền thực hiện thao tác này.'): void
    {
        abort_unless($allowed, 403, $message);
    }
}
