<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function summary(): JsonResponse
    {
        $today = today();

        return response()->json([
            'pending_orders' => Order::where('status', 'pending')->count(),
            'preparing_orders' => Order::where('status', 'preparing')->count(),
            'ready_orders' => Order::where('status', 'ready')->count(),
            'orders_today' => Order::whereDate('created_at', $today)->where('status', '!=', 'cancelled')->count(),
            // Only payments that staff already verified are counted.
            'sales_today' => (float) Payment::where('status', 'verified')
                ->whereDate('paid_at', $today)
                ->sum('amount'),
            'pending_reservations' => Reservation::where('status', 'pending')->count(),
            'reservations_today' => Reservation::whereDate('reservation_date', $today)
                ->whereIn('status', ['pending', 'confirmed'])->count(),
            'low_stock_count' => InventoryItem::whereColumn('quantity', '<=', 'low_stock_threshold')->count(),
        ]);
    }
}