<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Audit;

/**
 * IP address minimisation for audit records: "full", "truncated" (IPv4
 * keeps the /24 network, IPv6 the /48) or "none".
 */
final class NetworkAddress
{
    public static function minimise(?string $address, string $mode): ?string
    {
        if ($address === null || $mode === 'none' || filter_var($address, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        if ($mode === 'full') {
            return $address;
        }

        $packed = inet_pton($address);

        if ($packed === false) {
            return null;
        }

        $keep = strlen($packed) === 4 ? 3 : 6;
        $masked = substr($packed, 0, $keep).str_repeat("\0", strlen($packed) - $keep);
        $text = inet_ntop($masked);

        return $text === false ? null : $text;
    }
}
