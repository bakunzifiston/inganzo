<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ArtworkStatus;
use App\Http\Controllers\Controller;
use App\Models\Artwork;
use App\Models\CopyrightReport;
use App\Services\Artwork\ArtworkModeration;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ModerationController extends Controller
{
    public function __construct(private ArtworkModeration $moderation) {}

    public function index(Request $request): View
    {
        $request->validate(['status' => ['nullable', Rule::enum(ArtworkStatus::class)]]);
        $status = $request->string('status')->toString() ?: ArtworkStatus::PendingReview->value;

        return view('admin.moderation.index', [
            'status' => $status,
            'artworks' => Artwork::query()->with(['artist', 'currentVersion', 'category'])->withCount(['artistProducts as enabled_products_count' => fn ($q) => $q->where('is_enabled', true)])
                ->where('status', $status)
                ->when($request->filled('q'), fn ($q) => $q->where('title', 'ilike', '%'.$request->string('q').'%'))
                ->orderBy($status === 'pending_review' ? 'submitted_at' : 'updated_at', $status === 'pending_review' ? 'asc' : 'desc')
                ->paginate(24)->withQueryString(),
            'counts' => Artwork::query()->selectRaw('status, count(*) total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function show(Artwork $artwork): View
    {
        $artwork->load(['artist.user', 'currentVersion', 'versions', 'tags', 'category', 'moderationReviews.user',
            'artistProducts.product.printAreas', 'artistProducts.product.mockups', 'artistProducts.colors', 'artistProducts.placements.printArea']);

        return view('admin.moderation.show', [
            'artwork' => $artwork,
            'reports' => CopyrightReport::query()->where('artwork_id', $artwork->id)->latest()->get(),
        ]);
    }

    public function approve(Request $request, Artwork $artwork): RedirectResponse
    {
        $this->moderation->approve($artwork, $request->user(), $request->validate(['notes' => ['nullable', 'string', 'max:1000']])['notes'] ?? null);

        return $this->next($artwork, __('“:title” approved and published.', ['title' => $artwork->title]));
    }

    public function reject(Request $request, Artwork $artwork): RedirectResponse
    {
        $this->moderation->reject($artwork, $request->user(), $request->validate(['notes' => ['required', 'string', 'min:5', 'max:1000']])['notes']);

        return $this->next($artwork, __('Changes requested for “:title”.', ['title' => $artwork->title]));
    }

    public function suspend(Request $request, Artwork $artwork): RedirectResponse
    {
        $this->moderation->suspend($artwork, $request->user(), $request->validate(['notes' => ['required', 'string', 'min:5', 'max:1000']])['notes']);

        return back()->with('success', __('Design taken down.'));
    }

    public function reinstate(Request $request, Artwork $artwork): RedirectResponse
    {
        $this->moderation->reinstate($artwork, $request->user(), $request->validate(['notes' => ['nullable', 'string', 'max:1000']])['notes'] ?? null);

        return back()->with('success', __('Design reinstated.'));
    }

    private function next(Artwork $artwork, string $message): RedirectResponse
    {
        $next = Artwork::query()->where('status', ArtworkStatus::PendingReview)->where('id', '!=', $artwork->id)->orderBy('submitted_at')->first();

        return ($next ? redirect()->route('admin.moderation.show', $next) : redirect()->route('admin.moderation.index'))->with('success', $message);
    }
}
