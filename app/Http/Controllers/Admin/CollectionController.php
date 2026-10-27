<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArtCollection;
use App\Models\Artwork;
use App\Services\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Platform-curated collections (homepage features). Artist-owned collections are managed in the studio.
 */
class CollectionController extends Controller
{
    public function index(): View
    {
        return view('admin.collections.index', ['collections' => ArtCollection::query()->whereNull('artist_profile_id')->withCount('artworks')->orderBy('position')->get()]);
    }

    public function create(): View
    {
        return view('admin.collections.form', ['collection' => new ArtCollection(['is_active' => true]), 'artworkSlugs' => '']);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        [$data, $ids] = $this->validated($request);
        $collection = DB::transaction(function () use ($data, $ids) {
            $collection = ArtCollection::query()->create($data);
            $collection->artworks()->sync($ids);

            return $collection;
        });
        $audit->log('collection.created', $collection);

        return redirect()->route('admin.collections.index')->with('success', __('Collection created.'));
    }

    public function edit(ArtCollection $collection): View
    {
        abort_if($collection->artist_profile_id !== null, 404);

        return view('admin.collections.form', ['collection' => $collection, 'artworkSlugs' => $collection->artworks()->pluck('slug')->implode("\n")]);
    }

    public function update(Request $request, ArtCollection $collection, AuditLogger $audit): RedirectResponse
    {
        abort_if($collection->artist_profile_id !== null, 404);
        [$data, $ids] = $this->validated($request, $collection);

        DB::transaction(function () use ($collection, $data, $ids, $audit) {
            $collection->fill($data);
            $audit->logChanges('collection.updated', $collection);
            $collection->save();
            $collection->artworks()->sync($ids);
        });

        return redirect()->route('admin.collections.index')->with('success', __('Collection saved.'));
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<int, array{position: int}>}
     */
    private function validated(Request $request, ?ArtCollection $collection = null): array
    {
        $v = $request->validate([
            'slug' => ['required', 'alpha_dash', 'max:120', Rule::unique('collections', 'slug')->ignore($collection?->id)],
            'title_en' => ['required', 'string', 'max:120'],
            'title_rw' => ['nullable', 'string', 'max:120'],
            'description_en' => ['nullable', 'string', 'max:500'],
            'position' => ['nullable', 'integer', 'min:0'],
            'artwork_slugs' => ['nullable', 'string', 'max:10000'],
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
        ]);

        // Accept slugs or full product URLs, one per line, in display order.
        $slugs = collect(preg_split('/[\s,]+/', (string) ($v['artwork_slugs'] ?? '')))->filter()
            ->map(fn ($s) => str_contains($s, '/designs/') ? explode('/', explode('/designs/', $s)[1])[0] : trim($s, '/'))->unique()->values();
        $found = Artwork::query()->whereIn('slug', $slugs)->published()->pluck('id', 'slug');
        $missing = $slugs->diff($found->keys());

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages(['artwork_slugs' => __('Not found or not published: :list', ['list' => $missing->implode(', ')])]);
        }

        $ids = $slugs->mapWithKeys(fn ($slug, $i) => [$found[$slug] => ['position' => $i]])->all();

        return [[
            'slug' => strtolower($v['slug']),
            'title' => array_filter(['en' => $v['title_en'], 'rw' => $v['title_rw'] ?? null]),
            'description' => filled($v['description_en'] ?? null) ? ['en' => $v['description_en']] : null,
            'position' => $v['position'] ?? 0,
            'is_active' => $request->boolean('is_active'),
            'is_featured' => $request->boolean('is_featured'),
        ], $ids];
    }
}
