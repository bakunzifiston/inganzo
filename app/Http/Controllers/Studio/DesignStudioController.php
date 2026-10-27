<?php

namespace App\Http\Controllers\Studio;

use App\Enums\ArtworkStatus;
use App\Http\Controllers\Controller;
use App\Models\Artwork;
use App\Models\ArtworkVersion;
use App\Models\Category;
use App\Models\DesignAsset;
use App\Models\DesignDocument;
use App\Models\PrintArea;
use App\Services\Design\DesignConflictException;
use App\Services\Design\DesignDocumentValidator;
use App\Services\Design\DesignStudio;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * The Design Studio: a canvas editor for composing artwork from text, shapes and images.
 * Every endpoint is limited to the artist who owns the artwork or asset.
 */
class DesignStudioController extends Controller
{
    public function __construct(private DesignStudio $studio) {}

    public function create(Request $request): View
    {
        Gate::authorize('create', Artwork::class);

        return view('studio.design-create', [
            'areas' => $this->printAreas(),
            'categories' => Category::query()->active()->orderBy('position')->get(),
            'artwork' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Artwork::class);

        $validated = $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:120'],
            'category_id' => ['nullable', Rule::exists('categories', 'id')],
            'print_area_id' => ['required', Rule::in($this->printAreas()->pluck('id'))],
            'rights_confirmed' => ['accepted'],
        ]);

        $document = $this->studio->createDesign($request->user()->artistProfile, [
            'title' => $validated['title'],
            'category_id' => $validated['category_id'] ?? null,
        ], PrintArea::query()->findOrFail($validated['print_area_id']));

        return redirect()->route('studio.artworks.design', $document->artwork);
    }

    /**
     * Open an uploaded artwork in the studio (asks for the artboard first if it has no document yet).
     */
    public function start(Request $request, Artwork $artwork): RedirectResponse|View
    {
        $this->authorizeEditing($request, $artwork);

        if ($artwork->designDocument) {
            return redirect()->route('studio.artworks.design', $artwork);
        }

        if ($request->isMethod('get')) {
            return view('studio.design-create', ['areas' => $this->printAreas(), 'categories' => collect(), 'artwork' => $artwork]);
        }

        $validated = $request->validate(['print_area_id' => ['required', Rule::in($this->printAreas()->pluck('id'))]]);
        $this->studio->documentFor($artwork->loadMissing('currentVersion'), PrintArea::query()->findOrFail($validated['print_area_id']));

        return redirect()->route('studio.artworks.design', $artwork);
    }

    public function edit(Request $request, Artwork $artwork): View|RedirectResponse
    {
        $this->authorizeEditing($request, $artwork);

        $document = $artwork->designDocument;
        if ($document === null) {
            return redirect()->route('studio.artworks.design.start', $artwork);
        }

        $document->loadMissing('printArea.product');

        return view('studio.design', [
            'artwork' => $artwork,
            'document' => $document,
            'config' => $this->editorConfig($request, $artwork, $document),
        ]);
    }

    public function save(Request $request, Artwork $artwork): JsonResponse
    {
        $this->authorizeEditing($request, $artwork);
        $document = $artwork->designDocument ?? abort(404);

        $request->validate([
            'revision' => ['required', 'integer', 'min:1'],
            'data' => ['required', 'array'],
        ]);

        try {
            $document = $this->studio->save($document, $request->input('data'), $request->integer('revision'));
        } catch (DesignConflictException $e) {
            return response()->json(['message' => __('This design was changed in another tab or device.'), 'revision' => $e->currentRevision], 409);
        }

        return response()->json([
            'revision' => $document->revision,
            'saved_at' => $document->updated_at->toIso8601String(),
            'min_raster_dpi' => $document->min_raster_dpi,
        ]);
    }

    public function export(Request $request, Artwork $artwork): JsonResponse
    {
        $this->authorizeEditing($request, $artwork);
        $document = $artwork->designDocument ?? abort(404);

        $request->validate([
            'revision' => ['required', 'integer', 'min:1'],
            'file' => ['required', 'file', 'max:'.config('marketplace.artwork.max_upload_kb')],
        ]);

        $wasPublished = $artwork->status === ArtworkStatus::Approved;

        try {
            $version = $this->studio->export($document, $request->file('file'), $request->integer('revision'));
        } catch (DesignConflictException $e) {
            return response()->json(['message' => __('Save the latest changes before exporting.'), 'revision' => $e->currentRevision], 409);
        }

        session()->flash('success', $wasPublished
            ? __('Print file v:n created from the Design Studio. The design has been unpublished until it is approved again.', ['n' => $version->version_number])
            : __('Print file v:n created from the Design Studio. Your products are being updated.', ['n' => $version->version_number]));

        return response()->json(['version' => $version->version_number, 'redirect' => route('studio.artworks.show', $artwork)], 201);
    }

    /**
     * The artist's image library: studio uploads and their own artwork files.
     */
    public function assets(Request $request): JsonResponse
    {
        $artist = $request->user()->artistProfile;

        $uploads = $artist->designAssets()->latest()->limit(60)->get()->map(fn (DesignAsset $asset) => [
            'ref' => 'asset:'.$asset->id, 'name' => $asset->original_filename, 'url' => $asset->url(),
            'width' => $asset->width, 'height' => $asset->height, 'detail' => 1,
        ]);

        $artworks = $artist->artworks()->with('currentVersion')->whereNotNull('current_version_id')
            ->latest('updated_at')->limit(40)->get()
            ->filter(fn (Artwork $artwork) => $artwork->currentVersion?->isReady())
            ->map(fn (Artwork $artwork) => $this->versionSource($artwork->currentVersion, $artwork->title))
            ->values();

        return response()->json(['uploads' => $uploads, 'artworks' => $artworks]);
    }

    public function storeAsset(Request $request): JsonResponse
    {
        Gate::authorize('create', Artwork::class);

        $request->validate(['file' => ['required', 'file', 'max:'.config('marketplace.design.max_asset_kb')]]);
        $asset = $this->studio->storeAsset($request->user()->artistProfile, $request->file('file'));

        return response()->json([
            'ref' => 'asset:'.$asset->id, 'name' => $asset->original_filename, 'url' => $asset->url(),
            'width' => $asset->width, 'height' => $asset->height, 'detail' => 1,
        ], 201);
    }

    public function showAsset(Request $request, DesignAsset $asset): Response
    {
        abort_unless($request->user()->artistProfile?->id === $asset->artist_profile_id, 404);

        return $this->stream($asset->disk, $asset->path, $asset->mime_type);
    }

    /**
     * Full-resolution artwork file for use as a studio layer (owner only, shown inline).
     */
    public function showSource(Request $request, ArtworkVersion $version): Response
    {
        abort_unless($request->user()->artistProfile?->id === $version->artwork->artist_profile_id, 404);

        return $this->stream($version->disk, $version->original_path, $version->mime_type);
    }

    /**
     * @return array<string, mixed>
     */
    private function editorConfig(Request $request, Artwork $artwork, DesignDocument $document): array
    {
        [$width, $height] = $document->exportPixels();
        $area = $document->printArea;

        return [
            'document' => [
                'revision' => $document->revision,
                'data' => $document->data,
                'savedAt' => $document->updated_at->toIso8601String(),
                'exportedAt' => $document->exported_at?->toIso8601String(),
            ],
            'artboard' => [
                'widthMm' => $document->width_mm,
                'heightMm' => $document->height_mm,
                'dpi' => $document->design_dpi,
                'width' => $width,
                'height' => $height,
                'safeMm' => config('marketplace.design.safe_margin_mm'),
                'label' => $area ? $area->product->translate('name').' · '.$area->label : __('Custom'),
            ],
            'thresholds' => [
                'min' => $area->min_dpi ?? config('marketplace.print_quality.minimum_dpi'),
                'sharp' => $area->recommended_dpi ?? config('marketplace.print_quality.sharp_dpi'),
            ],
            'sources' => $this->sourcesFor($request, $document),
            'fonts' => DesignDocumentValidator::FONTS,
            'published' => $artwork->status === ArtworkStatus::Approved,
            'slug' => $artwork->slug,
            'i18n' => [
                'text' => __('Text'), 'image' => __('Image'), 'group' => __('Group'), 'shape' => __('Shape'),
                'shapes' => ['rect' => __('Rectangle'), 'ellipse' => __('Ellipse'), 'triangle' => __('Triangle'), 'line' => __('Line'), 'star' => __('Star')],
                'sampleText' => __('Your text'),
                'offBoard' => __(':name is completely outside the print area and will not be printed.'),
                'cutOff' => __(':name crosses the edge of the print area; the outside part will be cut off.'),
                'nearEdge' => __(':name is outside the safe area and may be trimmed slightly.'),
                'dpiFail' => __(':name is only :dpi DPI at this size (minimum :min). Make it smaller or replace it with a higher-resolution file — enlarging a small image cannot add detail.'),
                'dpiWarn' => __(':name is :dpi DPI at this size: printable, but fine detail will look soft.'),
                'uploadType' => __('Upload a PNG, JPEG or WebP image.'),
                'uploadFailed' => __('The upload failed. Check your connection and try again.'),
                'saved' => __('All changes saved'), 'saving' => __('Saving…'), 'unsaved' => __('Unsaved changes'),
                'saveError' => __('Could not save'), 'conflict' => __('Changed elsewhere'), 'offline' => __('Offline — will retry'),
                'saveFailed' => __('The design could not be saved.'),
                'saveFirst' => __('Save the design before exporting.'),
                'renderFailed' => __('The design could not be rendered. Try closing other tabs to free memory.'),
                'empty' => __('Add something to the design before exporting it.'),
                'confirmUnpublish' => __('This design is live in your shop. Exporting a new print file unpublishes it until it is approved again. Continue?'),
                'exportFailed' => __('The export failed. Your design is saved; try again.'),
            ],
            'storageKey' => 'inganzo-design-'.$document->id,
            'urls' => [
                'save' => route('studio.artworks.design.save', $artwork),
                'export' => route('studio.artworks.design.export', $artwork),
                'assets' => route('studio.design-assets.index'),
                'upload' => route('studio.design-assets.store'),
                'back' => route('studio.artworks.show', $artwork),
            ],
        ];
    }

    /**
     * Image sources referenced by the document, keyed by assetRef.
     *
     * @return array<string, array<string, mixed>>
     */
    private function sourcesFor(Request $request, DesignDocument $document): array
    {
        $refs = collect();
        $walk = function (array $objects) use (&$walk, $refs) {
            foreach ($objects as $object) {
                if (isset($object['assetRef'])) {
                    $refs->push($object['assetRef']);
                }
                if (isset($object['objects']) && is_array($object['objects'])) {
                    $walk($object['objects']);
                }
            }
        };
        $walk($document->data['objects'] ?? []);

        $ids = fn (string $kind) => $refs->filter(fn ($r) => str_starts_with($r, $kind.':'))->map(fn ($r) => (int) substr($r, strlen($kind) + 1))->unique()->all();
        $artist = $request->user()->artistProfile;
        $sources = [];

        foreach ($artist->designAssets()->whereKey($ids('asset'))->get() as $asset) {
            $sources['asset:'.$asset->id] = ['ref' => 'asset:'.$asset->id, 'name' => $asset->original_filename, 'url' => $asset->url(), 'width' => $asset->width, 'height' => $asset->height, 'detail' => 1];
        }
        $versions = ArtworkVersion::query()->with('artwork')->whereKey($ids('version'))
            ->whereHas('artwork', fn ($q) => $q->where('artist_profile_id', $artist->id))->get();
        foreach ($versions as $version) {
            $sources['version:'.$version->id] = $this->versionSource($version, $version->artwork->title);
        }

        return $sources;
    }

    /**
     * @return array<string, mixed>
     */
    private function versionSource(ArtworkVersion $version, string $title): array
    {
        return [
            'ref' => 'version:'.$version->id,
            'name' => $title.' (v'.$version->version_number.')',
            'url' => route('studio.design-sources.show', $version),
            'thumb' => $version->thumbnailUrl(),
            'width' => $version->width,
            'height' => $version->height,
            'detail' => DesignDocumentValidator::versionFactor($version),
        ];
    }

    /**
     * @return Collection<int, PrintArea>
     */
    private function printAreas(): Collection
    {
        return PrintArea::query()
            ->whereHas('product', fn ($q) => $q->active())
            ->with('product')
            ->orderBy('product_id')->orderBy('position')
            ->get();
    }

    private function authorizeEditing(Request $request, Artwork $artwork): void
    {
        abort_unless($request->user()->artistProfile?->id === $artwork->artist_profile_id, 404);
        Gate::authorize('update', $artwork);
    }

    private function stream(string $disk, string $path, string $mime): Response
    {
        $storage = Storage::disk($disk);
        abort_unless($storage->exists($path), 404);

        return $storage->response($path, null, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
