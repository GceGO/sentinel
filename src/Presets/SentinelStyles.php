<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Presets;

use Gcego\Chroma\ChromaManager;

/**
 * Class lists for Sentinel's views from the "sentinel.*" presets, merged
 * by gcego/chroma (later conflicting utilities win) and memoized:
 *
 *     <button class="{{ $styles('button', 'button.primary', 'button.block') }}">
 */
final class SentinelStyles
{
    /**
     * @var array<string, string>
     */
    private array $memo = [];

    public function __construct(private readonly ChromaManager $styling) {}

    public function __invoke(string ...$names): string
    {
        $key = implode('|', $names);

        return $this->memo[$key] ??= $this->styling->merge(...array_map(fn (string $name): string => $this->styling->preset('sentinel.'.$name)->toString(), $names));
    }

    public static function register(ChromaManager $styling): void
    {
        foreach (SentinelPresets::Presets as $name => $classes) {
            $styling->definePreset('sentinel.'.$name, $classes);
        }
    }
}
