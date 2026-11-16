<?php

namespace Database\Seeders;

use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['Croissant', 'pcs', 20, 5, 'Croissant'],
            ['Blueberry Muffin', 'pcs', 18, 5, 'Blueberry Muffin'],
            ['Coffee Beans', 'kg', 5, 2, null],
            ['Fresh Milk', 'L', 3, 4, null],  
            ['Paper Cups', 'pcs', 150, 40, null],
        ];

        foreach ($items as [$name, $unit, $qty, $threshold, $productName]) {
            if (InventoryItem::where('name', $name)->exists()) {
                continue;
            }

            $item = InventoryItem::create([
                'name' => $name,
                'unit' => $unit,
                'quantity' => $qty,
                'low_stock_threshold' => $threshold,
                'product_id' => $productName ? Product::where('name', $productName)->value('id') : null,
                'deduct_per_unit' => 1,
            ]);

            StockMovement::create([
                'inventory_item_id' => $item->id,
                'type' => 'in',
                'quantity' => $qty,
                'balance_after' => $qty,
                'reason' => 'Initial stock',
            ]);
        }
    }
}
