<?php

namespace App\Support;

/**
 * Order lifecycle. The stage list itself lives in shared/config.pkl (config('shared.orders.stages')).
 */
class OrderStatus
{
    public const CANCELLED = 'cancelled';
    public const RETURNED = 'returned';

    /** @return list<string> ordered pipeline keys */
    public static function pipeline(): array
    {
        return array_column(config('shared.orders.stages'), 'key');
    }

    public static function label(string $status): string
    {
        foreach (config('shared.orders.stages') as $s) {
            if ($s['key'] === $status) {
                return $s['label'];
            }
        }

        return match ($status) {
            self::CANCELLED => 'Cancelled',
            self::RETURNED => 'Returned',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    public static function next(string $status): ?string
    {
        $p = self::pipeline();
        $i = array_search($status, $p, true);

        return ($i === false || $i === count($p) - 1) ? null : $p[$i + 1];
    }

    public static function isFinal(string $status): bool
    {
        return in_array($status, ['delivered', self::CANCELLED, self::RETURNED], true);
    }

    /** Tone used by UI badges. */
    public static function tone(string $status): string
    {
        return match ($status) {
            'delivered' => 'success',
            self::CANCELLED, self::RETURNED => 'error',
            'placed' => 'neutral',
            'out_for_delivery', 'dispatched' => 'gold',
            default => 'primary',
        };
    }
}
