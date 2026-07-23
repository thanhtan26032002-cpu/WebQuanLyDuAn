<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\GeneratesCode;

class Member extends Model
{
    use HasFactory, GeneratesCode;

    protected $primaryKey = 'code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name', 'email', 'avatar', 'role', 
        'phone', 'department', 'join_date', 'bio', 'online'
    ];

    public function getCodePrefix()
    {
        return 'MB';
    }

    public function projects()
    {
        return $this->belongsToMany(Project::class, 'project_members', 'member_code', 'project_code')->withPivot('role');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'assignee_code', 'code');
    }

    public function activities()
    {
        return $this->hasMany(Activity::class, 'member_code', 'code');
    }

    public function comments()
    {
        return $this->hasMany(TaskComment::class, 'member_code', 'code');
    }
}
