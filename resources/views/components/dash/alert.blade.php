@props(['tone' => 'info', 'icon' => 'info', 'title', 'href' => null, 'action' => null])
@php
    $tones = [
        'danger' => ['box' => 'border-danger-700/20 bg-danger-50', 'icon' => 'bg-danger-700 text-white', 'text' => 'text-danger-700', 'label' => __('Urgent')],
        'warning' => ['box' => 'border-warning-700/20 bg-warning-50', 'icon' => 'bg-warning-700 text-white', 'text' => 'text-warning-700', 'label' => __('Attention')],
        'info' => ['box' => 'border-info-700/20 bg-info-50', 'icon' => 'bg-info-700 text-white', 'text' => 'text-info-700', 'label' => __('Info')],
        'success' => ['box' => 'border-success-600/25 bg-success-50', 'icon' => 'bg-success-600 text-white', 'text' => 'text-success-700', 'label' => __('All good')],
    ][$tone];
@endphp
<div {{ $attributes->class('flex items-start gap-3 rounded-xl border px-4 py-3 '.$tones['box']) }} role="status">
    <span class="mt-0.5 grid size-7 shrink-0 place-items-center rounded-lg {{ $tones['icon'] }}"><x-ui.icon :name="$icon" class="size-4" /></span>
    <div class="min-w-0 flex-1 text-sm">
        <p class="font-semibold {{ $tones['text'] }}"><span class="sr-only">{{ $tones['label'] }}: </span>{{ $title }}</p>
        @if ($slot->isNotEmpty())<div class="mt-0.5 text-ink-soft">{{ $slot }}</div>@endif
    </div>
    @if ($href)
        <a href="{{ $href }}" class="shrink-0 self-center rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-ink shadow-xs ring-1 ring-line hover:ring-line-strong">{{ $action ?? __('Open') }}</a>
    @endif
</div>
