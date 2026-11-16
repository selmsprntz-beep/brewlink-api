<?php

namespace Database\Seeders;

use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\ProductIngredient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RecipeSeeder extends Seeder
{
    /** name => [unit, starting stock, low-stock alert] */
    private array $stock = [
        'Coffee Beans'    => ['g', 5000, 500],
        'Fresh Milk'      => ['ml', 6000, 1000],
        'Ice'             => ['g', 10000, 1500],
        'Blended Base'    => ['g', 3000, 400],
        'Chocolate Sauce' => ['ml', 1500, 250],
        'Caramel Syrup'   => ['ml', 1500, 250],
        'Durian Puree'    => ['g', 3000, 500],
        'Whipped Cream'   => ['g', 1500, 200],
        'Cup Tall'        => ['pcs', 200, 30],
        'Cup Grande'      => ['pcs', 200, 30],
        'Lid'             => ['pcs', 400, 60],
        'Straw'           => ['pcs', 300, 50],
        'Bread'           => ['pcs', 40, 8],
        'Cheese'          => ['g', 2000, 300],
        'Chicken'         => ['g', 4000, 600],
        'Beef Sausage'    => ['pcs', 60, 10],
        'Boneless Bangus' => ['pcs', 30, 6],
        'Eggs'            => ['pcs', 120, 20],
        'Pasta'           => ['g', 4000, 600],
        'Pasta Sauce'     => ['ml', 3000, 500],
        'Rice'            => ['g', 10000, 1500],
        'Fries'           => ['g', 4000, 500],
        'Cooking Oil'     => ['ml', 3000, 400],
    ];

    public function run(): void
    {
        Model::unguard();

        foreach ($this->stock as $name => [$unit, $qty, $low]) {
            $inv = InventoryItem::where('name', $name)->first();

            if (!$inv) {
                InventoryItem::create([
                    'name' => $name,
                    'unit' => $unit,
                    'quantity' => $qty,
                    'low_stock_threshold' => $low,
                    'product_id' => null,
                    'deduct_per_unit' => 1,
                ]);
                continue;
            }

            $from = Str::lower($inv->unit);
            $factor = ($from === 'kg' && $unit === 'g') || ($from === 'l' && $unit === 'ml') ? 1000 : 1;

            if (Str::lower($inv->unit) !== $unit) {
                $inv->quantity = $inv->quantity * $factor;
                $inv->low_stock_threshold = $inv->low_stock_threshold * $factor;
                $inv->unit = $unit;
                $inv->save();
            }
        }

        $items = InventoryItem::whereIn('name', array_keys($this->stock))->get()->keyBy('name');

        foreach (Product::with('category')->get() as $product) {
            $lines = $this->recipeFor($product);

            if (empty($lines)) {
                $this->command?->warn("Skipped (no template): {$product->name} [" . ($product->category->name ?? '-') . ']');
                continue;
            }

            $product->ingredients()->delete();

            foreach ($lines as [$name, $qty, $gqty]) {
                if (!isset($items[$name])) {
                    continue;
                }

                ProductIngredient::create([
                    'product_id' => $product->id,
                    'inventory_item_id' => $items[$name]->id,
                    'quantity' => $qty,
                    'grande_quantity' => $gqty,
                ]);
            }

            $this->command?->info("Recipe set: {$product->name} (" . count($lines) . ' ingredients)');
        }

        Model::reguard();
    }

    /** @return array<int, array{0:string,1:float|int,2:float|int|null}> */
    private function recipeFor(Product $p): array
    {
        $cat = Str::lower($p->category->name ?? '');
        $name = Str::lower($p->name);
        $sized = $p->grande_price !== null;

        $has = fn (string ...$words) => collect($words)->contains(fn ($w) => Str::contains($name, $w));
        $L = fn (string $n, $tall, $grande = null) => [$n, $tall, $sized ? ($grande ?? $tall) : null];

        $cups = function (bool $straw) use ($sized, $L) {
            $lines = [['Cup Tall', 1, $sized ? 0 : null]];
            if ($sized) {
                $lines[] = ['Cup Grande', 0, 1];
            }
            $lines[] = $L('Lid', 1, 1);
            if ($straw) {
                $lines[] = $L('Straw', 1, 1);
            }
            return $lines;
        };

        $flavors = function () use ($has, $L) {
            $l = [];
            if ($has('mocha', 'chocolate')) {
                $l[] = $L('Chocolate Sauce', 30, 40);
            }
            if ($has('caramel')) {
                $l[] = $L('Caramel Syrup', 30, 40);
            }
            if ($has('durian')) {
                $l[] = $L('Durian Puree', 60, 80);
            }
            return $l;
        };

        $coffee = function () use ($has, $L, $flavors) {
            $l = [$L('Coffee Beans', 18, 24)];
            if ($has('latte')) {
                $l[] = $L('Fresh Milk', 180, 240);
            } elseif ($has('cappuccino')) {
                $l[] = $L('Fresh Milk', 120, 160);
            } elseif ($has('mocha', 'durian', 'caramel', 'macchiato')) {
                $l[] = $L('Fresh Milk', 150, 200);
            }
            return array_merge($l, $flavors());
        };

        // ---------- Drinks ----------
        if (Str::contains($cat, 'blended')) {
            $l = [$L('Blended Base', 40, 55), $L('Fresh Milk', 120, 160), $L('Ice', 150, 200), $L('Whipped Cream', 20, 30)];
            if ($has('coffee', 'mocha', 'java')) {
                $l[] = $L('Coffee Beans', 18, 24);
            }
            return array_merge($l, $flavors(), $cups(true));
        }

        if (Str::contains($cat, 'iced')) {
            return array_merge($coffee(), [$L('Ice', 120, 160)], $cups(true));
        }

        if (Str::contains($cat, 'hot') || Str::contains($cat, 'coffee')) {
            return array_merge($coffee(), $cups(false));
        }

        // ---------- Food ----------
        if (Str::contains($cat, 'panini')) {
            $l = [$L('Bread', 1), $L('Cheese', 40)];
            $l[] = $has('sausage', 'ham') ? $L('Beef Sausage', 1) : $L('Chicken', 80);
            return $l;
        }

        if (Str::contains($cat, 'pasta')) {
            $l = [$L('Pasta', 120), $L('Pasta Sauce', 100), $L('Cheese', 20), $L('Cooking Oil', 10)];
            if ($has('sausage')) {
                $l[] = $L('Beef Sausage', 2);
            }
            if ($has('chicken')) {
                $l[] = $L('Chicken', 80);
            }
            if ($has('bangus')) {
                $l[] = $L('Boneless Bangus', 1);
            }
            return $l;
        }

        if (Str::contains($cat, 'pica')) {
            $l = [$L('Fries', 150), $L('Cooking Oil', 30)];
            if ($has('sausage')) {
                $l[] = $L('Beef Sausage', 2);
            }
            if ($has('chicken')) {
                $l[] = $L('Chicken', 100);
            }
            return $l;
        }

        if (Str::contains($cat, 'rice')) {
            $l = [$L('Rice', 200), $L('Cooking Oil', 15)];
            if ($has('scrambl', 'egg')) {
                $l[] = $L('Eggs', 2);
            }
            if ($has('bangus')) {
                $l[] = $L('Boneless Bangus', 1);
            }
            if ($has('sausage')) {
                $l[] = $L('Beef Sausage', 2);
            }
            if ($has('chicken')) {
                $l[] = $L('Chicken', 150);
            }
            return $l;
        }

        return [];
    }
}