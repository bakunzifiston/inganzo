@props(['icon' => 'info', 'tone' => 'neutral', 'time', 'href' => null, 'last' => false])
@php $tones = ['neutral' => 'bg-sand text-ink-soft', 'clay' => 'bg-brand-100 text-brand-700', 'leaf' => 'bg-success-100 text-success-700', 'gold' => 'bg-warning-100 text-warning-700', 'sky' => 'bg-info-50 text-info-700', 'rose' => 'bg-danger-50 text-danger-700']; @endphp
<li class="relative flex gap-3 pb-4">
    @unless ($last)<span class="absolute top-8 bottom-0 left-[15px] w-px bg-line" aria-hidden="true"></span>@endunless
    <span class="relative grid size-8 shrink-0 place-items-center rounded-full {{ $tones[$tone] ?? $tones['neutral'] }}"><x-ui.icon :name="$icon" class="size-4" /></span>
    <div class="min-w-0 flex-1 pt-1 text-sm">
        @if ($href)<a href="{{ $href }}" class="hover:text-brand-700">{{ $slot }}</a>@else{{ $slot }}@endif
        <p class="text-xs text-subtle"><time datetime="{{ $time->toIso8601String() }}">{{ $time->diffForHumans() }}</time></p>
    </div>
</li>
