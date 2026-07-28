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

    protected $fillable = ['user_name', 'user_email', 'user_password', 'user_avatar', 'user_role', 'user_phone', 'user_department', 'user_api_token'];

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

    protected function casts(): array
    {
        return [
            'user_email_verified_at' => 'datetime',
            'user_password' => 'hashed',
        ];
    }
}
