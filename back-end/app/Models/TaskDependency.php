<?php

namespace App\Models;

use App\Traits\GeneratesCode;
use Illuminate\Database\Eloquent\Model;

class TaskDependency extends Model
{
    use GeneratesCode;

    protected $primaryKey = 'dependency_code';

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'dependency_created_at';

    const UPDATED_AT = 'dependency_updated_at';

    protected $fillable = ['dependency_task_code', 'dependency_depends_on_code'];

    public function getCodePrefix()
    {
        return 'DP';
    }
}
