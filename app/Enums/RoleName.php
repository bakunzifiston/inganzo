<?php

namespace App\Enums;

enum RoleName: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Finance = 'finance';
    case Moderator = 'moderator';
    case Support = 'support';
    case Artist = 'artist';
    case Customer = 'customer';
    case PartnerStaff = 'partner_staff';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => __('Super administrator'),
            self::Admin => __('Administrator'),
            self::Finance => __('Finance'),
            self::Moderator => __('Content moderator'),
            self::Support => __('Customer support'),
            self::Artist => __('Artist'),
            self::Customer => __('Customer'),
            self::PartnerStaff => __('Printing partner staff'),
        };
    }

    public function isStaff(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Admin, self::Finance, self::Moderator, self::Support], true);
    }

    /**
     * Default permission grants used by the seeder. Super administrators bypass checks.
     *
     * @return list<Ability>
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::SuperAdmin => Ability::cases(),
            self::Admin => array_values(array_filter(
                Ability::cases(),
                fn (Ability $ability) => ! in_array($ability, [Ability::StaffManage, Ability::FinanceManage, Ability::SettingsManage], true),
            )),
            self::Finance => [
                Ability::AdminAccess, Ability::OrdersView, Ability::PaymentsView, Ability::RefundsManage,
                Ability::FinanceView, Ability::FinanceManage, Ability::PayoutsManage, Ability::AuditView,
            ],
            self::Moderator => [
                Ability::AdminAccess, Ability::ArtistsView, Ability::ArtistsManage, Ability::ArtworksModerate,
                Ability::ReportsManage, Ability::UsersView,
            ],
            self::Support => [
                Ability::AdminAccess, Ability::OrdersView, Ability::UsersView, Ability::ArtistsView,
                Ability::SupportManage, Ability::PaymentsView,
            ],
            self::Artist, self::Customer, self::PartnerStaff => [],
        };
    }
}
