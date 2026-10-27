<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MarkupType;
use App\Http\Controllers\Controller;
use App\Models\ArtistProduct;
use App\Models\Currency;
use App\Models\PricingRule;
use App\Models\Product;
use App\Models\ProductType;
use App\Services\AuditLogger;
use App\Services\Pricing\PriceCalculator;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Pricing rules control platform margin and artist markup limits. Changes only affect
 * future quotes: orders keep the prices frozen at checkout.
 */
class PricingRuleController extends Controller
{
    public function index(PriceCalculator $pricing): View
    {
        $examples = Product::query()->active()->orderBy('position')->get()->map(function (Product $product) use ($pricing) {
            try {
                $rule = $pricing->ruleFor($product);
                $quote = $pricing->preview($product, MarkupType::Percent, $rule->artist_markup_default_bps, null);

                return ['product' => $product, 'rule' => $rule, 'quote' => $quote];
            } catch (\RuntimeException) {
                return ['product' => $product, 'rule' => null, 'quote' => null];
            }
        });

        return view('admin.pricing-rules.index', [
            'rules' => PricingRule::query()->with(['product', 'productType'])->orderByDesc('is_active')->orderBy('id')->get(),
            'examples' => $examples,
        ]);
    }

    public function create(): View
    {
        return view('admin.pricing-rules.form', ['rule' => new PricingRule(['currency' => 'RWF', 'platform_margin_bps' => 2500, 'artist_markup_min_bps' => 0, 'artist_markup_max_bps' => 10000, 'artist_markup_default_bps' => 2000, 'rounding_increment' => 100, 'is_active' => true]), ...$this->options()]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $rule = new PricingRule($this->validated($request));
        $rule->created_by = $request->user()->id;
        $rule->save();
        $audit->log('pricing_rule.created', $rule, $rule->only(['name', 'platform_margin_bps', 'platform_fixed_amount', 'artist_markup_min_bps', 'artist_markup_max_bps', 'artist_markup_default_bps', 'artist_fixed_max_amount', 'rounding_increment']));

        $this->refreshListingPrices();

        return redirect()->route('admin.pricing-rules.index')->with('success', __('Pricing rule created. Listing prices are being refreshed.'));
    }

    public function edit(PricingRule $pricingRule): View
    {
        return view('admin.pricing-rules.form', ['rule' => $pricingRule, ...$this->options()]);
    }

    public function update(Request $request, PricingRule $pricingRule, AuditLogger $audit): RedirectResponse
    {
        $pricingRule->fill($this->validated($request));
        $pricingRule->updated_by = $request->user()->id;
        $audit->logChanges('pricing_rule.updated', $pricingRule);
        $pricingRule->save();
        $this->refreshListingPrices();

        return redirect()->route('admin.pricing-rules.index')->with('success', __('Pricing rule updated. Existing orders are not affected.'));
    }

    /**
     * Cached "from" prices on listings are recalculated after the response is sent.
     */
    private function refreshListingPrices(): void
    {
        dispatch(function () {
            $pricing = app(PriceCalculator::class);
            $pricing->clearCache();
            ArtistProduct::query()->with(['product.variants', 'colors'])->chunkById(200, function ($products) use ($pricing) {
                foreach ($products as $artistProduct) {
                    try {
                        $pricing->refreshCachedPrice($artistProduct);
                    } catch (\RuntimeException) {
                        // No active rule for this product: leave the cached price untouched.
                    }
                }
            });
        })->afterResponse();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $v = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'scope' => ['required', Rule::in(['global', 'type', 'product'])],
            'product_type_id' => ['required_if:scope,type', 'nullable', 'exists:product_types,id'],
            'product_id' => ['required_if:scope,product', 'nullable', 'exists:products,id'],
            'currency' => ['required', 'exists:currencies,code'],
            'platform_margin_percent' => ['required', 'numeric', 'min:0', 'max:500'],
            'platform_fixed' => ['nullable', 'numeric', 'min:0'],
            'artist_min_percent' => ['required', 'numeric', 'min:0', 'max:1000'],
            'artist_default_percent' => ['required', 'numeric', 'gte:artist_min_percent', 'lte:artist_max_percent'],
            'artist_max_percent' => ['required', 'numeric', 'gte:artist_min_percent', 'max:1000'],
            'artist_fixed_max' => ['nullable', 'numeric', 'min:0'],
            'rounding_increment' => ['required', 'integer', 'min:1', 'max:100000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $bps = fn ($percent) => (int) round(((float) $percent) * 100);

        return [
            'name' => $v['name'],
            'product_type_id' => $v['scope'] === 'type' ? $v['product_type_id'] : null,
            'product_id' => $v['scope'] === 'product' ? $v['product_id'] : null,
            'currency' => $v['currency'],
            'platform_margin_bps' => $bps($v['platform_margin_percent']),
            'platform_fixed_amount' => Money::toMinor($v['platform_fixed'] ?? 0, $v['currency']),
            'artist_markup_min_bps' => $bps($v['artist_min_percent']),
            'artist_markup_default_bps' => $bps($v['artist_default_percent']),
            'artist_markup_max_bps' => $bps($v['artist_max_percent']),
            'artist_fixed_max_amount' => filled($v['artist_fixed_max'] ?? null) ? Money::toMinor($v['artist_fixed_max'], $v['currency']) : null,
            'rounding_increment' => $v['rounding_increment'],
            'starts_at' => $v['starts_at'] ?? null,
            'ends_at' => $v['ends_at'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'products' => Product::query()->orderBy('position')->get()->mapWithKeys(fn ($p) => [$p->id => $p->translate('name')]),
            'types' => ProductType::query()->orderBy('position')->get()->mapWithKeys(fn ($t) => [$t->id => $t->translate('name')]),
            'currencies' => Currency::query()->pluck('code', 'code'),
            'sampleCost' => Product::query()->where('slug', 'classic-tee')->value('base_cost_amount') ?? 6500,
        ];
    }
}
