<x-layouts.shop>
    {{-- Hero --}}
    @php $heroPromo = $promotions->firstWhere('placement', 'home_hero'); @endphp
    <section class="container-page pt-5">
        <div class="relative isolate overflow-hidden rounded-2xl bg-brand-600 text-ink">
            {{-- Texture: a faint Imigongo zig-zag field and a soft vignette --}}
            <div class="absolute inset-0 -z-10 opacity-[0.08]" style="background-image: linear-gradient(135deg, #000 25%, transparent 25%), linear-gradient(225deg, #000 25%, transparent 25%); background-size: 36px 36px;" aria-hidden="true"></div>
            <div class="absolute inset-0 -z-10 bg-[radial-gradient(ellipse_at_center,rgb(23_25_35/0)_0%,rgb(23_25_35/0.18)_100%)]" aria-hidden="true"></div>

            @foreach ([
                ['hoodie', 'left-[-4%] top-[-6%] w-[30%] -rotate-[10deg]'],
                ['tee', 'left-[6%] bottom-[-14%] w-[26%] rotate-[6deg]'],
                ['sticker', 'right-[2%] top-[4%] w-[15%] rotate-[12deg]'],
                ['mug', 'right-[16%] bottom-[-4%] w-[17%] -rotate-[6deg]'],
                ['tote', 'right-[-5%] bottom-[-10%] w-[22%] rotate-[8deg]'],
            ] as [$key, $classes])
                @if ($item = $heroItems->get($key))
                    <a href="{{ route('products.show', [$item->artwork, $item->product]) }}" class="absolute hidden aspect-square drop-shadow-[0_18px_24px_rgb(23_25_35/0.35)] transition duration-500 hover:scale-105 md:block {{ $classes }}" tabindex="-1" aria-hidden="true">
                        <x-shop.mockup :product="$item->product" :color="$item->displayColor()" :version="$item->artwork->currentVersion" :placements="$item->placements" />
                    </a>
                @endif
            @endforeach

            <div class="relative mx-auto flex min-h-[340px] max-w-xl flex-col items-center justify-center px-6 py-16 text-center sm:min-h-[420px]">
                <p class="text-xs font-semibold tracking-[0.18em] text-ink/70 uppercase">{{ __('Printed on demand in Kigali') }}</p>
                <h1 class="mt-3 text-4xl leading-[1.05] font-bold drop-shadow-sm sm:text-6xl">
                    {{ $heroPromo?->translate('title') ?: __('Art from the land of a thousand hills') }}
                </h1>
                <p class="mt-5 max-w-md text-lg text-ink/80 sm:text-xl">
                    {{ $heroPromo?->translate('subtitle') ?: __('Original designs by Rwandan artists on tees, hoodies, mugs and more. Every order pays the artist.') }}
                </p>
                <a href="{{ $heroPromo?->cta_url ?: route('shop.index') }}" class="mt-8 inline-flex items-center rounded-full bg-ink px-7 py-3.5 text-base font-semibold text-white shadow-lg transition hover:bg-ink-soft">
                    {{ $heroPromo?->translate('cta_label') ?: __('Shop original designs') }}
                </a>
                <div class="mt-8 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-sm text-ink/75">
                    <span class="flex items-center gap-2"><x-ui.icon name="truck" class="size-4" /> {{ __('Kigali delivery in 1–2 days') }}</span>
                    <span class="flex items-center gap-2"><x-ui.icon name="phone" class="size-4" /> {{ __('Pay with Mobile Money') }}</span>
                </div>
            </div>
        </div>
    </section>

    @foreach ($promotions->where('placement', 'home_banner')->take(1) as $promotion)
        <section class="container-page pt-8 pb-2">
            <div @class(['relative overflow-hidden rounded-2xl px-8 py-10 sm:px-12', 'bg-ink text-white' => $promotion->theme === 'ink', 'bg-brand-600 text-ink' => $promotion->theme === 'clay', 'bg-success-600 text-white' => $promotion->theme === 'leaf'])>
                <div class="absolute inset-x-0 bottom-0 imigongo-band opacity-20"></div>
                <div class="relative max-w-xl">
                    <h2 class="text-2xl font-bold sm:text-3xl">{{ $promotion->translate('title') }}</h2>
                    @if ($promotion->subtitle)<p class="mt-2 text-white/80">{{ $promotion->translate('subtitle') }}</p>@endif
                    @if ($promotion->cta_url)
                        <a href="{{ $promotion->cta_url }}" class="mt-5 inline-flex items-center gap-2 rounded-full bg-white px-5 py-2.5 text-sm font-semibold text-ink hover:bg-sand">{{ $promotion->translate('cta_label') ?: __('Shop now') }} <x-ui.icon name="arrow-right" class="size-4" /></a>
                    @endif
                </div>
            </div>
        </section>
    @endforeach

    @include('shop.partials.product-row', ['title' => __('Trending now'), 'subtitle' => __('What people in Kigali are wearing this week'), 'items' => $trending, 'link' => route('shop.index', ['sort' => 'trending'])])

    @include('shop.partials.product-row', ['title' => __('Ready-made by artists'), 'subtitle' => __('Handmade and in-stock products shipped by the artists themselves'), 'items' => $readyMade, 'link' => route('shop.index', ['type' => \App\Services\Catalog\CatalogSearch::READY_MADE])])

    {{-- Collections --}}
    @if ($collections->isNotEmpty())
        <section class="border-y border-line bg-white py-16">
            <div class="container-page">
                <div class="mb-8 flex items-end justify-between gap-4">
                    <div>
                        <p class="eyebrow">{{ __('Curated') }}</p>
                        <h2 class="mt-2 text-2xl font-bold sm:text-3xl">{{ __('Rwanda-inspired collections') }}</h2>
                    </div>
                </div>
                <div class="grid gap-5 md:grid-cols-3">
                    @foreach ($collections as $collection)
                        <a href="{{ route('shop.collection', $collection) }}" class="group card overflow-hidden transition hover:shadow-[var(--shadow-lift)]">
                            <div class="grid grid-cols-2 gap-px bg-line">
                                @foreach ($collection->artworks->take(4) as $art)
                                    <div class="aspect-square bg-white p-3">
                                        @if ($art->currentVersion)<img src="{{ $art->currentVersion->thumbnailUrl() }}" alt="{{ $art->title }}" loading="lazy" class="h-full w-full object-contain">@endif
                                    </div>
                                @endforeach
                            </div>
                            <div class="p-5">
                                <h3 class="font-semibold group-hover:text-brand-700">{{ $collection->translate('title') }}</h3>
                                @if ($collection->description)<p class="mt-1 line-clamp-2 text-sm text-muted">{{ $collection->translate('description') }}</p>@endif
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @include('shop.partials.product-row', ['title' => __('Popular T-shirts'), 'subtitle' => null, 'items' => $tees, 'link' => route('shop.index', ['type' => 't-shirts'])])
    @include('shop.partials.product-row', ['title' => __('Hoodies & sweatshirts'), 'subtitle' => __('For cool Kigali evenings'), 'items' => $hoodies, 'link' => route('shop.index', ['type' => 'hoodies-sweatshirts'])])

    {{-- Featured artists --}}
    @if ($artists->isNotEmpty())
        <section class="container-page py-14">
            <div class="mb-8 flex items-end justify-between gap-4">
                <div>
                    <p class="eyebrow">{{ __('The people behind the prints') }}</p>
                    <h2 class="mt-2 text-2xl font-bold sm:text-3xl">{{ __('Featured artists') }}</h2>
                </div>
                <a href="{{ route('artists.index') }}" class="link text-sm">{{ __('All artists') }}</a>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($artists as $artist)
                    <x-shop.artist-card :artist="$artist" />
                @endforeach
            </div>
        </section>
    @endif

    @include('shop.partials.product-row', ['title' => __('New arrivals'), 'subtitle' => __('Fresh from the studio, approved this week'), 'items' => $newArrivals, 'link' => route('shop.index', ['sort' => 'newest'])])

    {{-- Categories --}}
    @if ($categories->isNotEmpty())
        <section class="container-page py-10">
            <h2 class="mb-6 text-2xl font-bold">{{ __('Browse by theme') }}</h2>
            <div class="flex flex-wrap gap-3">
                @foreach ($categories as $category)
                    <a href="{{ route('shop.category', $category) }}" class="rounded-full border border-line-strong bg-white px-5 py-2.5 text-sm font-medium transition hover:border-brand-600 hover:bg-brand-600 hover:text-ink">{{ $category->translate('name') }}</a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Sell CTA --}}
    <section class="container-page pt-10">
        <div class="relative overflow-hidden rounded-3xl bg-ink px-8 py-14 text-white sm:px-14">
            <div class="absolute inset-x-0 top-0 imigongo-band-clay"></div>
            <div class="grid items-center gap-10 lg:grid-cols-2">
                <div>
                    <p class="eyebrow text-brand-200">{{ __('For artists & designers') }}</p>
                    <h2 class="mt-3 text-3xl font-bold sm:text-4xl">{{ __('Upload once. Sell on everything.') }}</h2>
                    <p class="mt-4 max-w-md text-sand/75">{{ __('Open your free shop, place your artwork on T-shirts, hoodies, totes, mugs and stickers, and earn on every sale. We handle printing, payment and delivery.') }}</p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="{{ auth()->check() ? route('account.become-artist') : route('register', ['as' => 'artist']) }}" class="inline-flex items-center gap-2 rounded-full bg-brand-600 px-6 py-3 text-sm font-semibold text-ink hover:bg-brand-500">{{ __('Start selling') }} <x-ui.icon name="arrow-right" class="size-4" /></a>
                        <a href="{{ route('pages.show', 'sell') }}" class="inline-flex items-center rounded-full border border-white/20 px-6 py-3 text-sm font-semibold hover:bg-white/10">{{ __('How it works') }}</a>
                    </div>
                </div>
                <dl class="grid grid-cols-3 gap-4 text-center">
                    @foreach ([[__('Upload'), __('PNG artwork, transparent backgrounds welcome')], [__('Set your price'), __('Choose your markup on every product')], [__('Get paid'), __('Withdraw to MTN or Airtel Money')]] as [$step, $text])
                        <div class="rounded-2xl border border-white/10 p-4">
                            <dt class="font-display text-lg font-semibold">{{ $step }}</dt>
                            <dd class="mt-1 text-xs text-sand/60">{{ $text }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </div>
    </section>
</x-layouts.shop>
