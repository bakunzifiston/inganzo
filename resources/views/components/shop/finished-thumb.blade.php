@props(['product' => null])
@php $cover = $product?->coverImage(); @endphp
@if ($cover)
    <img src="{{ $cover->thumbnailUrl() }}" alt="{{ $product->title }}" loading="lazy" {{ $attributes->class('object-cover') }}>
@else
    <div {{ $attributes->class('grid place-items-center text-subtle') }}><x-ui.icon name="package" class="size-6" /></div>
@endif
