<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\GeneratesCode;
use App\Traits\MapsAttributes;

class Project extends Model
{
    use HasFactory, GeneratesCode, MapsAttributes, SoftDeletes;

    protected $primaryKey = 'project_code';
    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'project_created_at';
    const UPDATED_AT = 'project_updated_at';
    const DELETED_AT = 'project_deleted_at';

    protected $casts = [
        'project_deleted_at' => 'datetime',
    ];

    protected $fillable = [
        'project_name', 'project_description', 'project_color', 'project_status',
        'project_start_date', 'project_due_date', 'project_created_by', 'project_progress',
        'project_customer_code', 'project_manager_code'
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
            'project_start_date' => 'start_date',
            'project_due_date' => 'due_date',
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
        return $this->belongsToMany(Member::class, 'project_members', 'pm_project_code', 'pm_member_code', 'project_code', 'member_code')
                    ->using(ProjectMember::class)
                    ->withPivot('pm_code', 'pm_role as role');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'project_customer_code', 'customer_code');
    }

    public function manager()
    {
        return $this->belongsTo(Member::class, 'project_manager_code', 'member_code');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'task_project_code', 'project_code');
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class, 'attachment_target_code', 'project_code')->where('attachment_target_type', 'Project');
    }
}
