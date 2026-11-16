<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
   
    public function index(Request $request): JsonResponse
    {
        $payments = Payment::with([
                'order.items',
                'order.cafeTable',
                'receiver:id,name',
            ])
            ->when(
                $request->filled('date'),
                fn ($q) => $q->whereDate('paid_at', $request->date)
            )
            ->latest()
            ->limit(200)
            ->get();

        return response()->json($payments);
    }

   
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|in:cash',
        ]);

        $payment = DB::transaction(function () use ($data) {
            $order = Order::lockForUpdate()
                ->findOrFail($data['order_id']);

            if ($order->payment_method !== 'cash') {
                abort(422, 'This order requires GCash payment.');
            }

            if ($order->is_paid) {
                abort(422, 'This order has already been paid.');
            }

            if (
                abs((float) $data['amount'] - (float) $order->total) > 0.009
            ) {
                abort(422, 'Cash payment must equal the full order amount.');
            }

            $payment = Payment::create([
                'order_id' => $order->id,
                'amount' => $order->total,
                'method' => 'cash',
                'reference_no' => null,
                'status' => 'verified',
                'received_by' => Auth::id(),
                'paid_at' => now(),
                'verified_by' => Auth::id(),
                'verified_at' => now(),
            ]);

            $order->update([
                'is_paid' => true,
            ]);

            return $payment;
        });

        return response()->json([
            'message' => 'Cash payment recorded successfully.',
            'payment' => $payment->load([
                'order',
                'receiver:id,name',
            ]),
        ], 201);
    }
}
