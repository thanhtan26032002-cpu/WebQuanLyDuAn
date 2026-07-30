<?php

namespace App\Models;

use App\Traits\GeneratesCode;
use App\Traits\MapsAttributes;
use Illuminate\Database\Eloquent\Model;

class ProjectMilestone extends Model
{
    use GeneratesCode, MapsAttributes;

    protected $primaryKey = 'milestone_code';

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'milestone_created_at';

    const UPDATED_AT = 'milestone_updated_at';

    protected $fillable = [
        'milestone_project_code',
        'milestone_name',
        'milestone_description',
        'milestone_target_date',
        'milestone_sort_order',
    ];

    protected $casts = [
        'milestone_target_date' => 'date:Y-m-d',
    ];

    protected $appends = ['progress'];

    public function getCodePrefix()
    {
        return 'MS';
    }

    public function getAttributeMapping(): array
    {
        return [
            'milestone_code' => 'code',
            'milestone_project_code' => 'project_code',
            'milestone_name' => 'name',
            'milestone_description' => 'description',
            'milestone_target_date' => 'target_date',
            'milestone_sort_order' => 'sort_order',
            'milestone_created_at' => 'created_at',
            'milestone_updated_at' => 'updated_at',
        ];
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'milestone_project_code', 'project_code');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'task_milestone_code', 'milestone_code');
    }

    public function getProgressAttribute(): int
    {
        $tasks = $this->relationLoaded('tasks') ? $this->tasks : $this->tasks()->get();
        if ($tasks->isEmpty()) {
            return 0;
        }

        return (int) round($tasks->avg(fn (Task $task) => $task->task_status === 'done' ? 100 : $task->task_progress));
    }
}
