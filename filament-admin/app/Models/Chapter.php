<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Chapter extends Model
{
    use HasFactory;

    protected $table = 'chapters';
    public $timestamps = false;

    protected $fillable = [
        'title',
        'parent_id',
        'category_id',
        'icon',
        'title_fa',
        'title_en',
        'title_tr',
        'title_ru',
        'title_tk',
    ];

    /**
     * Get the parent chapter.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Chapter::class, 'parent_id');
    }

    /**
     * Get the child chapters.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Chapter::class, 'parent_id');
    }

    /**
     * Get the category that owns the chapter.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
}
