<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(InventoryItem::with('product:id,name')->orderBy('name')->get());
    }

    public function lowStock(): JsonResponse
    {
        $items = InventoryItem::whereColumn('quantity', '<=', 'low_stock_threshold')
            ->orderBy('name')
            ->get();

        return response()->json($items);
    }

    public function movements(Request $request): JsonResponse
    {
        $movements = StockMovement::with(['inventoryItem:id,name,unit', 'user:id,name'])
            ->when($request->filled('item_id'), fn ($q) => $q->where('inventory_item_id', $request->item_id))
            ->latest()
            ->limit(100)
            ->get();

        return response()->json($movements);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'unit' => 'required|string|max:20',
            'quantity' => 'required|numeric|min:0',
            'low_stock_threshold' => 'required|numeric|min:0',
            'product_id' => 'nullable|exists:products,id',
            'deduct_per_unit' => 'nullable|numeric|min:0',
        ]);

        $data['deduct_per_unit'] = $data['deduct_per_unit'] ?? 1;

        $item = DB::transaction(function () use ($data) {
            $item = InventoryItem::create($data);

            if ($item->quantity > 0) {
                StockMovement::create([
                    'inventory_item_id' => $item->id,
                    'type' => 'in',
                    'quantity' => $item->quantity,
                    'balance_after' => $item->quantity,
                    'reason' => 'Initial stock',
                    'user_id' => Auth::id(),
                ]);
            }

            return $item;
        });

        return response()->json($item->load('product:id,name'), 201);
    }

    public function update(Request $request, InventoryItem $item): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'unit' => 'required|string|max:20',
            'low_stock_threshold' => 'required|numeric|min:0',
            'product_id' => 'nullable|exists:products,id',
            'deduct_per_unit' => 'nullable|numeric|min:0',
        ]);

        $data['deduct_per_unit'] = $data['deduct_per_unit'] ?? 1;

        $item->update($data);

        return response()->json($item->load('product:id,name'));
    }

    public function destroy(InventoryItem $item): JsonResponse
    {
        $item->delete();

        return response()->json(null, 204);
    }

    public function adjust(Request $request, InventoryItem $item): JsonResponse
    {
        $data = $request->validate([
            'type' => 'required|in:in,out,adjustment',
            'quantity' => 'required|numeric|min:0',
            'reason' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($data, $item) {
            $item = InventoryItem::lockForUpdate()->findOrFail($item->id);

            $newQty = match ($data['type']) {
                'in' => $item->quantity + $data['quantity'],
                'out' => max(0, $item->quantity - $data['quantity']),
                'adjustment' => (float) $data['quantity'],
            };

            $change = $newQty - $item->quantity;

            $item->update(['quantity' => $newQty]);

            StockMovement::create([
                'inventory_item_id' => $item->id,
                'type' => $data['type'],
                'quantity' => $change,
                'balance_after' => $newQty,
                'reason' => $data['reason'] ?? null,
                'user_id' => Auth::id(),
            ]);
        });

        return response()->json($item->fresh()->load('product:id,name'));
    }
}