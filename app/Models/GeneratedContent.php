<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedContent extends Model
{
    use HasFactory;

    protected $fillable = [
        'page_id',
        'layout',
        'status',
        'content',
        'error',
        'ai_model',
        'tokens_input',
        'tokens_output',
        'temperature',
        'provider',
        'prompt_version',
    ];

    protected $casts = [
        'tokens_input' => 'integer',
        'tokens_output' => 'integer',
        'temperature' => 'float',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
