<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    protected $fillable = [
        'name', 'unit', 'quantity', 'low_stock_threshold', 'product_id', 'deduct_per_unit',
    ];

    protected $casts = [
        'quantity' => 'float',
        'low_stock_threshold' => 'float',
        'deduct_per_unit' => 'float',
    ];

    protected $appends = ['is_low'];

    public function getIsLowAttribute(): bool
    {
        return $this->quantity <= $this->low_stock_threshold;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
