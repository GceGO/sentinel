@props(['name', 'class' => 'size-5'])
@if ($name === 'spinner')
    <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity="0.25" stroke-width="3"></circle><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path></svg>
@else
    <x-gcego-icon :name="\Gcego\Sentinel\Support\Icons::name($name)" size="none" decorative :attributes="$attributes->merge(['class' => $class])" />
@endif
