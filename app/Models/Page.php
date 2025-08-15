<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasFactory;

    protected $fillable = [
        'url',
        'page_type',
        'status',
        'cleaned_text',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];
}
