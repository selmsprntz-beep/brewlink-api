<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CafeTable;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // Remove spaces and dashes first (e.g. "1234 567 890123" -> "1234567890123")
        $this->normalizeReference($request);

        $data = $request->validate([
            'table_token' => 'nullable|string|exists:cafe_tables,qr_token',
            'customer_name' => 'required|string|max:100',
            'order_type' => 'required|in:dine_in,takeout',
            'payment_method' => 'required|in:cash,gcash',
            // GCash Reference No.: required for GCash, exactly 13 digits (numbers only)
            'reference_no' => ['required_if:payment_method,gcash', 'nullable', 'digits:13'],
            'notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1|max:50',
            'items.*.size' => 'nullable|in:Tall,Grande',
        ], [
            'reference_no.required_if' => 'Enter the GCash Reference No.',
            'reference_no.digits' => 'The GCash Reference No. must be exactly 13 digits (numbers only).',
        ]);

        $order = DB::transaction(function () use ($data) {
            $table = !empty($data['table_token'])
                ? CafeTable::where('qr_token', $data['table_token'])->first()
                : null;

            $order = Order::create([
                'order_number' => $this->makeOrderNumber(),
                'cafe_table_id' => $table?->id,
                'customer_name' => $data['customer_name'],
                'order_type' => $data['order_type'],
                'payment_method' => $data['payment_method'],
                'notes' => $data['notes'] ?? null,
                'status' => 'pending',
                'total' => 0,
                'is_paid' => false,
            ]);

            $total = 0;

            foreach ($data['items'] as $line) {
                $product = Product::findOrFail($line['product_id']);

                if (!$product->is_available) {
                    abort(422, $product->name . ' is currently unavailable.');
                }

                $grandePrice = $this->grandePrice($product);
                $size = $line['size'] ?? null;

                if ($grandePrice === null) {
                    $size = null;
                } elseif ($size === null) {
                    $size = 'Tall';
                }

                $price = $size === 'Grande'
                    ? $grandePrice
                    : (float) $product->price;

                $quantity = (int) $line['quantity'];
                $subtotal = $price * $quantity;

                $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'size' => $size,
                    'price' => $price,
                    'quantity' => $quantity,
                    'subtotal' => $subtotal,
                ]);

                $total += $subtotal;
            }

            $order->update([
                'total' => $total,
            ]);

            if ($data['payment_method'] === 'gcash') {
                Payment::create([
                    'order_id' => $order->id,
                    'method' => 'gcash',
                    'amount' => $total,
                    'reference_no' => $data['reference_no'] ?? null,
                    'status' => 'pending',
                ]);
            }

            return $order;
        });

        return response()->json(
            $order->load(['items', 'cafeTable', 'payments']),
            201
        );
    }

    public function storeGcashReference(Request $request, Order $order): JsonResponse
    {
        // Remove spaces and dashes first
        $this->normalizeReference($request);

        // GCash Reference No. must be exactly 13 digits (numbers only)
        $data = $request->validate([
            'reference_no' => ['required', 'digits:13'],
        ], [
            'reference_no.required' => 'Enter the GCash Reference No.',
            'reference_no.digits' => 'The GCash Reference No. must be exactly 13 digits (numbers only).',
        ]);

        $payment = Payment::updateOrCreate(
            [
                'order_id' => $order->id,
                'method' => 'gcash',
            ],
            [
                'amount' => $order->total,
                'reference_no' => $data['reference_no'],
                'status' => 'pending',
            ]
        );

        return response()->json([
            'message' => 'GCash reference submitted successfully.',
            'payment' => $payment->load('order'),
        ]);
    }

    public function track(Request $request, string $orderNumber): JsonResponse
    {
        $order = Order::with(['items', 'cafeTable', 'payments'])
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        $givenToken = trim((string) $request->query('table', ''));
        $orderToken = $order->cafeTable?->qr_token;

        if ($orderToken !== null) {
            if ($givenToken === '' || !hash_equals((string) $orderToken, $givenToken)) {
                abort(404);
            }
        } elseif ($givenToken !== '') {
            abort(404);
        }

        return response()->json($order);
    }

    public function byTable(string $token): JsonResponse
    {
        $table = CafeTable::where('qr_token', $token)->firstOrFail();

        $orders = Order::with(['items', 'cafeTable'])
            ->where('cafe_table_id', $table->id)
            ->whereDate('created_at', today())
            ->orderByDesc('id')
            ->get();

        return response()->json($orders);
    }

    public function index(Request $request): JsonResponse
    {
        $orders = Order::with(['items', 'cafeTable', 'payments'])
            ->when(
                $request->status === 'active',
                fn ($q) => $q->whereIn(
                    'status',
                    ['pending', 'preparing', 'ready']
                )
            )
            ->when(
                $request->filled('status')
                    && $request->status !== 'active',
                fn ($q) => $q->where('status', $request->status)
            )
            ->latest()
            ->limit(100)
            ->get();

        return response()->json($orders);
    }

    public function show(Order $order): JsonResponse
    {
        return response()->json(
            $order->load(['items', 'cafeTable', 'payments'])
        );
    }

    public function updateStatus(
        Request $request,
        Order $order
    ): JsonResponse {
        $data = $request->validate([
            'status' => 'required|in:pending,preparing,ready,completed,cancelled',
        ]);

        $newStatus = $data['status'];

        DB::transaction(function () use ($order, $newStatus) {
            $order = Order::lockForUpdate()->findOrFail($order->id);

            if (
                in_array($newStatus, ['preparing', 'ready', 'completed'])
                && !$order->is_paid
            ) {
                abort(422, 'Payment must be completed before preparing this order.');
            }

            $order->update([
                'status' => $newStatus,
            ]);

            if (
                $newStatus === 'completed'
                && !$order->stock_deducted
            ) {
                $this->deductStock($order);
            }
        });

        return response()->json(
            $order->fresh()->load(['items', 'cafeTable', 'payments'])
        );
    }


    public function recordCash(Order $order): JsonResponse
    {
        if ($order->payment_method !== 'cash') {
            abort(422, 'This is not a cash order.');
        }

        if ($order->status === 'cancelled') {
            abort(422, 'Cannot record payment for a cancelled order.');
        }

        DB::transaction(function () use ($order) {
            Payment::updateOrCreate(
                [
                    'order_id' => $order->id,
                    'method' => 'cash',
                ],
                [
                    'amount' => $order->total,
                    'status' => 'verified',
                    'received_by' => Auth::id(),
                    'verified_by' => Auth::id(),
                    'verified_at' => now(),
                    'paid_at' => now(),
                ]
            );

            $order->update(['is_paid' => true]);
        });

        return response()->json(
            $order->fresh()->load(['items', 'cafeTable', 'payments'])
        );
    }

    /**
     * Clean the reference_no before validation:
     * remove spaces and dashes; empty values become null.
     */
    private function normalizeReference(Request $request): void
    {
        if (!$request->has('reference_no')) {
            return;
        }

        $clean = preg_replace(
            '/[\s-]+/',
            '',
            (string) $request->input('reference_no')
        );

        $request->merge([
            'reference_no' => $clean === '' ? null : $clean,
        ]);
    }

    private function grandePrice(Product $product): ?float
    {
        $visible = $product->toArray();
        $raw = $product->getAttributes();

        $sizePrices = $visible['size_prices'] ?? $raw['size_prices'] ?? null;

        if (is_string($sizePrices)) {
            $sizePrices = json_decode($sizePrices, true);
        }

        $grande = is_array($sizePrices)
            ? ($sizePrices['Grande'] ?? null)
            : null;

        if ($grande === null) {
            $grande = $visible['grande_price'] ?? $raw['grande_price'] ?? null;
        }

        if ($grande === null || $grande === '' || !is_numeric($grande)) {
            return null;
        }

        return (float) $grande;
    }

    private function makeOrderNumber(): string
    {
        do {
            $number = 'BL-' . now()->format('ymd')
                . '-' . strtoupper(Str::random(4));
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }

    private function deductStock(Order $order): void
    {
        foreach ($order->items as $item) {
            if (!$item->product_id) {
                continue;
            }

            $productIngredients = \App\Models\ProductIngredient::where('product_id', $item->product_id)->get();

            foreach ($productIngredients as $pi) {
                $stock = InventoryItem::lockForUpdate()->find($pi->inventory_item_id);

                if (!$stock) {
                    continue;
                }

                $qtyPerServing = ($item->size === 'Grande' && $pi->grande_quantity !== null)
                    ? $pi->grande_quantity
                    : $pi->quantity;

                $used = $qtyPerServing * $item->quantity;
                $newQty = max(0, $stock->quantity - $used);

                $stock->update([
                    'quantity' => $newQty,
                ]);

                StockMovement::create([
                    'inventory_item_id' => $stock->id,
                    'type' => 'out',
                    'quantity' => -$used,
                    'balance_after' => $newQty,
                    'reason' => "Order {$order->order_number}",
                    'order_id' => $order->id,
                    'user_id' => Auth::id(),
                ]);
            }
        }

        $order->update([
            'stock_deducted' => true,
        ]);
    }
}