<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\GeneratesCode;
use App\Traits\MapsAttributes;

class TaskComment extends Model
{
    use HasFactory, GeneratesCode, MapsAttributes;

    protected $primaryKey = 'comment_code';
    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'comment_created_at';
    const UPDATED_AT = 'comment_updated_at';

    protected $fillable = ['comment_task_code', 'comment_user_code', 'comment_text', 'comment_file_url', 'comment_file_name'];

    public function getCodePrefix()
    {
        return 'TC';
    }

    public function getAttributeMapping(): array
    {
        return [
            'comment_code' => 'code',
            'comment_task_code' => 'task_code',
            'comment_user_code' => 'user_code',
            'comment_text' => 'text',
            'comment_file_url' => 'file_url',
            'comment_file_name' => 'file_name',
            'comment_created_at' => 'created_at',
            'comment_updated_at' => 'updated_at',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'comment_user_code', 'user_code');
    }

    public function task()
    {
        return $this->belongsTo(Task::class, 'comment_task_code', 'task_code');
    }
}
