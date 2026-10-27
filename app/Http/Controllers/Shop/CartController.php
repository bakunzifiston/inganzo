<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\ArtistProduct;
use App\Models\CartItem;
use App\Models\FinishedProductVariant;
use App\Models\ProductVariant;
use App\Services\Cart\CartManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private CartManager $carts) {}

    public function show(): View
    {
        $cart = $this->carts->current();

        return view('shop.cart', ['summary' => $this->carts->summarize($cart), 'cart' => $cart]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'finished_variant_id' => ['nullable', 'integer', 'exists:finished_product_variants,id'],
            'artist_product_id' => ['required_without:finished_variant_id', 'nullable', 'integer', 'exists:artist_products,id'],
            'variant_id' => ['required_without:finished_variant_id', 'nullable', 'integer', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.config('marketplace.cart_max_quantity')],
        ]);

        if (! empty($validated['finished_variant_id'])) {
            $this->carts->addFinished(FinishedProductVariant::query()->findOrFail($validated['finished_variant_id']), $validated['quantity']);

            return redirect()->route('cart.show')->with('success', __('Added to your cart.'));
        }

        $this->carts->add(
            ArtistProduct::query()->findOrFail($validated['artist_product_id']),
            ProductVariant::query()->findOrFail($validated['variant_id']),
            $validated['quantity'],
        );

        return redirect()->route('cart.show')->with('success', __('Added to your cart.'));
    }

    public function update(Request $request, CartItem $item): RedirectResponse
    {
        $validated = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:'.config('marketplace.cart_max_quantity')]]);
        $this->carts->updateQuantity($item, $validated['quantity']);

        return redirect()->route('cart.show');
    }

    public function destroy(CartItem $item): RedirectResponse
    {
        $this->carts->remove($item);

        return redirect()->route('cart.show')->with('success', __('Item removed.'));
    }

    public function applyDiscount(Request $request): RedirectResponse
    {
        $validated = $request->validate(['discount_code' => ['required', 'string', 'max:40']]);
        $this->carts->applyDiscount($validated['discount_code']);

        return back()->with('success', __('Discount code applied.'));
    }

    public function removeDiscount(): RedirectResponse
    {
        $this->carts->removeDiscount();

        return back();
    }
}
