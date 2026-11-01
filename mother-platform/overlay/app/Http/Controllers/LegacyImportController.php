<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Temporary token-protected intake for the old DutyFreeBD customer data (staging table only; nothing touches live customers). */
class LegacyImportController extends Controller
{
  private function ok(Request $r): bool { $t = (string)DB::table('settings')->where('key', 'legacy_import_token')->value('value'); return $t !== '' && hash_equals($t, (string)$r->query('k')); }
  public static function phone(?string $p): ?string { $s = preg_replace('/\D/', '', (string)$p); if (str_starts_with($s, '880')) $s = substr($s, 2); return preg_match('/^01[3-9]\d{8}$/', $s) ? $s : null; }
  public function ingest(Request $r) {
    if (!$this->ok($r)) abort(404);
    $rows = json_decode((string)$r->input('rows'), true); if (!is_array($rows)) return response()->json(['ok' => false]);
    $n = 0; $bad = 0;
    foreach ($rows as $x) {
      $p = self::phone($x['phone'] ?? ''); if (!$p) { $bad++; continue; }
      $amt = (float)preg_replace('/[^\d.]/', '', (string)($x['amount'] ?? '0'));
      DB::table('legacy_customers')->updateOrInsert(['phone' => $p], [
        'name' => mb_substr(trim((string)($x['name'] ?? '')), 0, 160) ?: null, 'email' => mb_substr(trim((string)($x['email'] ?? '')), 0, 160) ?: null,
        'address' => trim((string)($x['address'] ?? '')) ?: null, 'tier' => mb_substr(trim((string)($x['tier'] ?? '')), 0, 40) ?: null, 'ip_status' => mb_substr(trim((string)($x['ip'] ?? '')), 0, 40) ?: null,
        'orders_count' => (int)preg_replace('/\D/', '', (string)($x['orders'] ?? '0')), 'orders_amount' => $amt, 'last_order' => mb_substr(trim((string)($x['last'] ?? '')), 0, 40) ?: null,
        'comment' => trim((string)($x['comment'] ?? '')) ?: null, 'src_page' => (int)($x['page'] ?? 0), 'updated_at' => now(), 'created_at' => now()]);
      $n++;
    }
    return response()->json(['ok' => true, 'saved' => $n, 'bad' => $bad]);
  }
  public function stats(Request $r) {
    if (!$this->ok($r)) abort(404);
    return response()->json(['rows' => DB::table('legacy_customers')->count(), 'pages' => DB::table('legacy_customers')->distinct()->count('src_page'), 'maxpage' => DB::table('legacy_customers')->max('src_page')]);
  }
}
