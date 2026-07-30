<?php

namespace App\Models;

use App\Traits\GeneratesCode;
use App\Traits\MapsAttributes;
use Illuminate\Database\Eloquent\Model;

class SavedView extends Model
{
    use GeneratesCode, MapsAttributes;

    protected $primaryKey = 'view_code';

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'view_created_at';

    const UPDATED_AT = 'view_updated_at';

    protected $fillable = [
        'view_user_code',
        'view_name',
        'view_scope',
        'view_filters',
        'view_is_favorite',
    ];

    protected $casts = [
        'view_filters' => 'array',
        'view_is_favorite' => 'boolean',
    ];

    public function getCodePrefix()
    {
        return 'SV';
    }

    public function getAttributeMapping(): array
    {
        return [
            'view_code' => 'code',
            'view_user_code' => 'user_code',
            'view_name' => 'name',
            'view_scope' => 'scope',
            'view_filters' => 'filters',
            'view_is_favorite' => 'is_favorite',
            'view_created_at' => 'created_at',
            'view_updated_at' => 'updated_at',
        ];
    }
}
