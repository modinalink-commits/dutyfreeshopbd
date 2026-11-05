<?php

namespace App\Support;

/** Small display helpers shared by the route-sales screens. */
class Ui
{
    public const ST = ['Closed' => ['#e3f6ec', '#0f6b3c'], 'Closing pending' => ['#fff1d6', '#8a4b00'], 'Mismatch found' => ['#fde9e7', '#b3261e'], 'Returned' => ['#def5f7', '#0b6670'],
        'In market' => ['#efe7fb', '#5b2fa8'], 'Maintenance' => ['#eef2f9', '#14213d'], 'Waiting approval' => ['#fff1d6', '#8a4b00'], 'Dispatched' => ['#e3f6ec', '#0f6b3c'],
        'Not loading' => ['#eef2f9', '#14213d'], 'Available' => ['#e6eeff', '#1d4ed8'], 'Loading' => ['#fff1d6', '#8a4b00'], 'Not out today' => ['#eef2f9', '#51607a'],
        'Matches' => ['#e3f6ec', '#0f6b3c'], 'Stock OK' => ['#e3f6ec', '#0f6b3c'], 'Verified' => ['#e3f6ec', '#0f6b3c'], 'Not verified' => ['#fff1d6', '#8a4b00']];
    public const C = ['pos' => ['#e3f6ec', '#0f6b3c'], 'neg' => ['#fde9e7', '#b3261e'], 'warn' => ['#fff1d6', '#8a4b00'], 'info' => ['#def5f7', '#0b6670'],
        'vio' => ['#efe7fb', '#5b2fa8'], 'blue' => ['#e6eeff', '#1d4ed8'], 'gray' => ['#eef2f9', '#14213d']];

    public static function pill(string $s): string
    {
        $c = self::ST[$s] ?? ['#fde9e7', '#b3261e'];
        return 'background:'.$c[0].';color:'.$c[1];
    }

    public static function bg(string $c): string { return self::C[$c][0]; }
    public static function ink(string $c): string { return self::C[$c][1]; }

    public static function tk($n): string
    {
        return ((float) $n < 0 ? '−' : '').'৳'.number_format(abs((float) $n));
    }

    /** ৳1,74,200 — South Asian digit grouping. */
    public static function bd($n): string
    {
        $v = (int) round(abs((float) $n));
        $x = (string) $v;
        if (strlen($x) > 3) {
            $last = substr($x, -3);
            $rest = substr($x, 0, -3);
            $x = ltrim(strrev(implode(',', str_split(strrev($rest), 2))), ',').','.$last;
        }
        return ((float) $n < 0 ? '−' : '').'৳'.$x;
    }

    public static function lakh($n): string
    {
        return abs((float) $n) >= 100000 ? '৳'.number_format($n / 100000, 2).' Lakh' : self::tk($n);
    }

    public static function bn($s): string
    {
        return strtr((string) $s, ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯']);
    }

    /** Amount in words, South Asian grouping: "Five thousand two hundred forty taka only." */
    public static function words($n): string
    {
        $n = round((float) $n, 2);
        $tk = (int) floor($n);
        $ps = (int) round(($n - $tk) * 100);
        $w = $tk === 0 ? 'zero' : self::w3($tk);
        $out = $w.' taka'.($ps > 0 ? ' and '.self::w3($ps).' paisa' : '').' only.';
        return ucfirst($out);
    }

    private static function w3(int $n): string
    {
        $ones = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
        $tens = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];
        $two = fn (int $x) => $x < 20 ? $ones[$x] : trim($tens[intdiv($x, 10)].' '.$ones[$x % 10]);
        $parts = [];
        foreach ([[10000000, 'crore'], [100000, 'lakh'], [1000, 'thousand'], [100, 'hundred']] as [$d, $name]) {
            if ($n >= $d) {
                $q = intdiv($n, $d);
                $parts[] = ($q >= 100 ? self::w3($q) : $two($q)).' '.$name;
                $n %= $d;
            }
        }
        if ($n > 0) $parts[] = $two($n);
        return implode(' ', $parts);
    }
}
