<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DiscountType;
use App\Http\Controllers\Controller;
use App\Models\DiscountCode;
use App\Services\AuditLogger;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DiscountController extends Controller
{
    public function index(): View
    {
        return view('admin.discounts.index', [
            'discounts' => DiscountCode::query()->withSum('redemptions', 'amount')->latest()->paginate(30),
        ]);
    }

    public function create(): View
    {
        return view('admin.discounts.form', ['discount' => new DiscountCode(['type' => DiscountType::Percent, 'currency' => 'RWF', 'max_per_customer' => 1, 'is_active' => true])]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $discount = new DiscountCode($this->validated($request));
        $discount->created_by = $request->user()->id;
        $discount->save();
        $audit->log('discount.created', $discount, ['code' => $discount->code]);

        return redirect()->route('admin.discounts.index')->with('success', __('Discount :code created.', ['code' => $discount->code]));
    }

    public function edit(DiscountCode $discount): View
    {
        return view('admin.discounts.form', ['discount' => $discount]);
    }

    public function update(Request $request, DiscountCode $discount, AuditLogger $audit): RedirectResponse
    {
        $discount->fill($this->validated($request, $discount));
        $audit->logChanges('discount.updated', $discount);
        $discount->save();

        return redirect()->route('admin.discounts.index')->with('success', __('Discount saved.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?DiscountCode $discount = null): array
    {
        $v = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:40', Rule::unique('discount_codes', 'code')->ignore($discount?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::enum(DiscountType::class)],
            'percent' => ['required_if:type,percent', 'nullable', 'numeric', 'gt:0', 'max:100'],
            'amount' => ['required_if:type,fixed', 'nullable', 'numeric', 'gt:0'],
            'currency' => ['required', 'exists:currencies,code'],
            'min_subtotal' => ['nullable', 'numeric', 'min:0'],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'max_per_customer' => ['required', 'integer', 'min:1', 'max:1000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $percent = $v['type'] === DiscountType::Percent->value;

        return [
            'code' => mb_strtoupper($v['code']),
            'description' => $v['description'] ?? null,
            'type' => $v['type'],
            'percent_bps' => $percent ? (int) round($v['percent'] * 100) : null,
            'amount' => $percent ? null : Money::toMinor($v['amount'], $v['currency']),
            'currency' => $v['currency'],
            'min_subtotal_amount' => Money::toMinor($v['min_subtotal'] ?? 0, $v['currency']),
            'max_redemptions' => $v['max_redemptions'] ?? null,
            'max_per_customer' => $v['max_per_customer'],
            'starts_at' => $v['starts_at'] ?? null,
            'ends_at' => $v['ends_at'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
