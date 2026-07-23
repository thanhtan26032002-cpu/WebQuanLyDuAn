<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\GeneratesCode;

class Activity extends Model
{
    use HasFactory, GeneratesCode;

    protected $primaryKey = 'code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['user_code', 'action', 'target_type', 'target_code', 'detail'];

    public function getCodePrefix()
    {
        return 'AC';
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_code', 'code');
    }
}
