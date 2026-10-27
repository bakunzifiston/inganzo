<x-layouts.dashboard :title="__('System health')" area="admin">
    <x-ui.page-header :title="__('System health')" :description="__('Live checks run when this page loads.')" />
    <div class="grid gap-6 lg:grid-cols-[1fr_380px]">
        <x-ui.card :padding="false">
            <ul class="divide-y divide-line">
                @foreach ($checks as $check)
                    <li class="flex items-start gap-3 px-5 py-4">
                        <span @class(['mt-0.5 grid size-6 shrink-0 place-items-center rounded-full', 'bg-success-50 text-success-700' => $check['ok'], 'bg-danger-50 text-danger-700' => ! $check['ok']])><x-ui.icon :name="$check['ok'] ? 'check' : 'alert'" class="size-3.5" /></span>
                        <div><p class="text-sm font-medium">{{ $check['label'] }}</p><p class="text-xs break-all text-muted">{{ $check['detail'] }}</p></div>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
        <x-ui.card :title="__('Payment providers')" :description="__('A provider appears at checkout only when enabled and fully configured.')" :padding="false">
            <ul class="divide-y divide-line text-sm">
                @foreach ($providers as $provider)
                    <li class="flex items-center justify-between px-5 py-3">
                        <span>{{ $provider['label'] }} @unless ($provider['live'])<span class="text-xs text-warning-700">({{ __('dev only') }})</span>@endunless</span>
                        @if ($provider['configured'])<x-ui.badge tone="success">{{ __('Available') }}</x-ui.badge>@elseif ($provider['enabled'])<x-ui.badge tone="warning">{{ __('Missing credentials') }}</x-ui.badge>@else<x-ui.badge>{{ __('Disabled') }}</x-ui.badge>@endif
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    </div>
</x-layouts.dashboard>
