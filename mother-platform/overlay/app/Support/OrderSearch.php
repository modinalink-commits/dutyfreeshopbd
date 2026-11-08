<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
/** Orders search (owner 8 Nov 2026): one box finds an order by invoice number, phone, customer name, address, district, order date, email, note, courier tracking. Several words = all of them must match (any field). Bengali digits work. */
class OrderSearch {
  private static function ascii(string $s): string { return strtr($s, ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9']); }
  private static function mon(string $w): ?int { $i = array_search(substr(strtolower($w), 0, 3), ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'], true); return $i === false ? null : $i + 1; }
  /** [from,to] UTC range of a Dhaka day, or null when the token is not a date. */
  private static function day(string $t): ?array {
    $tz = 'Asia/Dhaka'; $d = null; $l = strtolower($t);
    try {
      if ($l === 'today' || $l === 'আজ') $d = now($tz); elseif ($l === 'yesterday' || $l === 'গতকাল') $d = now($tz)->subDay();
      elseif (preg_match('/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})$/', $t, $m)) $d = \Carbon\Carbon::create((int)$m[1], (int)$m[2], (int)$m[3], 0, 0, 0, $tz);
      elseif (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})$/', $t, $m)) $d = \Carbon\Carbon::create((int)$m[3], (int)$m[2], (int)$m[1], 0, 0, 0, $tz);
      elseif (preg_match('/^(\d{1,2})[-\/. ]?([a-z]{3,9})[-\/. ]?(\d{4})?$/i', $t, $m) && ($mo = self::mon($m[2]))) $d = \Carbon\Carbon::create((int)(($m[3] ?? null) ?: now($tz)->year), (int)$mo, (int)$m[1], 0, 0, 0, $tz);
      elseif (preg_match('/^([a-z]{3,9})[-\/. ]?(\d{1,2})(?:[-\/. ,]+(\d{4}))?$/i', $t, $m) && ($mo = self::mon($m[1]))) $d = \Carbon\Carbon::create((int)($m[3] ?? now($tz)->year), (int)$mo, (int)$m[2], 0, 0, 0, $tz);
    } catch (\Throwable $e) { $d = null; }
    return $d ? [$d->copy()->startOfDay()->utc(), $d->copy()->endOfDay()->utc()] : null;
  }
  public static function apply($q, string $raw): void {
    $raw = trim(preg_replace('/\s+/u', ' ', self::ascii($raw))); if ($raw === '') return;
    $tokens = array_slice(array_values(array_filter(preg_split('/[\s,;]+/u', $raw), fn($x) => $x !== '')), 0, 8);
    // month name written as a separate word ("8 Nov 2026"): join day + month (+ year) into one date token
    $j = []; for ($i = 0; $i < count($tokens); $i++) { $t = $tokens[$i];
      if (preg_match('/^\d{1,2}$/', $t) && isset($tokens[$i + 1]) && preg_match('/^[a-z]{3,9}$/i', $tokens[$i + 1]) && self::mon($tokens[$i + 1])) { $t .= ' ' . $tokens[$i + 1]; $i++; if (isset($tokens[$i + 1]) && preg_match('/^\d{4}$/', $tokens[$i + 1])) { $t .= ' ' . $tokens[$i + 1]; $i++; } }
      elseif (preg_match('/^[a-z]{3,9}$/i', $t) && self::mon($t) && isset($tokens[$i + 1]) && preg_match('/^\d{1,2}$/', $tokens[$i + 1])) { $t .= ' ' . $tokens[$i + 1]; $i++; if (isset($tokens[$i + 1]) && preg_match('/^\d{4}$/', $tokens[$i + 1])) { $t .= ' ' . $tokens[$i + 1]; $i++; } }
      $j[] = $t; }
    foreach ($j as $t) {
      if ($r = self::day($t)) { $q->where('created_at', '>=', $r[0])->where('created_at', '<=', $r[1]); continue; }
      $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $t) . '%'; $dg = preg_replace('/\D/', '', $t); if (str_starts_with($dg, '880')) $dg = substr($dg, 2);
      $inv = Inv::candidates($t);
      $q->where(function ($w) use ($t, $like, $dg, $inv) {
        $w->where('invoice', 'ilike', $like);
        if ($inv && preg_match('/^(INV-?)?\d{1,8}$/i', $t)) $w->orWhereIn('invoice', $inv);
        if (strlen($dg) >= 3 && strlen($dg) >= strlen(preg_replace('/[\s\-+()]/', '', $t)) - 3) $w->orWhere('phone', 'like', '%' . $dg . '%');
        if ($dg !== '' && ctype_digit($t) && strlen($t) <= 9) $w->orWhereRaw('cast(id as text) = ?', [$t]);
        $w->orWhere('customer_name', 'ilike', $like)->orWhere('address', 'ilike', $like)->orWhere('district', 'ilike', $like)->orWhere('email', 'ilike', $like)->orWhere('note', 'ilike', $like)->orWhere('consignment_id', 'ilike', $like)->orWhere('tracking_code', 'ilike', $like);
      });
    }
  }
}
