<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $products = Product::with('category')
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->category_id))
            ->when($request->boolean('available'), fn ($q) => $q->where('is_available', true))
            ->orderBy('name')
            ->get()
            ->each(fn (Product $p) => $this->present($p));

        return response()->json($products);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json($this->present($product->load('category')));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:150',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'grande_price' => 'nullable|numeric|min:0',
            'image' => 'nullable|string|max:500',
            'is_available' => 'boolean',
        ]);

        if (!empty($data['grande_price'])) {
            $data['size_prices'] = json_encode([
                'Tall' => (float) $data['price'],
                'Grande' => (float) $data['grande_price'],
            ]);
        } else {
            $data['size_prices'] = null;
        }

        unset($data['grande_price']); 

        $data['image'] = $this->normalizeImage($data['image'] ?? null);

        $product = Product::create($data);

        return response()->json($this->present($product->load('category')), 201);
    }

   
    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|file|image|mimes:jpg,jpeg,png,webp|max:4096', // Max 4MB
        ]);

        $file = $request->file('image');
        $dir = public_path('uploads/products');

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $name = Str::uuid() . '.' . $file->extension();
        $file->move($dir, $name);

        $path = '/uploads/products/' . $name;

        return response()->json([
            'path' => $path,
            'url' => url($path), 
        ], 201);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'category_id' => 'sometimes|exists:categories,id',
            'name' => 'sometimes|string|max:150',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'grande_price' => 'nullable|numeric|min:0',
            'image' => 'nullable|string|max:500',
            'is_available' => 'boolean',
        ]);

        if (array_key_exists('grande_price', $data)) {
            if (!empty($data['grande_price'])) {
                $basePrice = $data['price'] ?? $product->price;
                $data['size_prices'] = json_encode([
                    'Tall' => (float) $basePrice,
                    'Grande' => (float) $data['grande_price'],
                ]);
            } else {
                $data['size_prices'] = null;
            }
            unset($data['grande_price']);
        }

        if (array_key_exists('image', $data)) {
            $data['image'] = $this->normalizeImage($data['image']);
        }

        $product->update($data);

        return response()->json($this->present($product->load('category')));
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json(null, 204);
    }

    private function present(Product $product): Product
    {
        $image = $product->image;

        if ($image && str_starts_with($image, '/uploads/')) {
            $product->image = url($image);
        }

        return $product;
    }

    private function normalizeImage(?string $image): ?string
    {
        if (!$image) {
            return null;
        }

        $root = request()->getSchemeAndHttpHost();

        if (str_starts_with($image, $root)) {
            $image = substr($image, strlen($root));
        }

        return $image;
    }
}