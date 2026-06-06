<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kotob extends Model
{
    use HasFactory;

    protected $table = 'kotob';

    // The primary key is 'id', which is the default, so no need to specify.
    // Timestamps (created_at, updated_at) are not in your schema, so we disable them.
    public $timestamps = false;

    protected $fillable = [
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
}
