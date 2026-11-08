<?php
namespace App\Support;

/** Hours when the AI answers customers by itself (Asia/Dhaka). Outside them the chat is left to staff. Default: 00:00-10:00 and 21:00-24:00. */
class AiSchedule
{
  const DEF = ['on'=>true, 'w'=>[['00:00', '10:00'], ['21:00', '24:00']]];
  public static function get(): array {
    $j = json_decode((string)Sms::secret('ai_schedule'), true);
    return is_array($j) && isset($j['w']) ? $j + ['on'=>true] : self::DEF;
  }
  private static function mins(string $t): int { [$h, $m] = array_pad(explode(':', $t), 2, 0); return min(1440, max(0, (int)$h * 60 + (int)$m)); }
  public static function activeNow(): bool {
    try {
      $c = self::get(); if (empty($c['on'])) return true;
      $n = now('Asia/Dhaka'); $x = (int)$n->format('G') * 60 + (int)$n->format('i');
      foreach ((array)$c['w'] as $w) { $a = self::mins((string)($w[0] ?? '')); $b = self::mins((string)($w[1] ?? '')); if ($a === $b) continue; if ($a < $b ? ($x >= $a && $x < $b) : ($x >= $a || $x < $b)) return true; }
      return false;
    } catch (\Throwable $e) { return true; }
  }
}
