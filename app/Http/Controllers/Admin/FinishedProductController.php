<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FinishedProductStatus;
use App\Http\Controllers\Controller;
use App\Models\FinishedProduct;
use App\Services\Inventory\FinishedProductService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Platform moderation of ready-made products: review, approve, reject, take down and reinstate.
 * Uses the same artworks.moderate permission as design moderation.
 */
class FinishedProductController extends Controller
{
    public function __construct(private FinishedProductService $products) {}

    public function index(Request $request): View
    {
        $request->validate(['status' => ['nullable', Rule::enum(FinishedProductStatus::class)]]);
        $status = $request->string('status')->toString() ?: FinishedProductStatus::PendingReview->value;

        return view('admin.finished-products.index', [
            'status' => $status,
            'products' => FinishedProduct::query()->with(['artist', 'images', 'category', 'variants'])
                ->where('status', $status)
                ->when($request->filled('q'), fn ($q) => $q->where('title', 'ilike', '%'.$request->string('q').'%'))
                ->orderBy('updated_at', $status === 'pending_review' ? 'asc' : 'desc')
                ->paginate(24)->withQueryString(),
            'counts' => FinishedProduct::query()->selectRaw('status, count(*) total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function show(FinishedProduct $finishedProduct): View
    {
        return view('admin.finished-products.show', [
            'product' => $finishedProduct->load(['artist.user', 'images', 'variants', 'category', 'reviewer']),
        ]);
    }

    public function decide(Request $request, FinishedProduct $finishedProduct, string $decision): RedirectResponse
    {
        Gate::authorize('moderate', $finishedProduct);
        abort_unless(in_array($decision, ['approve', 'reject', 'suspend', 'reinstate'], true), 404);

        $validated = $request->validate(['notes' => [in_array($decision, ['reject', 'suspend'], true) ? 'required' : 'nullable', 'string', 'min:5', 'max:1000']]);
        $this->products->moderate($finishedProduct, $decision, $request->user(), $validated['notes'] ?? null);

        return back()->with('success', __('Decision saved: :status.', ['status' => $finishedProduct->refresh()->status->label()]));
    }
}
