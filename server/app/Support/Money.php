<?php

namespace App\Support;

class Money
{
    /** Paise (int) → "₹1,23,456.50" using Indian digit grouping. */
    public static function fmt(int|float|string|null $paise, bool $isRupees = false): string
    {
        $value = $paise === null ? 0 : (float) $paise;
        $rupees = $isRupees ? $value : $value / 100;
        $neg = $rupees < 0;
        $rupees = abs($rupees);
        $whole = (int) floor($rupees);
        $dec = (int) round(($rupees - $whole) * 100);
        if ($dec === 100) {
            $whole++;
            $dec = 0;
        }
        $s = (string) $whole;
        if (strlen($s) > 3) {
            $last3 = substr($s, -3);
            $rest = substr($s, 0, -3);
            $s = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest) . ',' . $last3;
        }

        return ($neg ? '-' : '') . '₹' . $s . ($dec ? '.' . str_pad((string) $dec, 2, '0', STR_PAD_LEFT) : '');
    }

    public static function rupees(int $paise): float
    {
        return round($paise / 100, 2);
    }
}
