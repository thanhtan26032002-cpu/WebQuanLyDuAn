<?php

namespace App\Models;

use App\Traits\GeneratesCode;
use App\Traits\MapsAttributes;
use Illuminate\Database\Eloquent\Model;

class ProjectUpdate extends Model
{
    use GeneratesCode, MapsAttributes;

    protected $primaryKey = 'update_code';

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'update_created_at';

    const UPDATED_AT = 'update_updated_at';

    protected $fillable = [
        'update_project_code',
        'update_author_code',
        'update_health',
        'update_completed',
        'update_risks',
        'update_next_steps',
    ];

    public function getCodePrefix()
    {
        return 'PU';
    }

    public function getAttributeMapping(): array
    {
        return [
            'update_code' => 'code',
            'update_project_code' => 'project_code',
            'update_author_code' => 'author_code',
            'update_health' => 'health',
            'update_completed' => 'completed',
            'update_risks' => 'risks',
            'update_next_steps' => 'next_steps',
            'update_created_at' => 'created_at',
            'update_updated_at' => 'updated_at',
        ];
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'update_project_code', 'project_code');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'update_author_code', 'user_code');
    }
}
