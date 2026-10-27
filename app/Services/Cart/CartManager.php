<?php

namespace App\Services\Cart;

use App\Models\ArtistProduct;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Country;
use App\Models\DeliveryZone;
use App\Models\FinishedProductVariant;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Pricing\PriceCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Session/user cart handling. Prices shown in the cart are always recalculated from the
 * catalogue; nothing price-related is stored on cart rows.
 */
class CartManager
{
    private const SESSION_KEY = 'cart_token';

    public function __construct(
        private PriceCalculator $pricing,
        private DiscountEvaluator $discounts,
    ) {}

    public function current(bool $create = false): ?Cart
    {
        $user = auth()->user();

        if ($user instanceof User) {
            $cart = Cart::query()->where('user_id', $user->id)->first();
            if ($cart === null && $create) {
                $cart = Cart::query()->create(['token' => (string) Str::uuid(), 'user_id' => $user->id, 'currency' => config('marketplace.default_currency')]);
            }

            return $cart;
        }

        $token = session(self::SESSION_KEY);
        $cart = $token ? Cart::query()->where('token', $token)->whereNull('user_id')->first() : null;

        if ($cart === null && $create) {
            $cart = Cart::query()->create(['token' => (string) Str::uuid(), 'currency' => config('marketplace.default_currency')]);
            session([self::SESSION_KEY => $cart->token]);
        }

        return $cart;
    }

    public function add(ArtistProduct $artistProduct, ProductVariant $variant, int $quantity): CartItem
    {
        $artistProduct->loadMissing(['product', 'artwork.artist', 'colors']);
        $variant->loadMissing(['product', 'color', 'size']);

        if (! $artistProduct->isListed()) {
            throw ValidationException::withMessages(['variant' => __('This product is not available.')]);
        }
        if ($variant->product_id !== $artistProduct->product_id || ! $variant->isPurchasable()) {
            throw ValidationException::withMessages(['variant' => __('Choose an available colour and size.')]);
        }
        if (! $artistProduct->colors->contains('id', $variant->product_color_id)) {
            throw ValidationException::withMessages(['variant' => __('This colour is not offered for this design.')]);
        }

        $max = config('marketplace.cart_max_quantity');

        return DB::transaction(function () use ($artistProduct, $variant, $quantity, $max) {
            $cart = $this->current(create: true);
            $item = $cart->items()->where('artist_product_id', $artistProduct->id)->where('product_variant_id', $variant->id)->lockForUpdate()->first();

            $newQuantity = min($max, ($item?->quantity ?? 0) + max(1, $quantity));

            if ($item) {
                $item->update(['quantity' => $newQuantity]);

                return $item;
            }

            return $cart->items()->create([
                'artist_product_id' => $artistProduct->id,
                'product_variant_id' => $variant->id,
                'quantity' => $newQuantity,
            ]);
        });
    }

    /**
     * Add a ready-made product. The quantity is capped at the stock on hand; the real
     * reservation happens at checkout, so this is a convenience, not a guarantee.
     */
    public function addFinished(FinishedProductVariant $variant, int $quantity): CartItem
    {
        $variant->loadMissing('product.artist');

        if (! $variant->product->isListed() || ! $variant->is_active) {
            throw ValidationException::withMessages(['variant' => __('This product is not available.')]);
        }
        if ($variant->stock_quantity < 1) {
            throw ValidationException::withMessages(['variant' => __('This option is out of stock.')]);
        }

        return DB::transaction(function () use ($variant, $quantity) {
            $cart = $this->current(create: true);
            $item = $cart->items()->where('finished_product_variant_id', $variant->id)->lockForUpdate()->first();
            $limit = min(config('marketplace.cart_max_quantity'), $variant->stock_quantity);

            if (($item?->quantity ?? 0) >= $limit && $item !== null) {
                throw ValidationException::withMessages(['variant' => __('You already have all :count available in your cart.', ['count' => $limit])]);
            }

            $newQuantity = min($limit, ($item?->quantity ?? 0) + max(1, $quantity));

            if ($item) {
                $item->update(['quantity' => $newQuantity]);

                return $item;
            }

            return $cart->items()->create(['finished_product_variant_id' => $variant->id, 'quantity' => $newQuantity]);
        });
    }

    public function updateQuantity(CartItem $item, int $quantity): void
    {
        $this->assertOwned($item);

        if ($quantity <= 0) {
            $item->delete();

            return;
        }

        $limit = config('marketplace.cart_max_quantity');
        if ($item->isFinished()) {
            $limit = min($limit, max(1, (int) $item->finishedVariant()->value('stock_quantity')));
        }

        $item->update(['quantity' => min($limit, $quantity)]);
    }

    public function remove(CartItem $item): void
    {
        $this->assertOwned($item);
        $item->delete();
    }

    public function applyDiscount(string $code): void
    {
        $cart = $this->current(create: true);
        $summary = $this->summarize($cart);
        $this->discounts->evaluate($code, $summary->subtotal, $cart->currency);
        $cart->update(['discount_code' => mb_strtoupper(trim($code))]);
    }

    public function removeDiscount(): void
    {
        $this->current()?->update(['discount_code' => null]);
    }

    /**
     * Move a guest cart into the user's cart after login or registration.
     */
    public function mergeGuestCart(User $user, ?string $guestToken): void
    {
        if (! $guestToken) {
            return;
        }

        DB::transaction(function () use ($user, $guestToken) {
            $guest = Cart::query()->where('token', $guestToken)->whereNull('user_id')->with('items')->lockForUpdate()->first();
            if ($guest === null) {
                return;
            }

            $userCart = Cart::query()->where('user_id', $user->id)->lockForUpdate()->first();

            if ($userCart === null) {
                $guest->update(['user_id' => $user->id]);

                return;
            }

            foreach ($guest->items as $item) {
                $existing = $userCart->items()
                    ->when($item->isFinished(),
                        fn ($q) => $q->where('finished_product_variant_id', $item->finished_product_variant_id),
                        fn ($q) => $q->where('artist_product_id', $item->artist_product_id)->where('product_variant_id', $item->product_variant_id))
                    ->first();
                $existing
                    ? $existing->update(['quantity' => min(config('marketplace.cart_max_quantity'), $existing->quantity + $item->quantity)])
                    : $userCart->items()->create($item->only(['artist_product_id', 'product_variant_id', 'finished_product_variant_id', 'quantity']));
            }

            if ($guest->discount_code && ! $userCart->discount_code) {
                $userCart->update(['discount_code' => $guest->discount_code]);
            }

            $guest->delete();
        });
    }

    public function guestToken(): ?string
    {
        return session(self::SESSION_KEY);
    }

    public function summarize(?Cart $cart, ?DeliveryZone $zone = null, ?Country $country = null, ?string $email = null, ?int $userId = null): CartSummary
    {
        $currency = $cart?->currency ?? config('marketplace.default_currency');
        $lines = [];

        if ($cart !== null) {
            $items = $cart->items()->with([
                'artistProduct.artwork.artist',
                'artistProduct.artwork.currentVersion',
                'artistProduct.product.printAreas',
                'artistProduct.product.mockups',
                'artistProduct.colors',
                'artistProduct.placements',
                'variant.color',
                'variant.size',
                'variant.product',
                'finishedVariant.product.artist',
                'finishedVariant.product.images',
            ])->get();

            foreach ($items as $item) {
                $lines[] = $this->line($item);
            }
        }

        $subtotal = array_sum(array_map(fn (CartLine $line) => $line->available ? $line->total() : 0, $lines));

        $discount = 0;
        $discountCode = null;
        $discountError = null;
        if ($cart?->discount_code && $subtotal > 0) {
            try {
                $evaluated = $this->discounts->evaluate($cart->discount_code, $subtotal, $currency, $email, $userId);
                $discount = $evaluated['amount'];
                $discountCode = $evaluated['code'];
            } catch (ValidationException $e) {
                $discountError = $e->validator->errors()->first('discount_code');
            }
        }

        $delivery = $zone ? $zone->feeFor($subtotal - $discount) : 0;
        $country ??= $zone?->country;

        return new CartSummary($lines, $currency, $subtotal, $discount, $discountCode, $discountError, $zone, $delivery, $country?->tax_rate_bps ?? 0);
    }

    private function line(CartItem $item): CartLine
    {
        if ($item->isFinished()) {
            return $this->finishedLine($item);
        }

        $artistProduct = $item->artistProduct;
        $variant = $item->variant;

        if (! $artistProduct->isListed()) {
            return new CartLine($item, null, false, __('This design is no longer available.'));
        }
        if (! $variant->isPurchasable() || ! $artistProduct->colors->contains('id', $variant->product_color_id)) {
            return new CartLine($item, null, false, __('This colour or size is no longer available.'));
        }

        return new CartLine($item, $this->pricing->quote($artistProduct, $variant), true);
    }

    private function finishedLine(CartItem $item): CartLine
    {
        $variant = $item->finishedVariant;

        if (! $variant->product->isListed() || ! $variant->is_active) {
            return new CartLine($item, null, false, __('This product is no longer available.'));
        }
        if ($variant->stock_quantity < 1) {
            return new CartLine($item, null, false, __('This option is out of stock.'));
        }
        if ($variant->stock_quantity < $item->quantity) {
            return new CartLine($item, null, false, trans_choice('Only :count left in stock. Reduce the quantity to continue.|Only :count left in stock. Reduce the quantity to continue.', $variant->stock_quantity, ['count' => $variant->stock_quantity]));
        }

        return new CartLine($item, $this->pricing->quoteFinished($variant), true);
    }

    private function assertOwned(CartItem $item): void
    {
        $cart = $this->current();

        if ($cart === null || $item->cart_id !== $cart->id) {
            abort(404);
        }
    }
}
