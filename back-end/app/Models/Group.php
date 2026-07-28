<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\GeneratesCode;
use App\Traits\MapsAttributes;

class Group extends Model
{
    use HasFactory, GeneratesCode, MapsAttributes;

    protected $primaryKey = 'group_code';
    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'group_created_at';
    const UPDATED_AT = 'group_updated_at';

    protected $fillable = ['group_name', 'group_description'];

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
            'group_created_at' => 'created_at',
            'group_updated_at' => 'updated_at',
        ];
    }
}
