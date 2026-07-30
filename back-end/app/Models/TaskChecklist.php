<?php

namespace App\Models;

use App\Traits\GeneratesCode;
use App\Traits\MapsAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskChecklist extends Model
{
    use HasFactory, GeneratesCode, MapsAttributes;

    protected $primaryKey = 'checklist_code';
    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'checklist_created_at';
    const UPDATED_AT = 'checklist_updated_at';

    protected $fillable = [
        'checklist_task_code', 'checklist_text', 'checklist_is_completed',
    ];

    protected $casts = [
        'checklist_is_completed' => 'boolean',
    ];

    public function getCodePrefix()
    {
        return 'CK';
    }

    public function getAttributeMapping(): array
    {
        return [
            'checklist_code' => 'code',
            'checklist_task_code' => 'task_code',
            'checklist_text' => 'text',
            'checklist_is_completed' => 'completed',
            'checklist_created_at' => 'created_at',
            'checklist_updated_at' => 'updated_at',
        ];
    }

    public function task()
    {
        return $this->belongsTo(Task::class, 'checklist_task_code', 'task_code');
    }
}
