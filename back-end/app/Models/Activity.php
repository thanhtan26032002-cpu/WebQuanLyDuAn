<?php

namespace App\Models;

use App\Traits\GeneratesCode;
use App\Traits\MapsAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    use GeneratesCode, HasFactory, MapsAttributes;

    protected $primaryKey = 'activity_code';

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'activity_created_at';

    const UPDATED_AT = 'activity_updated_at';

    protected $fillable = ['activity_user_code', 'activity_project_code', 'activity_action', 'activity_target_type', 'activity_target_code', 'activity_detail'];

    public function getCodePrefix()
    {
        return 'AC';
    }

    public function getAttributeMapping(): array
    {
        return [
            'activity_code' => 'code',
            'activity_user_code' => 'user_code',
            'activity_project_code' => 'project_code',
            'activity_action' => 'action',
            'activity_target_type' => 'target_type',
            'activity_target_code' => 'target_code',
            'activity_detail' => 'detail',
            'activity_created_at' => 'created_at',
            'activity_updated_at' => 'updated_at',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'activity_user_code', 'user_code');
    }
}
