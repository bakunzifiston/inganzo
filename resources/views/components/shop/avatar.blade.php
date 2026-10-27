@props(['artist'])
@if ($artist->avatarUrl())
    <img src="{{ $artist->avatarUrl() }}" alt="" {{ $attributes->class('shrink-0 rounded-full object-cover ring-1 ring-line') }}>
@else
    @php $hues = ['bg-brand-100 text-brand-700', 'bg-success-100 text-success-700', 'bg-warning-100 text-warning-700', 'bg-info-50 text-info-700']; @endphp
    <span {{ $attributes->class('grid shrink-0 place-items-center rounded-full font-display text-lg font-bold '.$hues[$artist->id % 4]) }} aria-hidden="true">{{ $artist->initials() }}</span>
@endif
