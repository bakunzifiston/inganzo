<x-layouts.dashboard :title="__('Notifications')" :area="$area">
    <x-ui.page-header :title="__('Notifications')">
        <x-slot:actions>
            @if (auth()->user()->unreadNotifications()->exists())
                <form method="post" action="{{ route('account.notifications.read') }}">@csrf<x-ui.button variant="secondary" size="sm">{{ __('Mark all as read') }}</x-ui.button></form>
            @endif
        </x-slot:actions>
    </x-ui.page-header>
    @if ($notifications->isEmpty())
        <x-ui.empty icon="bell" :title="__('You’re all caught up')" :description="__('Order, payout and design updates will appear here.')" />
    @else
        <ul class="card divide-y divide-line">
            @foreach ($notifications as $notification)
                <li class="flex gap-4 px-5 py-4 {{ $notification->read_at ? '' : 'bg-brand-50/40' }}">
                    <span @class(['mt-1.5 size-2 shrink-0 rounded-full', 'bg-brand-600' => ! $notification->read_at, 'bg-transparent' => $notification->read_at])></span>
                    <div class="flex-1 text-sm">
                        <p class="font-semibold">{{ $notification->data['title'] ?? __('Update') }}</p>
                        <p class="text-muted">{{ $notification->data['body'] ?? '' }}</p>
                        <p class="mt-1 text-xs text-subtle">{{ $notification->created_at->diffForHumans() }}</p>
                    </div>
                    @if (! empty($notification->data['url']))<a href="{{ $notification->data['url'] }}" class="link self-center text-xs">{{ __('View') }}</a>@endif
                </li>
            @endforeach
        </ul>
        <div class="mt-6">{{ $notifications->links() }}</div>
    @endif
</x-layouts.dashboard>
