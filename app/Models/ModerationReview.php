<?php

namespace App\Models;

use App\Enums\ModerationDecision;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['artwork_id', 'artwork_version_id', 'user_id', 'decision', 'notes', 'created_at'])]
#[WithoutTimestamps]
class ModerationReview extends Model
{
    protected function casts(): array
    {
        return ['decision' => ModerationDecision::class, 'created_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Artwork, $this>
     */
    public function artwork(): BelongsTo
    {
        return $this->belongsTo(Artwork::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
