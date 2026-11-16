<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductIngredient extends Model
{
    protected $fillable = ['product_id', 'inventory_item_id', 'quantity', 'grande_quantity'];

    protected $casts = [
        'quantity' => 'float',
        'grande_quantity' => 'float',
    ];

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}