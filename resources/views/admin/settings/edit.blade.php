<x-layouts.dashboard :title="__('Settings')" area="admin">
    <x-ui.page-header :title="__('Platform settings')" :description="__('Every change is written to the audit log with the old and new value.')" />
    <form method="post" action="{{ route('admin.settings.update') }}" class="grid max-w-4xl gap-6">
        @csrf @method('put')
        <x-ui.card :title="__('Artist settlement')" :description="__('Earnings become available for payout this many days after the item is delivered (if not refunded).')">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="payouts_hold_days" type="number" :label="__('Hold period (days after delivery)')" :value="$settings['payouts.hold_days']" required />
                <x-form.input name="payouts_minimum_amount" type="number" :label="__('Minimum payout (:c)', ['c' => $settings['payouts.currency']])" :value="$settings['payouts.minimum_amount']" required />
            </div>
        </x-ui.card>
        <x-ui.card :title="__('Checkout')">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="checkout_payment_timeout_minutes" type="number" :label="__('Unpaid order expiry (minutes)')" :value="$settings['checkout.payment_timeout_minutes']" required />
                <x-form.checkbox name="checkout_cod_enabled" :label="__('Enable cash on delivery')" :checked="(bool) $settings['checkout.cod_enabled']" :hint="__('Must also be enabled for each country.')" />
            </div>
        </x-ui.card>
        <x-ui.card :title="__('Ready-made products')" :description="__('Artists selling finished products set their own prices. The platform keeps this share of each sale; the artist earns the rest.')">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="marketplace_finished_commission_percent" type="number" step="0.5" min="0" max="50" :label="__('Platform commission (%)')" :value="$settings['marketplace.finished_commission_bps'] / 100" required />
            </div>
        </x-ui.card>
        <x-ui.card :title="__('Contact details')">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="marketplace_contact_email" type="email" :label="__('Support e-mail')" :value="$settings['marketplace.contact_email']" required />
                <x-form.input name="marketplace_contact_phone" :label="__('Support phone')" :value="$settings['marketplace.contact_phone']" required />
            </div>
        </x-ui.card>
        <div><x-ui.button>{{ __('Save settings') }}</x-ui.button></div>
    </form>
</x-layouts.dashboard>
