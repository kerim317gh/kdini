<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $table = 'categories';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'title',
        'sort_order',
        'icon',
    ];

    public function books()
    {
        return $this->belongsToMany(Book::class, 'book_categories');
    }

    public function chapters()
    {
        return $this->hasMany(Chapter::class);
    }
}
