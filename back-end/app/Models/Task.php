<?php

namespace App\Models;

use App\Traits\GeneratesCode;
use App\Traits\MapsAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use GeneratesCode, HasFactory, MapsAttributes, SoftDeletes;

    protected $primaryKey = 'task_code';

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'task_created_at';

    const UPDATED_AT = 'task_updated_at';

    const DELETED_AT = 'task_deleted_at';

    protected $casts = [
        'task_deleted_at' => 'datetime',
        'task_completed_at' => 'datetime',
        'task_blocked_override' => 'boolean',
        'task_recurrence_until' => 'date:Y-m-d',
    ];

    protected $appends = ['is_blocked', 'actual_minutes', 'remaining_hours'];

    protected $fillable = [
        'task_project_code', 'task_title', 'task_description', 'task_status',
        'task_priority', 'task_start_date', 'task_due_date', 'task_assignee_code',
        'task_progress', 'task_estimated_hours', 'task_type', 'task_tags',
        'task_milestone_code', 'task_blocked_reason', 'task_blocked_override',
        'task_recurrence', 'task_recurrence_until', 'task_completed_at',
    ];

    public function getCodePrefix()
    {
        return 'TK';
    }

    public function getAttributeMapping(): array
    {
        return [
            'task_code' => 'code',
            'task_project_code' => 'project_code',
            'task_milestone_code' => 'milestone_code',
            'task_title' => 'title',
            'task_description' => 'description',
            'task_type' => 'type',
            'task_status' => 'status',
            'task_priority' => 'priority',
            'task_start_date' => 'start_date',
            'task_due_date' => 'due_date',
            'task_assignee_code' => 'assignee_code',
            'task_tags' => 'tags',
            'task_progress' => 'progress',
            'task_blocked_reason' => 'blocked_reason',
            'task_blocked_override' => 'blocked_override',
            'task_estimated_hours' => 'estimated_hours',
            'task_recurrence' => 'recurrence',
            'task_recurrence_until' => 'recurrence_until',
            'task_completed_at' => 'completed_at',
            'task_created_at' => 'created_at',
            'task_updated_at' => 'updated_at',
            'task_deleted_at' => 'deleted_at',
        ];
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'task_project_code', 'project_code');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'task_assignee_code', 'user_code');
    }

    public function comments()
    {
        return $this->hasMany(TaskComment::class, 'comment_task_code', 'task_code');
    }

    public function checklists()
    {
        return $this->hasMany(TaskChecklist::class, 'checklist_task_code', 'task_code')
            ->orderBy('checklist_created_at');
    }

    public function workLogs()
    {
        return $this->hasMany(TaskWorkLog::class, 'worklog_task_code', 'task_code')
            ->orderByDesc('worklog_created_at');
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class, 'attachment_target_code', 'task_code')->where('attachment_target_type', 'Task');
    }

    public function milestone()
    {
        return $this->belongsTo(ProjectMilestone::class, 'task_milestone_code', 'milestone_code');
    }

    public function dependencies()
    {
        return $this->belongsToMany(Task::class, 'task_dependencies', 'dependency_task_code', 'dependency_depends_on_code', 'task_code', 'task_code');
    }

    public function blocking()
    {
        return $this->belongsToMany(Task::class, 'task_dependencies', 'dependency_depends_on_code', 'dependency_task_code', 'task_code', 'task_code');
    }

    public function watchers()
    {
        return $this->belongsToMany(User::class, 'task_watchers', 'watcher_task_code', 'watcher_user_code', 'task_code', 'user_code');
    }

    public function getIsBlockedAttribute(): bool
    {
        if ($this->task_blocked_override) {
            return false;
        }

        $dependencies = $this->relationLoaded('dependencies') ? $this->dependencies : $this->dependencies()->get();

        return $dependencies->contains(fn (Task $task) => $task->task_status !== 'done');
    }

    public function getActualMinutesAttribute(): int
    {
        return $this->relationLoaded('workLogs')
            ? (int) $this->workLogs->sum('worklog_duration_minutes')
            : (int) $this->workLogs()->sum('worklog_duration_minutes');
    }

    public function getRemainingHoursAttribute(): ?float
    {
        if ($this->task_estimated_hours === null) {
            return null;
        }

        return max(0, round((float) $this->task_estimated_hours - ($this->actual_minutes / 60), 2));
    }
}
