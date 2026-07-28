<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\GeneratesCode;
use App\Traits\MapsAttributes;

class ProjectMember extends Pivot
{
    use HasFactory, GeneratesCode, MapsAttributes;

    protected $table = 'project_members';
    protected $primaryKey = 'pm_code';
    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'pm_created_at';
    const UPDATED_AT = 'pm_updated_at';

    protected $fillable = ['pm_code', 'pm_project_code', 'pm_member_code', 'pm_role'];

    public function getCodePrefix()
    {
        return 'PM';
    }

    public function getAttributeMapping(): array
    {
        return [
            'pm_code' => 'code',
            'pm_project_code' => 'project_code',
            'pm_member_code' => 'member_code',
            'pm_role' => 'role',
            'pm_created_at' => 'created_at',
            'pm_updated_at' => 'updated_at',
        ];
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'pm_project_code', 'project_code');
    }

    public function member()
    {
        return $this->belongsTo(Member::class, 'pm_member_code', 'member_code');
    }
}
