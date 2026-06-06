<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sound extends Model
{
    use HasFactory;

    protected $table = 'sounds';
    public $timestamps = false;

    protected $fillable = [
        'chapter_id',
        'url',
        'url_fa',
        'url_en',
        'url_tr',
        'url_ru',
        'url_tk',
    ];

    /**
     * Get the chapter that the sound belongs to.
     */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class, 'chapter_id');
    }
}
