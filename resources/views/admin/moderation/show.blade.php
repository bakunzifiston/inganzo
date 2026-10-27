@php $version = $artwork->currentVersion; @endphp
<x-layouts.dashboard :title="__('Review: :title', ['title' => $artwork->title])" area="admin">
    <x-ui.page-header :eyebrow="__('Design review')" :title="$artwork->title" :description="__('By :artist · :category', ['artist' => $artwork->artist->display_name, 'category' => $artwork->category?->translate('name') ?? __('No category')])">
        <x-slot:actions><x-ui.badge :status="$artwork->status" /></x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-6 xl:grid-cols-[1fr_380px]">
        <div class="space-y-6">
            <x-ui.card :title="__('Source artwork')">
                <div class="grid gap-6 md:grid-cols-[1fr_220px]">
                    <div class="aspect-square rounded-xl bg-checker p-4">
                        @if ($version?->isReady())<img src="{{ $version->previewUrl() }}" alt="{{ $artwork->title }}" class="h-full w-full object-contain">@else<p class="text-sm text-muted">{{ __('Processing…') }}</p>@endif
                    </div>
                    <dl class="space-y-2 text-sm">
                        <div><dt class="text-xs text-subtle">{{ __('File') }}</dt><dd class="truncate">{{ $version?->original_filename }}</dd></div>
                        <div><dt class="text-xs text-subtle">{{ __('Dimensions') }}</dt><dd>{{ $version?->width }} × {{ $version?->height }} px</dd></div>
                        <div><dt class="text-xs text-subtle">{{ __('Type / size') }}</dt><dd>{{ $version?->mime_type }} · {{ number_format(($version?->file_size ?? 0) / 1048576, 1) }} MB</dd></div>
                        <div><dt class="text-xs text-subtle">{{ __('Transparency') }}</dt><dd>{{ $version?->has_alpha ? __('Yes') : __('No') }}</dd></div>
                        <div><dt class="text-xs text-subtle">{{ __('Version') }}</dt><dd>v{{ $version?->version_number }} ({{ trans_choice(':count upload|:count uploads', $artwork->versions->count(), ['count' => $artwork->versions->count()]) }})</dd></div>
                        <div><dt class="text-xs text-subtle">{{ __('Rights confirmed') }}</dt><dd>{{ $artwork->rights_confirmed_at?->translatedFormat('j M Y H:i') ?? __('No') }}</dd></div>
                        <div><dt class="text-xs text-subtle">{{ __('SHA-256') }}</dt><dd class="truncate font-mono text-xs" title="{{ $version?->sha256 }}">{{ $version?->sha256 }}</dd></div>
                        @if ($version)<a href="{{ route('media.original', $version) }}" class="link text-xs">{{ __('Download original') }}</a>@endif
                    </dl>
                </div>
                @if ($artwork->description)<p class="mt-4 whitespace-pre-line text-sm text-muted">{{ $artwork->description }}</p>@endif
                @if ($artwork->tags->isNotEmpty())<p class="mt-3 flex flex-wrap gap-1.5">@foreach ($artwork->tags as $tag)<span class="rounded-full bg-sand px-2.5 py-0.5 text-xs">#{{ $tag->name }}</span>@endforeach</p>@endif
            </x-ui.card>

            <x-ui.card :title="__('Products & print checks')" :description="__('How the design will appear on each enabled product.')">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($artwork->artistProducts as $ap)
                        <div @class(['rounded-xl border p-3', 'border-line' => $ap->is_enabled, 'border-dashed border-line opacity-60' => ! $ap->is_enabled])>
                            <div class="aspect-square rounded-lg bg-sand">
                                <x-shop.mockup :product="$ap->product" :color="$ap->displayColor()" :version="$version" :placements="$ap->placements" class="h-full w-full p-2" />
                            </div>
                            <p class="mt-2 text-sm font-medium">{{ $ap->product->translate('name') }} @unless ($ap->is_enabled)<span class="text-xs text-muted">({{ __('disabled') }})</span>@endunless</p>
                            <div class="mt-1 flex items-center justify-between"><x-ui.badge :status="$ap->print_check_status" /><span class="text-xs text-muted">{{ $ap->price_from_amount ? \App\Support\Money::format($ap->price_from_amount, $ap->currency) : '' }}</span></div>
                            @foreach ($ap->placements as $placement)
                                <p class="mt-1 text-xs text-muted">{{ $placement->printArea->label }}: {{ $placement->effective_dpi }} DPI</p>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </x-ui.card>
        </div>

        <aside class="space-y-6">
            <x-ui.card :title="__('Decision')">
                @if ($artwork->status->value === 'pending_review')
                    <form method="post" action="{{ route('admin.moderation.approve', $artwork) }}" class="space-y-3">
                        @csrf
                        <x-form.textarea name="notes" :label="__('Note to artist (optional)')" rows="2" />
                        <x-ui.button variant="success" class="w-full">{{ __('Approve & publish') }}</x-ui.button>
                    </form>
                    <form method="post" action="{{ route('admin.moderation.reject', $artwork) }}" class="mt-6 space-y-3 border-t border-line pt-6">
                        @csrf
                        <x-form.textarea name="notes" :label="__('What needs to change?')" rows="3" required :hint="__('Shared with the artist.')" />
                        <x-ui.button variant="secondary" class="w-full">{{ __('Request changes') }}</x-ui.button>
                    </form>
                    @if ($artwork->artist->status->value !== 'approved')
                        <p class="mt-4 rounded-lg bg-warning-50 px-3 py-2 text-xs text-warning-700">{{ __('The artist account is :status. The design stays hidden until the artist is approved too.', ['status' => $artwork->artist->status->label()]) }} <a class="underline" href="{{ route('admin.artists.show', $artwork->artist) }}">{{ __('Review artist') }}</a></p>
                    @endif
                @elseif ($artwork->status->value === 'suspended')
                    <form method="post" action="{{ route('admin.moderation.reinstate', $artwork) }}" class="space-y-3">
                        @csrf
                        <x-form.textarea name="notes" :label="__('Reason for reinstating')" rows="2" />
                        <x-ui.button variant="success" class="w-full">{{ __('Reinstate') }}</x-ui.button>
                    </form>
                @else
                    <p class="text-sm text-muted">{{ __('This design is :status.', ['status' => $artwork->status->label()]) }}</p>
                @endif
                @if (! in_array($artwork->status->value, ['suspended', 'archived'], true))
                    <form method="post" action="{{ route('admin.moderation.suspend', $artwork) }}" class="mt-6 space-y-3 border-t border-line pt-6" onsubmit="return confirm(@js(__('Take this design down?')))">
                        @csrf
                        <x-form.textarea name="notes" :label="__('Takedown reason')" rows="2" required />
                        <x-ui.button variant="danger" class="w-full">{{ __('Take down') }}</x-ui.button>
                    </form>
                @endif
            </x-ui.card>

            <x-ui.card :title="__('Artist')">
                <a href="{{ route('admin.artists.show', $artwork->artist) }}" class="flex items-center gap-3">
                    <x-shop.avatar :artist="$artwork->artist" class="size-10" />
                    <span><span class="block font-medium">{{ $artwork->artist->display_name }}</span><span class="text-xs text-muted">{{ $artwork->artist->user->email }}</span></span>
                </a>
                <x-ui.badge :status="$artwork->artist->status" class="mt-3" />
            </x-ui.card>

            @if ($reports->isNotEmpty())
                <x-ui.card :title="__('Copyright reports')">
                    @foreach ($reports as $report)<a href="{{ route('admin.copyright.show', $report) }}" class="flex items-center justify-between text-sm hover:text-brand-700"><span>{{ $report->rights_holder }}</span><x-ui.badge :status="$report->status" /></a>@endforeach
                </x-ui.card>
            @endif

            <x-ui.card :title="__('History')">
                <ol class="space-y-3 text-sm">
                    @forelse ($artwork->moderationReviews as $review)
                        <li><x-ui.badge :status="$review->decision" /> <span class="text-xs text-muted">{{ $review->created_at->translatedFormat('j M Y H:i') }} · {{ $review->user?->name }}</span>@if ($review->notes)<p class="mt-1 text-muted">{{ $review->notes }}</p>@endif</li>
                    @empty
                        <li class="text-muted">{{ __('No reviews yet.') }}</li>
                    @endforelse
                </ol>
            </x-ui.card>
        </aside>
    </div>
</x-layouts.dashboard>
