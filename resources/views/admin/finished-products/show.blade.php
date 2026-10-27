<x-layouts.dashboard :title="$product->title" area="admin">
    <div class="mb-6 text-sm text-muted"><a href="{{ route('admin.finished.index') }}" class="hover:text-ink">{{ __('Ready-made products') }}</a> / {{ $product->title }}</div>
    <x-ui.page-header :title="$product->title" :description="__('By :artist', ['artist' => $product->artist->display_name])">
        <x-slot:actions><x-ui.badge :status="$product->status" /></x-slot:actions>
    </x-ui.page-header>

    <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="space-y-6">
            <x-ui.card :title="__('Photos')">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    @forelse ($product->images as $image)<a href="{{ $image->previewUrl() }}" target="_blank" rel="noopener"><img src="{{ $image->thumbnailUrl() }}" alt="" class="aspect-square w-full rounded-xl bg-sand object-cover"></a>
                    @empty<p class="col-span-full text-sm text-muted">{{ __('No photos.') }}</p>@endforelse
                </div>
            </x-ui.card>
            <x-ui.card :title="__('Details')">
                <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-muted">{{ __('Price') }}</dt><dd class="font-medium"><x-ui.money :amount="$product->price_amount" :currency="$product->currency" />@if ($product->sale_price_amount) · {{ __('sale') }} <x-ui.money :amount="$product->sale_price_amount" :currency="$product->currency" />@endif</dd></div>
                    <div><dt class="text-muted">{{ __('Category') }}</dt><dd>{{ $product->category?->translate('name') ?? '—' }}</dd></div>
                    <div><dt class="text-muted">{{ __('Handmade') }}</dt><dd>{{ $product->is_handmade ? __('Yes') : __('No') }}</dd></div>
                    <div><dt class="text-muted">{{ __('Ships from') }}</dt><dd>{{ $product->ships_from ?: '—' }} · {{ trans_choice(':count day|:count days', $product->processing_days, ['count' => $product->processing_days]) }}</dd></div>
                </dl>
                @if ($product->description)<p class="mt-4 whitespace-pre-line text-sm text-muted">{{ $product->description }}</p>@endif
            </x-ui.card>
            <x-ui.card :title="__('Options and stock')" :padding="false">
                <div class="overflow-x-auto"><table class="table-base">
                    <thead><tr><th>{{ __('Option') }}</th><th>{{ __('SKU') }}</th><th class="text-right">{{ __('Price') }}</th><th class="text-right">{{ __('Stock') }}</th><th>{{ __('Available') }}</th></tr></thead>
                    <tbody>@foreach ($product->variants as $variant)
                        <tr><td>{{ $variant->label() ?: __('Standard') }}</td><td class="font-mono text-xs">{{ $variant->sku }}</td><td class="text-right"><x-ui.money :amount="$variant->currentPrice($product)" :currency="$product->currency" /></td><td class="text-right tabular-nums">{{ $variant->stock_quantity }}</td><td>{{ $variant->is_active ? __('Yes') : __('No') }}</td></tr>
                    @endforeach</tbody>
                </table></div>
            </x-ui.card>
        </div>

        <aside class="space-y-6">
            <x-ui.card :title="__('Decision')">
                @if ($product->moderation_note)<p class="mb-3 rounded-lg bg-sand p-3 text-sm">{{ $product->moderation_note }}<span class="mt-1 block text-xs text-muted">{{ $product->reviewer?->name }} · {{ $product->reviewed_at?->diffForHumans() }}</span></p>@endif
                <form method="post" class="space-y-3">
                    @csrf
                    <textarea name="notes" rows="3" class="input" maxlength="1000" placeholder="{{ __('Notes for the artist (required to reject or take down)') }}">{{ old('notes') }}</textarea>
                    @error('notes')<p class="text-xs text-danger-700">{{ $message }}</p>@enderror
                    <div class="flex flex-wrap gap-2">
                        @if (in_array($product->status->value, ['pending_review', 'rejected', 'draft'], true))
                            <x-ui.button variant="success" formaction="{{ route('admin.finished.decide', [$product, 'approve']) }}">{{ __('Approve') }}</x-ui.button>
                            <x-ui.button variant="secondary" formaction="{{ route('admin.finished.decide', [$product, 'reject']) }}">{{ __('Request changes') }}</x-ui.button>
                        @endif
                        @if ($product->status->value === 'active')
                            <x-ui.button variant="danger" formaction="{{ route('admin.finished.decide', [$product, 'suspend']) }}">{{ __('Take down') }}</x-ui.button>
                        @endif
                        @if ($product->status->value === 'suspended')
                            <x-ui.button variant="success" formaction="{{ route('admin.finished.decide', [$product, 'reinstate']) }}">{{ __('Reinstate') }}</x-ui.button>
                        @endif
                    </div>
                </form>
            </x-ui.card>
            <x-ui.card :title="__('Artist')">
                <p class="text-sm"><a href="{{ route('admin.artists.show', $product->artist) }}" class="link">{{ $product->artist->display_name }}</a> <x-ui.badge :status="$product->artist->status" class="ml-1" /></p>
                @if ($product->status->value === 'active')<p class="mt-3 text-xs"><a href="{{ route('finished.show', $product) }}" class="link" target="_blank">{{ __('View in shop') }}</a></p>@endif
            </x-ui.card>
        </aside>
    </div>
</x-layouts.dashboard>
