<?php

namespace App\Models;

use App\Traits\GeneratesCode;
use App\Traits\MapsAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use GeneratesCode, HasFactory, MapsAttributes, Notifiable;

    protected $primaryKey = 'user_code';

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'user_created_at';

    const UPDATED_AT = 'user_updated_at';

    protected $fillable = [
        'user_name', 'user_email', 'user_password', 'user_avatar', 'user_role',
        'user_phone', 'user_department', 'user_api_token', 'user_notification_preferences',
        'user_color', 'user_job_title', 'user_join_date', 'user_bio', 'user_online',
        'user_weekly_capacity_hours', 'user_profile_completed_at',
    ];

    protected $hidden = ['user_password', 'user_api_token', 'user_remember_token'];

    public function getCodePrefix()
    {
        return 'US';
    }

    public function getAttributeMapping(): array
    {
        return [
            'user_code' => 'code',
            'user_name' => 'name',
            'user_email' => 'email',
            'user_avatar' => 'avatar',
            'user_role' => 'role',
            'user_phone' => 'phone',
            'user_department' => 'department',
            'user_color' => 'color',
            'user_job_title' => 'job_title',
            'user_join_date' => 'join_date',
            'user_bio' => 'bio',
            'user_online' => 'online',
            'user_weekly_capacity_hours' => 'weekly_capacity_hours',
            'user_profile_completed_at' => 'profile_completed_at',
            'user_notification_preferences' => 'notification_preferences',
            'user_email_verified_at' => 'email_verified_at',
            'user_password' => 'password',
            'user_api_token' => 'api_token',
            'user_remember_token' => 'remember_token',
            'user_created_at' => 'created_at',
            'user_updated_at' => 'updated_at',
        ];
    }

    public function projects()
    {
        return $this->hasMany(Project::class, 'project_created_by', 'user_code');
    }

    public function assignedTasks()
    {
        return $this->hasMany(Task::class, 'task_assignee_code', 'user_code');
    }

    public function memberProjects()
    {
        return $this->belongsToMany(Project::class, 'project_members', 'pm_member_code', 'pm_project_code', 'user_code', 'project_code')
            ->using(ProjectMember::class)
            ->withPivot('pm_code', 'pm_role as role');
    }

    public function savedViews()
    {
        return $this->hasMany(SavedView::class, 'view_user_code', 'user_code');
    }

    protected function casts(): array
    {
        return [
            'user_email_verified_at' => 'datetime',
            'user_password' => 'hashed',
            'user_notification_preferences' => 'array',
            'user_join_date' => 'date:Y-m-d',
            'user_online' => 'boolean',
            'user_weekly_capacity_hours' => 'decimal:2',
            'user_profile_completed_at' => 'datetime',
        ];
    }
}
