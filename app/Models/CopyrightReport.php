<?php

namespace App\Models;

use App\Enums\CopyrightReportStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'artwork_id', 'reporter_user_id', 'reporter_name', 'reporter_email', 'rights_holder',
    'original_work_url', 'claim_details', 'good_faith_confirmed',
])]
class CopyrightReport extends Model
{
    protected function casts(): array
    {
        return [
            'status' => CopyrightReportStatus::class,
            'good_faith_confirmed' => 'boolean',
            'resolved_at' => 'datetime',
        ];
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
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
