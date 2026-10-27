<?php

namespace App\Http\Resources\V1;

use App\Models\ArtistProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ArtistProduct
 */
class ArtistProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->artwork->title,
            'url' => route('products.show', [$this->artwork, $this->product]),
            'artwork' => [
                'slug' => $this->artwork->slug,
                'description' => $this->artwork->description,
                'preview_url' => $this->artwork->currentVersion?->previewUrl(),
                'thumbnail_url' => $this->artwork->currentVersion?->thumbnailUrl(),
            ],
            'artist' => [
                'name' => $this->artwork->artist->display_name,
                'slug' => $this->artwork->artist->slug,
                'url' => route('artists.show', $this->artwork->artist),
            ],
            'product' => [
                'slug' => $this->product->slug,
                'name' => $this->product->translate('name'),
                'production_days' => $this->product->production_days,
            ],
            'price_from' => ['amount' => $this->price_from_amount, 'currency' => $this->currency],
            'colors' => $this->colors->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'hex' => $c->hex])->values(),
        ];
    }
}
