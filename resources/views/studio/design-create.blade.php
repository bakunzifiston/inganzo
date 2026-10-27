@php
    $byProduct = $areas->groupBy(fn ($area) => $area->product->translate('name'));
    $title = $artwork ? __('Open “:title” in the Design Studio', ['title' => $artwork->title]) : __('Create a design');
@endphp
<x-layouts.dashboard :title="$title" area="studio">
    <x-ui.page-header :title="$title" :description="__('Choose the print area to design for. The artboard is drawn at its real printed size, so text and shapes print crisply and photo resolution is checked as you work.')" />

    <form method="post" action="{{ $artwork ? route('studio.artworks.design.start', $artwork) : route('studio.design.store') }}" class="grid gap-8 lg:grid-cols-[1fr_380px]" x-data="{ area: '{{ old('print_area_id', $areas->first()?->id) }}' }">
        @csrf
        <div class="card p-6">
            <h2 class="font-semibold">{{ __('Artboard') }}</h2>
            <p class="mt-1 text-sm text-muted">{{ __('You can still place the finished design on every other product afterwards.') }}</p>
            <div class="mt-5 grid gap-6 sm:grid-cols-2">
                @foreach ($byProduct as $productName => $productAreas)
                    <fieldset>
                        <legend class="text-xs font-semibold uppercase tracking-wide text-subtle">{{ $productName }}</legend>
                        <div class="mt-2 grid gap-2">
                            @foreach ($productAreas as $area)
                                @php $longest = max($area->width_mm, $area->height_mm); @endphp
                                <label class="flex cursor-pointer items-center gap-4 rounded-xl border p-3 transition"
                                    :class="area == '{{ $area->id }}' ? 'border-brand-600 bg-brand-50 ring-1 ring-brand-600' : 'border-line hover:border-line-strong'">
                                    <input type="radio" name="print_area_id" value="{{ $area->id }}" x-model="area" class="sr-only">
                                    <span class="grid size-12 shrink-0 place-items-center rounded-lg bg-sand">
                                        <span class="block border-2 border-ink/60 bg-white" style="width: {{ round(36 * $area->width_mm / $longest) }}px; height: {{ round(36 * $area->height_mm / $longest) }}px"></span>
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-medium">{{ $area->label }}</span>
                                        <span class="block text-xs text-muted">{{ __(':w × :h mm', ['w' => $area->width_mm, 'h' => $area->height_mm]) }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach
            </div>
            @error('print_area_id')<p class="mt-3 text-sm font-medium text-danger-700">{{ $message }}</p>@enderror
        </div>

        <div class="space-y-5">
            <div class="card space-y-5 p-6">
                @if ($artwork)
                    <p class="text-sm text-muted">{{ __('Your current file becomes the first layer. The original stays untouched; exporting from the studio adds a new file version.') }}</p>
                @else
                    <x-form.input name="title" :label="__('Title')" required maxlength="120" />
                    <x-form.select name="category_id" :label="__('Category')" :options="$categories->mapWithKeys(fn ($c) => [$c->id => $c->translate('name')])" :placeholder="__('None')" />
                    <x-form.checkbox name="rights_confirmed" :label="__('I own or have licensed everything I will add to this design')" />
                    @error('rights_confirmed')<p class="text-sm font-medium text-danger-700">{{ $message }}</p>@enderror
                @endif
                <x-ui.button variant="accent" class="w-full"><x-ui.icon name="pencil" class="size-4" /> {{ __('Open Design Studio') }}</x-ui.button>
            </div>
        </div>
    </form>
</x-layouts.dashboard>
