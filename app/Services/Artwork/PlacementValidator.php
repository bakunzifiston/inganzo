<?php

namespace App\Services\Artwork;

use App\Enums\PrintCheckStatus;
use App\Models\ArtworkVersion;
use App\Models\PrintArea;

/**
 * Keeps a design inside its print area and checks print resolution.
 *
 * Coordinates are fractions of the print area: (offsetX, offsetY) is the top-left of the
 * design's bounding box and scale is the bounding-box width ÷ print-area width. The box
 * height follows from the artwork aspect ratio, so the design is never distorted.
 *
 * Effective DPI is measured on both axes – source pixels ÷ (printed millimetres ÷ 25.4) –
 * and the lower value decides the print quality:
 *   below the area's min_dpi (default 150)          → Fail: not print-ready, never sold
 *   min_dpi up to recommended_dpi (default 250)     → Warn: printable, softer detail
 *   recommended_dpi and above                       → Pass: sharp
 *
 * Source dimensions come from the stored artwork_versions record, so no image is decoded
 * while an artist edits placements. resources/js/editor.js mirrors these calculations.
 *
 * Design Studio exports record source_dpi_limit: the real resolution of the photos inside
 * the composition at its designed size. A photo enlarged in the studio gains pixels but no
 * detail, so its DPI is scaled by source_dpi_limit ÷ design_dpi (see sourceFactor()).
 */
class PlacementValidator
{
    /**
     * Smallest permitted design width as a fraction of the print area.
     */
    public const MIN_SCALE = 0.05;

    public function validate(ArtworkVersion $version, PrintArea $area, float $offsetX, float $offsetY, float $scale, int $rotation = 0): PlacementResult
    {
        $rotation = in_array($rotation, [0, 90, 180, 270], true) ? $rotation : 0;
        [$pixelWidth, $pixelHeight] = $this->orientedPixels($version, $rotation);

        if (! $pixelWidth || ! $pixelHeight) {
            return new PlacementResult(0, 0, self::MIN_SCALE, $rotation, 0, PrintCheckStatus::Fail, __('The artwork file has no readable dimensions. It may still be processing, or it is damaged — upload it again.'));
        }

        $heightPerScale = $this->heightPerScale($pixelWidth, $pixelHeight, $area);
        $scale = max(min(self::MIN_SCALE, $this->maxScale($version, $area, $rotation)), min($scale, $this->maxScale($version, $area, $rotation)));
        $boxHeight = $scale * $heightPerScale;

        $offsetX = max(0.0, min($offsetX, 1.0 - $scale));
        $offsetY = max(0.0, min($offsetY, 1.0 - $boxHeight));

        $measure = $this->measure($version, $area, $scale, $rotation);
        $dpi = $measure['dpi'];

        [$status, $message] = match (true) {
            $dpi < $area->min_dpi => [PrintCheckStatus::Fail, $this->failMessage($version, $area, $measure, $rotation)],
            $dpi < $area->recommended_dpi => [PrintCheckStatus::Warn, __('Printable at :dpi DPI, but fine detail will look soft. :recommended DPI or more gives a sharp print.', ['dpi' => $dpi, 'recommended' => $area->recommended_dpi])],
            default => [PrintCheckStatus::Pass, __('Print ready at :dpi DPI.', ['dpi' => $dpi])],
        };

        return new PlacementResult(
            round($offsetX, 6),
            round($offsetY, 6),
            round($scale, 6),
            $rotation,
            $dpi,
            $status,
            $message,
            $measure['width_mm'],
            $measure['height_mm'],
            $measure['dpi_x'],
            $measure['dpi_y'],
        );
    }

    /**
     * Physical size and effective resolution of a placement.
     *
     * @return array{width_mm: float, height_mm: float, dpi_x: int, dpi_y: int, dpi: int}
     */
    public function measure(ArtworkVersion $version, PrintArea $area, float $scale, int $rotation = 0): array
    {
        [$pixelWidth, $pixelHeight] = $this->orientedPixels($version, $rotation);

        if (! $pixelWidth || ! $pixelHeight) {
            return ['width_mm' => 0.0, 'height_mm' => 0.0, 'dpi_x' => 0, 'dpi_y' => 0, 'dpi' => 0];
        }

        $widthMm = $scale * $area->width_mm;
        $heightMm = $scale * $this->heightPerScale($pixelWidth, $pixelHeight, $area) * $area->height_mm;
        $factor = self::sourceFactor($version);
        $dpiX = self::dpi($pixelWidth, $widthMm / $factor);
        $dpiY = self::dpi($pixelHeight, $heightMm / $factor);

        return [
            'width_mm' => round($widthMm, 1),
            'height_mm' => round($heightMm, 1),
            'dpi_x' => $dpiX,
            'dpi_y' => $dpiY,
            'dpi' => min($dpiX, $dpiY),
        ];
    }

    /**
     * DPI = pixels ÷ (millimetres ÷ 25.4), rounded down so a placement never claims more
     * resolution than it has. A tiny epsilon absorbs floating-point noise at exact values.
     */
    public static function dpi(int $pixels, float $millimetres): int
    {
        if ($millimetres <= 0) {
            return 0;
        }

        return (int) floor($pixels / ($millimetres / 25.4) + 1e-9);
    }

    /**
     * Pixels needed to print the given size at the given DPI.
     */
    public static function pixelsFor(float $millimetres, int $dpi): int
    {
        return (int) ceil($millimetres / 25.4 * $dpi);
    }

    /**
     * Share of the file's pixels that carry real detail: 1 for uploads, and
     * source_dpi_limit ÷ design_dpi for studio exports containing enlarged images.
     */
    public static function sourceFactor(ArtworkVersion $version): float
    {
        return $version->source_dpi_limit && $version->design_dpi && $version->source_dpi_limit < $version->design_dpi
            ? $version->source_dpi_limit / $version->design_dpi
            : 1.0;
    }

    /**
     * Largest scale at which the design still fits inside the print area (aspect preserved).
     */
    public function maxScale(ArtworkVersion $version, PrintArea $area, int $rotation = 0): float
    {
        [$pixelWidth, $pixelHeight] = $this->orientedPixels($version, $rotation);

        if (! $pixelWidth || ! $pixelHeight) {
            return 1.0;
        }

        return min(1.0, 1.0 / $this->heightPerScale($pixelWidth, $pixelHeight, $area));
    }

    /**
     * Largest scale that fits the print area and still reaches the given DPI on both axes.
     */
    public function maxScaleForDpi(ArtworkVersion $version, PrintArea $area, int $dpi, int $rotation = 0): float
    {
        [$pixelWidth, $pixelHeight] = $this->orientedPixels($version, $rotation);

        if (! $pixelWidth || ! $pixelHeight || $dpi <= 0) {
            return $this->maxScale($version, $area, $rotation);
        }

        $factor = self::sourceFactor($version);
        $byWidth = ($pixelWidth * $factor / $dpi * 25.4) / $area->width_mm;
        $byHeight = ($pixelHeight * $factor / $dpi * 25.4) / ($this->heightPerScale($pixelWidth, $pixelHeight, $area) * $area->height_mm);

        return min($this->maxScale($version, $area, $rotation), $byWidth, $byHeight);
    }

    /**
     * The "Best print size": the largest size that is sharp (recommended DPI), or failing
     * that the largest acceptable size (minimum DPI). Null when even the smallest permitted
     * size cannot reach the minimum, i.e. only a higher-resolution file can help.
     *
     * @return array{scale: float, target_dpi: int}|null
     */
    public function bestPrintScale(ArtworkVersion $version, PrintArea $area, int $rotation = 0): ?array
    {
        foreach ([$area->recommended_dpi, $area->min_dpi] as $target) {
            $scale = $this->maxScaleForDpi($version, $area, $target, $rotation);
            if ($scale >= self::MIN_SCALE) {
                return ['scale' => $scale, 'target_dpi' => $target];
            }
        }

        return null;
    }

    /**
     * Starting placement for a newly enabled product: a balanced composition (up to 80 % of
     * the width, horizontally centred, near the top like a chest print), never larger than
     * the best print size. If no size can reach the minimum DPI the placement is created
     * at the smallest permitted size and fails with an explanation.
     */
    public function defaultPlacement(ArtworkVersion $version, PrintArea $area): PlacementResult
    {
        $maxScale = $this->maxScale($version, $area);
        $composition = min(0.8, 0.9 * $maxScale);
        $best = $this->bestPrintScale($version, $area);
        $scale = $best === null ? self::MIN_SCALE : min($composition, $best['scale']);

        [$pixelWidth, $pixelHeight] = $this->orientedPixels($version, 0);
        $boxHeight = $pixelWidth && $pixelHeight ? $scale * $this->heightPerScale($pixelWidth, $pixelHeight, $area) : 0;
        $offsetY = max(0.0, min(0.08, 1 - $boxHeight));

        return $this->validate($version, $area, (1 - $scale) / 2, $offsetY, $scale);
    }

    /**
     * @param  array{width_mm: float, height_mm: float, dpi_x: int, dpi_y: int, dpi: int}  $measure
     */
    private function failMessage(ArtworkVersion $version, PrintArea $area, array $measure, int $rotation): string
    {
        $needed = __('needs :w × :h px for a sharp print at this size', [
            'w' => self::pixelsFor($measure['width_mm'], $area->recommended_dpi),
            'h' => self::pixelsFor($measure['height_mm'], $area->recommended_dpi),
        ]);

        if ($this->bestPrintScale($version, $area, $rotation) === null) {
            return __('Too low to print (:dpi DPI, minimum :min) even at the smallest size. Upload the original artwork at a higher resolution; this print :needed.', ['dpi' => $measure['dpi'], 'min' => $area->min_dpi, 'needed' => $needed]);
        }

        $maxMm = round($this->maxScaleForDpi($version, $area, $area->min_dpi, $rotation) * $area->width_mm);

        return __('Too low to print (:dpi DPI, minimum :min). Reduce the width to :max mm or less, or upload a higher-resolution file (this print :needed).', ['dpi' => $measure['dpi'], 'min' => $area->min_dpi, 'max' => $maxMm, 'needed' => $needed]);
    }

    /**
     * @return array{0: int|null, 1: int|null} pixel width and height as printed (after rotation)
     */
    private function orientedPixels(ArtworkVersion $version, int $rotation): array
    {
        $quarterTurn = $rotation === 90 || $rotation === 270;

        return $quarterTurn ? [$version->height, $version->width] : [$version->width, $version->height];
    }

    /**
     * Box height as a fraction of print-area height for each unit of scale.
     */
    private function heightPerScale(int $pixelWidth, int $pixelHeight, PrintArea $area): float
    {
        return ($pixelHeight / $pixelWidth) / $area->aspectRatio();
    }
}
