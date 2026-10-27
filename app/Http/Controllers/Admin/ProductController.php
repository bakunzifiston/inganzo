<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\PrintArea;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductSize;
use App\Models\ProductType;
use App\Models\ProductVariant;
use App\Services\AuditLogger;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Database-driven merchandise catalogue: blanks, colours, sizes, variants, print areas, mockups.
 */
class ProductController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(): View
    {
        return view('admin.products.index', [
            'products' => Product::query()->with(['type', 'colors', 'sizes', 'printAreas', 'mockups'])->withCount(['variants', 'partnerServices'])->orderBy('product_type_id')->orderBy('position')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.products.form', ['product' => new Product(['currency' => 'RWF', 'production_days' => 3, 'weight_grams' => 250, 'is_active' => true, 'is_shippable' => true]), ...$this->options()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $product = DB::transaction(function () use ($data, $request) {
            $product = Product::query()->create($data);
            $area = $request->validate([
                'area_width_mm' => ['required', 'integer', 'min:10', 'max:2000'],
                'area_height_mm' => ['required', 'integer', 'min:10', 'max:2000'],
            ]);
            $printArea = $product->printAreas()->create(['key' => 'front', 'label' => 'Front', 'width_mm' => $area['area_width_mm'], 'height_mm' => $area['area_height_mm'], 'is_required' => true,
                'min_dpi' => config('marketplace.print_quality.minimum_dpi'), 'recommended_dpi' => config('marketplace.print_quality.sharp_dpi')]);
            // Default mockup rectangle keeps the physical aspect ratio, centred.
            $w = 0.4;
            $h = min(0.6, $w * $area['area_height_mm'] / $area['area_width_mm']);
            $w = $h * $area['area_width_mm'] / $area['area_height_mm'];
            $product->mockups()->create(['print_area_id' => $printArea->id, 'view' => 'front', 'area_x' => round((1 - $w) / 2, 4), 'area_y' => 0.25, 'area_width' => round($w, 4), 'area_height' => round($h, 4)]);

            return $product;
        });

        $this->audit->log('product.created', $product, ['slug' => $product->slug, 'base_cost' => $product->base_cost_amount]);

        return redirect()->route('admin.products.edit', $product)->with('success', __('Product created. Add colours and sizes to generate variants.'));
    }

    public function edit(Product $product): View
    {
        $product->load(['colors', 'sizes', 'variants.color', 'variants.size', 'printAreas', 'mockups.color', 'partnerServices.partner']);

        return view('admin.products.form', ['product' => $product, ...$this->options()]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $product->fill($this->validated($request, $product));
        $this->audit->logChanges('product.updated', $product);
        $product->save();

        return back()->with('success', __('Product saved.'));
    }

    public function storeColor(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('product_colors')->where('product_id', $product->id)],
            'hex' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_dark' => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($product, $validated, $request) {
            $color = $product->colors()->create([...$validated, 'hex' => strtoupper($validated['hex']), 'is_dark' => $request->boolean('is_dark'), 'position' => $product->colors()->count()]);
            $this->createVariants($product, colors: [$color]);
        });

        return back()->with('success', __('Colour added with its variants.'));
    }

    public function storeSize(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:20', Rule::unique('product_sizes')->where('product_id', $product->id)],
            'extra_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($product, $validated) {
            $hadSizes = $product->sizes()->exists();
            $size = $product->sizes()->create(['label' => $validated['label'], 'position' => $product->sizes()->count()]);
            if (! $hadSizes) {
                // Size-less variants are replaced by sized ones; deactivate rather than delete (orders may reference them).
                $product->variants()->whereNull('product_size_id')->update(['is_active' => false]);
            }
            $this->createVariants($product, sizes: [$size], extra: Money::toMinor($validated['extra_cost'] ?? 0, $product->currency));
        });

        return back()->with('success', __('Size added with its variants.'));
    }

    public function updateVariants(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'variants' => ['required', 'array'],
            'variants.*.extra_cost' => ['nullable', 'numeric', 'min:0'],
            'variants.*.is_active' => ['sometimes', 'boolean'],
            'colors' => ['array'],
            'colors.*' => ['sometimes', 'boolean'],
            'sizes' => ['array'],
            'sizes.*' => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($product, $validated) {
            foreach ($product->variants as $variant) {
                $input = $validated['variants'][$variant->id] ?? null;
                if ($input === null) {
                    continue;
                }
                $variant->fill([
                    'extra_cost_amount' => Money::toMinor($input['extra_cost'] ?? 0, $product->currency),
                    'is_active' => (bool) ($input['is_active'] ?? false),
                ]);
                if ($variant->isDirty()) {
                    $this->audit->logChanges('product_variant.updated', $variant);
                    $variant->save();
                }
            }
            foreach ($product->colors as $color) {
                $color->update(['is_active' => (bool) ($validated['colors'][$color->id] ?? false)]);
            }
            foreach ($product->sizes as $size) {
                $size->update(['is_active' => (bool) ($validated['sizes'][$size->id] ?? false)]);
            }
        });

        return back()->with('success', __('Variants saved.'));
    }

    public function updatePrintArea(Request $request, Product $product, PrintArea $printArea): RedirectResponse
    {
        abort_unless($printArea->product_id === $product->id, 404);

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:50'],
            'width_mm' => ['required', 'integer', 'min:10', 'max:2000'],
            'height_mm' => ['required', 'integer', 'min:10', 'max:2000'],
            'min_dpi' => ['required', 'integer', 'min:50', 'max:600'],
            'recommended_dpi' => ['required', 'integer', 'gte:min_dpi', 'max:1200'],
            'is_required' => ['sometimes', 'boolean'],
        ]);

        $printArea->fill([...$validated, 'is_required' => $request->boolean('is_required')]);
        $this->audit->logChanges('print_area.updated', $printArea);
        $printArea->save();

        return back()->with('success', __('Print area saved. Existing placements are re-checked when artists next save them.'));
    }

    public function storeMockup(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'print_area_id' => ['required', Rule::exists('print_areas', 'id')->where('product_id', $product->id)],
            'product_color_id' => ['nullable', Rule::exists('product_colors', 'id')->where('product_id', $product->id)],
            'view' => ['required', Rule::in(['front', 'back', 'wrap'])],
            'image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:8192', 'dimensions:min_width=600,min_height=600'],
            'area_x' => ['required', 'numeric', 'min:0', 'max:1'],
            'area_y' => ['required', 'numeric', 'min:0', 'max:1'],
            'area_width' => ['required', 'numeric', 'gt:0', 'max:1'],
            'area_height' => ['required', 'numeric', 'gt:0', 'max:1'],
        ]);

        if ($validated['area_x'] + $validated['area_width'] > 1 || $validated['area_y'] + $validated['area_height'] > 1) {
            return back()->withErrors(['area_width' => __('The print area must fit inside the image.')])->withInput();
        }

        $path = $request->file('image')?->store("mockups/{$product->id}", 'public');

        $mockup = $product->mockups()->updateOrCreate(
            ['print_area_id' => $validated['print_area_id'], 'product_color_id' => $validated['product_color_id'] ?? null, 'view' => $validated['view']],
            array_filter([
                'image_path' => $path,
                'area_x' => $validated['area_x'], 'area_y' => $validated['area_y'],
                'area_width' => $validated['area_width'], 'area_height' => $validated['area_height'],
            ], fn ($v) => $v !== null),
        );
        $this->audit->log('product_mockup.saved', $mockup, ['product' => $product->slug]);

        return back()->with('success', __('Mockup saved.'));
    }

    /**
     * @param  list<ProductColor>|null  $colors
     * @param  list<ProductSize>|null  $sizes
     */
    private function createVariants(Product $product, ?array $colors = null, ?array $sizes = null, int $extra = 0): void
    {
        $colors ??= $product->colors()->get()->all();
        $sizes ??= $product->sizes()->get()->all();

        foreach ($colors as $color) {
            foreach ($sizes ?: [null] as $size) {
                $sku = Str::upper(Str::limit(Str::slug($product->slug, ''), 8, '')).'-'.Str::upper(Str::slug($color->name, '')).($size ? '-'.Str::upper(Str::slug($size->label, '')) : '');
                while (ProductVariant::query()->where('sku', $sku)->exists()) {
                    $sku .= '-'.Str::upper(Str::random(2));
                }
                $product->variants()->firstOrCreate(
                    ['product_color_id' => $color->id, 'product_size_id' => $size?->id],
                    ['sku' => $sku, 'extra_cost_amount' => $extra, 'is_active' => true],
                );
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Product $product = null): array
    {
        $v = $request->validate([
            'product_type_id' => ['required', 'exists:product_types,id'],
            'slug' => ['required', 'alpha_dash', 'max:120', Rule::unique('products', 'slug')->ignore($product?->id)],
            'name_en' => ['required', 'string', 'max:120'],
            'name_rw' => ['nullable', 'string', 'max:120'],
            'description_en' => ['nullable', 'string', 'max:2000'],
            'description_rw' => ['nullable', 'string', 'max:2000'],
            'base_cost' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'exists:currencies,code'],
            'production_days' => ['required', 'integer', 'min:1', 'max:60'],
            'weight_grams' => ['required', 'integer', 'min:1', 'max:50000'],
            'mockup_template' => ['required', Rule::in($this->templates())],
            'position' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'is_shippable' => ['sometimes', 'boolean'],
        ]);

        return [
            'product_type_id' => $v['product_type_id'],
            'slug' => Str::lower($v['slug']),
            'name' => array_filter(['en' => $v['name_en'], 'rw' => $v['name_rw'] ?? null]),
            'description' => array_filter(['en' => $v['description_en'] ?? null, 'rw' => $v['description_rw'] ?? null]) ?: null,
            'base_cost_amount' => Money::toMinor($v['base_cost'], $v['currency']),
            'currency' => $v['currency'],
            'production_days' => $v['production_days'],
            'weight_grams' => $v['weight_grams'],
            'mockup_template' => $v['mockup_template'],
            'position' => $v['position'] ?? 0,
            'is_active' => $request->boolean('is_active'),
            'is_shippable' => $request->boolean('is_shippable'),
        ];
    }

    /**
     * @return list<string>
     */
    private function templates(): array
    {
        return collect(glob(resource_path('views/mockups/*.blade.php')))->map(fn ($f) => basename($f, '.blade.php'))->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'types' => ProductType::query()->orderBy('position')->get()->mapWithKeys(fn ($t) => [$t->id => $t->translate('name')]),
            'templates' => collect($this->templates())->mapWithKeys(fn ($t) => [$t => $t])->all(),
            'currencies' => Currency::query()->pluck('code', 'code'),
        ];
    }
}
