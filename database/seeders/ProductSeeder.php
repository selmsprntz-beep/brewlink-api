<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Hot Coffee' => [
                ['name' => 'Durian Coffeeccino', 'price' => 215, 'grande' => 235],
                ['name' => 'Americano', 'price' => 120, 'grande' => 130],
                ['name' => 'Cappuccino', 'price' => 140, 'grande' => 160],
                ['name' => 'Cafe Latte', 'price' => 140, 'grande' => 160],
                ['name' => 'Cafe Mocha', 'price' => 170, 'grande' => 185],
                ['name' => 'Espresso', 'price' => 100, 'grande' => 110],
            ],
            'Iced Coffee' => [
                ['name' => 'Iced Americano', 'price' => 130, 'grande' => 140],
                ['name' => 'Iced Latte', 'price' => 140, 'grande' => 160],
                ['name' => 'Iced Mocha', 'price' => 175, 'grande' => 190],
                ['name' => 'Iced White Mocha', 'price' => 175, 'grande' => 190],
                ['name' => 'Iced Caramel', 'price' => 175, 'grande' => 190],
            ],
            'Blended Drinks' => [
                ['name' => 'Durian Arctic Blast', 'price' => 220, 'grande' => 250],
                ['name' => 'Mocha Blended', 'price' => 185, 'grande' => 205],
                ['name' => 'White Mocha Blended', 'price' => 185, 'grande' => 205],
                ['name' => 'Oreo Blended', 'price' => 190, 'grande' => 210],
                ['name' => 'Mochagelo', 'price' => 190, 'grande' => 210],
            ],
            'Pasta' => [
                ['name' => 'Spaghetti Beef Carbonara', 'price' => 225],
                ['name' => 'Penne Tuna Pesto Cream', 'price' => 320],
                ['name' => 'Spaghetti Meat Sauce', 'price' => 205],
                ['name' => 'Beef Sausage Pepper Penne', 'price' => 320],
                ['name' => 'Chicken Enchilada', 'price' => 285],
            ],
            'Panini' => [
                ['name' => 'Chicken Adobo Cheesemelt', 'price' => 190],
                ['name' => 'Chicken Pesto', 'price' => 225],
                ['name' => 'Tuna Cheesemelt', 'price' => 185],
                ['name' => 'Chicken Ham Cheesemelt', 'price' => 210],
            ],
            'Rice Meals' => [
                ['name' => 'Tapa Scrambler', 'price' => 240],
                ['name' => 'Chicken Ham Scrambler', 'price' => 190],
                ['name' => 'Bangus Scrambler', 'price' => 220],
                ['name' => 'Corned Beef Scrambler', 'price' => 260],
                ['name' => 'Beef Sausage Scrambler', 'price' => 170],
                ['name' => 'Tuna Scrambler', 'price' => 160],
                ['name' => 'Marinated Garlic Chicken', 'price' => 240],
            ],
            'Pica-Pica' => [
                ['name' => 'Spicy Beef Sausage', 'price' => 210],
                ['name' => 'Potato Chips w/ Spiced Mayo Dip', 'price' => 165],
                ['name' => 'Grilled Cheese Quesadillas', 'price' => 150],
                ['name' => 'Pita Chips w/ Tuna Mozzarella', 'price' => 180],
            ],
        ];

        foreach ($categories as $categoryName => $products) {
            $cat = Category::firstOrCreate(['name' => $categoryName]);

            foreach ($products as $p) {
                $grandePrice = $p['grande'] ?? null;

                $lowerName = strtolower($p['name']);

                if (str_contains($lowerName, 'americano')) {
                    $imagePath = 'assets/menu/americano.jpg';
                } elseif (str_contains($lowerName, 'latte')) {
                    $imagePath = 'assets/menu/cafe-latte.jpg';
                } elseif (str_contains($lowerName, 'mocha')) {
                    $imagePath = 'assets/menu/mocha.jpg';
                } elseif (str_contains($lowerName, 'cappuccino')) {
                    $imagePath = 'assets/menu/cappuccino.jpg';
                } elseif (str_contains($lowerName, 'durian')) {
                    $imagePath = 'assets/menu/durian.jpg';
                } elseif (str_contains($lowerName, 'bangus') || str_contains($lowerName, 'scrambler')) {
                    $imagePath = 'assets/menu/bangus.jpg';
                } elseif (str_contains($lowerName, 'sausage') || str_contains($lowerName, 'penne') || str_contains($lowerName, 'beef')) {
                    $imagePath = 'assets/menu/beef-sausage.jpg';
                } else {
                    $imagePath = 'assets/menu/americano.jpg';
                }

                Product::updateOrCreate(
                    ['name' => $p['name']],
                    [
                        'category_id' => $cat->id,
                        'price' => $p['price'],
                        'grande_price' => $grandePrice,
                        'image' => $imagePath,
                        'is_available' => true,
                    ]
                );
            }
        }
    }
}