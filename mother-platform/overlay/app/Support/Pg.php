<?php
namespace App\Support;
/** Page size chosen with the global pagination 'Show: 24 | 50 | 100' selector. */
class Pg {
  public static function per(int $default = 24): int { $p = (int)request('per', 0); return in_array($p, [24, 50, 100], true) ? $p : $default; }
}
