<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\GeneratesCode;

class TaskComment extends Model
{
    use HasFactory, GeneratesCode;

    protected $primaryKey = 'code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['task_code', 'member_code', 'text', 'file_url', 'file_name'];

    public function getCodePrefix()
    {
        return 'TC';
    }

    public function member()
    {
        return $this->belongsTo(Member::class, 'member_code', 'code');
    }

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_code', 'code');
    }
}
