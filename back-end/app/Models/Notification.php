<?php

namespace App\Models;

use App\Traits\GeneratesCode;
use App\Traits\MapsAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use GeneratesCode, HasFactory, MapsAttributes;

    protected $primaryKey = 'notif_code';

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'notif_created_at';

    const UPDATED_AT = 'notif_updated_at';

    protected $fillable = [
        'notif_user_code',
        'notif_title',
        'notif_message',
        'notif_type',
        'notif_target_type',
        'notif_target_code',
        'notif_is_read',
    ];

    protected $casts = [
        'notif_is_read' => 'boolean',
    ];

    public function getCodePrefix()
    {
        return 'NO';
    }

    public function getAttributeMapping(): array
    {
        return [
            'notif_code' => 'code',
            'notif_user_code' => 'user_code',
            'notif_title' => 'title',
            'notif_message' => 'message',
            'notif_type' => 'type',
            'notif_target_type' => 'target_type',
            'notif_target_code' => 'target_code',
            'notif_is_read' => 'is_read',
            'notif_created_at' => 'created_at',
            'notif_updated_at' => 'updated_at',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'notif_user_code', 'user_code');
    }
}
