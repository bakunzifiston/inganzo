<?php

namespace App\Http\Controllers\Studio;

use App\Enums\ArtworkStatus;
use App\Enums\ModerationDecision;
use App\Http\Controllers\Controller;
use App\Http\Requests\Studio\StoreArtworkRequest;
use App\Models\Artwork;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use App\Services\Artwork\ArtworkModeration;
use App\Services\Artwork\ArtworkUploader;
use App\Services\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ArtworkController extends Controller
{
    public function index(Request $request): View
    {
        $artist = $request->user()->artistProfile;
        $status = $request->string('status')->toString();

        $artworks = $artist->artworks()
            ->with(['currentVersion', 'category'])
            ->withCount(['artistProducts as enabled_products_count' => fn ($q) => $q->where('is_enabled', true)])
            ->when(in_array($status, ArtworkStatus::values(), true), fn ($q) => $q->where('status', $status))
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'ilike', '%'.$request->string('q').'%'))
            ->latest('updated_at')
            ->paginate(18)
            ->withQueryString();

        return view('studio.artworks.index', [
            'artworks' => $artworks,
            'status' => $status,
            'counts' => $artist->artworks()->selectRaw('status, count(*) AS total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Artwork::class);

        return view('studio.artworks.create', [
            'categories' => Category::query()->active()->orderBy('position')->get(),
            'artist' => $request->user()->artistProfile,
        ]);
    }

    public function store(StoreArtworkRequest $request, ArtworkUploader $uploader): RedirectResponse|JsonResponse
    {
        $artwork = $uploader->create($request->user()->artistProfile, [
            'title' => $request->string('title')->toString(),
            'description' => $request->input('description'),
            'category_id' => $request->integer('category_id') ?: null,
            'tags' => $request->tagList(),
            'is_mature' => $request->boolean('is_mature'),
        ], $request->file('file'));

        session()->flash('success', __('Artwork uploaded. Review the products below, then submit it for approval.'));

        // The upload form posts via XHR (for progress) and expects JSON; plain form posts get a redirect.
        return $request->wantsJson()
            ? response()->json(['redirect' => route('studio.artworks.show', $artwork)], 201)
            : redirect()->route('studio.artworks.show', $artwork);
    }

    public function show(Request $request, Artwork $artwork): View
    {
        $this->authorizeOwner($request, $artwork);

        $artwork->load(['currentVersion', 'versions', 'tags', 'category', 'moderationReviews.user', 'artistProducts.product.printAreas', 'artistProducts.product.mockups', 'artistProducts.colors', 'artistProducts.placements', 'artistProducts.defaultColor', 'designDocument']);

        return view('studio.artworks.show', [
            'artwork' => $artwork,
            'products' => Product::query()->active()->with('type')->orderBy('product_type_id')->orderBy('position')->get(),
            'categories' => Category::query()->active()->orderBy('position')->get(),
        ]);
    }

    public function update(Request $request, Artwork $artwork): RedirectResponse
    {
        Gate::authorize('update', $artwork);

        $validated = $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category_id' => ['nullable', Rule::exists('categories', 'id')],
            'tags' => ['nullable', 'string', 'max:300'],
            'is_mature' => ['sometimes', 'boolean'],
        ]);

        $artwork->fill([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'is_mature' => $request->boolean('is_mature'),
        ])->save();
        $artwork->tags()->sync(Tag::idsFor(array_slice(array_filter(array_map('trim', explode(',', (string) ($validated['tags'] ?? '')))), 0, 15)));

        return back()->with('success', __('Details saved.'));
    }

    public function replaceFile(Request $request, Artwork $artwork, ArtworkUploader $uploader): RedirectResponse
    {
        Gate::authorize('update', $artwork);

        $request->validate([
            'file' => ['required', 'file', 'mimetypes:image/png,image/jpeg,image/webp', 'max:'.config('marketplace.artwork.max_upload_kb')],
            'rights_confirmed' => ['accepted'],
        ]);

        $wasPublished = $artwork->status === ArtworkStatus::Approved;
        $uploader->addVersion($artwork, $request->file('file'));

        return back()->with('success', $wasPublished
            ? __('New file uploaded. The design has been unpublished until it is approved again.')
            : __('New file uploaded. Your product placements were re-checked.'));
    }

    public function submit(Request $request, Artwork $artwork, ArtworkModeration $moderation): RedirectResponse
    {
        Gate::authorize('submit', $artwork);
        $moderation->submit($artwork, $request->user());

        return back()->with('success', __('Submitted for review. We will notify you once it has been checked.'));
    }

    public function archive(Request $request, Artwork $artwork): RedirectResponse
    {
        Gate::authorize('archive', $artwork);

        if ($artwork->status === ArtworkStatus::Suspended) {
            return back()->withErrors(['status' => __('Taken-down designs cannot be archived.')]);
        }

        $artwork->forceFill(['status' => ArtworkStatus::Archived])->save();

        return redirect()->route('studio.artworks.index')->with('success', __('Design archived and removed from your shop.'));
    }

    /**
     * Bring an archived design back. A design that had passed review is published again
     * straight away (its file cannot change while archived); anything else returns to
     * draft so it goes through review before being sold.
     */
    public function restore(Request $request, Artwork $artwork, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('archive', $artwork);

        if ($artwork->status !== ArtworkStatus::Archived) {
            return back()->withErrors(['status' => __('Only archived designs can be restored.')]);
        }

        $lastDecision = $artwork->moderationReviews()->whereIn('decision', [
            ModerationDecision::Approved, ModerationDecision::Rejected, ModerationDecision::Suspended, ModerationDecision::Reinstated,
        ])->first()?->decision;
        $wasApproved = $artwork->published_at !== null && in_array($lastDecision, [ModerationDecision::Approved, ModerationDecision::Reinstated], true);

        $artwork->forceFill(['status' => $wasApproved ? ArtworkStatus::Approved : ArtworkStatus::Draft])->save();
        $audit->log('artwork.restored', $artwork, ['status' => $artwork->status->value]);

        return redirect()->route('studio.artworks.show', $artwork)->with('success', $wasApproved
            ? __('Design restored and back in your shop.')
            : __('Design restored as a draft. Submit it for review when it is ready.'));
    }

    private function authorizeOwner(Request $request, Artwork $artwork): void
    {
        abort_unless($request->user()->artistProfile?->id === $artwork->artist_profile_id, 404);
    }
}
