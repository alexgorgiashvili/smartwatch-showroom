<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PromptEnhancementLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'original_prompt',
        'enhanced_prompt',
        'analysis_metadata',
        'is_accepted',
        'feedback',
    ];

    protected $casts = [
        'analysis_metadata' => 'array',
        'is_accepted' => 'boolean',
    ];
}
