<?php

namespace App\Models;

use App\Traits\GeneratesCode;
use App\Traits\MapsAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    use GeneratesCode, HasFactory, MapsAttributes;

    protected $primaryKey = 'group_code';

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'group_created_at';

    const UPDATED_AT = 'group_updated_at';

    protected $fillable = [
        'group_name', 'group_description', 'group_icon', 'group_color', 'group_member_ids',
    ];

    protected $casts = [
        'group_member_ids' => 'array',
    ];

    public function getCodePrefix()
    {
        return 'GR';
    }

    public function getAttributeMapping(): array
    {
        return [
            'group_code' => 'code',
            'group_name' => 'name',
            'group_description' => 'description',
            'group_icon' => 'icon',
            'group_color' => 'color',
            'group_member_ids' => 'member_ids',
            'group_created_at' => 'created_at',
            'group_updated_at' => 'updated_at',
        ];
    }
}
