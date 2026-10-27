<x-layouts.dashboard :title="__('Copyright report')" area="admin">
    <x-ui.page-header :eyebrow="__('Copyright report')" :title="$report->artwork->title" :description="__('Reported by :name (:email) on behalf of :holder', ['name' => $report->reporter_name, 'email' => $report->reporter_email, 'holder' => $report->rights_holder])">
        <x-slot:actions><x-ui.badge :status="$report->status" /></x-slot:actions>
    </x-ui.page-header>
    <div class="grid gap-6 lg:grid-cols-[1fr_360px]">
        <x-ui.card :title="__('Claim')">
            <p class="whitespace-pre-line text-sm">{{ $report->claim_details }}</p>
            @if ($report->original_work_url)<p class="mt-4 text-sm">{{ __('Original work') }}: <a href="{{ $report->original_work_url }}" target="_blank" rel="noopener nofollow" class="link">{{ $report->original_work_url }}</a></p>@endif
            <p class="mt-2 text-xs text-muted">{{ $report->good_faith_confirmed ? __('Good-faith statement confirmed.') : '' }}</p>
            <div class="mt-6 grid gap-4 sm:grid-cols-[200px_1fr]">
                <div class="aspect-square rounded-xl bg-sand p-3">@if ($report->artwork->currentVersion?->isReady())<img src="{{ $report->artwork->currentVersion->previewUrl() }}" class="h-full w-full object-contain" alt="">@endif</div>
                <div class="text-sm">
                    <p>{{ __('Artist') }}: <a href="{{ route('admin.artists.show', $report->artwork->artist) }}" class="link">{{ $report->artwork->artist->display_name }}</a></p>
                    <p class="mt-1">{{ __('Design status') }}: <x-ui.badge :status="$report->artwork->status" /></p>
                    <a href="{{ route('admin.moderation.show', $report->artwork) }}" class="link mt-2 inline-block">{{ __('Open moderation view') }}</a>
                </div>
            </div>
        </x-ui.card>
        <x-ui.card :title="__('Resolution')">
            @if (in_array($report->status->value, ['upheld', 'dismissed'], true))
                <p class="text-sm">{{ $report->resolution_notes }}</p>
                <p class="mt-2 text-xs text-muted">{{ $report->resolver?->name }} · {{ $report->resolved_at?->translatedFormat('j M Y H:i') }}</p>
            @else
                <form method="post" action="{{ route('admin.copyright.resolve', $report) }}" class="space-y-3">
                    @csrf
                    <x-form.select name="decision" :label="__('Decision')" :options="['upheld' => __('Uphold — take the design down'), 'dismissed' => __('Dismiss — no infringement found')]" />
                    <x-form.textarea name="notes" :label="__('Resolution notes')" rows="4" required />
                    <x-ui.button class="w-full">{{ __('Resolve report') }}</x-ui.button>
                </form>
            @endif
        </x-ui.card>
    </div>
</x-layouts.dashboard>
