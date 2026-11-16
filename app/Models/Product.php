<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
class Product extends Model
{
  protected $fillable = [
    'category_id',
    'name',
    'description',
    'price',
    'size_prices', 
    'image',
    'is_available',
];

protected $casts = [
    'size_prices' => 'array', // Automatic conversion gikan sa JSON ngadto sa Array
];


protected function image(): Attribute
{
    return Attribute::get(function (?string $value) {
        return $value && str_starts_with($value, '/storage/') ? url($value) : $value;
    });
}
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function ingredients(): HasMany
{
    return $this->hasMany(ProductIngredient::class);
}
}
