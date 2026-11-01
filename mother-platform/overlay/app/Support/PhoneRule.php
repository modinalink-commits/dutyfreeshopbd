<?php
namespace App\Support;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Bangladesh customer mobile: exactly 11 digits, starts with 01 (^01[0-9]{9}$). No +88 / 88 conversion. */
class PhoneRule implements ValidationRule
{
  public static function error(?string $v): ?string {
    $v = trim((string)$v);
    if ($v === '' || !preg_match('/^[0-9]+$/', $v)) return 'Only numbers are allowed.';
    if (!str_starts_with($v, '01')) return 'Mobile number must start with 01.';
    if (strlen($v) !== 11) return 'Please enter a valid 11-digit mobile number.';
    return null;
  }
  public function validate(string $attribute, mixed $value, Closure $fail): void { if ($e = self::error(is_scalar($value) ? (string)$value : '')) $fail($e); }
  /** Trim accidental spaces around the number before validating. */
  public static function trim(\Illuminate\Http\Request $r, string $key = 'phone'): void { if ($r->has($key) && is_string($r->input($key))) $r->merge([$key => trim($r->input($key))]); }
}
