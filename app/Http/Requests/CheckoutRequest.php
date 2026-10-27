<?php

namespace App\Http\Requests;

use App\Models\Country;
use App\Models\District;
use App\Rules\ValidPhone;
use App\Services\Payments\GatewayRegistry;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function country(): Country
    {
        return Country::query()->active()->where('iso2', config('marketplace.checkout_country', 'RW'))->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $country = $this->country();
        $methods = array_map(fn ($gateway) => $gateway->key(), app(GatewayRegistry::class)->availableFor($country->iso2, $country->currency_code));
        $needsPhone = in_array($this->input('payment_method'), ['mtn_momo', 'airtel_money', 'simulator'], true);

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['required', 'string', new ValidPhone($country)],
            'district_id' => ['required', Rule::exists('districts', 'id')->where('country_id', $country->id)],
            'sector' => ['nullable', 'string', 'max:80'],
            'street_address' => ['required', 'string', 'max:255'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:500'],
            'payment_method' => ['required', Rule::in($methods)],
            'payer_phone' => [Rule::requiredIf($needsPhone), 'nullable', 'string', new ValidPhone($country)],
            'save_address' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'district_id' => __('district'),
            'payer_phone' => __('mobile money number'),
            'payment_method' => __('payment method'),
        ];
    }

    public function normalizedPhone(string $field): ?string
    {
        $value = $this->input($field);

        return $value ? PhoneNumber::normalize($value, $this->country()) : null;
    }

    public function district(): District
    {
        return District::query()->with('deliveryZone')->findOrFail($this->integer('district_id'));
    }
}
