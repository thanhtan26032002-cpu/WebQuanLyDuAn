<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\GeneratesCode;
use App\Traits\MapsAttributes;

class Member extends Model
{
    use HasFactory, GeneratesCode, MapsAttributes;

    protected $primaryKey = 'member_code';
    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'member_created_at';
    const UPDATED_AT = 'member_updated_at';

    protected $fillable = [
        'member_name', 'member_email', 'member_avatar', 'member_role', 
        'member_phone', 'member_department', 'member_join_date', 'member_bio', 'member_online'
    ];

    public function getCodePrefix()
    {
        return 'MB';
    }

    public function getAttributeMapping(): array
    {
        return [
            'member_code' => 'code',
            'member_name' => 'name',
            'member_email' => 'email',
            'member_avatar' => 'avatar',
            'member_role' => 'role',
            'member_phone' => 'phone',
            'member_department' => 'department',
            'member_join_date' => 'join_date',
            'member_bio' => 'bio',
            'member_online' => 'online',
            'member_created_at' => 'created_at',
            'member_updated_at' => 'updated_at',
        ];
    }

    public function projects()
    {
        return $this->belongsToMany(Project::class, 'project_members', 'pm_member_code', 'pm_project_code', 'member_code', 'project_code')
                    ->using(ProjectMember::class)
                    ->withPivot('pm_code', 'pm_role as role');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'task_assignee_code', 'member_code');
    }

    public function activities()
    {
        return $this->hasMany(Activity::class, 'activity_user_code', 'member_code');
    }

    public function comments()
    {
        return $this->hasMany(TaskComment::class, 'comment_user_code', 'member_code');
    }
}
