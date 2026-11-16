<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RecipeController extends Controller
{
    public function index(): JsonResponse
    {
        $products = Product::with(['category', 'ingredients.inventoryItem'])
            ->orderBy('category_id')
            ->orderBy('name')
            ->get();

        return response()->json($products);
    }

    public function sync(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'ingredients' => 'present|array',
            'ingredients.*.inventory_item_id' => 'required|integer|exists:inventory_items,id|distinct',
            'ingredients.*.quantity' => 'required|numeric|min:0',
            'ingredients.*.grande_quantity' => 'nullable|numeric|min:0',
        ]);

        DB::transaction(function () use ($product, $data) {
            $product->ingredients()->delete();

            foreach ($data['ingredients'] as $line) {
                $product->ingredients()->create([
                    'inventory_item_id' => $line['inventory_item_id'],
                    'quantity' => $line['quantity'],
                    'grande_quantity' => $line['grande_quantity'] ?? null,
                ]);
            }
        });

        return response()->json($product->load(['category', 'ingredients.inventoryItem']));
    }
}