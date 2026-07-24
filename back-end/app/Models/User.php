<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Traits\GeneratesCode;

class User extends Authenticatable
{
    use HasFactory, Notifiable, GeneratesCode;

    protected $primaryKey = 'code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['name', 'email', 'password', 'avatar', 'role'];

    protected $hidden = ['password', 'remember_token'];

    public function getCodePrefix()
    {
        return 'US';
    }

    public function projects()
    {
        return $this->hasMany(Project::class, 'created_by', 'code');
    }

    public function assignedTasks()
    {
        return $this->hasMany(Task::class, 'assignee_code', 'code');
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
