<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Content extends Model
{
    use HasFactory;

    protected $table = 'content';
    public $timestamps = false;

    protected $fillable = [
        'chapters_id',
        'text',
        'text_fa',
        'text_turkmen',
        'kotob_id',
        'text_en',
        'text_tr',
        'text_ru',
    ];

    public function chapter()
    {
        return $this->belongsTo(Chapter::class, 'chapters_id');
    }

    public function book()
    {
        return $this->belongsTo(Book::class, 'kotob_id');
    }
}
