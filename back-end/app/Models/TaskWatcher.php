<?php

namespace App\Models;

use App\Traits\GeneratesCode;
use Illuminate\Database\Eloquent\Model;

class TaskWatcher extends Model
{
    use GeneratesCode;

    protected $primaryKey = 'watcher_code';

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'watcher_created_at';

    const UPDATED_AT = 'watcher_updated_at';

    protected $fillable = ['watcher_task_code', 'watcher_user_code'];

    public function getCodePrefix()
    {
        return 'TW';
    }
}
