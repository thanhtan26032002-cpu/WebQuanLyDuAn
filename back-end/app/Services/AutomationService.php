<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

class AutomationService
{
    public static function taskStatusChanged(Task $task, string $previousStatus): void
    {
        $project = $task->project;
        if (! $project) {
            return;
        }

        $rules = $project->automations()->where('automation_enabled', true)->get();
        if ($task->task_status === 'done' && $previousStatus !== 'done' && $rules->contains('automation_rule', 'completion_notify_manager')) {
            $managerUser = $project->manager;
            if ($managerUser) {
                ActivityService::notify($managerUser->user_code, 'Nhiệm vụ đã hoàn thành', $task->task_title.' đã được hoàn thành.', 'success');
            }
        }

        $handover = $rules->firstWhere('automation_rule', 'status_handover');
        if ($handover) {
            $config = $handover->automation_config ?? [];
            $nextAssignee = ! empty($config['assignee_code']) ? User::find($config['assignee_code']) : null;
            if (($config['status'] ?? null) === $task->task_status && $nextAssignee) {
                $task->update(['task_assignee_code' => $nextAssignee->user_code]);
                $project->members()->syncWithoutDetaching([$nextAssignee->user_code]);
                if (($nextAssignee->user_notification_preferences['assignment'] ?? true) !== false) {
                    ActivityService::notify(
                        $nextAssignee->user_code,
                        'Nhiệm vụ được bàn giao',
                        'Bạn đã được bàn giao nhiệm vụ: '.$task->task_title,
                        'info'
                    );
                }
            }
        }
    }

    public static function sendDeadlineReminders(): int
    {
        return self::sendUpcomingTaskReminders()
            + self::sendOverdueTaskAlerts()
            + self::sendOverdueProjectAlerts();
    }

    private static function sendUpcomingTaskReminders(): int
    {
        $count = 0;
        Task::with(['project.automations', 'assignee'])
            ->where('task_status', '!=', 'done')
            ->whereNotNull('task_due_date')
            ->whereDate('task_due_date', '>=', now()->toDateString())
            ->whereDate('task_due_date', '<=', now()->addDay()->toDateString())
            ->chunkById(100, function ($tasks) use (&$count) {
                foreach ($tasks as $task) {
                    if (! $task->project?->automations->contains(fn ($automation) => $automation->automation_rule === 'deadline_reminder' && $automation->automation_enabled)) {
                        continue;
                    }
                    $user = $task->assignee;
                    if (! $user || (($user->user_notification_preferences['deadline'] ?? true) === false)) {
                        continue;
                    }
                    $message = $task->task_title.' có hạn vào '.$task->task_due_date->toDateString().'.';
                    $alreadySent = Notification::where('notif_user_code', $user->user_code)
                        ->where('notif_message', $message)
                        ->whereDate('notif_created_at', now()->toDateString())
                        ->exists();
                    if (! $alreadySent) {
                        ActivityService::notify($user->user_code, 'Nhắc hạn nhiệm vụ', $message, 'warning');
                        $count++;
                    }
                }
            }, 'task_code');

        return $count;
    }

    private static function sendOverdueTaskAlerts(): int
    {
        $count = 0;
        $admins = User::where('user_role', 'admin')->get();

        Task::with(['project.manager', 'assignee'])
            ->where('task_status', '!=', 'done')
            ->whereNotNull('task_due_date')
            ->whereDate('task_due_date', '<', now()->toDateString())
            ->chunkById(100, function ($tasks) use (&$count, $admins) {
                foreach ($tasks as $task) {
                    $level = self::overdueLevel($task->overdue_days);
                    if (! $level) {
                        continue;
                    }

                    $recipients = collect([$task->assignee])->filter();
                    if ($level >= 3 || $recipients->isEmpty()) {
                        $recipients->push($task->project?->manager);
                    }
                    if ($level >= 7) {
                        $recipients = $recipients->merge($admins);
                    }

                    $message = 'Nhiệm vụ "'.$task->task_title.'" đã vượt mức cảnh báo quá hạn '
                        .$level.' ngày (hạn '.$task->task_due_date->toDateString().').';
                    foreach ($recipients->filter()->unique('user_code') as $recipient) {
                        if (self::deadlineNotificationsEnabled($recipient)
                            && self::notifyOnce($recipient->user_code, 'Cảnh báo nhiệm vụ quá hạn', $message, 'danger')) {
                            $count++;
                        }
                    }
                }
            }, 'task_code');

        return $count;
    }

    private static function sendOverdueProjectAlerts(): int
    {
        $count = 0;
        $admins = User::where('user_role', 'admin')->get();

        Project::with('manager')
            ->where('project_status', '!=', 'completed')
            ->whereNotNull('project_due_date')
            ->whereDate('project_due_date', '<', now()->toDateString())
            ->chunkById(100, function ($projects) use (&$count, $admins) {
                foreach ($projects as $project) {
                    $level = self::overdueLevel($project->overdue_days);
                    if (! $level) {
                        continue;
                    }

                    $recipients = collect([$project->manager])->filter();
                    if ($level >= 7 || $recipients->isEmpty()) {
                        $recipients = $recipients->merge($admins);
                    }

                    $message = 'Dự án "'.$project->project_name.'" đã vượt mức cảnh báo quá hạn '
                        .$level.' ngày (hạn '.$project->project_due_date->toDateString().').';
                    foreach ($recipients->filter()->unique('user_code') as $recipient) {
                        if (self::deadlineNotificationsEnabled($recipient)
                            && self::notifyOnce($recipient->user_code, 'Cảnh báo dự án quá hạn', $message, 'danger')) {
                            $count++;
                        }
                    }
                }
            }, 'project_code');

        return $count;
    }

    private static function overdueLevel(int $days): ?int
    {
        return match (true) {
            $days >= 7 => 7,
            $days >= 3 => 3,
            $days >= 1 => 1,
            default => null,
        };
    }

    private static function deadlineNotificationsEnabled(User $user): bool
    {
        return ($user->user_notification_preferences['deadline'] ?? true) !== false;
    }

    private static function notifyOnce(string $userCode, string $title, string $message, string $type): bool
    {
        if (Notification::where('notif_user_code', $userCode)->where('notif_message', $message)->exists()) {
            return false;
        }

        ActivityService::notify($userCode, $title, $message, $type);

        return true;
    }
}
