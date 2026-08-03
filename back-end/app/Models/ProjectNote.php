<?php

namespace App\Models;

use App\Traits\GeneratesCode;
use App\Traits\MapsAttributes;
use Illuminate\Database\Eloquent\Model;

class ProjectNote extends Model
{
    use GeneratesCode, MapsAttributes;

    protected $primaryKey = 'note_code';

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'note_created_at';

    const UPDATED_AT = 'note_updated_at';

    protected $fillable = [
        'note_project_code', 'note_author_code', 'note_title', 'note_content', 'note_is_pinned',
    ];

    protected $casts = ['note_is_pinned' => 'boolean'];

    public function getCodePrefix()
    {
        return 'PN';
    }

    public function getAttributeMapping(): array
    {
        return [
            'note_code' => 'code',
            'note_project_code' => 'project_code',
            'note_author_code' => 'author_code',
            'note_title' => 'title',
            'note_content' => 'content',
            'note_is_pinned' => 'is_pinned',
            'note_created_at' => 'created_at',
            'note_updated_at' => 'updated_at',
        ];
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'note_project_code', 'project_code');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'note_author_code', 'user_code');
    }
}
