@props(['context', 'inverse' => false])
@php
    $s = app(\Gcego\Sentinel\Presets\SentinelStyles::class);
    $branding = $context->branding;
@endphp
<footer class="{{ $inverse ? $s('layout.branded.footer') : $s('layout.footer') }}">
    <div class="{{ $s('layout.footer.inner') }}">
    <div class="{{ $s('layout.footer.meta') }}">
        <p>{{ $branding->footer ?? __('gcego-sentinel::messages.footer.copyright', ['year' => date('Y'), 'name' => $branding->name]) }}</p>
        @if ($branding->legal !== [] || $branding->supportUrl !== null || $branding->supportEmail !== null)
            <nav aria-label="{{ __('gcego-sentinel::messages.footer.label') }}" class="{{ $s('layout.legal') }}">
                @foreach ($branding->legal as $label => $url)
                    <a href="{{ $url }}" class="{{ $s('layout.legal.link') }}">{{ $label }}</a>
                @endforeach
                @if ($branding->supportUrl !== null)
                    <a href="{{ $branding->supportUrl }}" class="{{ $s('layout.legal.link') }}">{{ __('gcego-sentinel::messages.footer.support') }}</a>
                @elseif ($branding->supportEmail !== null)
                    <a href="mailto:{{ $branding->supportEmail }}" class="{{ $s('layout.legal.link') }}">{{ __('gcego-sentinel::messages.footer.support') }}</a>
                @endif
            </nav>
        @endif
    </div>
    <x-gcego-sentinel::appearance />
    </div>
</footer>
