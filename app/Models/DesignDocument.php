<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Editable Design Studio composition for an artwork. The flattened print file is a separate
 * artwork_version (last_exported_version_id); this document is never overwritten by an export.
 */
#[Fillable(['artwork_id', 'print_area_id', 'schema_version', 'width_mm', 'height_mm', 'design_dpi', 'data', 'revision', 'min_raster_dpi', 'last_exported_version_id', 'exported_at'])]
class DesignDocument extends Model
{
    public const SCHEMA_VERSION = 1;

    public const PREFERRED_DPI = 300;

    public const MAX_EXPORT_SIDE = 6000;

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'width_mm' => 'integer',
            'height_mm' => 'integer',
            'design_dpi' => 'integer',
            'revision' => 'integer',
            'min_raster_dpi' => 'integer',
            'exported_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Artwork, $this>
     */
    public function artwork(): BelongsTo
    {
        return $this->belongsTo(Artwork::class);
    }

    /**
     * @return BelongsTo<PrintArea, $this>
     */
    public function printArea(): BelongsTo
    {
        return $this->belongsTo(PrintArea::class);
    }

    /**
     * @return BelongsTo<ArtworkVersion, $this>
     */
    public function lastExportedVersion(): BelongsTo
    {
        return $this->belongsTo(ArtworkVersion::class, 'last_exported_version_id');
    }

    /**
     * Export pixel size: the artboard at design_dpi.
     *
     * @return array{0: int, 1: int}
     */
    public function exportPixels(): array
    {
        return [
            (int) round($this->width_mm / 25.4 * $this->design_dpi),
            (int) round($this->height_mm / 25.4 * $this->design_dpi),
        ];
    }

    /**
     * Render resolution for an artboard: 300 DPI, raised when needed so the short side meets
     * the upload minimum (small areas such as mug wraps), and lowered when needed so the long
     * side stays within MAX_EXPORT_SIDE (large posters – browsers cannot render bigger canvases
     * reliably). The print check then reports the honest resulting DPI.
     */
    public static function dpiFor(int $widthMm, int $heightMm): int
    {
        $shortest = min($widthMm, $heightMm) / 25.4;
        $longest = max($widthMm, $heightMm) / 25.4;
        $dpi = max(self::PREFERRED_DPI, (int) ceil(config('marketplace.artwork.min_pixels') / $shortest));

        return (int) min($dpi, floor(self::MAX_EXPORT_SIDE / $longest));
    }
}
