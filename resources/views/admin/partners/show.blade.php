<x-layouts.dashboard :title="$partner->name" area="admin">
    <x-ui.page-header :eyebrow="__('Printing partner')" :title="$partner->name" :description="$partner->city.', '.$partner->country->name.' · '.$partner->email.' · '.\App\Support\PhoneNumber::display($partner->phone)">
        <x-slot:actions>
            <x-ui.badge :tone="$partner->is_active ? 'success' : 'neutral'">{{ $partner->is_active ? __('Active') : __('Paused') }}</x-ui.badge>
            <x-ui.button variant="secondary" size="sm" :href="route('admin.partners.edit', $partner)">{{ __('Edit') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>
    <div class="grid gap-4 sm:grid-cols-4">
        <x-ui.stat :label="__('Open jobs')" :value="$stats['open']" :hint="__('Capacity :n/day', ['n' => $partner->daily_capacity])" />
        <x-ui.stat :label="__('Delayed')" :value="$stats['delayed']" :tone="$stats['delayed'] ? 'danger' : 'default'" />
        <x-ui.stat :label="__('Dispatched (30 days)')" :value="$stats['dispatched_30d']" tone="leaf" />
        <x-ui.stat :label="__('Failed (30 days)')" :value="$stats['failed_30d']" :tone="$stats['failed_30d'] ? 'warning' : 'default'" />
    </div>
    <div class="mt-6 grid gap-6 xl:grid-cols-[1fr_380px]">
        <div class="space-y-6">
            <x-ui.card :title="__('Products & unit costs')" :description="__('What the partner charges per unit. Must stay below the product base cost to protect margin.')">
                <form method="post" action="{{ route('admin.partners.services.update', $partner) }}">
                    @csrf @method('put')
                    <table class="table-base">
                        <thead><tr><th>{{ __('Offers') }}</th><th>{{ __('Product') }}</th><th>{{ __('Base cost') }}</th><th>{{ __('Partner cost') }}</th><th>{{ __('Days') }}</th></tr></thead>
                        <tbody>
                            @foreach ($products as $product)
                                @php $service = $partner->services->firstWhere('product_id', $product->id); @endphp
                                <tr>
                                    <td><input type="hidden" name="services[{{ $product->id }}][enabled]" value="0"><input type="checkbox" name="services[{{ $product->id }}][enabled]" value="1" @checked($service?->is_active) class="accent-brand-600" aria-label="{{ $product->translate('name') }}"></td>
                                    <td>{{ $product->translate('name') }}</td>
                                    <td class="text-muted"><x-ui.money :amount="$product->base_cost_amount" :currency="$product->currency" /></td>
                                    <td><input name="services[{{ $product->id }}][unit_cost]" value="{{ $service ? \App\Support\Money::toMajor($service->unit_cost_amount, $product->currency) : '' }}" inputmode="decimal" class="w-28 rounded-lg border border-line-strong px-2 py-1 text-sm">@if ($service && $service->unit_cost_amount > $product->base_cost_amount)<p class="text-xs text-danger-700">{{ __('Above base cost') }}</p>@endif</td>
                                    <td><input name="services[{{ $product->id }}][production_days]" type="number" min="1" value="{{ $service?->production_days ?? $product->production_days }}" class="w-16 rounded-lg border border-line-strong px-2 py-1 text-sm"></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @error('services')<p class="mt-2 text-sm text-danger-700">{{ $message }}</p>@enderror
                    <x-ui.button size="sm" class="mt-4">{{ __('Save products') }}</x-ui.button>
                </form>
            </x-ui.card>
            <x-ui.card :title="__('Recent jobs')" :padding="false">
                <table class="table-base">
                    <tbody>
                        @forelse ($jobs as $job)
                            <tr @class(['bg-danger-50/60' => $job->isDelayed()])><td class="font-medium">{{ $job->reference }}</td><td><a href="{{ route('admin.orders.show', $job->orderItem->order) }}" class="hover:text-brand-700">{{ $job->orderItem->order->number }}</a></td><td>{{ $job->orderItem->title }}</td><td><x-ui.badge :status="$job->status" /></td><td class="text-muted">{{ $job->due_at->translatedFormat('j M') }}</td></tr>
                        @empty
                            <tr><td class="text-muted">{{ __('No jobs yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-ui.card>
        </div>
        <aside class="space-y-6">
            <x-ui.card :title="__('Service areas')">
                <ul class="space-y-2 text-sm">
                    @forelse ($partner->serviceAreas as $area)
                        <li class="flex items-center justify-between">
                            <span>{{ $area->country->name }} · {{ $area->deliveryZone?->name ?? __('all zones') }}</span>
                            <form method="post" action="{{ route('admin.partners.areas.destroy', [$partner, $area]) }}">@csrf @method('delete')<button class="text-xs text-muted underline hover:text-danger-700">{{ __('Remove') }}</button></form>
                        </li>
                    @empty
                        <li class="text-warning-700">{{ __('No service area — this partner will never be assigned automatically.') }}</li>
                    @endforelse
                </ul>
                <form method="post" action="{{ route('admin.partners.areas.store', $partner) }}" class="mt-4 space-y-2 border-t border-line pt-4" x-data="{ country: '{{ $countries->first()?->id }}' }">
                    @csrf
                    <select name="country_id" x-model="country" class="input">@foreach ($countries as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>
                    <select name="delivery_zone_id" class="input">
                        <option value="">{{ __('All zones') }}</option>
                        @foreach ($countries as $c)@foreach ($c->deliveryZones as $z)<option value="{{ $z->id }}" x-show="country == '{{ $c->id }}'">{{ $z->name }}</option>@endforeach @endforeach
                    </select>
                    <x-ui.button size="sm" variant="secondary">{{ __('Add area') }}</x-ui.button>
                </form>
            </x-ui.card>
            <x-ui.card :title="__('Staff with portal access')" :description="__('They only see jobs assigned to this partner.')">
                <ul class="space-y-2 text-sm">
                    @foreach ($partner->members as $member)
                        <li class="flex items-center justify-between gap-2">
                            <span>{{ $member->user->name }} <span class="text-xs text-muted">· {{ $member->user->email }} · {{ $member->role }}</span></span>
                            <form method="post" action="{{ route('admin.partners.members.destroy', [$partner, $member]) }}" onsubmit="return confirm(@js(__('Remove portal access?')))">@csrf @method('delete')<button class="text-xs text-muted underline hover:text-danger-700">{{ __('Remove') }}</button></form>
                        </li>
                    @endforeach
                </ul>
                <form method="post" action="{{ route('admin.partners.members.store', $partner) }}" class="mt-4 space-y-2 border-t border-line pt-4">
                    @csrf
                    <x-form.input name="email" type="email" :label="__('Existing user e-mail')" required />
                    <x-form.select name="role" :label="__('Role')" :options="['operator' => __('Operator'), 'manager' => __('Manager')]" />
                    <x-ui.button size="sm" variant="secondary">{{ __('Grant access') }}</x-ui.button>
                </form>
            </x-ui.card>
            @if ($partner->notes)<x-ui.card :title="__('Notes')"><p class="whitespace-pre-line text-sm text-muted">{{ $partner->notes }}</p></x-ui.card>@endif
        </aside>
    </div>
</x-layouts.dashboard>
