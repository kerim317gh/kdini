<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContentAudio extends Model
{
    use HasFactory;

    protected $table = 'content_audio';
    public $timestamps = false;

    protected $casts = [
        'created_at' => 'datetime',
        'timing' => 'array', // <-- Cast timing column to array
    ];

    protected $fillable = [
        'kotob_id',
        'chapters_id',
        'lang',
        'narrator',
        'title',
        'url',
        'checksum',
        'bytes',
        'duration_ms',
        'local_path',
        'is_downloaded',
        'selected',
        'created_at',
        'image_url',
        'timing', // <-- Add timing to fillable
    ];

    public function chapter()
    {
        return $this->belongsTo(Chapter::class, 'chapters_id');
    }

    public function book()
    {
        return $this->belongsTo(Book::class, 'kotob_id');
    }
    
    public function content()
    {
        // Assuming one content per chapter per book
        return $this->hasOneThrough(
            Content::class, 
            Chapter::class, 
            'id', // Foreign key on chapters table...
            'chapters_id', // Foreign key on content table...
            'chapters_id', // Local key on content_audio table...
            'id' // Local key on chapters table...
        )->where('content.kotob_id', '=', $this->kotob_id);
    }
}
