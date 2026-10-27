<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\ProductionJob;
use App\Models\User;

/**
 * Printing partner staff may only see and update jobs assigned to their own facility.
 */
class ProductionJobPolicy
{
    public function view(User $user, ProductionJob $job): bool
    {
        return $this->belongsToPartner($user, $job) || $user->hasPermission(Ability::OrdersView) || $user->isSuperAdmin();
    }

    public function update(User $user, ProductionJob $job): bool
    {
        return $this->belongsToPartner($user, $job) || $user->hasPermission(Ability::OrdersManage) || $user->isSuperAdmin();
    }

    private function belongsToPartner(User $user, ProductionJob $job): bool
    {
        return $user->isPartnerStaff() && $user->printingPartnerId() === $job->printing_partner_id;
    }
}
