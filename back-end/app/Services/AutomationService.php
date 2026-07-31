<?php

namespace App\Services;

use App\Models\Notification;
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
        $count = 0;
        Task::with(['project.automations', 'assignee'])
            ->where('task_status', '!=', 'done')
            ->whereNotNull('task_due_date')
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
                    $message = $task->task_title.' có hạn vào '.$task->task_due_date.'.';
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
}
