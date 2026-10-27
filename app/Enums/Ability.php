<?php

namespace App\Enums;

/**
 * Staff permissions. Each case is registered as a Gate ability with the same name.
 */
enum Ability: string
{
    case AdminAccess = 'admin.access';
    case UsersView = 'users.view';
    case UsersManage = 'users.manage';
    case StaffManage = 'staff.manage';
    case ArtistsView = 'artists.view';
    case ArtistsManage = 'artists.manage';
    case ArtworksModerate = 'artworks.moderate';
    case CatalogManage = 'catalog.manage';
    case PartnersManage = 'partners.manage';
    case OrdersView = 'orders.view';
    case OrdersManage = 'orders.manage';
    case PaymentsView = 'payments.view';
    case RefundsManage = 'refunds.manage';
    case FinanceView = 'finance.view';
    case FinanceManage = 'finance.manage';
    case PayoutsManage = 'payouts.manage';
    case DiscountsManage = 'discounts.manage';
    case ContentManage = 'content.manage';
    case LocalizationManage = 'localization.manage';
    case ReportsManage = 'reports.manage';
    case SupportManage = 'support.manage';
    case SettingsManage = 'settings.manage';
    case AuditView = 'audit.view';

    public function label(): string
    {
        return match ($this) {
            self::AdminAccess => 'Access the admin console',
            self::UsersView => 'View users',
            self::UsersManage => 'Suspend and edit users',
            self::StaffManage => 'Manage staff roles and permissions',
            self::ArtistsView => 'View artist accounts',
            self::ArtistsManage => 'Approve, reject and suspend artists',
            self::ArtworksModerate => 'Review and publish artwork',
            self::CatalogManage => 'Manage product types, blanks, variants and mockups',
            self::PartnersManage => 'Manage printing partners',
            self::OrdersView => 'View orders',
            self::OrdersManage => 'Assign partners, cancel and manage orders',
            self::PaymentsView => 'View payments',
            self::RefundsManage => 'Approve and process refunds',
            self::FinanceView => 'View ledger and earnings reports',
            self::FinanceManage => 'Change pricing and commission rules',
            self::PayoutsManage => 'Approve and record artist payouts',
            self::DiscountsManage => 'Manage discount codes',
            self::ContentManage => 'Manage homepage, categories, collections and promotions',
            self::LocalizationManage => 'Manage countries, currencies and delivery zones',
            self::ReportsManage => 'Handle copyright reports and takedowns',
            self::SupportManage => 'Respond to support tickets',
            self::SettingsManage => 'Change platform settings',
            self::AuditView => 'View audit logs and system health',
        };
    }

    public function group(): string
    {
        return explode('.', $this->value)[0];
    }
}
