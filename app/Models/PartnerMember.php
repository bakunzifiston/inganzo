<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['printing_partner_id', 'user_id', 'role'])]
class PartnerMember extends Model
{
    /**
     * @return BelongsTo<PrintingPartner, $this>
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(PrintingPartner::class, 'printing_partner_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
