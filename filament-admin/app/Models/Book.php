<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'kotob';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'title',
        'description',
        'current_version',
        'latest_version',
        'sql_download_url',
        'is_default',
        'is_downloaded',
        'cover_image_url',
        'status',
    ];

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'book_categories');
    }
}
