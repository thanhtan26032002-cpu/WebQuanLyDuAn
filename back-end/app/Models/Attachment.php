<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\GeneratesCode;
use App\Traits\MapsAttributes;

class Attachment extends Model
{
    use HasFactory, GeneratesCode, MapsAttributes;

    protected $primaryKey = 'attachment_code';
    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'attachment_created_at';
    const UPDATED_AT = 'attachment_updated_at';

    protected $fillable = [
        'attachment_file_name', 'attachment_file_path', 'attachment_mime_type', 
        'attachment_size_bytes', 'attachment_target_type', 'attachment_target_code', 'attachment_uploaded_by'
    ];

    public function getCodePrefix()
    {
        return 'AT';
    }

    public function getAttributeMapping(): array
    {
        return [
            'attachment_code' => 'code',
            'attachment_file_name' => 'file_name',
            'attachment_file_path' => 'file_path',
            'attachment_mime_type' => 'mime_type',
            'attachment_size_bytes' => 'size_bytes',
            'attachment_target_type' => 'target_type',
            'attachment_target_code' => 'target_code',
            'attachment_uploaded_by' => 'uploaded_by',
            'attachment_created_at' => 'created_at',
            'attachment_updated_at' => 'updated_at',
        ];
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'attachment_uploaded_by', 'user_code');
    }
}
