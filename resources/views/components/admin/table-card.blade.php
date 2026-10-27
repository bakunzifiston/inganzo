@props(['empty' => null, 'rows' => null])
<div {{ $attributes->class('card overflow-hidden') }}>
    @isset($toolbar)<div class="flex flex-wrap items-end gap-3 border-b border-line p-4">{{ $toolbar }}</div>@endisset
    <div class="overflow-x-auto">
        <table class="table-base">{{ $slot }}</table>
    </div>
    @if ($rows !== null && $rows->isEmpty())
        <p class="px-5 py-10 text-center text-sm text-muted">{{ $empty ?? __('Nothing to show.') }}</p>
    @endif
    @if ($rows instanceof \Illuminate\Contracts\Pagination\Paginator && $rows->hasPages())
        <div class="border-t border-line px-4 py-3">{{ $rows->links() }}</div>
    @endif
</div>
