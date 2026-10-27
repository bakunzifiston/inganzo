<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_item_id', 'user_id', 'artist_product_id', 'rating', 'title', 'body', 'status'])]
class Review extends Model
{
    protected function casts(): array
    {
        return ['rating' => 'integer'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<ArtistProduct, $this>
     */
    public function artistProduct(): BelongsTo
    {
        return $this->belongsTo(ArtistProduct::class);
    }
}
