<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentAsset extends Model
{
    protected $fillable = ['content_item_id', 'content_revision_id', 'disk', 'path', 'public_url', 'mime_type', 'size_bytes', 'width', 'height', 'validation'];
    protected $casts = ['validation' => 'array'];
}
