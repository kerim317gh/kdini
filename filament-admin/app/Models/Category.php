<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $table = 'categories';

    // The primary key is 'id', which is the default, so no need to specify.
    // Timestamps (created_at, updated_at) are not in your schema, so we disable them.
    public $timestamps = false;

    protected $fillable = [
        'title',
        'sort_order',
        'icon',
    ];
}
