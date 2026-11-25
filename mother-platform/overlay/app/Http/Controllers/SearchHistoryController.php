<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Admin search memory: every staff member's own searches are kept quietly and offered back as suggestions when they type again. Never shared between staff. */
class SearchHistoryController extends Controller {
  private function clean($v): string { return mb_substr(trim(preg_replace('/\s+/u', ' ', (string)$v)), 0, 80); }
  public function suggest(Request $r) {
    $q = $this->clean($r->query('q')); $uid = (int)auth()->id();
    if ($uid < 1 || mb_strlen($q) < 2) return response()->json([]);
    $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q).'%';
    $rows = DB::table('staff_search_history')->where('user_id', $uid)->where('q', 'ilike', $like)->where('q', '<>', $q)->orderByDesc('last_at')->limit(8)->get(['q', 'hits', 'last_at']);
    $phones = $rows->pluck('q')->filter(fn($x) => preg_match('/^01\d{9}$/', $x))->all();
    $names = [];
    if ($phones) foreach (DB::table('orders')->whereIn('phone', $phones)->orderBy('id')->get(['phone', 'customer_name']) as $o) $names[$o->phone] = $o->customer_name;
    return response()->json($rows->map(fn($x) => ['q' => $x->q, 'n' => $names[$x->q] ?? null, 'h' => (int)$x->hits])->values());
  }
  public function record(Request $r) {
    $q = $this->clean($r->input('q')); $uid = (int)auth()->id();
    if ($uid < 1 || mb_strlen($q) < 3) return response()->json(['ok' => false]);
    DB::statement('insert into staff_search_history (user_id, q, hits, last_at) values (?, ?, 1, now()) on conflict (user_id, q) do update set hits = staff_search_history.hits + 1, last_at = now()', [$uid, $q]);
    if (random_int(1, 40) === 1) DB::table('staff_search_history')->where('user_id', $uid)->where('last_at', '<', now()->subDays(90))->delete(); // keep 90 days only
    return response()->json(['ok' => true]);
  }
}
