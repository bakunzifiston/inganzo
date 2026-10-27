<?php

namespace App\Http\Controllers\Studio;

use App\Enums\FinishedProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Studio\StoreFinishedProductRequest;
use App\Models\Category;
use App\Models\FinishedProduct;
use App\Services\Inventory\FinishedProductService;
use App\Services\Inventory\StockManager;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * An artist's ready-made products: create, edit, stock, publish and archive.
 * Every action is authorised through FinishedProductPolicy, so artists only ever reach their own listings.
 */
class FinishedProductController extends Controller
{
    public function __construct(private FinishedProductService $products) {}

    public function create(): View
    {
        Gate::authorize('create', FinishedProduct::class);

        return view('studio.finished.form', ['product' => null, 'categories' => $this->categories()]);
    }

    public function store(StoreFinishedProductRequest $request): RedirectResponse
    {
        Gate::authorize('create', FinishedProduct::class);

        $product = $this->products->create($request->user()->artistProfile, $request->validated());
        $this->products->addImages($product, $request->file('images', []));

        if ($request->input('intent') === 'publish') {
            return $this->publishAndRedirect($product);
        }

        return redirect()->route('studio.finished.edit', $product)->with('success', __('Product saved as a draft. Add photos, then publish it when it is ready.'));
    }

    public function edit(FinishedProduct $finishedProduct): View
    {
        Gate::authorize('manage', $finishedProduct);

        return view('studio.finished.form', [
            'product' => $finishedProduct->load(['images', 'variants']),
            'categories' => $this->categories(),
        ]);
    }

    public function update(StoreFinishedProductRequest $request, FinishedProduct $finishedProduct): RedirectResponse
    {
        Gate::authorize('update', $finishedProduct);

        $data = $request->validated();
        $this->products->update($finishedProduct, $data);

        if ($request->filled('remove_images')) {
            $this->products->removeImages($finishedProduct, array_map('intval', $request->input('remove_images')));
        }
        $this->products->addImages($finishedProduct->refresh(), $request->file('images', []));
        if ($request->filled('cover_image_id')) {
            $this->products->makeCover($finishedProduct, $request->integer('cover_image_id'));
        }

        if ($request->input('intent') === 'publish') {
            return $this->publishAndRedirect($finishedProduct->refresh());
        }

        return redirect()->route('studio.finished.edit', $finishedProduct)->with('success', __('Product saved.'));
    }

    public function publish(FinishedProduct $finishedProduct): RedirectResponse
    {
        Gate::authorize('update', $finishedProduct);

        return $this->publishAndRedirect($finishedProduct);
    }

    public function archive(FinishedProduct $finishedProduct): RedirectResponse
    {
        Gate::authorize('manage', $finishedProduct);

        if ($finishedProduct->status === FinishedProductStatus::Suspended) {
            return back()->withErrors(['status' => __('Taken-down products cannot be archived.')]);
        }

        $this->products->archive($finishedProduct);

        return redirect()->route('studio.products.index')->with('success', __('Product archived and removed from your shop.'));
    }

    public function restore(FinishedProduct $finishedProduct): RedirectResponse
    {
        Gate::authorize('manage', $finishedProduct);

        if ($finishedProduct->status !== FinishedProductStatus::Archived) {
            return back()->withErrors(['status' => __('Only archived products can be restored.')]);
        }

        $finishedProduct->forceFill(['status' => FinishedProductStatus::Draft])->save();

        return redirect()->route('studio.finished.edit', $finishedProduct)->with('success', __('Product restored as a draft. Publish it when you are ready.'));
    }

    /**
     * Quick stock correction from the inventory tables. Only the artist's own variants are accepted.
     */
    public function stock(Request $request, FinishedProduct $finishedProduct, StockManager $stock): RedirectResponse
    {
        Gate::authorize('manage', $finishedProduct);

        $validated = $request->validate([
            'stock' => ['required', 'array'],
            'stock.*' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);

        $variants = $finishedProduct->variants()->whereIn('id', array_keys($validated['stock']))->get();
        if ($variants->count() !== count($validated['stock'])) {
            throw ValidationException::withMessages(['stock' => __('One of the options does not belong to this product.')]);
        }

        foreach ($variants as $variant) {
            $stock->set($variant, (int) $validated['stock'][$variant->id]);
        }
        $finishedProduct->load('variants')->refreshPriceFrom();

        return back()->with('success', __('Stock updated.'));
    }

    private function publishAndRedirect(FinishedProduct $product): RedirectResponse
    {
        try {
            $published = $this->products->publish($product);
        } catch (ValidationException $e) {
            return redirect()->route('studio.finished.edit', $product)->withErrors($e->errors())->withInput();
        }

        return redirect()->route('studio.finished.edit', $product)->with('success', $published->status === FinishedProductStatus::Active
            ? __('Your product is on sale.')
            : __('Submitted for review. We will notify you once it has been checked.'));
    }

    /**
     * @return Collection<int, Category>
     */
    private function categories()
    {
        return Category::query()->active()->orderBy('position')->get();
    }
}
