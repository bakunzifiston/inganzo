<?php

namespace App\Services\Artwork;

use App\Models\ArtworkVersion;
use GdImage;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * GD-based derivative generation. The original file is never modified.
 */
class ImageProcessor
{
    /**
     * @return array{preview_path: string, thumbnail_path: string, has_alpha: bool}
     */
    public function process(ArtworkVersion $version): array
    {
        $disk = Storage::disk($version->disk);
        $contents = $disk->get($version->original_path);

        if ($contents === null) {
            throw new RuntimeException("Original file missing for artwork version {$version->id}.");
        }

        $previousLimit = ini_get('memory_limit');
        ini_set('memory_limit', config('marketplace.artwork.processing_memory'));

        try {
            $image = @imagecreatefromstring($contents);
            if (! $image instanceof GdImage) {
                throw new RuntimeException('The image could not be decoded.');
            }

            imagealphablending($image, false);
            imagesavealpha($image, true);

            $hasAlpha = $version->mime_type !== 'image/jpeg' && $this->detectAlpha($image);
            $directory = dirname($version->original_path);

            $previewPath = "{$directory}/preview.webp";
            $thumbnailPath = "{$directory}/thumbnail.webp";

            $disk->put($previewPath, $this->resizeToWebp($image, config('marketplace.artwork.preview_size'), 86));
            $disk->put($thumbnailPath, $this->resizeToWebp($image, config('marketplace.artwork.thumbnail_size'), 80));

            imagedestroy($image);

            return ['preview_path' => $previewPath, 'thumbnail_path' => $thumbnailPath, 'has_alpha' => $hasAlpha];
        } finally {
            ini_set('memory_limit', (string) $previousLimit);
        }
    }

    private function resizeToWebp(GdImage $source, int $maxSide, int $quality): string
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $ratio = min(1, $maxSide / max($width, $height));
        $targetWidth = max(1, (int) round($width * $ratio));
        $targetHeight = max(1, (int) round($height * $ratio));

        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        imagewebp($target, null, $quality);
        $data = (string) ob_get_clean();
        imagedestroy($target);

        return $data;
    }

    /**
     * Sample a grid of pixels; any non-opaque pixel means the artwork uses transparency.
     */
    private function detectAlpha(GdImage $image): bool
    {
        if (! imageistruecolor($image)) {
            return imagecolortransparent($image) >= 0;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $stepX = max(1, intdiv($width, 120));
        $stepY = max(1, intdiv($height, 120));

        for ($y = 0; $y < $height; $y += $stepY) {
            for ($x = 0; $x < $width; $x += $stepX) {
                if (((imagecolorat($image, $x, $y) >> 24) & 0x7F) > 0) {
                    return true;
                }
            }
        }

        return false;
    }
}
