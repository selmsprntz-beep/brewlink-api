<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    protected $fillable = [
        'reference_code', 'customer_name', 'contact_number', 'email',
        'reservation_date', 'reservation_time', 'party_size', 'notes', 'status',
    ];

    protected $casts = [
        'reservation_date' => 'date:Y-m-d',
        'party_size' => 'integer',
    ];
}
