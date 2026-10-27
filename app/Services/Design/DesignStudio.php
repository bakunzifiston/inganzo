<?php

namespace App\Services\Design;

use App\Models\ArtistProfile;
use App\Models\Artwork;
use App\Models\ArtworkVersion;
use App\Models\DesignAsset;
use App\Models\DesignDocument;
use App\Models\PrintArea;
use App\Services\Artwork\ArtworkUploader;
use App\Services\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Design Studio persistence: editable documents, studio image uploads and print-file exports.
 *
 * The editable document (JSON, validated by DesignDocumentValidator) and the print file are
 * kept apart. Exporting renders the document to a transparent PNG in the browser and stores
 * it as a new immutable artwork_version through ArtworkUploader, so moderation, placement
 * checks and publishing rules apply exactly as for an uploaded file. The document is never
 * replaced by its export.
 */
class DesignStudio
{
    public const MIN_ASSET_PIXELS = 16;

    public function __construct(private ArtworkUploader $uploader, private AuditLogger $audit) {}

    /**
     * Start a new, empty design on the artboard of a product print area.
     *
     * @param  array{title: string, category_id?: int|null}  $attributes
     */
    public function createDesign(ArtistProfile $artist, array $attributes, PrintArea $area): DesignDocument
    {
        return DB::transaction(function () use ($artist, $attributes, $area) {
            $artwork = $this->uploader->createBlank($artist, $attributes);

            return $this->newDocument($artwork, $area, []);
        });
    }

    /**
     * Open an uploaded artwork in the studio: its current file becomes the first image layer,
     * centred at the largest size that stays sharp (never larger than the safe area).
     */
    public function documentFor(Artwork $artwork, PrintArea $area): DesignDocument
    {
        if ($artwork->designDocument) {
            return $artwork->designDocument;
        }

        $objects = [];
        $version = $artwork->currentVersion;

        if ($version?->isReady() && $version->width && $version->height) {
            $dpi = DesignDocument::dpiFor($area->width_mm, $area->height_mm);
            $width = $area->width_mm / 25.4 * $dpi;
            $height = $area->height_mm / 25.4 * $dpi;
            $safe = config('marketplace.design.safe_margin_mm') / 25.4 * $dpi;
            $fit = min(($width - 2 * $safe) / $version->width, ($height - 2 * $safe) / $version->height);
            $sharp = $dpi * DesignDocumentValidator::versionFactor($version) / $area->recommended_dpi;
            $scale = round(max(0.01, min($fit, $sharp)), 4);

            $objects[] = [
                'type' => 'image',
                'id' => Str::random(10),
                'name' => Str::limit($artwork->title, 60, ''),
                'assetRef' => 'version:'.$version->id,
                'left' => round($width / 2, 2),
                'top' => round($height / 2, 2),
                'originX' => 'center',
                'originY' => 'center',
                'width' => $version->width,
                'height' => $version->height,
                'cropX' => 0,
                'cropY' => 0,
                'scaleX' => $scale,
                'scaleY' => $scale,
                'lockAspect' => true,
            ];
        }

        return $this->newDocument($artwork, $area, $objects);
    }

    /**
     * Save with optimistic locking: a save based on an older revision is refused so two tabs
     * never silently overwrite each other.
     *
     * @throws ValidationException
     * @throws DesignConflictException
     */
    public function save(DesignDocument $document, mixed $data, int $baseRevision): DesignDocument
    {
        $result = $this->validator($document)->validate($data);

        return DB::transaction(function () use ($document, $result, $baseRevision) {
            $locked = DesignDocument::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();

            if ($locked->revision !== $baseRevision) {
                throw new DesignConflictException($locked->revision);
            }

            $locked->forceFill([
                'data' => $result['data'],
                'schema_version' => DesignDocument::SCHEMA_VERSION,
                'min_raster_dpi' => $result['min_raster_dpi'],
                'revision' => $locked->revision + 1,
            ])->save();

            return $locked;
        });
    }

    /**
     * Turn the saved document into the artwork's print file (a new artwork version).
     *
     * @throws DesignConflictException
     * @throws ValidationException
     */
    public function export(DesignDocument $document, UploadedFile $file, int $revision): ArtworkVersion
    {
        $document->refresh();
        if ($document->revision !== $revision) {
            throw new DesignConflictException($document->revision);
        }
        if (($document->data['objects'] ?? []) === []) {
            throw ValidationException::withMessages(['file' => __('Add something to the design before exporting it.')]);
        }

        $mime = $file->getRealPath() ? (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath()) : false;
        $size = $mime === 'image/png' ? @getimagesize($file->getRealPath()) : false;
        [$width, $height] = $document->exportPixels();

        if ($size === false || abs($size[0] - $width) > 1 || abs($size[1] - $height) > 1) {
            throw ValidationException::withMessages(['file' => __('The exported print file does not match the design size (:w × :h px). Reload the studio and export again.', ['w' => $width, 'h' => $height])]);
        }

        // Re-validate the saved document so the resolution limit reflects the current files.
        $result = $this->validator($document)->validate($document->data);
        $limit = $result['min_raster_dpi'] !== null && $result['min_raster_dpi'] < $document->design_dpi ? $result['min_raster_dpi'] : null;

        return DB::transaction(function () use ($document, $file, $limit, $result) {
            $version = $this->uploader->addVersion($document->artwork, $file, [
                'source_dpi_limit' => $limit,
                'design_dpi' => $document->design_dpi,
            ]);

            $document->forceFill([
                'min_raster_dpi' => $result['min_raster_dpi'],
                'last_exported_version_id' => $version->id,
                'exported_at' => now(),
            ])->save();

            $this->audit->log('design.exported', $document->artwork, ['version' => $version->id, 'revision' => $document->revision, 'source_dpi_limit' => $limit]);

            return $version;
        });
    }

    /**
     * Store an image for use in the studio. The type is read from the file content; the same
     * file uploaded twice is stored once.
     *
     * @throws ValidationException
     */
    public function storeAsset(ArtistProfile $artist, UploadedFile $file): DesignAsset
    {
        $path = $file->getRealPath();
        $mime = $path ? (new \finfo(FILEINFO_MIME_TYPE))->file($path) : false;

        if (! is_string($mime) || ! array_key_exists($mime, ArtworkUploader::ALLOWED_MIME_TYPES)) {
            throw ValidationException::withMessages(['file' => __('Upload a PNG, JPEG or WebP image.')]);
        }

        $size = @getimagesize($path);
        if ($size === false || $size['mime'] !== $mime) {
            throw ValidationException::withMessages(['file' => __('The file could not be read as an image. It may be damaged.')]);
        }

        [$width, $height] = $size;
        $max = config('marketplace.artwork.max_pixels');
        if ($width < self::MIN_ASSET_PIXELS || $height < self::MIN_ASSET_PIXELS || $width > $max || $height > $max) {
            throw ValidationException::withMessages(['file' => __('Images must be between :min and :max pixels on each side.', ['min' => self::MIN_ASSET_PIXELS, 'max' => $max])]);
        }

        $sha = hash_file('sha256', $path);
        $existing = $artist->designAssets()->where('sha256', $sha)->first();
        if ($existing) {
            return $existing;
        }

        $disk = config('marketplace.artwork_disk');
        $stored = $file->storeAs("design-assets/{$artist->id}", Str::uuid().'.'.ArtworkUploader::ALLOWED_MIME_TYPES[$mime], ['disk' => $disk]);

        return $artist->designAssets()->create([
            'disk' => $disk,
            'path' => $stored,
            'original_filename' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime_type' => $mime,
            'width' => $width,
            'height' => $height,
            'file_size' => $file->getSize(),
            'sha256' => $sha,
        ]);
    }

    public function validator(DesignDocument $document): DesignDocumentValidator
    {
        return new DesignDocumentValidator($document->artwork->artist, $document->design_dpi);
    }

    /**
     * @param  list<array<string, mixed>>  $objects
     */
    private function newDocument(Artwork $artwork, PrintArea $area, array $objects): DesignDocument
    {
        $document = new DesignDocument([
            'artwork_id' => $artwork->id,
            'print_area_id' => $area->id,
            'schema_version' => DesignDocument::SCHEMA_VERSION,
            'width_mm' => $area->width_mm,
            'height_mm' => $area->height_mm,
            'design_dpi' => DesignDocument::dpiFor($area->width_mm, $area->height_mm),
            'data' => ['objects' => [], 'background' => null],
            'revision' => 1,
        ]);
        $document->setRelation('artwork', $artwork);

        if ($objects !== []) {
            $result = $this->validator($document)->validate(['objects' => $objects, 'background' => null]);
            $document->data = $result['data'];
            $document->min_raster_dpi = $result['min_raster_dpi'];
        }

        $document->save();
        $artwork->setRelation('designDocument', $document);

        return $document;
    }
}
