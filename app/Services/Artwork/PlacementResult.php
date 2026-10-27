<?php

namespace App\Services\Artwork;

use App\Enums\PrintCheckStatus;

final readonly class PlacementResult
{
    public function __construct(
        public float $offsetX,
        public float $offsetY,
        public float $scale,
        public int $rotation,
        public int $effectiveDpi,
        public PrintCheckStatus $status,
        public string $message,
        public float $printWidthMm = 0.0,
        public float $printHeightMm = 0.0,
        public int $dpiX = 0,
        public int $dpiY = 0,
    ) {}
}
