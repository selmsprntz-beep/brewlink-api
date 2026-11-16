<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CafeTable extends Model
{
    protected $table = 'cafe_tables';

    protected $fillable = ['name', 'qr_token', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
