<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\GeneratesCode;
use App\Traits\MapsAttributes;

class Task extends Model
{
    use HasFactory, GeneratesCode, MapsAttributes;

    protected $primaryKey = 'task_code';
    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'task_created_at';
    const UPDATED_AT = 'task_updated_at';

    protected $fillable = [
        'task_project_code', 'task_title', 'task_description', 'task_status', 
        'task_priority', 'task_start_date', 'task_due_date', 'task_assignee_code',
        'task_progress', 'task_estimated_hours', 'task_type', 'task_tags'
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
            'task_estimated_hours' => 'estimated_hours',
            'task_created_at' => 'created_at',
            'task_updated_at' => 'updated_at',
        ];
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'task_project_code', 'project_code');
    }

    public function assignee()
    {
        return $this->belongsTo(Member::class, 'task_assignee_code', 'member_code');
    }

    public function comments()
    {
        return $this->hasMany(TaskComment::class, 'comment_task_code', 'task_code');
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class, 'attachment_target_code', 'task_code')->where('attachment_target_type', 'Task');
    }
}
