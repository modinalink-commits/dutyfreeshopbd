<?php

namespace App\Support;

class Icons
{
    public const PATHS = [
        'home' => 'M3 11l9-7 9 7M5 10v10h5v-6h4v6h5V10',
        'cart' => 'M3 4h2l2.4 11h11l2-8H6.2M9 20h.01M17 20h.01',
        'truck' => 'M2 6h11v10H2zM13 10h4l4 4v2h-8M6 19a2 2 0 100-4 2 2 0 000 4zM17 19a2 2 0 100-4 2 2 0 000 4z',
        'users' => 'M9 11a4 4 0 100-8 4 4 0 000 8zM2 21v-1a6 6 0 0112 0v1M16 3.5a4 4 0 010 7.5M22 21v-1a6 6 0 00-4-5.6',
        'cyl' => 'M8 3h8M9 3v3M15 3v3M6 9a3 3 0 013-3h6a3 3 0 013 3v10a2 2 0 01-2 2H8a2 2 0 01-2-2zM6 13h12',
        'book' => 'M4 4h11a3 3 0 013 3v13H7a3 3 0 01-3-3zM4 17a3 3 0 013-3h11',
        'wallet' => 'M3 7h15a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2zM3 7l12-4v4M16 13h.01',
        'globe' => 'M12 21a9 9 0 100-18 9 9 0 000 18zM3 12h18M12 3c2.5 3 2.5 15 0 18M12 3c-2.5 3-2.5 15 0 18',
        'route' => 'M6 19a2 2 0 100-4 2 2 0 000 4zM18 9a2 2 0 100-4 2 2 0 000 4zM6 15V9a4 4 0 014-4h4M18 9v6a4 4 0 01-4 4h-4',
        'load' => 'M12 3v12M7 10l5 5 5-5M4 21h16',
        'ret' => 'M9 14L4 9l5-5M4 9h11a5 5 0 010 10h-3',
        'cash' => 'M2 6h20v12H2zM12 15a3 3 0 100-6 3 3 0 000 6zM6 9v.01M18 15v.01',
        'check' => 'M9 11l3 3 8-8M20 12v7a2 2 0 01-2 2H6a2 2 0 01-2-2V5a2 2 0 012-2h9',
        'chart' => 'M4 20V10M10 20V4M16 20v-7M22 20H2',
        'gear' => 'M12 15a3 3 0 100-6 3 3 0 000 6zM19.4 15a1.7 1.7 0 00.3 1.8l.1.1a2 2 0 11-2.8 2.8l-.1-.1a1.7 1.7 0 00-1.8-.3 1.7 1.7 0 00-1 1.5V21a2 2 0 11-4 0v-.1a1.7 1.7 0 00-1.1-1.5 1.7 1.7 0 00-1.8.3l-.1.1a2 2 0 11-2.8-2.8l.1-.1a1.7 1.7 0 00.3-1.8 1.7 1.7 0 00-1.5-1H3a2 2 0 110-4h.1a1.7 1.7 0 001.5-1.1 1.7 1.7 0 00-.3-1.8l-.1-.1a2 2 0 112.8-2.8l.1.1a1.7 1.7 0 001.8.3H9a1.7 1.7 0 001-1.5V3a2 2 0 114 0v.1a1.7 1.7 0 001 1.5 1.7 1.7 0 001.8-.3l.1-.1a2 2 0 112.8 2.8l-.1.1a1.7 1.7 0 00-.3 1.8V9a1.7 1.7 0 001.5 1H21a2 2 0 110 4h-.1a1.7 1.7 0 00-1.5 1z',
        'sms' => 'M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2zM8 9h8M8 13h5',
    ];

    public const KEYS = [
        'dashboard' => 'home',
        'map' => 'globe',
        'pos' => 'cart',
        'wholesale' => 'truck',
        'customers' => 'users',
        'corders' => 'cart',
        'sales' => 'book',
        'rdash' => 'route',
        'live' => 'route',
        'vehicles' => 'truck',
        'loading' => 'load',
        'return' => 'ret',
        'settle' => 'cash',
        'closing' => 'check',
        'vpl' => 'chart',
        'stock' => 'cyl',
        'brands' => 'cyl',
        'products' => 'cyl',
        'supplier' => 'book',
        'accounts' => 'wallet',
        'exp' => 'wallet',
        'website' => 'globe',
        'hero' => 'globe',
        'erp' => 'book',
        'hr' => 'users',
        'export' => 'load',
        'import' => 'ret',
        'company' => 'gear',
        'users' => 'users',
        'activity' => 'check',
        'sms' => 'sms',
    ];

    public static function svg(string $key): string
    {
        $p = self::PATHS[self::KEYS[$key] ?? 'book'];
        return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="flex-shrink:0"><path d="'.$p.'"></path></svg>';
    }
}
