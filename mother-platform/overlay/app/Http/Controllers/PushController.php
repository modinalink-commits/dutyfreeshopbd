<?php
namespace App\Http\Controllers;

use App\Support\Push;
use App\Support\WebPush;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PushController extends Controller
{
  public function key() { return response()->json(['key' => WebPush::publicKey()])->header('Cache-Control', 'no-store'); }

  private function cid(Request $r): ?int { $c = app(AccountController::class)->me($r); return $c ? (int)$c->id : null; }

  public function subscribe(Request $r) {
    $e = (string)$r->input('endpoint'); $k = $r->input('keys', []);
    if (!preg_match('#^https://#', $e) || strlen($e) > 1000 || empty($k['p256dh']) || empty($k['auth'])) return response()->json(['ok' => false], 422);
    $row = ['customer_id' => $this->cid($r), 'endpoint' => $e, 'p256dh' => (string)$k['p256dh'], 'auth' => (string)$k['auth'], 'ua' => mb_substr((string)$r->userAgent(), 0, 200), 'active' => true, 'fail_count' => 0, 'updated_at' => now()];
    $h = hash('sha256', $e);
    $ex = DB::table('push_subscriptions')->where('endpoint_hash', $h)->first();
    if ($ex) { if (!$row['customer_id']) unset($row['customer_id']); DB::table('push_subscriptions')->where('id', $ex->id)->update($row); }
    else DB::table('push_subscriptions')->insert($row + ['endpoint_hash' => $h, 'created_at' => now()]);
    return response()->json(['ok' => true]);
  }

  public function unsubscribe(Request $r) {
    $e = (string)$r->input('endpoint'); if ($e === '') return response()->json(['ok' => false], 422);
    DB::table('push_subscriptions')->where('endpoint_hash', hash('sha256', $e))->delete();
    return response()->json(['ok' => true]);
  }

  /** Is this browser subscribed? (used by the account page) */
  public function status(Request $r) {
    $e = (string)$r->input('endpoint');
    return response()->json(['on' => $e !== '' && DB::table('push_subscriptions')->where('endpoint_hash', hash('sha256', $e))->where('active', true)->exists()])->header('Cache-Control', 'no-store');
  }

  public function clicked(Request $r) {
    $id = (int)$r->input('nid'); if ($id > 0) DB::table('push_campaigns')->where('id', $id)->increment('clicks');
    return response()->json(['ok' => true]);
  }

  public function read(Request $r) {
    $cid = $this->cid($r); if (!$cid) return response()->json(['ok' => false], 401);
    $q = DB::table('customer_notifications')->where('customer_id', $cid)->whereNull('read_at');
    if ($r->input('id') !== 'all') $q->where('id', (int)$r->input('id'));
    $q->update(['read_at' => now()]);
    return response()->json(['ok' => true]);
  }

  /** Header bell: unread count + the latest notifications. */
  public function bell(Request $r) {
    $cid = $this->cid($r);
    if (!$cid) return response()->json(['auth' => false, 'unread' => 0, 'items' => []])->header('Cache-Control', 'no-store');
    $rows = DB::table('customer_notifications')->where('customer_id', $cid)->orderByDesc('id')->limit(8)->get()->map(fn($n) => ['id' => $n->id, 'title' => $n->title, 'body' => \Illuminate\Support\Str::limit((string)$n->body, 90), 'url' => $n->url ?: '/account/notifications', 'cat' => $n->category, 'read' => (bool)$n->read_at, 'ago' => \Carbon\Carbon::parse($n->created_at, 'UTC')->diffForHumans()]);
    return response()->json(['auth' => true, 'unread' => (int)DB::table('customer_notifications')->where('customer_id', $cid)->whereNull('read_at')->count(), 'items' => $rows])->header('Cache-Control', 'no-store');
  }

  public function remove(Request $r) {
    $cid = $this->cid($r); if (!$cid) return response()->json(['ok' => false], 401);
    $q = DB::table('customer_notifications')->where('customer_id', $cid);
    if ($r->input('id') === 'read') $q->whereNotNull('read_at'); else $q->where('id', (int)$r->input('id'));
    $q->delete(); return response()->json(['ok' => true]);
  }

  public function prefs(Request $r) {
    $cid = $this->cid($r); if (!$cid) return response()->json(['ok' => false], 401);
    $d = []; foreach (array_keys(Push::CATEGORIES) as $c) $d[$c] = $r->boolean($c);
    $has = DB::table('customer_notification_prefs')->where('customer_id', $cid)->exists();
    if ($has) DB::table('customer_notification_prefs')->where('customer_id', $cid)->update($d + ['updated_at' => now()]);
    else DB::table('customer_notification_prefs')->insert($d + ['customer_id' => $cid, 'created_at' => now(), 'updated_at' => now()]);
    return response()->json(['ok' => true]);
  }
}
