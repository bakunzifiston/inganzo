<?php

namespace App\Support;

use App\Enums\ArtworkStatus;
use App\Enums\FinishedProductStatus;
use App\Enums\PayoutStatus;
use App\Enums\ProductionJobStatus;
use App\Enums\RefundStatus;
use App\Enums\RoleName;
use App\Models\Artwork;
use App\Models\CopyrightReport;
use App\Models\FinishedProduct;
use App\Models\OrderItem;
use App\Models\PayoutRequest;
use App\Models\ProductionJob;
use App\Models\Refund;
use App\Models\SupportTicket;
use App\Models\User;

/**
 * Sidebar definitions for the dashboard layouts. Items the user may not access are omitted.
 */
final class Navigation
{
    /**
     * @return array<int|string, list<array{label: string, url: string, icon: string, active: bool, badge?: int|null}>>
     */
    public static function for(string $area, User $user): array
    {
        $groups = match ($area) {
            'admin' => self::admin($user),
            'studio' => self::studio($user),
            'partner' => self::partner($user),
            default => self::account($user),
        };

        return array_filter(array_map(fn (array $items) => array_values(array_filter($items)), $groups));
    }

    /**
     * Areas this account can open, for the dashboard workspace switcher.
     *
     * @return list<array{area: string, label: string, description: string, icon: string, tone: string, url: string}>
     */
    public static function workspaces(User $user): array
    {
        return array_values(array_filter([
            $user->can('admin.access') ? ['area' => 'admin', 'label' => __('Admin console'), 'description' => __('Operate the marketplace'), 'icon' => 'shield', 'tone' => 'bg-brand-600 text-ink', 'url' => route('admin.dashboard')] : null,
            $user->isArtist() ? ['area' => 'studio', 'label' => __('Artist studio'), 'description' => $user->artistProfile->display_name, 'icon' => 'palette', 'tone' => 'bg-brand-100 text-brand-700', 'url' => route('studio.dashboard')] : null,
            $user->isPartnerStaff() ? ['area' => 'partner', 'label' => __('Production portal'), 'description' => $user->partnerMembership->partner->name, 'icon' => 'printer', 'tone' => 'bg-success-100 text-success-700', 'url' => route('partner.dashboard')] : null,
            ['area' => 'account', 'label' => __('My account'), 'description' => __('Orders, addresses, favourites'), 'icon' => 'user', 'tone' => 'bg-warning-100 text-warning-700', 'url' => route('account.dashboard')],
        ]));
    }

    /**
     * Human label for the most significant role, shown in the sidebar user card.
     */
    public static function roleLabel(User $user): string
    {
        foreach ([RoleName::SuperAdmin, RoleName::Admin, RoleName::Finance, RoleName::Moderator, RoleName::Support, RoleName::PartnerStaff, RoleName::Artist] as $role) {
            if ($user->hasRole($role)) {
                return $role->label();
            }
        }

        return RoleName::Customer->label();
    }

    /**
     * @return array{label: string, url: string, icon: string, active: bool, badge: int|null}
     */
    private static function item(string $label, string $route, string $icon, string|array|null $pattern = null, ?int $badge = null): array
    {
        return [
            'label' => $label,
            'url' => route($route),
            'icon' => $icon,
            'active' => request()->routeIs(...(array) ($pattern ?? $route)),
            'badge' => $badge ?: null,
        ];
    }

    private static function admin(User $user): array
    {
        $can = fn (string $ability) => $user->can($ability);

        return [
            [
                self::item(__('Overview'), 'admin.dashboard', 'chart'),
            ],
            __('Commerce') => [
                $can('orders.view') ? self::item(__('Orders'), 'admin.orders.index', 'receipt', 'admin.orders.*') : null,
                $can('orders.manage') ? self::item(__('Production'), 'admin.production.index', 'printer', 'admin.production.*', ProductionJob::query()->delayed()->count()) : null,
                $can('payments.view') ? self::item(__('Payments'), 'admin.payments.index', 'wallet', 'admin.payments.*') : null,
                $can('refunds.manage') ? self::item(__('Refunds'), 'admin.refunds.index', 'refresh', 'admin.refunds.*', Refund::query()->where('status', RefundStatus::Requested)->count()) : null,
                $can('discounts.manage') ? self::item(__('Discounts'), 'admin.discounts.index', 'tag', 'admin.discounts.*') : null,
                $can('support.manage') ? self::item(__('Support'), 'admin.support.index', 'mail', 'admin.support.*', SupportTicket::query()->where('status', 'open')->count()) : null,
            ],
            __('Marketplace') => [
                $can('artworks.moderate') ? self::item(__('Moderation'), 'admin.moderation.index', 'eye', 'admin.moderation.*', Artwork::query()->where('status', ArtworkStatus::PendingReview)->count()) : null,
                $can('artworks.moderate') ? self::item(__('Ready-made products'), 'admin.finished.index', 'package', 'admin.finished.*', FinishedProduct::query()->where('status', FinishedProductStatus::PendingReview->value)->count()) : null,
                $can('artists.view') ? self::item(__('Artists'), 'admin.artists.index', 'palette', 'admin.artists.*') : null,
                $can('reports.manage') ? self::item(__('Copyright reports'), 'admin.copyright.index', 'flag', 'admin.copyright.*', CopyrightReport::query()->whereIn('status', ['open', 'under_review'])->count()) : null,
                $can('content.manage') ? self::item(__('Categories'), 'admin.categories.index', 'grid', 'admin.categories.*') : null,
                $can('content.manage') ? self::item(__('Collections'), 'admin.collections.index', 'sparkles', 'admin.collections.*') : null,
                $can('content.manage') ? self::item(__('Promotions'), 'admin.promotions.index', 'store', 'admin.promotions.*') : null,
            ],
            __('Operations') => [
                $can('catalog.manage') ? self::item(__('Products'), 'admin.products.index', 'package', 'admin.products.*') : null,
                $can('catalog.manage') ? self::item(__('Product types'), 'admin.product-types.index', 'grid', 'admin.product-types.*') : null,
                $can('partners.manage') ? self::item(__('Printing partners'), 'admin.partners.index', 'printer', 'admin.partners.*') : null,
                $can('localization.manage') ? self::item(__('Countries & delivery'), 'admin.localization.index', 'globe', 'admin.localization.*') : null,
            ],
            __('Finance') => [
                $can('finance.view') ? self::item(__('Finance & ledger'), 'admin.finance.index', 'chart', 'admin.finance.*') : null,
                $can('payouts.manage') ? self::item(__('Payouts'), 'admin.payouts.index', 'wallet', 'admin.payouts.*', PayoutRequest::query()->where('status', PayoutStatus::Requested)->count()) : null,
                $can('finance.manage') ? self::item(__('Pricing rules'), 'admin.pricing-rules.index', 'tag', 'admin.pricing-rules.*') : null,
            ],
            __('Administration') => [
                $can('users.view') ? self::item(__('Users'), 'admin.users.index', 'users', 'admin.users.*') : null,
                $can('staff.manage') ? self::item(__('Roles & permissions'), 'admin.roles.index', 'shield', 'admin.roles.*') : null,
                $can('settings.manage') ? self::item(__('Settings'), 'admin.settings.edit', 'settings', 'admin.settings.*') : null,
                $can('audit.view') ? self::item(__('Audit log'), 'admin.audit.index', 'receipt', 'admin.audit.*') : null,
                $can('audit.view') ? self::item(__('System health'), 'admin.system.health', 'shield', 'admin.system.*') : null,
            ],
        ];
    }

    private static function studio(User $user): array
    {
        return [
            [
                self::item(__('Dashboard'), 'studio.dashboard', 'chart'),
                self::item(__('Products'), 'studio.products.index', 'package', ['studio.products.*', 'studio.finished.*']),
                self::item(__('Add product'), 'studio.sell', 'plus', 'studio.sell'),
                self::item(__('Designs'), 'studio.artworks.index', 'image', 'studio.artworks.*'),
                self::item(__('Design Studio'), 'studio.design.create', 'pencil', 'studio.design.*'),
                self::item(__('Orders'), 'studio.orders.index', 'truck', 'studio.orders.*', OrderItem::query()->where('artist_profile_id', $user->artistProfile?->id)->where('selling_type', 'finished')->where('fulfillment_status', 'awaiting_shipment')->count()),
                self::item(__('Sales'), 'studio.sales', 'receipt'),
                self::item(__('Earnings & payouts'), 'studio.earnings', 'wallet', 'studio.earnings*'),
                self::item(__('Shop settings'), 'studio.shop.edit', 'store', 'studio.shop.*'),
                self::item(__('Notifications'), 'account.notifications', 'bell'),
            ],
        ];
    }

    private static function partner(User $user): array
    {
        $partnerId = $user->printingPartnerId();

        return [
            [
                self::item(__('Production jobs'), 'partner.dashboard', 'printer', 'partner.dashboard', ProductionJob::query()->where('printing_partner_id', $partnerId)->where('status', ProductionJobStatus::Assigned)->count()),
                self::item(__('Shipments'), 'partner.shipments.index', 'truck', 'partner.shipments.*'),
            ],
        ];
    }

    private static function account(User $user): array
    {
        return [
            [
                self::item(__('Overview'), 'account.dashboard', 'user'),
                self::item(__('Orders'), 'account.orders.index', 'receipt', 'account.orders.*'),
                self::item(__('Addresses'), 'account.addresses.index', 'map-pin', 'account.addresses.*'),
                self::item(__('Favourites'), 'account.favorites.index', 'heart'),
                self::item(__('Profile & security'), 'account.profile.edit', 'settings'),
                self::item(__('Notifications'), 'account.notifications', 'bell'),
                $user->isArtist() ? self::item(__('Artist studio'), 'studio.dashboard', 'palette') : self::item(__('Sell your art'), 'account.become-artist', 'palette'),
            ],
        ];
    }
}
