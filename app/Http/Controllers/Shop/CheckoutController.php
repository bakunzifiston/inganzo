<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Models\Country;
use App\Models\CustomerAddress;
use App\Models\District;
use App\Services\Cart\CartManager;
use App\Services\Checkout\CheckoutData;
use App\Services\Checkout\CheckoutService;
use App\Services\Payments\GatewayRegistry;
use App\Services\Payments\PaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function __construct(
        private CartManager $carts,
        private GatewayRegistry $gateways,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        $cart = $this->carts->current();
        $country = Country::query()->active()->where('iso2', 'RW')->firstOrFail();
        $district = $request->integer('district_id') ? District::query()->with('deliveryZone')->find($request->integer('district_id')) : null;
        $savedAddress = $request->user()?->addresses()->orderByDesc('is_default')->with('district.deliveryZone')->first();
        $district ??= $savedAddress?->district;

        $summary = $this->carts->summarize($cart, $district?->deliveryZone, $country, $request->user()?->email, $request->user()?->id);

        if ($summary->isEmpty()) {
            return redirect()->route('cart.show');
        }

        return view('shop.checkout', [
            'summary' => $summary,
            'country' => $country,
            'districts' => $country->districts()->with('deliveryZone')->get()->groupBy('province'),
            'zones' => $country->deliveryZones()->where('is_active', true)->get(),
            'selectedDistrict' => $district,
            'savedAddress' => $savedAddress,
            'gateways' => $this->gateways->availableFor($country->iso2, $country->currency_code),
        ]);
    }

    public function store(CheckoutRequest $request, CheckoutService $checkout, PaymentService $payments): RedirectResponse
    {
        $cart = $this->carts->current();

        if ($cart === null) {
            throw ValidationException::withMessages(['cart' => __('Your cart is empty.')]);
        }

        $user = $request->user();
        $district = $request->district();

        $order = $checkout->placeOrder($cart, new CheckoutData(
            user: $user,
            name: $request->string('name')->toString(),
            email: $request->string('email')->toString(),
            phone: $request->normalizedPhone('phone'),
            country: $request->country(),
            district: $district,
            sector: $request->input('sector'),
            streetAddress: $request->string('street_address')->toString(),
            landmark: $request->input('landmark'),
            note: $request->input('note'),
            paymentMethod: $request->string('payment_method')->toString(),
            payerPhone: $request->normalizedPhone('payer_phone'),
            locale: app()->getLocale(),
        ));

        if ($user && $request->boolean('save_address') && ! $user->addresses()->where('street_address', $request->input('street_address'))->exists()) {
            CustomerAddress::query()->create([
                'user_id' => $user->id,
                'recipient_name' => $order->customer_name,
                'phone' => $order->phone,
                'country_id' => $order->country_id,
                'district_id' => $district->id,
                'sector' => $request->input('sector'),
                'street_address' => $request->input('street_address'),
                'landmark' => $request->input('landmark'),
                'is_default' => ! $user->addresses()->exists(),
            ]);
        }

        try {
            $payment = $payments->initiate($order, $order->payment_method, $request->normalizedPhone('payer_phone'));
        } catch (ValidationException $e) {
            return redirect()->route('orders.track', $order->tracking_token)->withErrors($e->errors());
        }

        $redirect = $payment->meta['redirect_url'] ?? null;

        return $redirect
            ? redirect()->to($redirect)
            : redirect()->route('orders.track', $order->tracking_token);
    }
}
