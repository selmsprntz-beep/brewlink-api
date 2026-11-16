<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\ProductIngredient;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class InventoryDeduction
{
    
    public function deductForOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $locked = Order::lockForUpdate()->find($order->id);

            if (!$locked || $locked->inventory_deducted_at) {
                return; 
            }

            $locked->load('items');

            $totals = [];

            foreach ($locked->items as $item) {
                if (!$item->product_id) {
                    continue;
                }

                $isGrande = strtolower(trim((string) $item->size)) === 'grande';
                $lines = ProductIngredient::where('product_id', $item->product_id)->get();

                foreach ($lines as $line) {
                    $tall = (float) $line->quantity;
                    $grande = $line->grande_quantity;

                    $per = $isGrande
                        ? ($grande !== null ? (float) $grande : $tall)
                        : $tall;

                    if ($per <= 0) {
                        continue;
                    }

                    $totals[$line->inventory_item_id] =
                        ($totals[$line->inventory_item_id] ?? 0) + ($per * (int) $item->quantity);
                }
            }

            foreach ($totals as $inventoryItemId => $needed) {
                if ($needed <= 0) {
                    continue;
                }

                $inv = InventoryItem::lockForUpdate()->find($inventoryItemId);
                if (!$inv) {
                    continue;
                }

                $newQty = max(0, (float) $inv->quantity - $needed);
                $used = (float) $inv->quantity - $newQty;

                $inv->forceFill(['quantity' => $newQty])->save();

                (new StockMovement())->forceFill([
                    'inventory_item_id' => $inv->id,
                    'type' => 'out',
                    'quantity' => -$used,
                    'balance_after' => $newQty,
                    'reason' => 'Order ' . $locked->order_number,
                    'user_id' => auth()->id(),
                ])->save();
            }

            $locked->forceFill(['inventory_deducted_at' => now()])->save();
        });
    }
}