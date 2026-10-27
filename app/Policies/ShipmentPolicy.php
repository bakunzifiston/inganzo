<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\Shipment;
use App\Models\User;

class ShipmentPolicy
{
    public function update(User $user, Shipment $shipment): bool
    {
        if ($user->hasPermission(Ability::OrdersManage) || $user->isSuperAdmin()) {
            return true;
        }

        return $user->isPartnerStaff() && $shipment->printing_partner_id !== null && $user->printingPartnerId() === $shipment->printing_partner_id;
    }
}
