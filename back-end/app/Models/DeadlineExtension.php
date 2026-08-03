<?php

namespace App\Models;

use App\Traits\GeneratesCode;
use App\Traits\MapsAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeadlineExtension extends Model
{
    use GeneratesCode, HasFactory, MapsAttributes;

    protected $primaryKey = 'extension_code';

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'extension_created_at';

    const UPDATED_AT = 'extension_updated_at';

    protected $fillable = [
        'extension_target_type', 'extension_target_code', 'extension_old_due_date',
        'extension_new_due_date', 'extension_reason', 'extension_created_by',
    ];

    protected $casts = [
        'extension_old_due_date' => 'date:Y-m-d',
        'extension_new_due_date' => 'date:Y-m-d',
    ];

    public function getCodePrefix(): string
    {
        return 'DE';
    }

    public function getAttributeMapping(): array
    {
        return [
            'extension_code' => 'code',
            'extension_target_type' => 'target_type',
            'extension_target_code' => 'target_code',
            'extension_old_due_date' => 'old_due_date',
            'extension_new_due_date' => 'new_due_date',
            'extension_reason' => 'reason',
            'extension_created_by' => 'created_by',
            'extension_created_at' => 'created_at',
            'extension_updated_at' => 'updated_at',
        ];
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'extension_created_by', 'user_code');
    }
}
