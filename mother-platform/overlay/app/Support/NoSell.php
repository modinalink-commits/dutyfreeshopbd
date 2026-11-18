<?php
namespace App\Support;

/**
 * Owner 18 Nov 2026: gift cards and gift vouchers are never sourced or sold. All existing ones were deleted;
 * every importer asks here first so a later import cannot bring them back.
 * "Gift Set" (perfume and other real products) is NOT blocked.
 */
class NoSell {
  public static function blocked(?string $name): bool {
    $n = (string)$name;
    return (bool)preg_match('/gift\s*-?\s*(card|voucher)|\bvoucher\b|black\s+society\s+card/iu', $n);
  }
}
