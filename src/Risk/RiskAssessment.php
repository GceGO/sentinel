<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Risk;

/**
 * The risk signals of one completed sign-in: hints for notifying and
 * auditing, never grounds for refusing a correct sign-in.
 */
final readonly class RiskAssessment
{
    public const string Low = 'low';

    public const string Elevated = 'elevated';

    public const string High = 'high';

    /**
     * @param  list<string>  $signals  "new_browser", "recent_failures"...
     */
    public function __construct(public array $signals = []) {}

    public function level(): string
    {
        return match (true) {
            count($this->signals) >= 2 => self::High,
            count($this->signals) === 1 => self::Elevated,
            default => self::Low,
        };
    }

    public function has(string $signal): bool
    {
        return in_array($signal, $this->signals, true);
    }
}
