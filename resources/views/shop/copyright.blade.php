<x-layouts.shop :title="__('Report intellectual property infringement')">
    <div class="container-page max-w-2xl py-14">
        <x-ui.page-header :eyebrow="__('Copyright & trademarks')" :title="__('Report a design')" :description="__('If you believe a design on :app uses your work without permission, tell us. We review every report and remove infringing work.', ['app' => config('app.name')])" />
        <form method="post" action="{{ route('copyright.store') }}" class="card space-y-5 p-6">
            @csrf
            <x-form.input name="artwork_url" :label="__('Link to the design on our site')" :value="$artwork ? route('designs.show', $artwork) : null" required />
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="reporter_name" :label="__('Your name')" required />
                <x-form.input name="reporter_email" type="email" :label="__('Your e-mail')" required />
            </div>
            <x-form.input name="rights_holder" :label="__('Rights holder (person or company)')" required />
            <x-form.input name="original_work_url" type="url" :label="__('Link to your original work (optional)')" />
            <x-form.textarea name="claim_details" :label="__('Describe the infringement')" rows="6" required :hint="__('Explain what is copied and how you hold the rights.')" />
            <x-form.checkbox name="good_faith_confirmed" :label="__('I confirm in good faith that this use is not authorised by the rights holder and that the information above is accurate.')" />
            <x-ui.button>{{ __('Submit report') }}</x-ui.button>
        </form>
    </div>
</x-layouts.shop>
