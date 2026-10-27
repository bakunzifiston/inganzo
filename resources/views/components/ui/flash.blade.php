@php
    $messages = array_filter([
        'success' => session('success'),
        'error' => session('error'),
        'info' => session('status') ?? session('info'),
    ]);
@endphp
@if ($messages || $errors->any())
    <div class="space-y-3" role="status" aria-live="polite">
        @foreach ($messages as $type => $message)
            <div x-data="{ open: true }" x-show="open" @class([
                'flex items-start gap-3 rounded-xl border px-4 py-3 text-sm',
                'border-success-600/20 bg-success-50 text-success-700' => $type === 'success',
                'border-danger-700/20 bg-danger-50 text-danger-700' => $type === 'error',
                'border-info-700/20 bg-info-50 text-info-700' => $type === 'info',
            ])>
                <x-ui.icon :name="$type === 'success' ? 'check-circle' : ($type === 'error' ? 'alert' : 'info')" class="mt-0.5 size-4 shrink-0" />
                <p class="flex-1">{{ $message }}</p>
                <button type="button" class="opacity-60 hover:opacity-100" @click="open = false" aria-label="{{ __('Dismiss') }}">&times;</button>
            </div>
        @endforeach
        @if ($errors->any() && ! $errors->hasAny(array_keys(request()->except(['_token', '_method']))))
            <div class="rounded-xl border border-danger-700/20 bg-danger-50 px-4 py-3 text-sm text-danger-700">
                <ul class="list-inside list-disc space-y-0.5">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif
    </div>
@endif
