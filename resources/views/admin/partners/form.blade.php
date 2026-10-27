<x-layouts.dashboard :title="$partner->exists ? __('Edit partner') : __('New partner')" area="admin">
    <x-ui.page-header :title="$partner->exists ? $partner->name : __('New printing partner')" />
    <x-ui.card class="max-w-3xl">
        <form method="post" action="{{ $partner->exists ? route('admin.partners.update', $partner) : route('admin.partners.store') }}" class="grid gap-5 sm:grid-cols-2">
            @csrf @if ($partner->exists) @method('put') @endif
            <x-form.input name="name" :label="__('Name')" :value="$partner->name" required />
            <x-form.input name="legal_name" :label="__('Legal name')" :value="$partner->legal_name" />
            <x-form.input name="email" type="email" :label="__('Operations e-mail')" :value="$partner->email" required />
            <x-form.input name="phone" :label="__('Phone')" :value="$partner->phone" required />
            <x-form.select name="country_id" :label="__('Country')" :options="$countries" :value="$partner->country_id" required />
            <x-form.input name="city" :label="__('City')" :value="$partner->city" required />
            <x-form.input name="address" :label="__('Address')" :value="$partner->address" class="sm:col-span-2" />
            <x-form.input name="daily_capacity" type="number" :label="__('Daily capacity (items)')" :value="$partner->daily_capacity" required />
            <x-form.input name="priority" type="number" :label="__('Priority (lower = preferred)')" :value="$partner->priority" required />
            <x-form.textarea name="notes" :label="__('Internal notes')" :value="$partner->notes" rows="3" class="sm:col-span-2" />
            <x-form.checkbox name="is_active" :label="__('Active — can receive new jobs')" :checked="$partner->is_active" />
            <div class="sm:col-span-2"><x-ui.button>{{ __('Save partner') }}</x-ui.button></div>
        </form>
    </x-ui.card>
</x-layouts.dashboard>
