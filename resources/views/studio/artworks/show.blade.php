@php
    $byProduct = $artwork->artistProducts->keyBy('product_id');
    $status = $artwork->status;
    $canSubmit = in_array($status->value, ['draft', 'rejected'], true);
@endphp
<x-layouts.dashboard :title="$artwork->title" area="studio">
    <div class="mb-6 text-sm text-muted"><a href="{{ route('studio.artworks.index') }}" class="hover:text-ink">{{ __('Designs') }}</a> / {{ $artwork->title }}</div>

    <div class="grid gap-6 xl:grid-cols-[320px_1fr]">
        <aside class="space-y-6">
            <div class="card overflow-hidden">
                <div class="aspect-square bg-checker p-6">
                    @if ($artwork->currentVersion?->isReady())
                        <img src="{{ $artwork->currentVersion->previewUrl() }}" alt="{{ $artwork->title }}" class="h-full w-full object-contain">
                    @elseif (! $artwork->current_version_id)
                        <div class="grid h-full place-items-center px-4 text-center text-sm text-muted">{{ __('No print file yet. Finish the design in the Design Studio and export it.') }}</div>
                    @elseif ($artwork->currentVersion?->status->value === 'failed')
                        <div class="grid h-full place-items-center text-center text-sm text-danger-700">{{ __('Processing failed: :error', ['error' => $artwork->currentVersion->error]) }}</div>
                    @else
                        <div class="grid h-full place-items-center text-sm text-muted" x-data x-init="setTimeout(() => location.reload(), 4000)">{{ __('Processing your artwork…') }}</div>
                    @endif
                </div>
                <div class="space-y-1 border-t border-line p-4 text-xs text-muted">
                    @if ($v = $artwork->currentVersion)
                        <p>{{ __('Version :n · :w × :h px · :size MB', ['n' => $v->version_number, 'w' => $v->width, 'h' => $v->height, 'size' => number_format($v->file_size / 1048576, 1)]) }}</p>
                        <p>{{ $v->has_alpha ? __('Transparent background detected') : __('No transparency — the background will be printed') }}</p>
                        @if ($v->source_dpi_limit)
                            <p>{{ __('Made in the Design Studio · photos inside reach :dpi DPI at the designed size', ['dpi' => $v->source_dpi_limit]) }}</p>
                        @endif
                    @endif
                    @if ($artwork->isEditable())
                        <x-ui.button :href="$artwork->designDocument ? route('studio.artworks.design', $artwork) : route('studio.artworks.design.start', $artwork)" variant="secondary" size="sm" class="mt-3 w-full">
                            <x-ui.icon name="pencil" class="size-4" /> {{ $artwork->designDocument ? __('Open in Design Studio') : __('Edit in Design Studio') }}
                        </x-ui.button>
                        @if ($artwork->designDocument && $artwork->designDocument->revision > 1 && $artwork->designDocument->updated_at > ($artwork->designDocument->exported_at ?? $artwork->designDocument->created_at))
                            <p class="mt-2 text-warning-700">{{ __('The studio design has changes that are not exported yet.') }}</p>
                        @endif
                    @endif
                </div>
            </div>

            <div class="card p-5">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold">{{ __('Status') }}</h2>
                    <x-ui.badge :status="$status" />
                </div>
                @if ($status->value === 'rejected' && $artwork->latestRejection())
                    <div class="mt-3 rounded-lg bg-danger-50 p-3 text-sm text-danger-700">
                        <p class="font-semibold">{{ __('Reviewer feedback') }}</p>
                        <p class="mt-1">{{ $artwork->latestRejection()->notes }}</p>
                    </div>
                @endif
                <p class="mt-3 text-sm text-muted">
                    @switch($status->value)
                        @case('draft') {{ __('Choose products and placements, then submit for review.') }} @break
                        @case('pending_review') {{ __('Our team is reviewing this design. This usually takes 1–2 business days.') }} @break
                        @case('approved') {{ __('Live in your shop.') }} @break
                        @case('rejected') {{ __('Address the feedback above and submit again.') }} @break
                        @case('suspended') {{ __('This design was taken down. Contact support if you believe this is a mistake.') }} @break
                        @default {{ __('Archived designs are hidden from your shop. Restore it any time to bring it back.') }}
                    @endswitch
                </p>
                <div class="mt-4 flex flex-col gap-2">
                    @if ($canSubmit)
                        <form method="post" action="{{ route('studio.artworks.submit', $artwork) }}">@csrf<x-ui.button variant="accent" class="w-full">{{ __('Submit for review') }}</x-ui.button></form>
                        @error('submit')<ul class="space-y-1 text-xs text-danger-700">@foreach ($errors->get('submit') as $message)<li>• {{ is_array($message) ? implode(' ', $message) : $message }}</li>@endforeach</ul>@enderror
                    @endif
                    @if ($status->value === 'approved')
                        <x-ui.button :href="route('designs.show', $artwork)" variant="secondary" class="w-full">{{ __('View in shop') }}</x-ui.button>
                    @endif
                    @if ($status->value === 'archived')
                        <form method="post" action="{{ route('studio.artworks.restore', $artwork) }}">@csrf<x-ui.button class="w-full"><x-ui.icon name="refresh" class="size-4" /> {{ __('Restore design') }}</x-ui.button></form>
                        <p class="text-xs text-muted">{{ $artwork->published_at ? __('It was approved before, so it goes straight back on sale.') : __('It returns as a draft for you to finish and submit.') }}</p>
                    @endif
                    @if (! in_array($status->value, ['archived', 'suspended'], true))
                        <form method="post" action="{{ route('studio.artworks.archive', $artwork) }}" onsubmit="return confirm('{{ __('Archive this design? It will be removed from your shop.') }}')">@csrf<button class="w-full py-1 text-xs text-muted underline hover:text-danger-700">{{ __('Archive design') }}</button></form>
                    @endif
                </div>
            </div>

            @if ($artwork->moderationReviews->isNotEmpty())
                <div class="card p-5">
                    <h2 class="font-semibold">{{ __('History') }}</h2>
                    <ol class="mt-3 space-y-3 text-sm">
                        @foreach ($artwork->moderationReviews->take(8) as $review)
                            <li>
                                <x-ui.badge :status="$review->decision" />
                                <span class="ml-1 text-xs text-muted">{{ $review->created_at->diffForHumans() }}</span>
                                @if ($review->notes)<p class="mt-1 text-xs text-muted">{{ $review->notes }}</p>@endif
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endif
        </aside>

        <div class="space-y-6">
            <x-ui.card :title="__('Products')" :description="__('Click a product to position your design, choose colours and set your markup.')">
                <x-slot:actions>
                    @if ($artwork->isEditable() && $artwork->currentVersion?->isReady())
                        <form method="post" action="{{ route('studio.artworks.products.defaults', $artwork) }}">@csrf<x-ui.button variant="secondary" size="sm">{{ __('Add all products') }}</x-ui.button></form>
                    @endif
                </x-slot:actions>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                    @foreach ($products as $product)
                        @php $ap = $byProduct->get($product->id); @endphp
                        <div @class(['rounded-2xl border p-3 transition', 'border-line bg-white' => $ap?->is_enabled, 'border-dashed border-line-strong bg-paper' => ! $ap?->is_enabled])>
                            <div class="relative aspect-square overflow-hidden rounded-xl bg-sand">
                                @if ($ap && $artwork->currentVersion?->isReady())
                                    <x-shop.mockup :product="$ap->product" :color="$ap->displayColor()" :version="$artwork->currentVersion" :placements="$ap->placements" class="h-full w-full p-2" />
                                @else
                                    <x-shop.mockup :product="$product->load(['printAreas', 'mockups'])" class="h-full w-full p-2 opacity-50" />
                                @endif
                                @if ($ap && ! $ap->is_enabled)<span class="absolute top-2 left-2 rounded-full bg-white/90 px-2 py-0.5 text-[11px] font-medium">{{ __('Disabled') }}</span>@endif
                            </div>
                            <div class="mt-3 flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold">{{ $product->translate('name') }}</p>
                                    @if ($ap?->price_from_amount)<p class="text-xs text-muted">{{ __('from :price', ['price' => \App\Support\Money::format($ap->price_from_amount, $ap->currency)]) }}</p>@endif
                                    @if ($ap && $ap->placements->isNotEmpty())<p class="text-xs text-muted">{{ __(':dpi DPI', ['dpi' => $ap->placements->min('effective_dpi')]) }}@if ($ap->print_check_status->value === 'fail') · <span class="font-medium text-danger-700">{{ __('not for sale until fixed') }}</span>@endif</p>@endif
                                </div>
                                @if ($ap)<x-ui.badge :status="$ap->print_check_status" />@endif
                            </div>
                            @if ($artwork->isEditable() && $artwork->currentVersion?->isReady())
                                <x-ui.button :href="route('studio.artworks.products.edit', [$artwork, $product])" :variant="$ap ? 'secondary' : 'ghost'" size="sm" class="mt-3 w-full">{{ $ap ? __('Edit') : __('Add product') }}</x-ui.button>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-ui.card>

            <div class="grid gap-6 lg:grid-cols-2">
                <x-ui.card :title="__('Details')">
                    <form method="post" action="{{ route('studio.artworks.update', $artwork) }}" class="space-y-4">
                        @csrf @method('put')
                        <x-form.input name="title" :label="__('Title')" :value="$artwork->title" required :disabled="! $artwork->isEditable()" />
                        <x-form.textarea name="description" :label="__('Description')" :value="$artwork->description" rows="3" :disabled="! $artwork->isEditable()" />
                        <x-form.select name="category_id" :label="__('Category')" :options="$categories->mapWithKeys(fn ($c) => [$c->id => $c->translate('name')])" :value="$artwork->category_id" :placeholder="__('None')" :disabled="! $artwork->isEditable()" />
                        <x-form.input name="tags" :label="__('Tags')" :value="$artwork->tags->pluck('name')->implode(', ')" :disabled="! $artwork->isEditable()" />
                        <x-form.checkbox name="is_mature" :label="__('Contains mature content')" :checked="$artwork->is_mature" />
                        @if ($artwork->isEditable())<x-ui.button variant="secondary">{{ __('Save details') }}</x-ui.button>@endif
                    </form>
                </x-ui.card>
                <x-ui.card :title="__('Replace artwork file')" :description="__('Upload a sharper version. Placements are kept and re-checked; published designs must be approved again.')">
                    @if ($artwork->isEditable())
                        <form method="post" action="{{ route('studio.artworks.file', $artwork) }}" enctype="multipart/form-data" class="space-y-4">
                            @csrf
                            <x-form.input name="file" type="file" :label="__('New file')" accept="image/png,image/jpeg,image/webp" required />
                            <x-form.checkbox name="rights_confirmed" :label="__('I own the rights to this artwork')" />
                            <x-ui.button variant="secondary">{{ __('Upload new version') }}</x-ui.button>
                        </form>
                    @else
                        <p class="text-sm text-muted">{{ __('Files cannot be replaced while the design is :status.', ['status' => mb_strtolower($status->label())]) }}</p>
                    @endif
                    @if ($artwork->versions->count() > 1)
                        <ul class="mt-5 space-y-1 border-t border-line pt-4 text-xs text-muted">
                            @foreach ($artwork->versions as $version)<li>v{{ $version->version_number }} · {{ $version->width }}×{{ $version->height }} · {{ $version->created_at->translatedFormat('j M Y') }}</li>@endforeach
                        </ul>
                    @endif
                </x-ui.card>
            </div>
        </div>
    </div>
</x-layouts.dashboard>
