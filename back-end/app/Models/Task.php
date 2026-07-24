<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\GeneratesCode;

class Task extends Model
{
    use HasFactory, GeneratesCode;

    protected $primaryKey = 'code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'project_code', 'title', 'description', 'status', 'priority', 'due_date', 'assignee_code', 'progress'
    ];

    public function getCodePrefix()
    {
        return 'TK';
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_code', 'code');
    }

    public function assignee()
    {
        return $this->belongsTo(Member::class, 'assignee_code', 'code');
    }

    public function comments()
    {
        return $this->hasMany(TaskComment::class, 'task_code', 'code');
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class, 'target_code', 'code')->where('target_type', 'Task');
    }
}
