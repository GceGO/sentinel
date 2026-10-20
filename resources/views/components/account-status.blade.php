@props(['state'])
@if ($state === \Gcego\Sentinel\Accounts\AccountState::Active)
    <x-gcego-sentinel::badge tone="success">{{ __('gcego-sentinel::messages.account.states.active') }}</x-gcego-sentinel::badge>
@else
    <x-gcego-sentinel::badge tone="danger">{{ __('gcego-sentinel::messages.account.states.'.$state->value) }}</x-gcego-sentinel::badge>
@endif
