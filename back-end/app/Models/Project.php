<?php

namespace App\Models;

use App\Traits\GeneratesCode;
use App\Traits\MapsAttributes;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use GeneratesCode, HasFactory, MapsAttributes, SoftDeletes;

    protected $primaryKey = 'project_code';

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'project_created_at';

    const UPDATED_AT = 'project_updated_at';

    const DELETED_AT = 'project_deleted_at';

    protected $casts = [
        'project_deleted_at' => 'datetime',
        'project_start_date' => 'date:Y-m-d',
        'project_due_date' => 'date:Y-m-d',
        'project_completed_at' => 'datetime',
    ];

    protected $appends = ['deadline_state', 'overdue_days', 'late_days'];

    protected $fillable = [
        'project_name', 'project_description', 'project_color', 'project_status',
        'project_start_date', 'project_due_date', 'project_created_by', 'project_progress',
        'project_customer_code', 'project_manager_code', 'project_health',
        'project_update_cadence', 'project_delay_reason', 'project_recovery_plan',
        'project_completed_at',
    ];

    public function getCodePrefix()
    {
        return 'PJ';
    }

    public function getAttributeMapping(): array
    {
        return [
            'project_code' => 'code',
            'project_customer_code' => 'customer_code',
            'project_name' => 'name',
            'project_description' => 'description',
            'project_color' => 'color',
            'project_status' => 'status',
            'project_health' => 'health',
            'project_update_cadence' => 'update_cadence',
            'project_start_date' => 'start_date',
            'project_due_date' => 'due_date',
            'project_delay_reason' => 'delay_reason',
            'project_recovery_plan' => 'recovery_plan',
            'project_completed_at' => 'completed_at',
            'project_progress' => 'progress',
            'project_created_by' => 'created_by',
            'project_manager_code' => 'manager_code',
            'project_created_at' => 'created_at',
            'project_updated_at' => 'updated_at',
            'project_deleted_at' => 'deleted_at',
        ];
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'project_members', 'pm_project_code', 'pm_member_code', 'project_code', 'user_code')
            ->using(ProjectMember::class)
            ->withPivot('pm_code', 'pm_role as role');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'project_customer_code', 'customer_code');
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'project_manager_code', 'user_code');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'task_project_code', 'project_code');
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class, 'attachment_target_code', 'project_code')->where('attachment_target_type', 'Project');
    }

    public function updates()
    {
        return $this->hasMany(ProjectUpdate::class, 'update_project_code', 'project_code')
            ->orderByDesc('update_created_at');
    }

    public function milestones()
    {
        return $this->hasMany(ProjectMilestone::class, 'milestone_project_code', 'project_code')
            ->orderBy('milestone_sort_order')
            ->orderBy('milestone_target_date');
    }

    public function automations()
    {
        return $this->hasMany(ProjectAutomation::class, 'automation_project_code', 'project_code');
    }

    public function deadlineExtensions()
    {
        return $this->hasMany(DeadlineExtension::class, 'extension_target_code', 'project_code')
            ->where('extension_target_type', 'Project')
            ->orderByDesc('extension_created_at');
    }

    public function getOverdueDaysAttribute(): int
    {
        if (! $this->project_due_date) {
            return 0;
        }

        $dueDate = Carbon::parse($this->project_due_date)->startOfDay();
        $endDate = $this->project_status === 'completed' && $this->project_completed_at
            ? Carbon::parse($this->project_completed_at)->startOfDay()
            : now()->startOfDay();

        return $endDate->greaterThan($dueDate) ? (int) $dueDate->diffInDays($endDate) : 0;
    }

    public function getLateDaysAttribute(): int
    {
        return $this->project_status === 'completed' ? $this->overdue_days : 0;
    }

    public function getDeadlineStateAttribute(): string
    {
        if (! $this->project_due_date) {
            return 'none';
        }
        if ($this->project_status === 'completed') {
            return $this->late_days > 0 ? 'completed_late' : 'completed_on_time';
        }
        if ($this->overdue_days > 0) {
            return 'overdue';
        }

        return Carbon::parse($this->project_due_date)->isToday() ? 'due' : 'normal';
    }
}
