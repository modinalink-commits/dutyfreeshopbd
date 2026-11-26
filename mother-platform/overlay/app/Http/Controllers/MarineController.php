<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** DutyFree BD Marine Supply admin group (marinedutyfreebd.com): access requests from the marine site, which products the marine channel offers, and the marine desk contact shown on the site.
 *  First step only: quotations, port calls, orders and the crew shop are not built yet. Tobacco and alcohol can only ever be "after login" here (owner rule 26 Nov 2026). */
class MarineController extends Controller
{
  const SITE = 'https://marinedutyfreebd.com';
  const PORTS = ['chattogram' => 'Chattogram', 'mongla' => 'Mongla', 'payra' => 'Payra'];
  const KEYS = ['marine_desk_email', 'marine_desk_phone', 'marine_desk_whatsapp'];

  private static function ready(): bool { return Schema::hasTable('marine_access_requests') && Schema::hasTable('marine_products'); }
  public static function pending(): int { try { return Schema::hasTable('marine_access_requests') ? DB::table('marine_access_requests')->where('status', 'pending')->count() : 0; } catch (\Throwable $e) { return 0; } }
  private static function conf(): array { $c = DB::table('settings')->whereIn('key', self::KEYS)->pluck('value', 'key')->all(); $o = []; foreach (self::KEYS as $k) $o[$k] = trim((string)($c[$k] ?? '')); return $o; }
  /** Bonded goods: never public on the marine site, whatever is posted. */
  private static function bonded($cat): bool { return $cat && (StoreController::isSmokeCat($cat) || preg_match('/alcohol|liquor|whisk|wine|beer|spirit|tobacco|cigar/i', (string)($cat->name ?? '') . ' ' . (string)($cat->slug ?? '')) === 1); }

  public function index() {
    if (!self::ready()) return view('marine.index', ['ready' => false]);
    $n = DB::table('marine_access_requests')->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status')->all();
    $p = ['on' => DB::table('marine_products')->where('enabled', true)->count(), 'public' => DB::table('marine_products')->where('enabled', true)->where('visibility', 'public')->count(), 'noprice' => DB::table('marine_products')->where('enabled', true)->whereNull('usd_price')->count()];
    $last = DB::table('marine_access_requests')->orderByDesc('id')->limit(5)->get();
    return view('marine.index', ['ready' => true, 'n' => $n, 'p' => $p, 'last' => $last, 'conf' => self::conf(), 'site' => self::SITE]);
  }

  public function requests(Request $r) {
    $st = in_array($r->query('status'), ['pending', 'approved', 'rejected'], true) ? $r->query('status') : 'pending';
    $rows = DB::table('marine_access_requests')->where('status', $st)->orderByDesc('id')->paginate(50)->withQueryString();
    $n = DB::table('marine_access_requests')->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status')->all();
    return view('marine.requests', ['rows' => $rows, 'st' => $st, 'n' => $n]);
  }
  public function decide(Request $r, $id) {
    $q = DB::table('marine_access_requests')->where('id', $id)->first(); if (!$q) return redirect('/admin/marine/requests');
    $ok = $r->input('action') === 'approve'; $note = mb_substr(trim((string)$r->input('note')), 0, 300); $by = (string)(optional(auth()->user())->name ?? 'admin');
    $done = DB::table('marine_access_requests')->where('id', $q->id)->where('status', 'pending')->update(['status' => $ok ? 'approved' : 'rejected', 'staff_note' => $note ?: null, 'decided_by' => $by, 'decided_at' => now(), 'updated_at' => now()]);
    if (!$done) return redirect('/admin/marine/requests')->with('ok', 'Already decided');
    try { \App\Support\Procure::audit('marine.access.' . ($ok ? 'approved' : 'rejected'), 'Marine access request #' . $q->id . ' (' . $q->kind . ': ' . ($q->company ?: $q->name) . ')', 'pending', $ok ? 'approved' : 'rejected'); } catch (\Throwable $e) {}
    return redirect('/admin/marine/requests')->with('ok', $ok ? 'অনুমোদিত হয়েছে। অ্যাকাউন্ট তৈরির ধাপ এখনো তৈরি হয়নি, আবেদনকারীকে আলাদা করে জানাতে হবে।' : 'বাতিল করা হয়েছে');
  }

  public function catalog(Request $r) {
    $q = trim((string)$r->query('q')); $show = in_array($r->query('show'), ['on', 'all'], true) ? $r->query('show') : 'all'; $cat = (int)$r->query('category');
    $pq = DB::table('products as p')->leftJoin('marine_products as m', 'm.product_id', '=', 'p.id')->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
      ->select('p.id', 'p.sku', 'p.name', 'p.price', 'p.stock', 'p.image', 'c.name as cat', 'c.slug as cslug', 'c.id as cid', 'm.enabled', 'm.visibility', 'm.ports', 'm.usd_price');
    if ($q !== '') $pq->where(fn($w) => $w->where('p.name', 'ilike', '%' . $q . '%')->orWhere('p.sku', 'ilike', '%' . $q . '%'));
    if ($cat) $pq->where('p.category_id', $cat);
    if ($show === 'on') $pq->where('m.enabled', true);
    $rows = $pq->orderByRaw('coalesce(m.enabled, false) desc')->orderBy('p.name')->paginate(50)->withQueryString();
    $cats = DB::table('categories')->orderBy('name')->get(['id', 'name', 'slug']); $bond = [];
    foreach ($cats as $c) if (self::bonded($c)) $bond[$c->id] = true;
    return view('marine.catalog', ['rows' => $rows, 'q' => $q, 'show' => $show, 'cat' => $cat, 'cats' => $cats, 'bond' => $bond, 'ports' => self::PORTS]);
  }
  public function saveProduct(Request $r, $id) {
    $p = DB::table('products')->where('id', $id)->first(['id', 'sku', 'name', 'category_id']); if (!$p) return back();
    $c = $p->category_id ? DB::table('categories')->where('id', $p->category_id)->first(['id', 'slug', 'name']) : null;
    $on = (bool)$r->input('enabled'); $vis = $r->input('visibility') === 'public' ? 'public' : 'login'; if (self::bonded($c)) $vis = 'login';
    $ports = implode(',', array_values(array_intersect(array_keys(self::PORTS), (array)$r->input('ports', []))));
    $usd = trim((string)$r->input('usd_price')); $usd = ($usd !== '' && is_numeric($usd) && (float)$usd > 0 && (float)$usd < 1000000) ? round((float)$usd, 2) : null;
    $old = DB::table('marine_products')->where('product_id', $p->id)->first();
    $new = ['enabled' => $on, 'visibility' => $vis, 'ports' => $ports ?: null, 'usd_price' => $usd, 'updated_by' => (string)(optional(auth()->user())->name ?? 'admin'), 'updated_at' => now()];
    if ($old) DB::table('marine_products')->where('id', $old->id)->update($new); else DB::table('marine_products')->insert($new + ['product_id' => $p->id, 'created_at' => now()]);
    try { \App\Support\Procure::audit('marine.product', 'Marine channel: ' . ($p->sku ?: $p->id) . ' ' . mb_substr((string)$p->name, 0, 80), $old ? ['enabled' => (bool)$old->enabled, 'visibility' => $old->visibility, 'ports' => $old->ports, 'usd_price' => $old->usd_price] : null, ['enabled' => $on, 'visibility' => $vis, 'ports' => $ports, 'usd_price' => $usd]); } catch (\Throwable $e) {}
    return back()->with('ok', ($p->sku ?: 'Product') . ' সেভ হয়েছে');
  }

  public function settings() { return view('marine.settings', ['conf' => self::conf(), 'site' => self::SITE]); }
  public function saveSettings(Request $r) {
    $d = $r->validate(['marine_desk_email' => 'nullable|email|max:160', 'marine_desk_phone' => ['nullable', 'max:30', 'regex:/^[0-9+ ()-]*$/'], 'marine_desk_whatsapp' => ['nullable', 'max:30', 'regex:/^[0-9+ ()-]*$/']]);
    $old = self::conf();
    foreach (self::KEYS as $k) DB::table('settings')->updateOrInsert(['key' => $k], ['value' => trim((string)($d[$k] ?? '')), 'updated_at' => now()]);
    try { \Illuminate\Support\Facades\Cache::forget('marine.public'); \App\Support\Procure::audit('marine.settings', 'Marine desk contact', $old, self::conf()); } catch (\Throwable $e) {}
    return redirect('/admin/marine/settings')->with('ok', 'সেভ হয়েছে। মেরিন সাইটে কয়েক মিনিটের মধ্যে দেখাবে।');
  }

  /** Public, read-only: marine desk contact for the static marine site (no private data). */
  public function publicInfo() {
    $c = \Illuminate\Support\Facades\Cache::remember('marine.public', 300, fn() => self::conf());
    return response()->json(['email' => $c['marine_desk_email'], 'phone' => $c['marine_desk_phone'], 'whatsapp' => $c['marine_desk_whatsapp']])
      ->header('Access-Control-Allow-Origin', self::SITE)->header('Vary', 'Origin')->header('Cache-Control', 'public, max-age=300');
  }
  /** Public: the "Request Access" form on the marine site posts here (plain form post, then back to the marine site). Stores the request only; nothing is created or sent. */
  public function accessRequest(Request $r) {
    $back = self::SITE . '/request-access.html';
    if (!self::ready() || trim((string)$r->input('website')) !== '') return redirect()->away($back . '?sent=1'); // honeypot: bots get the same answer
    $kind = $r->input('kind') === 'crew' ? 'crew' : 'company';
    $f = fn($k, $n) => mb_substr(trim(strip_tags((string)$r->input($k))), 0, $n) ?: null;
    $email = $f('email', 160); $imo = preg_replace('/\D/', '', (string)$r->input('imo'));
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL) || ($imo !== '' && strlen($imo) !== 7)) return redirect()->away($back . '?sent=0');
    if (DB::table('marine_access_requests')->where('email', $email)->where('status', 'pending')->where('created_at', '>', now()->subDay())->exists()) return redirect()->away($back . '?sent=1'); // same request again: no second row
    DB::table('marine_access_requests')->insert(['kind' => $kind, 'company' => $f('company', 160), 'role' => $f('role', 80), 'name' => $f('name', 120), 'crew_rank' => $f('crew_rank', 80), 'email' => $email, 'phone' => $f('phone', 40),
      'imo' => $imo ?: null, 'vessel' => $f('vessel', 120), 'port' => $f('port', 40), 'message' => $f('message', 1000), 'status' => 'pending', 'ip' => $r->ip(), 'created_at' => now(), 'updated_at' => now()]);
    try { \Illuminate\Support\Facades\Cache::forget('sb.counts.v3'); } catch (\Throwable $e) {}
    return redirect()->away($back . '?sent=1');
  }
}
