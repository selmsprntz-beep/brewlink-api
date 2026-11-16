<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'order_number', 'cafe_table_id', 'customer_name', 'order_type',   'payment_method',
        'status', 'total', 'notes', 'is_paid', 'stock_deducted',
    ];

    protected $casts = [
        'total' => 'float',
        'is_paid' => 'boolean',
        'stock_deducted' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function cafeTable(): BelongsTo
    {
        return $this->belongsTo(CafeTable::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
