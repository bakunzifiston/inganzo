<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Currency;
use App\Models\DeliveryZone;
use App\Models\ExchangeRate;
use App\Services\AuditLogger;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocalizationController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(): View
    {
        return view('admin.localization.index', [
            'countries' => Country::query()->with(['currency', 'deliveryZones' => fn ($q) => $q->withCount('districts')])->orderByDesc('is_active')->orderBy('name')->get(),
            'currencies' => Currency::query()->orderBy('code')->get(),
            'rates' => ExchangeRate::query()->latest('effective_at')->limit(20)->get(),
        ]);
    }

    public function updateCountry(Request $request, Country $country): RedirectResponse
    {
        $validated = $request->validate([
            'tax_rate_percent' => ['required', 'numeric', 'min:0', 'max:50'],
            'phone_pattern' => ['required', 'string', 'max:100', function ($attribute, $value, $fail) {
                if (@preg_match('/^'.$value.'$/', '') === false) {
                    $fail(__('The phone pattern is not a valid regular expression.'));
                }
            }],
            'default_locale' => ['required', Rule::in(array_keys(config('marketplace.locales')))],
            'is_active' => ['sometimes', 'boolean'],
            'cod_enabled' => ['sometimes', 'boolean'],
        ]);

        if ($request->boolean('is_active') && ! $country->deliveryZones()->where('is_active', true)->exists()) {
            return back()->withErrors(['is_active' => __('Add an active delivery zone before activating :country.', ['country' => $country->name])]);
        }

        $country->fill([
            'tax_rate_bps' => (int) round($validated['tax_rate_percent'] * 100),
            'phone_pattern' => $validated['phone_pattern'],
            'default_locale' => $validated['default_locale'],
            'is_active' => $request->boolean('is_active'),
            'cod_enabled' => $request->boolean('cod_enabled'),
        ]);
        $this->audit->logChanges('country.updated', $country);
        $country->save();

        return back()->with('success', __(':country saved.', ['country' => $country->name]));
    }

    public function storeZone(Request $request): RedirectResponse
    {
        $data = $this->zoneData($request);
        $zone = DeliveryZone::query()->create($data);
        $this->audit->log('delivery_zone.created', $zone, $data);

        return back()->with('success', __('Delivery zone added.'));
    }

    public function updateZone(Request $request, DeliveryZone $zone): RedirectResponse
    {
        $zone->fill($this->zoneData($request, $zone));
        $this->audit->logChanges('delivery_zone.updated', $zone);
        $zone->save();

        return back()->with('success', __('Delivery zone saved.'));
    }

    public function storeRate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'base_currency' => ['required', 'exists:currencies,code'],
            'quote_currency' => ['required', 'exists:currencies,code', 'different:base_currency'],
            'rate' => ['required', 'numeric', 'gt:0'],
            'source' => ['nullable', 'string', 'max:120'],
        ]);

        $rate = ExchangeRate::query()->create([...$validated, 'effective_at' => now()]);
        $this->audit->log('exchange_rate.added', $rate, $validated);

        return back()->with('success', __('Exchange rate recorded. It is used for indicative display only.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function zoneData(Request $request, ?DeliveryZone $zone = null): array
    {
        $countryId = $zone?->country_id ?? $request->integer('country_id');
        $currency = Country::query()->findOrFail($countryId)->currency_code;
        $v = $request->validate([
            'country_id' => [$zone ? 'nullable' : 'required', 'exists:countries,id'],
            'name' => ['required', 'string', 'max:80', Rule::unique('delivery_zones')->where('country_id', $countryId)->ignore($zone?->id)],
            'fee' => ['required', 'numeric', 'min:0'],
            'free_over' => ['nullable', 'numeric', 'min:0'],
            'min_days' => ['required', 'integer', 'min:0', 'max:60'],
            'max_days' => ['required', 'integer', 'gte:min_days', 'max:90'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return [
            'country_id' => $countryId,
            'name' => $v['name'],
            'fee_amount' => Money::toMinor($v['fee'], $currency),
            'free_over_amount' => filled($v['free_over'] ?? null) ? Money::toMinor($v['free_over'], $currency) : null,
            'min_days' => $v['min_days'],
            'max_days' => $v['max_days'],
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
