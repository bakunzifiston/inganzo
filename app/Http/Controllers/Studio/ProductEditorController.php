<?php

namespace App\Http\Controllers\Studio;

use App\Enums\MarkupType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Studio\UpdateProductRequest;
use App\Models\ArtistProduct;
use App\Models\Artwork;
use App\Models\Product;
use App\Services\Artwork\ArtistProductConfigurator;
use App\Services\Artwork\PlacementValidator;
use App\Services\Pricing\PriceCalculator;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ProductEditorController extends Controller
{
    public function edit(Artwork $artwork, Product $product, PlacementValidator $validator, PriceCalculator $pricing): View
    {
        Gate::authorize('update', $artwork);
        abort_unless($product->is_active, 404);

        $version = $artwork->currentVersion;
        abort_unless($version?->isReady(), 409, __('Your artwork is still processing.'));

        $product->load(['printAreas', 'mockups', 'colors' => fn ($q) => $q->where('is_active', true), 'type']);
        $artistProduct = ArtistProduct::query()->where('artwork_id', $artwork->id)->where('product_id', $product->id)->with(['placements', 'colors'])->first();
        $rule = $pricing->ruleFor($product);

        $areas = $product->printAreas->map(function ($area) use ($product) {
            $mockup = $product->mockups->where('print_area_id', $area->id)->firstWhere('product_color_id', null) ?? $product->mockups->firstWhere('print_area_id', $area->id);

            return [
                'id' => $area->id,
                'key' => $area->key,
                'label' => $area->label,
                'width_mm' => $area->width_mm,
                'height_mm' => $area->height_mm,
                'min_dpi' => $area->min_dpi,
                'recommended_dpi' => $area->recommended_dpi,
                'preferred_dpi' => max($area->recommended_dpi, config('marketplace.print_quality.preferred_dpi')),
                'required' => $area->is_required,
                'mockup' => ['x' => $mockup->area_x, 'y' => $mockup->area_y, 'w' => $mockup->area_width, 'h' => $mockup->area_height, 'view' => $mockup->view === 'back' ? 'back' : 'front'],
            ];
        })->values();

        $placements = [];
        foreach ($product->printAreas as $area) {
            $saved = $artistProduct?->placementFor($area);
            $default = $validator->defaultPlacement($version, $area);
            $placements[$area->id] = [
                'enabled' => $saved !== null || ($artistProduct === null && $area->is_required),
                'offset_x' => $saved?->offset_x ?? $default->offsetX,
                'offset_y' => $saved?->offset_y ?? $default->offsetY,
                'scale' => $saved?->scale ?? $default->scale,
                'rotation' => $saved?->rotation ?? 0,
            ];
        }

        $colorIds = $artistProduct?->colors->pluck('id')->all() ?? $product->colors->pluck('id')->all();
        $markupType = $artistProduct?->markup_type ?? MarkupType::Percent;
        $markupBps = $artistProduct?->markup_bps ?? $rule->artist_markup_default_bps;

        return view('studio.editor', [
            'artwork' => $artwork,
            'product' => $product,
            'version' => $version,
            'artistProduct' => $artistProduct,
            'rule' => $rule,
            'editorConfig' => [
                'areas' => $areas,
                'image' => ['width' => $version->width, 'height' => $version->height, 'url' => $version->previewUrl(), 'detail' => PlacementValidator::sourceFactor($version)],
                'minScale' => PlacementValidator::MIN_SCALE,
                'placements' => $placements,
                'colors' => $product->colors->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'hex' => $c->hex])->values(),
                'selectedColors' => array_values($colorIds),
                'previewColor' => $artistProduct?->default_color_id ?? ($colorIds[0] ?? null),
            ],
            'pricing' => [
                'base' => $product->base_cost_amount,
                'marginBps' => $rule->platform_margin_bps,
                'marginFixed' => $rule->platform_fixed_amount,
                'rounding' => $rule->rounding_increment,
                'minBps' => $rule->artist_markup_min_bps,
                'maxBps' => $rule->artist_markup_max_bps,
                'fixedMax' => $rule->artist_fixed_max_amount,
                'currency' => $product->currency,
                'type' => $markupType->value,
                'percent' => $markupBps / 100,
                'amount' => $artistProduct?->markup_amount ? (int) Money::toMajor($artistProduct->markup_amount, $product->currency) : 0,
            ],
            'isEnabled' => $artistProduct?->is_enabled ?? true,
        ]);
    }

    public function update(UpdateProductRequest $request, Artwork $artwork, Product $product, ArtistProductConfigurator $configurator): RedirectResponse
    {
        abort_unless($product->is_active, 404);

        $artistProduct = $configurator->save($artwork, $product, $request->configuration($product->currency));

        $message = match ($artistProduct->print_check_status->value) {
            'fail' => __('Saved, but this product failed the print check and will not be sold until the design is resized or a larger file is uploaded.'),
            'warn' => __('Saved. The print resolution is acceptable but a larger file would print sharper.'),
            default => __('Saved. This product is print ready.'),
        };

        return redirect()->route('studio.artworks.show', $artwork)->with($artistProduct->print_check_status->value === 'fail' ? 'error' : 'success', $message);
    }

    public function enableDefaults(Artwork $artwork, ArtistProductConfigurator $configurator): RedirectResponse
    {
        Gate::authorize('update', $artwork);

        $count = $configurator->enableDefaults($artwork);

        return back()->with('success', trans_choice('Added :count product with a default placement.|Added :count products with default placements.', $count, ['count' => $count]));
    }
}
