<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\ArtworkVersion;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves artwork from the private disk after an authorisation check, so unpublished or
 * taken-down artwork cannot be fetched by guessing URLs.
 */
class MediaController extends Controller
{
    public function artwork(ArtworkVersion $version, string $variant): Response
    {
        Gate::authorize('viewPreview', $version);

        $path = $variant === 'thumbnail' ? $version->thumbnail_path : $version->preview_path;
        $disk = Storage::disk($version->disk);

        if (! $path || ! $disk->exists($path)) {
            return $this->placeholder();
        }

        $public = $version->artwork->isPublic();

        return $disk->response($path, null, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => $public ? 'public, max-age=604800, immutable' : 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function original(ArtworkVersion $version): StreamedResponse
    {
        Gate::authorize('downloadOriginal', $version);

        $extension = pathinfo($version->original_path, PATHINFO_EXTENSION);
        $name = str($version->artwork->slug)->limit(60, '').'-v'.$version->version_number.'.'.$extension;

        return Storage::disk($version->disk)->download($version->original_path, $name, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function placeholder(): Response
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 400"><rect width="400" height="400" fill="#f1f2f7"/><path d="M120 250l50-60 40 45 30-30 40 45" stroke="#d1d5db" stroke-width="10" fill="none"/></svg>';

        return response($svg, 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'no-store']);
    }
}
