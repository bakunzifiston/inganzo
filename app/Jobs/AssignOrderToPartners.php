<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\Fulfillment\PartnerAssigner;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AssignOrderToPartners implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $orderId) {}

    public function uniqueId(): string
    {
        return (string) $this->orderId;
    }

    public function handle(PartnerAssigner $assigner): void
    {
        $order = Order::query()->find($this->orderId);

        if ($order !== null) {
            $assigner->assignOrder($order);
        }
    }
}
