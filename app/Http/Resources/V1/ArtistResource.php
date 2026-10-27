<?php

namespace App\Http\Resources\V1;

use App\Models\ArtistProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ArtistProfile
 */
class ArtistResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->display_name,
            'tagline' => $this->tagline,
            'bio' => $this->bio,
            'location' => $this->location,
            'avatar_url' => $this->avatarUrl(),
            'banner_url' => $this->bannerUrl(),
            'url' => route('artists.show', $this->resource),
            'published_designs' => $this->whenCounted('publishedArtworks'),
        ];
    }
}
