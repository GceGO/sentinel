@props(['bag' => null, 'inline' => []])
@php
    $s = app(\Gcego\Sentinel\Presets\SentinelStyles::class);
    $bag ??= $errors ?? new \Illuminate\Support\ViewErrorBag;
    $messages = array_values(array_unique($bag->all()));
    // Messages already shown next to their field (aria-describedby) are not repeated here.
    $inlineMessages = array_values(array_unique(array_merge([], ...array_map(static fn (string $field): array => $bag->get($field), $inline))));
    $others = array_values(array_diff($messages, $inlineMessages));
    $invalidFields = count(array_filter($inline, static fn (string $field): bool => $bag->has($field)));
@endphp
@if ($messages !== [])
    <div role="alert" tabindex="-1" data-sentinel-error-summary aria-labelledby="sentinel-error-summary-title" class="{{ $s('alert') }} {{ $s('alert.danger') }} focus:outline-none">
        <x-gcego-sentinel::icon name="alert" :class="$s('alert.icon').' '.$s('alert.icon.danger')" />
        <div class="min-w-0 flex-1">
            <p id="sentinel-error-summary-title" class="font-medium">{{ trans_choice('gcego-sentinel::messages.errors.summary', $others === [] ? $invalidFields : count($messages)) }}</p>
            @if ($others === [])
                <p>{{ trans_choice('gcego-sentinel::messages.errors.check_fields', $invalidFields) }}</p>
            @elseif (count($others) > 1)
                <ul class="mt-1 list-disc ps-5">
                    @foreach ($others as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            @else
                <p>{{ $others[0] }}</p>
            @endif
        </div>
    </div>
@endif
