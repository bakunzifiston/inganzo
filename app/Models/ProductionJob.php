<?php

namespace App\Models;

use App\Enums\ProductionJobStatus;
use App\Enums\QcResult;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'reference', 'order_item_id', 'printing_partner_id', 'reprint_of_id', 'status', 'quantity', 'unit_cost_amount',
    'currency', 'qc_result', 'qc_notes', 'has_issue', 'failure_reason', 'assigned_by', 'assigned_at', 'due_at',
    'accepted_at', 'started_at', 'completed_at', 'dispatched_at', 'failed_at',
])]
class ProductionJob extends Model
{
    protected function casts(): array
    {
        return [
            'status' => ProductionJobStatus::class,
            'qc_result' => QcResult::class,
            'has_issue' => 'boolean',
            'quantity' => 'integer',
            'unit_cost_amount' => 'integer',
            'assigned_at' => 'datetime',
            'due_at' => 'datetime',
            'accepted_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<OrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * @return BelongsTo<PrintingPartner, $this>
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(PrintingPartner::class, 'printing_partner_id');
    }

    /**
     * @return BelongsTo<ProductionJob, $this>
     */
    public function reprintOf(): BelongsTo
    {
        return $this->belongsTo(ProductionJob::class, 'reprint_of_id');
    }

    /**
     * @return HasMany<ProductionJobEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(ProductionJobEvent::class)->latest('id');
    }

    /**
     * @param  Builder<ProductionJob>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('production_jobs.status', ProductionJobStatus::activeValues());
    }

    /**
     * @param  Builder<ProductionJob>  $query
     */
    public function scopeDelayed(Builder $query): void
    {
        $query->active()->where('due_at', '<', now());
    }

    public function isDelayed(): bool
    {
        return $this->status->isActive() && $this->due_at->isPast();
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }
}
