<x-layouts.dashboard :title="__('Addresses')" area="account">
    <x-ui.page-header :title="__('Delivery addresses')" :description="__('Saved addresses fill in automatically at checkout.')">
        <x-slot:actions><x-ui.button :href="route('account.addresses.create')"><x-ui.icon name="plus" class="size-4" /> {{ __('Add address') }}</x-ui.button></x-slot:actions>
    </x-ui.page-header>
    @if ($addresses->isEmpty())
        <x-ui.empty icon="map-pin" :title="__('No saved addresses')" :description="__('Add an address to check out faster.')">
            <x-ui.button :href="route('account.addresses.create')">{{ __('Add address') }}</x-ui.button>
        </x-ui.empty>
    @else
        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($addresses as $address)
                <article class="card flex flex-col p-5">
                    <div class="flex items-start justify-between gap-2">
                        <p class="font-semibold">{{ $address->label ?: $address->recipient_name }}</p>
                        @if ($address->is_default)<x-ui.badge tone="success">{{ __('Default') }}</x-ui.badge>@endif
                    </div>
                    <p class="mt-2 flex-1 text-sm text-muted">{{ $address->recipient_name }}<br>{{ $address->summary() }}@if ($address->landmark)<br>{{ $address->landmark }}@endif<br>{{ \App\Support\PhoneNumber::display($address->phone) }}</p>
                    <div class="mt-4 flex items-center gap-4 text-sm">
                        <a href="{{ route('account.addresses.edit', $address) }}" class="link">{{ __('Edit') }}</a>
                        <form method="post" action="{{ route('account.addresses.destroy', $address) }}" x-data @submit="if (! confirm(@js(__('Remove this address?')))) $event.preventDefault()">
                            @csrf @method('delete')
                            <button class="text-muted underline hover:text-danger-700">{{ __('Remove') }}</button>
                        </form>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</x-layouts.dashboard>
