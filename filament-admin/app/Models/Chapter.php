<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Chapter extends Model
{
    use HasFactory;

    protected $table = 'chapters';
    public $timestamps = false;

    protected $fillable = [
        'id',
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

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function parent()
    {
        return $this->belongsTo(Chapter::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Chapter::class, 'parent_id');
    }

    public function content()
    {
        return $this->hasMany(Content::class, 'chapters_id');
    }
}
