<?php

namespace App\Models;

use App\Traits\GeneratesCode;
use App\Traits\MapsAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory, GeneratesCode, MapsAttributes;

    protected $primaryKey = 'customer_code';
    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'customer_created_at';
    const UPDATED_AT = 'customer_updated_at';

    protected $fillable = [
        'customer_name', 'customer_company', 'customer_email',
        'customer_phone', 'customer_address', 'customer_notes',
    ];

    public function getCodePrefix()
    {
        return 'KH';
    }

    public function getAttributeMapping(): array
    {
        return [
            'customer_code' => 'code',
            'customer_name' => 'name',
            'customer_company' => 'company',
            'customer_email' => 'email',
            'customer_phone' => 'phone',
            'customer_address' => 'address',
            'customer_notes' => 'notes',
            'customer_created_at' => 'created_at',
            'customer_updated_at' => 'updated_at',
        ];
    }

    public function projects()
    {
        return $this->hasMany(Project::class, 'project_customer_code', 'customer_code');
    }
}
