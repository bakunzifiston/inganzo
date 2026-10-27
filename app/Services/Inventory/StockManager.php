<?php

namespace App\Services\Inventory;

use App\Enums\SellingType;
use App\Models\FinishedProductVariant;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Stock for finished products. Stock is reserved when an order is placed and returned when the
 * order is cancelled or fully refunded before the item shipped.
 *
 * Reservation is a single conditional UPDATE (stock_quantity >= quantity), so concurrent
 * checkouts can never take the same unit; the CHECK constraint on the column is the last line
 * of defence. Call inside the transaction that creates the order, so a failure rolls it back.
 */
class StockManager
{
    /**
     * @param  array<int, int>  $quantities  variant id => quantity wanted
     *
     * @throws ValidationException
     */
    public function reserve(array $quantities): void
    {
        ksort($quantities);

        foreach ($quantities as $variantId => $quantity) {
            $reserved = DB::table('finished_product_variants')
                ->where('id', $variantId)
                ->where('is_active', true)
                ->where('stock_quantity', '>=', $quantity)
                ->decrement('stock_quantity', $quantity);

            if ($reserved === 0) {
                $variant = FinishedProductVariant::query()->with('product')->find($variantId);

                throw ValidationException::withMessages(['cart' => $variant && $variant->stock_quantity > 0 && $variant->is_active
                    ? __('Only :count of ":title" are left in stock. Update your cart and try again.', ['count' => $variant->stock_quantity, 'title' => $variant->product->title])
                    : __('":title" is out of stock. Remove it from your cart and try again.', ['title' => $variant?->product->title ?? __('An item')])]);
            }
        }
    }

    /**
     * Return the stock reserved for an order item. Safe to call more than once.
     */
    public function release(OrderItem $item): void
    {
        if ($item->selling_type !== SellingType::Finished || $item->stock_released_at !== null) {
            return;
        }

        $marked = DB::table('order_items')->where('id', $item->id)->whereNull('stock_released_at')->update(['stock_released_at' => now()]);

        if ($marked === 1) {
            DB::table('finished_product_variants')->where('id', $item->finished_product_variant_id)->increment('stock_quantity', $item->quantity);
            $item->stock_released_at = now();
        }
    }

    public function releaseOrder(Order $order): void
    {
        $order->items()->where('selling_type', SellingType::Finished->value)->whereNull('stock_released_at')->get()->each(fn (OrderItem $item) => $this->release($item));
    }

    /**
     * Artist stock correction: set the absolute quantity.
     */
    public function set(FinishedProductVariant $variant, int $quantity): void
    {
        DB::table('finished_product_variants')->where('id', $variant->id)->update(['stock_quantity' => max(0, $quantity), 'updated_at' => now()]);
        $variant->stock_quantity = max(0, $quantity);
    }
}
