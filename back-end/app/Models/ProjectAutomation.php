<?php

namespace App\Models;

use App\Traits\GeneratesCode;
use App\Traits\MapsAttributes;
use Illuminate\Database\Eloquent\Model;

class ProjectAutomation extends Model
{
    use GeneratesCode, MapsAttributes;

    protected $primaryKey = 'automation_code';

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'automation_created_at';

    const UPDATED_AT = 'automation_updated_at';

    protected $fillable = [
        'automation_project_code',
        'automation_rule',
        'automation_enabled',
        'automation_config',
    ];

    protected $casts = [
        'automation_enabled' => 'boolean',
        'automation_config' => 'array',
    ];

    public function getCodePrefix()
    {
        return 'AU';
    }

    public function getAttributeMapping(): array
    {
        return [
            'automation_code' => 'code',
            'automation_project_code' => 'project_code',
            'automation_rule' => 'rule',
            'automation_enabled' => 'enabled',
            'automation_config' => 'config',
            'automation_created_at' => 'created_at',
            'automation_updated_at' => 'updated_at',
        ];
    }
}
