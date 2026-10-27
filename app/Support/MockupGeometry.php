<?php

namespace App\Support;

use App\Models\PrintArea;
use App\Models\ProductMockup;

/**
 * Converts a placement (fractions of a print area) into coordinates on a 1000×1000 mockup.
 * The same maths runs in resources/js/editor.js so previews match the saved result.
 */
final class MockupGeometry
{
    public const CANVAS = 1000;

    /**
     * @return array{area: array{x: float, y: float, w: float, h: float}, box: array{x: float, y: float, w: float, h: float}, image: array{x: float, y: float, w: float, h: float}, rotation: int, cx: float, cy: float}
     */
    public static function place(ProductMockup $mockup, PrintArea $area, float $offsetX, float $offsetY, float $scale, int $rotation, int $pixelWidth, int $pixelHeight): array
    {
        $canvas = self::CANVAS;
        $areaBox = [
            'x' => $mockup->area_x * $canvas,
            'y' => $mockup->area_y * $canvas,
            'w' => $mockup->area_width * $canvas,
            'h' => $mockup->area_height * $canvas,
        ];

        $quarter = $rotation === 90 || $rotation === 270;
        $boxPixelW = $quarter ? $pixelHeight : $pixelWidth;
        $boxPixelH = $quarter ? $pixelWidth : $pixelHeight;
        $heightPerScale = ($boxPixelH / max(1, $boxPixelW)) / $area->aspectRatio();

        $box = [
            'x' => $areaBox['x'] + $offsetX * $areaBox['w'],
            'y' => $areaBox['y'] + $offsetY * $areaBox['h'],
            'w' => $scale * $areaBox['w'],
            'h' => $scale * $heightPerScale * $areaBox['h'],
        ];

        $cx = $box['x'] + $box['w'] / 2;
        $cy = $box['y'] + $box['h'] / 2;
        $imageW = $quarter ? $box['h'] : $box['w'];
        $imageH = $quarter ? $box['w'] : $box['h'];

        return [
            'area' => $areaBox,
            'box' => $box,
            'image' => ['x' => $cx - $imageW / 2, 'y' => $cy - $imageH / 2, 'w' => $imageW, 'h' => $imageH],
            'rotation' => $rotation,
            'cx' => $cx,
            'cy' => $cy,
        ];
    }

    /**
     * Readable outline colour for a garment colour (light on dark garments).
     */
    public static function contrast(string $hex): string
    {
        $hex = ltrim($hex, '#');
        [$r, $g, $b] = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        return $luminance < 0.5 ? 'rgba(255,255,255,0.16)' : 'rgba(23,25,35,0.12)';
    }
}
