@php
    $s = app(\Gcego\Sentinel\Presets\SentinelStyles::class);
    $sent = session('status') === 'verification-link-sent';
    $email = data_get(request()->user(), 'email');
@endphp
<x-gcego-sentinel::page :context="$context">
    <x-gcego-sentinel::header icon="mail" :title="__('gcego-sentinel::messages.verify.heading')" :description="is_string($email) ? __('gcego-sentinel::messages.verify.description', ['email' => $email]) : __('gcego-sentinel::messages.verify.description_generic')" />

    @if ($sent)
        <x-gcego-sentinel::status class="mb-5" :message="__('gcego-sentinel::messages.status.verification-link-sent')" />
    @endif

    <x-gcego-sentinel::errors />

    <div class="{{ $s('actions') }}">
        @if (($resend = $context->url('verification.send')) !== null)
            <form method="POST" action="{{ $resend }}" data-sentinel-form class="group">
                @csrf
                <x-gcego-sentinel::button>{{ __('gcego-sentinel::messages.verify.resend') }}</x-gcego-sentinel::button>
            </form>
        @endif
        @if (($logout = $context->url('logout')) !== null)
            <form method="POST" action="{{ $logout }}" data-sentinel-form class="group">
                @csrf
                @foreach ($context->logoutFields() as $field => $value)
                    <input type="hidden" name="{{ $field }}" value="{{ $value }}">
                @endforeach
                <x-gcego-sentinel::button variant="ghost">{{ __('gcego-sentinel::messages.logout') }}</x-gcego-sentinel::button>
            </form>
        @endif
    </div>

    <p class="{{ $s('help') }} mt-6">{{ __('gcego-sentinel::messages.verify.help') }}</p>
</x-gcego-sentinel::page>
