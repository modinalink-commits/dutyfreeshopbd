<?php
namespace App\Support;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
/** The ONE place that turns a raw Pathao status into "what it means for us". The mapping lives in the pathao_status_map table and is edited from the admin (Courier & Delivery > Pathao Status Map). */
class PathaoStatusMapper {
  const OUTCOMES = ['Partial Return', 'Exchange', 'Regular Return', 'Paid Return'];
  const ACTIONS = [
    'none' => 'No order change (courier tracking only)',
    'partial_pending' => 'Partial Return Pending',
    'exchange_pending' => 'Exchange Pending',
    'return_pending' => 'Return Pending',
  ];
  public static function key(?string $raw): string { return trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower((string)$raw))); }
  private static function all(): array { return Cache::remember('pathao.status_map', 60, fn() => DB::table('pathao_status_map')->get()->keyBy('raw_key')->all()); }
  public static function forget(): void { Cache::forget('pathao.status_map'); }
  /** An existing mapping row (never creates one). */
  public static function peek(?string $raw): ?object { return self::all()[self::key($raw)] ?? null; }
  /** @return array{action:string,outcome:?string,stage:?string,needs:bool,row:?object} An unseen status is recorded and flagged for mapping, never guessed. */
  public static function map(?string $raw): array {
    $k = self::key($raw); if ($k === '') return ['action' => 'none', 'outcome' => null, 'stage' => null, 'needs' => false, 'row' => null];
    $row = self::all()[$k] ?? null;
    if (!$row) {
      DB::table('pathao_status_map')->insertOrIgnore(['raw_key' => $k, 'raw_label' => mb_substr(trim((string)$raw), 0, 160), 'action' => 'none', 'needs_mapping' => true, 'confirmed' => true, 'seen_count' => 0, 'note' => 'New status from Pathao. Choose what it means, then save.', 'created_at' => now(), 'updated_at' => now()]);
      self::forget();
      return ['action' => 'none', 'outcome' => null, 'stage' => null, 'needs' => true, 'row' => null];
    }
    return ['action' => (string)$row->action, 'outcome' => $row->outcome, 'stage' => $row->stage, 'needs' => (bool)$row->needs_mapping, 'row' => $row];
  }
  public static function seen(?string $raw): void { DB::table('pathao_status_map')->where('raw_key', self::key($raw))->update(['seen_count' => DB::raw('seen_count + 1'), 'last_seen_at' => now()]); }
}
