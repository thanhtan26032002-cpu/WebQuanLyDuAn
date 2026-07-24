<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\GeneratesCode;

class Project extends Model
{
    use HasFactory, GeneratesCode;

    protected $primaryKey = 'code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name', 'description', 'status', 
        'start_date', 'due_date', 'created_by', 'progress'
    ];

    public function getCodePrefix()
    {
        return 'PJ';
    }

    public function members()
    {
        return $this->belongsToMany(Member::class, 'project_members', 'project_code', 'member_code')->withPivot('role');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'project_code', 'code');
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class, 'target_code', 'code')->where('target_type', 'Project');
    }
}
