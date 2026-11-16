<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PaymentVerificationController extends Controller
{

    public function submitReference(
        Request $request,
        Order $order
    ): JsonResponse {
        // Remove spaces and dashes first (e.g. "1234 567 890123" -> "1234567890123")
        $request->merge([
            'reference_no' => preg_replace(
                '/[\s-]+/',
                '',
                (string) $request->input('reference_no')
            ),
        ]);

        // GCash Reference No. must be exactly 13 digits (numbers only)
        $data = $request->validate([
            'reference_no' => ['required', 'digits:13'],
        ], [
            'reference_no.required' => 'Enter the GCash Reference No.',
            'reference_no.digits'   => 'The GCash Reference No. must be exactly 13 digits (numbers only).',
        ]);

        $payment = DB::transaction(function () use ($order, $data) {
            $order = Order::lockForUpdate()->findOrFail($order->id);

            if ($order->payment_method !== 'gcash') {
                abort(422, 'This order is not a GCash order.');
            }

            if ($order->is_paid) {
                abort(422, 'This order has already been paid.');
            }

            $existing = Payment::where('order_id', $order->id)
                ->where('method', 'gcash')
                ->lockForUpdate()
                ->first();

            if ($existing && $existing->status === 'verified') {
                abort(422, 'This payment has already been verified.');
            }

            // Prevent reusing another order's GCash reference.
            $duplicate = Payment::where('method', 'gcash')
                ->where('reference_no', $data['reference_no'])
                ->where('order_id', '!=', $order->id)
                ->exists();

            if ($duplicate) {
                abort(422, 'This GCash reference number has already been submitted.');
            }

            return Payment::updateOrCreate(
                [
                    'order_id' => $order->id,
                    'method' => 'gcash',
                ],
                [
                    'amount' => $order->total,
                    'reference_no' => $data['reference_no'],
                    'status' => 'pending',
                    'screenshot' => null,
                    'received_by' => null,
                    'paid_at' => null,
                    'verified_by' => null,
                    'verified_at' => null,
                ]
            );
        });

        return response()->json([
            'message' => 'GCash reference submitted successfully.',
            'payment' => $payment->load('order'),
        ], 201);
    }


    public function pending(): JsonResponse
    {
        $payments = Payment::with([
                'order.items',
                'order.cafeTable',
            ])
            ->where('method', 'gcash')
            ->where('status', 'pending')
            ->latest()
            ->get();

        return response()->json([
            'data' => $payments,
        ]);
    }


    public function verify(Payment $payment): JsonResponse
    {
        $result = DB::transaction(function () use ($payment) {
            $payment = Payment::lockForUpdate()
                ->findOrFail($payment->id);

            $order = Order::lockForUpdate()
                ->findOrFail($payment->order_id);

            if ($payment->method !== 'gcash') {
                abort(422, 'Only GCash payments can be verified here.');
            }

            if ($payment->status !== 'pending') {
                abort(422, 'This payment is no longer pending.');
            }

            if ($order->payment_method !== 'gcash') {
                abort(422, 'Order payment method does not match.');
            }

            if ((float) $payment->amount !== (float) $order->total) {
                abort(422, 'Payment amount does not match the order total.');
            }

            $payment->update([
                'status' => 'verified',
                'received_by' => Auth::id(),
                'verified_by' => Auth::id(),
                'verified_at' => now(),
                'paid_at' => now(),
            ]);

            $order->update([
                'is_paid' => true,
            ]);

            return [
                'payment' => $payment->fresh()->load('order'),
                'order' => $order->fresh(),
            ];
        });

        return response()->json([
            'message' => 'GCash payment verified successfully.',
            'payment' => $result['payment'],
            'order' => $result['order'],
        ]);
    }


    public function reject(
        Request $request,
        Payment $payment
    ): JsonResponse {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $payment = DB::transaction(function () use ($payment) {
            $payment = Payment::lockForUpdate()
                ->findOrFail($payment->id);

            if ($payment->method !== 'gcash') {
                abort(422, 'Only GCash payments can be rejected here.');
            }

            if ($payment->status !== 'pending') {
                abort(422, 'This payment is no longer pending.');
            }

            $payment->update([
                'status' => 'rejected',
            ]);

            return $payment->fresh()->load('order');
        });

        return response()->json([
            'message' => 'GCash payment rejected.',
            'payment' => $payment,
        ]);
    }
}