<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Page extends Model
{
    use HasFactory;

    protected $fillable = [
        'url',
        'page_type',
        'status',
        'cleaned_text',
        'meta',
        'last_fetched_at',
        'http_status',
        'content_length',
        'fetch_error',
    ];

    protected $casts = [
        'meta' => 'array',
        'last_fetched_at' => 'datetime',
    ];

    /**
     * Generated AI contents for this page.
     */
    public function generatedContents(): HasMany
    {
        return $this->hasMany(GeneratedContent::class);
    }
}
