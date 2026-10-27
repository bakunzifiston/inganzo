@props(['title', 'description' => null, 'icon' => 'sparkles'])
<div {{ $attributes->class('flex flex-col items-center justify-center rounded-[var(--radius-card)] border border-dashed border-line-strong bg-white/60 px-6 py-14 text-center') }}>
    <div class="mb-4 grid size-12 place-items-center rounded-full bg-brand-50 text-brand-700">
        <x-ui.icon :name="$icon" class="size-6" />
    </div>
    <h3 class="text-base font-semibold">{{ $title }}</h3>
    @if ($description)<p class="mt-1 max-w-sm text-sm text-muted">{{ $description }}</p>@endif
    @if ($slot->isNotEmpty())<div class="mt-5 flex flex-wrap justify-center gap-3">{{ $slot }}</div>@endif
</div>
