@php($s = app(\Gcego\Sentinel\Presets\SentinelStyles::class))
<section aria-labelledby="sentinel-sessions-title" class="{{ $s('section') }}">
    <x-gcego-sentinel::section-header id="sentinel-sessions-title" icon="desktop" :title="__('gcego-sentinel::messages.sessions.heading')" :description="__('gcego-sentinel::messages.sessions.description')" />
    <div class="{{ $s('section.body') }}">
        @if (! $supported)
            <x-gcego-sentinel::status tone="info" :message="__('gcego-sentinel::messages.sessions.unsupported')" />
        @elseif ($sessions !== [])
            <ul class="{{ $s('sessions') }}" aria-label="{{ __('gcego-sentinel::messages.sessions.list') }}">
                @foreach ($sessions as $session)
                    <li class="{{ $s('session') }}" wire:key="session-{{ $session->id }}">
                        <span class="{{ $session->current ? $s('session.icon', 'session.icon.current') : $s('session.icon') }}"><x-gcego-sentinel::icon :name="$session->agent->mobile ? 'mobile' : 'desktop'" class="size-5" /></span>
                        <div class="{{ $s('session.text') }}">
                            <span class="{{ $s('session.name') }}">
                                <span class="truncate">{{ __('gcego-sentinel::messages.sessions.device', ['browser' => $session->agent->browser ?? __('gcego-sentinel::messages.sessions.unknown_browser'), 'platform' => $session->agent->platform ?? __('gcego-sentinel::messages.sessions.unknown_platform')]) }}</span>
                                @if ($session->current)
                                    <x-gcego-sentinel::badge tone="success">{{ __('gcego-sentinel::messages.sessions.current') }}</x-gcego-sentinel::badge>
                                @endif
                            </span>
                            <span class="{{ $s('session.meta') }}">
                                {{ $session->ipAddress ?? __('gcego-sentinel::messages.sessions.unknown_ip') }}
                                @unless ($session->current)
                                    · {{ __('gcego-sentinel::messages.sessions.last_active', ['time' => \Illuminate\Support\Carbon::instance($session->lastActive)->locale(app()->getLocale())->diffForHumans()]) }}
                                @endunless
                            </span>
                        </div>
                        @unless ($session->current)
                            <x-gcego-sentinel::button type="button" variant="ghost" :block="false" :small="true" wire:click="startConfirmingPassword('revokeSession', '{{ $session->id }}')" :aria-label="__('gcego-sentinel::messages.sessions.revoke_label', ['device' => ($session->agent->browser ?? '').' '.($session->agent->platform ?? '')])">{{ __('gcego-sentinel::messages.sessions.revoke') }}</x-gcego-sentinel::button>
                        @endunless
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($confirmingPassword)
            @include('gcego-sentinel::livewire.partials.confirm-password', ['id' => 'sentinel-sessions-confirm'])
        @endif

        <form wire:submit="logoutOtherBrowserSessions" class="flex flex-col gap-4 border-t border-gcego-border pt-5">
            <p class="text-sm text-gcego-text">{{ __('gcego-sentinel::messages.sessions.logout_text') }}</p>
            <div class="sm:max-w-sm">
                <x-gcego-sentinel::password name="password" id="sentinel-sessions-password" :label="__('gcego-sentinel::messages.fields.password')" model="password" autocomplete="current-password" />
            </div>
            <div class="{{ $s('actions.row') }}">
                <div role="status" class="text-sm">
                    @if ($signedOut)
                        <span class="{{ $s('saved') }}">{{ __('gcego-sentinel::messages.sessions.done') }}</span>
                    @endif
                </div>
                <x-gcego-sentinel::button :block="false" variant="secondary" icon="logout" loading="logoutOtherBrowserSessions" wire:loading.attr="disabled">{{ __('gcego-sentinel::messages.sessions.logout') }}</x-gcego-sentinel::button>
            </div>
        </form>

        @if (\Gcego\Sentinel\Support\Routes::has('logout'))
            <form method="POST" action="{{ route('logout') }}" data-sentinel-form class="group flex flex-col gap-3 border-t border-gcego-border pt-5">
                @csrf
                <input type="hidden" name="everywhere" value="1">
                <p class="text-sm text-gcego-text">{{ __('gcego-sentinel::messages.sessions.everywhere_text') }}</p>
                <div>
                    <x-gcego-sentinel::button variant="danger" :block="false" icon="logout">{{ __('gcego-sentinel::messages.sessions.everywhere') }}</x-gcego-sentinel::button>
                </div>
            </form>
        @endif
    </div>
</section>
