<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\GeneratesCode;

class Attachment extends Model
{
    use HasFactory, GeneratesCode;

    protected $primaryKey = 'code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['file_name', 'file_path', 'mime_type', 'size_bytes', 'target_type', 'target_code', 'uploaded_by'];

    public function getCodePrefix()
    {
        return 'AT';
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by', 'code');
    }
}
