<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ShopController extends Controller
{
    public function edit(Request $request): View
    {
        return view('studio.shop', ['artist' => $request->user()->artistProfile]);
    }

    public function update(Request $request): RedirectResponse
    {
        $artist = $request->user()->artistProfile;

        $validated = $request->validate([
            'display_name' => ['required', 'string', 'min:2', 'max:80'],
            'slug' => ['required', 'string', 'min:3', 'max:60', 'alpha_dash:ascii', 'lowercase', Rule::unique('artist_profiles', 'slug')->ignore($artist->id), Rule::notIn(['create', 'edit'])],
            'tagline' => ['nullable', 'string', 'max:160'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'location' => ['nullable', 'string', 'max:100'],
            'website_url' => ['nullable', 'url:https,http', 'max:255'],
            'instagram_handle' => ['nullable', 'string', 'max:60', 'regex:/^@?[A-Za-z0-9._]+$/'],
            'x_handle' => ['nullable', 'string', 'max:60', 'regex:/^@?[A-Za-z0-9_]+$/'],
            'avatar' => ['nullable', 'image', 'mimetypes:image/png,image/jpeg,image/webp', 'max:4096', 'dimensions:min_width=200,min_height=200'],
            'banner' => ['nullable', 'image', 'mimetypes:image/png,image/jpeg,image/webp', 'max:8192', 'dimensions:min_width=1200,min_height=300'],
        ]);

        foreach (['avatar' => 'avatar_path', 'banner' => 'banner_path'] as $input => $column) {
            if ($request->hasFile($input)) {
                $path = $this->storeImage($request->file($input), "artists/{$artist->id}");
                if ($artist->{$column}) {
                    Storage::disk('public')->delete($artist->{$column});
                }
                $artist->{$column} = $path;
            }
        }

        $artist->fill(collect($validated)->except(['avatar', 'banner'])->all())->save();

        return back()->with('success', __('Shop updated.'));
    }

    /**
     * Re-encode images so no metadata or embedded payload from the upload is published.
     */
    private function storeImage(UploadedFile $file, string $directory): string
    {
        $image = imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        abort_if($image === false, 422, __('The image could not be read.'));

        ob_start();
        imagewebp($image, null, 85);
        $data = (string) ob_get_clean();
        imagedestroy($image);

        $path = $directory.'/'.bin2hex(random_bytes(10)).'.webp';
        Storage::disk('public')->put($path, $data);

        return $path;
    }
}
