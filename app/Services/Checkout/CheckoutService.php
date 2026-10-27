<?php

namespace App\Services\Checkout;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderItemStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\SellingType;
use App\Models\Cart;
use App\Models\DiscountRedemption;
use App\Models\Order;
use App\Notifications\OrderPlaced;
use App\Services\Cart\CartLine;
use App\Services\Cart\CartManager;
use App\Services\Cart\DiscountEvaluator;
use App\Services\Inventory\StockManager;
use App\Support\Money;
use App\Support\Reference;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Converts a cart into an order. Every price, discount and delivery fee is recalculated on
 * the server inside one transaction; the cart row is locked so a double-submitted checkout
 * cannot create two orders.
 */
class CheckoutService
{
    public function __construct(
        private CartManager $carts,
        private DiscountEvaluator $discounts,
        private StockManager $stock,
    ) {}

    public function placeOrder(Cart $cart, CheckoutData $data): Order
    {
        $order = DB::transaction(function () use ($cart, $data) {
            $cart = Cart::query()->lockForUpdate()->findOrFail($cart->id);
            $zone = $data->district->deliveryZone;

            if (! $zone->is_active || $zone->country_id !== $data->country->id) {
                throw ValidationException::withMessages(['district_id' => __('We do not deliver to this area yet.')]);
            }

            $email = mb_strtolower($data->email);
            $summary = $this->carts->summarize($cart, $zone, $data->country, $email, $data->user?->id);

            if ($summary->isEmpty()) {
                throw ValidationException::withMessages(['cart' => __('Your cart is empty.')]);
            }
            if ($summary->hasUnavailableItems()) {
                throw ValidationException::withMessages(['cart' => __('Some items in your cart are no longer available. Review your cart and try again.')]);
            }

            $discountAmount = 0;
            $discountCode = null;
            if ($cart->discount_code) {
                // Lock the code row so concurrent orders cannot exceed its redemption limit.
                $evaluated = $this->discounts->evaluate($cart->discount_code, $summary->subtotal, $summary->currency, $email, $data->user?->id, lock: true);
                $discountAmount = $evaluated['amount'];
                $discountCode = $evaluated['code'];
            }

            $delivery = $zone->feeFor($summary->subtotal - $discountAmount);
            $total = $summary->subtotal - $discountAmount + $delivery;
            $taxRate = $data->country->tax_rate_bps;
            $tax = $taxRate > 0 ? intdiv($total * $taxRate + intdiv(10000 + $taxRate, 2), 10000 + $taxRate) : 0;

            $order = Order::query()->create([
                'number' => Reference::generate('IGZ', Order::class, 'number'),
                'user_id' => $data->user?->id,
                'customer_name' => $data->name,
                'email' => $email,
                'phone' => $data->phone,
                'currency' => $summary->currency,
                'country_id' => $data->country->id,
                'delivery_zone_id' => $zone->id,
                'locale' => $data->locale,
                'status' => OrderStatus::PendingPayment,
                'payment_status' => OrderPaymentStatus::Unpaid,
                'fulfillment_status' => FulfillmentStatus::Unfulfilled,
                'payment_method' => $data->paymentMethod,
                'subtotal_amount' => $summary->subtotal,
                'discount_amount' => $discountAmount,
                'delivery_amount' => $delivery,
                'tax_amount' => $tax,
                'tax_rate_bps' => $taxRate,
                'total_amount' => $total,
                'discount_code_id' => $discountCode?->id,
                'tracking_token' => Str::random(48),
                'customer_note' => $data->note,
                'placed_at' => now(),
            ]);

            $order->address()->create([
                'recipient_name' => $data->name,
                'phone' => $data->phone,
                'country_id' => $data->country->id,
                'district' => $data->district->name,
                'sector' => $data->sector,
                'street_address' => $data->streetAddress,
                'landmark' => $data->landmark,
            ]);

            $allocation = Money::allocate($discountAmount, array_map(fn ($line) => $line->total(), $summary->lines));

            // Ready-made items: take the stock now. A shortage throws and rolls the whole order back.
            $this->stock->reserve($this->finishedQuantities($summary->lines));

            foreach ($summary->lines as $index => $line) {
                if ($line->item->isFinished()) {
                    $this->createFinishedItem($order, $line, $allocation[$index] ?? 0);

                    continue;
                }

                $artistProduct = $line->item->artistProduct;
                $variant = $line->item->variant;
                $version = $artistProduct->artwork->currentVersion;

                $order->items()->create([
                    'artist_product_id' => $artistProduct->id,
                    'artwork_id' => $artistProduct->artwork_id,
                    'artwork_version_id' => $version->id,
                    'artist_profile_id' => $artistProduct->artwork->artist_profile_id,
                    'product_id' => $artistProduct->product_id,
                    'product_variant_id' => $variant->id,
                    'pricing_rule_id' => $line->price->pricingRuleId,
                    'title' => $artistProduct->artwork->title,
                    'product_name' => $artistProduct->product->translate('name', 'en'),
                    'color_name' => $variant->color->name,
                    'color_hex' => $variant->color->hex,
                    'size_label' => $variant->size?->label,
                    'sku' => $variant->sku,
                    'quantity' => $line->item->quantity,
                    'unit_price_amount' => $line->price->unitPrice,
                    'unit_base_cost_amount' => $line->price->baseCost,
                    'unit_artist_earning_amount' => $line->price->artistEarning,
                    'unit_platform_margin_amount' => $line->price->platformMargin,
                    'line_total_amount' => $line->total(),
                    'discount_amount' => $allocation[$index] ?? 0,
                    'fulfillment_status' => OrderItemStatus::Pending,
                    'placement_snapshot' => $this->placementSnapshot($artistProduct, $version),
                ]);
            }

            if ($discountCode !== null) {
                DiscountRedemption::query()->create([
                    'discount_code_id' => $discountCode->id,
                    'order_id' => $order->id,
                    'user_id' => $data->user?->id,
                    'email' => $email,
                    'amount' => $discountAmount,
                    'created_at' => now(),
                ]);
                $discountCode->increment('redemptions_count');
            }

            $cart->items()->delete();
            $cart->update(['discount_code' => null]);

            return $order;
        });

        $notification = new OrderPlaced($order);
        $order->user
            ? $order->user->notify($notification)
            : Notification::route('mail', $order->email)->notify($notification);

        return $order;
    }

    /**
     * @param  list<CartLine>  $lines
     * @return array<int, int> finished variant id => total quantity
     */
    private function finishedQuantities(array $lines): array
    {
        $quantities = [];
        foreach ($lines as $line) {
            if ($line->item->isFinished()) {
                $id = $line->item->finished_product_variant_id;
                $quantities[$id] = ($quantities[$id] ?? 0) + $line->item->quantity;
            }
        }

        return $quantities;
    }

    private function createFinishedItem(Order $order, CartLine $line, int $discount): void
    {
        $variant = $line->item->finishedVariant;
        $product = $variant->product;

        $order->items()->create([
            'selling_type' => SellingType::Finished,
            'finished_product_id' => $product->id,
            'finished_product_variant_id' => $variant->id,
            'artist_profile_id' => $product->artist_profile_id,
            'title' => $product->title,
            'product_name' => 'Ready-made',
            'color_name' => $variant->color_name,
            'color_hex' => $variant->color_hex,
            'size_label' => $variant->size_label,
            'sku' => $variant->sku,
            'quantity' => $line->item->quantity,
            'unit_price_amount' => $line->price->unitPrice,
            'unit_base_cost_amount' => 0,
            'unit_artist_earning_amount' => $line->price->artistEarning,
            'unit_platform_margin_amount' => $line->price->platformMargin,
            'line_total_amount' => $line->total(),
            'discount_amount' => $discount,
            'fulfillment_status' => OrderItemStatus::Pending,
        ]);
    }

    /**
     * Release discount redemptions and reserved stock of an order that will never be paid.
     */
    public function cancelUnpaid(Order $order, string $reason): void
    {
        DB::transaction(function () use ($order, $reason) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->isPaid() || $order->status !== OrderStatus::PendingPayment) {
                return;
            }

            $order->forceFill(['status' => OrderStatus::Cancelled, 'cancelled_at' => now(), 'cancel_reason' => $reason])->save();
            $order->items()->update(['fulfillment_status' => OrderItemStatus::Cancelled->value]);
            $this->stock->releaseOrder($order);
            $order->payments()->where('status', 'pending')->update(['status' => 'cancelled', 'failed_at' => now()]);

            $redemption = DiscountRedemption::query()->where('order_id', $order->id)->first();
            if ($redemption) {
                $redemption->delete();
                DB::table('discount_codes')->where('id', $redemption->discount_code_id)->where('redemptions_count', '>', 0)->decrement('redemptions_count');
            }
        });
    }

    /**
     * Frozen production specification for printing partners.
     *
     * @return list<array<string, mixed>>
     */
    private function placementSnapshot($artistProduct, $version): array
    {
        $areas = $artistProduct->product->printAreas->keyBy('id');

        return $artistProduct->placements->map(fn ($placement) => [
            'print_area_id' => $placement->print_area_id,
            'print_area_key' => $areas[$placement->print_area_id]->key,
            'print_area_label' => $areas[$placement->print_area_id]->label,
            'print_area_mm' => [$areas[$placement->print_area_id]->width_mm, $areas[$placement->print_area_id]->height_mm],
            'offset_x' => $placement->offset_x,
            'offset_y' => $placement->offset_y,
            'scale' => $placement->scale,
            'rotation' => $placement->rotation,
            'effective_dpi' => $placement->effective_dpi,
            'print_check_status' => $placement->print_check_status->value,
            'artwork_version_id' => $version->id,
            'artwork_pixels' => [$version->width, $version->height],
            'artwork_sha256' => $version->sha256,
            'printed_width_mm' => round($placement->scale * $areas[$placement->print_area_id]->width_mm, 1),
        ])->values()->all();
    }
}
