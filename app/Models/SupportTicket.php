<?php

namespace App\Models;

use App\Enums\SupportCategory;
use App\Enums\SupportTicketStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['reference', 'user_id', 'order_id', 'name', 'email', 'category', 'subject', 'message', 'status', 'staff_response', 'handled_by', 'responded_at'])]
class SupportTicket extends Model
{
    protected function casts(): array
    {
        return [
            'status' => SupportTicketStatus::class,
            'category' => SupportCategory::class,
            'responded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }
}
