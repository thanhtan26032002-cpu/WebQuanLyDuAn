<?php

namespace App\Models;

use App\Traits\GeneratesCode;
use App\Traits\MapsAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskWorkLog extends Model
{
    use HasFactory, GeneratesCode, MapsAttributes;

    protected $primaryKey = 'worklog_code';
    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'worklog_created_at';
    const UPDATED_AT = 'worklog_updated_at';

    protected $fillable = [
        'worklog_task_code', 'worklog_reporter_code', 'worklog_time',
        'worklog_note', 'worklog_date', 'worklog_completed_items', 'worklog_files',
    ];

    protected $casts = [
        'worklog_date' => 'date:Y-m-d',
        'worklog_completed_items' => 'array',
        'worklog_files' => 'array',
    ];

    public function getCodePrefix()
    {
        return 'WL';
    }

    public function getAttributeMapping(): array
    {
        return [
            'worklog_code' => 'code',
            'worklog_task_code' => 'task_code',
            'worklog_reporter_code' => 'reporter_code',
            'worklog_time' => 'time',
            'worklog_note' => 'note',
            'worklog_date' => 'date',
            'worklog_completed_items' => 'completed_items',
            'worklog_files' => 'files',
            'worklog_created_at' => 'created_at',
            'worklog_updated_at' => 'updated_at',
        ];
    }

    public function task()
    {
        return $this->belongsTo(Task::class, 'worklog_task_code', 'task_code');
    }

    public function reporter()
    {
        return $this->belongsTo(Member::class, 'worklog_reporter_code', 'member_code');
    }
}
