<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductType;
use App\Services\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductTypeController extends Controller
{
    public function index(): View
    {
        return view('admin.product-types.index', ['types' => ProductType::query()->withCount('products')->orderBy('position')->get()]);
    }

    public function create(): View
    {
        return view('admin.product-types.form', ['type' => new ProductType(['is_active' => true])]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $type = ProductType::query()->create($this->validated($request));
        $audit->log('product_type.created', $type);

        return redirect()->route('admin.product-types.index')->with('success', __('Product type created.'));
    }

    public function edit(ProductType $productType): View
    {
        return view('admin.product-types.form', ['type' => $productType]);
    }

    public function update(Request $request, ProductType $productType, AuditLogger $audit): RedirectResponse
    {
        $productType->fill($this->validated($request, $productType));
        $audit->logChanges('product_type.updated', $productType);
        $productType->save();

        return redirect()->route('admin.product-types.index')->with('success', __('Product type saved.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?ProductType $type = null): array
    {
        $v = $request->validate([
            'slug' => ['required', 'alpha_dash', 'max:120', Rule::unique('product_types', 'slug')->ignore($type?->id)],
            'name_en' => ['required', 'string', 'max:80'],
            'name_rw' => ['nullable', 'string', 'max:80'],
            'description_en' => ['nullable', 'string', 'max:500'],
            'position' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return [
            'slug' => strtolower($v['slug']),
            'name' => array_filter(['en' => $v['name_en'], 'rw' => $v['name_rw'] ?? null]),
            'description' => filled($v['description_en'] ?? null) ? ['en' => $v['description_en']] : null,
            'position' => $v['position'] ?? 0,
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
