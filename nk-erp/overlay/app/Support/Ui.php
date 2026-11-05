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

    public static function lakh($n): string
    {
        return abs((float) $n) >= 100000 ? '৳'.number_format($n / 100000, 2).' Lakh' : self::tk($n);
    }

    public static function bn($s): string
    {
        return strtr((string) $s, ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯']);
    }
}
