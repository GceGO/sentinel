@php
    $s = app(\Gcego\Sentinel\Presets\SentinelStyles::class);
    $status = \Gcego\Sentinel\Support\StatusMessages::for(session('status'));
    $initials = mb_strtoupper(mb_substr((string) ($name ?? $email ?? '?'), 0, 1));
    $hasTwoFactor = $context->has(\Gcego\Sentinel\Features\Feature::TwoFactorAuthentication);
@endphp
<x-gcego-sentinel::document :context="$context">
    <header class="{{ $s('account.bar') }}">
        <div class="{{ $s('account.bar.inner') }}">
            <x-gcego-sentinel::brand :context="$context" />
            <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                <x-gcego-sentinel::appearance />
                @if (($logout = $context->url('logout')) !== null)
                    <form method="POST" action="{{ $logout }}" data-sentinel-form class="group">
                        @csrf
                        @foreach ($context->logoutFields() as $field => $value)
                            <input type="hidden" name="{{ $field }}" value="{{ $value }}">
                        @endforeach
                        <x-gcego-sentinel::button variant="ghost" :block="false" :small="true" icon="logout"><span class="max-sm:sr-only">{{ __('gcego-sentinel::messages.logout') }}</span></x-gcego-sentinel::button>
                    </form>
                @endif
            </div>
        </div>
    </header>

    <main id="sentinel-main" tabindex="-1" aria-labelledby="sentinel-heading" class="{{ $s('account') }} focus:outline-none">
        <div class="{{ $s('account.top') }}">
            <div class="{{ $s('account.header') }}">
                <h1 id="sentinel-heading" class="{{ $s('account.title') }}">{{ __('gcego-sentinel::messages.security.heading') }}</h1>
                <p class="{{ $s('description') }}">{{ __('gcego-sentinel::messages.security.description') }}</p>
            </div>
            @if ($continue !== null)
                <a href="{{ $continue }}" class="{{ $s('button', 'button.primary') }}">{{ __('gcego-sentinel::messages.security.continue') }}<x-gcego-sentinel::icon name="arrow-right" class="size-4" /></a>
            @endif
        </div>

        @if ($status === __('gcego-sentinel::messages.status.two-factor-required') || ($twoFactorRequired && ! $twoFactorEnrolled))
            <x-gcego-sentinel::status tone="warning" :message="__('gcego-sentinel::messages.two_factor.required')" />
        @elseif ($status !== null)
            <x-gcego-sentinel::status :message="$status" />
        @endif

        <section aria-labelledby="sentinel-profile-title" class="{{ $s('profile') }}">
            <span class="{{ $s('avatar') }}" aria-hidden="true">{{ $initials }}</span>
            <div class="min-w-0 flex-1">
                <h2 id="sentinel-profile-title" class="{{ $s('profile.name') }}">{{ $name ?? $email }}</h2>
                @if ($email !== null)
                    <p class="{{ $s('profile.email') }}">{{ $email }}</p>
                @endif
            </div>
            <x-gcego-sentinel::account-status :state="$accountState" />
        </section>

        <section aria-label="{{ __('gcego-sentinel::messages.security.overview') }}" class="{{ $s('account.summary') }}">
            <div class="{{ $s('account.stat') }}">
                <span class="{{ $s('account.stat.icon') }}"><x-gcego-sentinel::icon name="mail" class="size-5" /></span>
                <div class="{{ $s('account.stat.body') }}">
                    <span class="{{ $s('account.stat.label') }}">{{ __('gcego-sentinel::messages.security.email') }}</span>
                    @if ($emailVerified === null)
                        <x-gcego-sentinel::badge>{{ __('gcego-sentinel::messages.security.not_required') }}</x-gcego-sentinel::badge>
                    @elseif ($emailVerified)
                        <x-gcego-sentinel::badge tone="success">{{ __('gcego-sentinel::messages.security.verified') }}</x-gcego-sentinel::badge>
                    @else
                        <x-gcego-sentinel::badge tone="warning">{{ __('gcego-sentinel::messages.security.unverified') }}</x-gcego-sentinel::badge>
                    @endif
                </div>
            </div>
            <div class="{{ $s('account.stat') }}">
                <span class="{{ $s('account.stat.icon') }}"><x-gcego-sentinel::icon name="shield" class="size-5" /></span>
                <div class="{{ $s('account.stat.body') }}">
                    <span class="{{ $s('account.stat.label') }}">{{ __('gcego-sentinel::messages.security.two_factor') }}</span>
                    @if (! $hasTwoFactor)
                        <x-gcego-sentinel::badge>{{ __('gcego-sentinel::messages.security.unavailable') }}</x-gcego-sentinel::badge>
                    @elseif ($twoFactorEnrolled)
                        <x-gcego-sentinel::badge tone="success">{{ __('gcego-sentinel::messages.security.enabled') }}</x-gcego-sentinel::badge>
                    @else
                        <x-gcego-sentinel::badge :tone="$twoFactorRequired ? 'danger' : 'warning'">{{ __('gcego-sentinel::messages.security.disabled') }}</x-gcego-sentinel::badge>
                    @endif
                </div>
            </div>
            <div class="{{ $s('account.stat') }}">
                <span class="{{ $s('account.stat.icon') }}"><x-gcego-sentinel::icon name="user" class="size-5" /></span>
                <div class="{{ $s('account.stat.body') }}">
                    <span class="{{ $s('account.stat.label') }}">{{ __('gcego-sentinel::messages.security.account') }}</span>
                    <x-gcego-sentinel::account-status :state="$accountState" />
                </div>
            </div>
        </section>

        @if ($emailVerified === false && $context->has(\Gcego\Sentinel\Features\Feature::EmailVerification) && ($resend = $context->url('verification.send')) !== null)
            <x-gcego-sentinel::status tone="warning">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <span>{{ __('gcego-sentinel::messages.verify.description_generic') }}</span>
                    <form method="POST" action="{{ $resend }}" data-sentinel-form class="group">
                        @csrf
                        <x-gcego-sentinel::button variant="secondary" :block="false" :small="true">{{ __('gcego-sentinel::messages.verify.resend') }}</x-gcego-sentinel::button>
                    </form>
                </div>
            </x-gcego-sentinel::status>
        @endif

        @if ($context->has(\Gcego\Sentinel\Features\Feature::ProfileInformation))
            <livewire:gcego-sentinel.profile-information />
        @endif

        @if ($context->has(\Gcego\Sentinel\Features\Feature::PasswordUpdate))
            <livewire:gcego-sentinel.update-password />
        @endif

        @if ($hasTwoFactor)
            <livewire:gcego-sentinel.two-factor />
        @endif

        @if ($context->has(\Gcego\Sentinel\Features\Feature::Passkeys))
            <livewire:gcego-sentinel.passkeys />
        @endif

        @if (app(\Gcego\Sentinel\SentinelConfig::class)->browserSessions)
            <livewire:gcego-sentinel.browser-sessions />
        @endif

        @if ($activity->isNotEmpty())
            <section aria-labelledby="sentinel-activity-title" class="{{ $s('section') }}">
                <x-gcego-sentinel::section-header id="sentinel-activity-title" icon="clock" :title="__('gcego-sentinel::messages.activity.heading')" :description="__('gcego-sentinel::messages.activity.description')" />
                <ul class="{{ $s('section.body') }} {{ $s('activity') }}" aria-label="{{ __('gcego-sentinel::messages.activity.heading') }}">
                    @foreach ($activity as $entry)
                        @php($key = 'gcego-sentinel::messages.activity.types.'.str_replace('.', '_', $entry->type))
                        <li class="{{ $s('activity.item') }}">
                            <span class="{{ $s('activity.dot', 'activity.dot.'.(in_array($entry->outcome, ['succeeded', 'failed', 'denied'], true) ? $entry->outcome : 'recorded')) }}" aria-hidden="true"></span>
                            <span class="{{ $s('activity.label') }}">{{ \Illuminate\Support\Facades\Lang::has($key) ? __($key) : $entry->type }}</span>
                            <span class="{{ $s('activity.meta') }}">
                                <time datetime="{{ $entry->occurred_at->toAtomString() }}">{{ $entry->occurred_at->locale(app()->getLocale())->diffForHumans() }}</time>
                                @if ($entry->ip_address !== null)
                                    <span class="hidden sm:inline"> · {{ $entry->ip_address }}</span>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section aria-labelledby="sentinel-tips-title" class="flex flex-col gap-3">
            <h2 id="sentinel-tips-title" class="{{ $s('section.title') }}">{{ __('gcego-sentinel::messages.security.tips_title') }}</h2>
            <ul class="{{ $s('tips') }}">
                @foreach (['unique' => 'key', 'two_factor' => 'shield', 'phishing' => 'mail', 'devices' => 'desktop'] as $tip => $icon)
                    <li class="{{ $s('tip') }}"><x-gcego-sentinel::icon :name="$icon" class="mt-0.5 size-5 shrink-0 text-gcego-primary" /><span>{{ __('gcego-sentinel::messages.security.tips.'.$tip) }}</span></li>
                @endforeach
            </ul>
        </section>
    </main>
</x-gcego-sentinel::document>
