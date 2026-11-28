<?php
namespace App\Http\Controllers;
use App\Support\ChannelScope;
use App\Support\StockGuard;
use App\Support\StoreScanner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
/** Sales Channels (admin-only, falls under the 'users' permission): add, list and edit each customer-facing channel (domain, storefront design, allowed categories and a price rule per category).
 *  The storefront side is ChannelScope. A channel starts as Draft; Preview is private; "Live" is deliberately not switchable here (needs a separate owner approval). Orders and payments are OFF until a channel is Live. */
class ChannelsController extends Controller {
  const STATUSES = ['draft' => 'Draft', 'preview' => 'Preview', 'live' => 'Live', 'paused' => 'Paused'];
  const THEMES = ['light' => 'Light (white, clean)', 'dark' => 'Dark', 'premium' => 'Premium (navy + gold accent)'];
  public static function defaults(): array {
    return ['brand' => ['logo_url' => '', 'favicon_url' => '', 'primary' => '#F95505', 'accent' => '#0F1B33', 'theme' => 'light'], 'hero' => ['image_url' => '', 'title' => '', 'subtitle' => '', 'cta_text' => '', 'cta_url' => ''], 'cats' => [], 'access_key' => '', 'history' => []];
  }
  private function load($c): array { $s = json_decode((string)$c->settings, true); $s = array_replace_recursive(self::defaults(), is_array($s) ? $s : []); $s['cats'] = is_array($s['cats'] ?? null) ? $s['cats'] : []; return $s; }
  private function cats() { return DB::table('categories')->whereNull('parent_id')->orderBy('position')->orderBy('id')->get(['id', 'name']); }
  public function index() {
    $rows = DB::table('sales_channels')->orderByRaw("case status when 'live' then 0 when 'preview' then 1 when 'draft' then 2 else 3 end")->orderBy('name')->get();
    $set = []; foreach ($rows as $c) $set[$c->id] = $this->load($c);
    return view('channels', ['rows' => $rows, 'set' => $set, 'statuses' => self::STATUSES, 'catNames' => $this->cats()->pluck('name', 'id')]);
  }
  public function create() { return view('channel_form', ['c' => null, 's' => self::defaults(), 'cats' => $this->cats(), 'statuses' => self::STATUSES, 'themes' => self::THEMES, 'pv' => [], 'link' => null]); }
  public function store(Request $r) {
    $d = $this->validated($r, null);
    $s = $this->build($d, self::defaults(), 'Channel created as Draft (no orders, no payments)');
    $s['access_key'] = Str::random(24);
    $id = DB::table('sales_channels')->insertGetId(['name' => $d['name'], 'slug' => $this->slug($d['name']), 'domain' => $d['domain'], 'status' => 'draft', 'active' => false, 'settings' => json_encode($s, JSON_UNESCAPED_UNICODE), 'created_at' => now(), 'updated_at' => now()]);
    ChannelScope::forget($id);
    return redirect('/admin/channels/' . $id . '/edit')->with('ok', 'Channel added as Draft. Tick its categories below. Nothing is live and orders / payments are off.');
  }
  public function edit($id) {
    $c = DB::table('sales_channels')->find($id); abort_unless($c, 404);
    $s = $this->load($c);
    return view('channel_form', ['c' => $c, 's' => $s, 'cats' => $this->cats(), 'statuses' => self::STATUSES, 'themes' => self::THEMES, 'pv' => $this->preview($c, $s),
      'link' => ($s['access_key'] ?? '') !== '' && $c->domain ? 'https://' . $c->domain . '/?pv=' . $s['access_key'] : null]);
  }
  public function update(Request $r, $id) {
    $c = DB::table('sales_channels')->find($id); abort_unless($c, 404);
    $d = $this->validated($r, (int)$id);
    $old = $this->load($c);
    $status = $r->input('status', $c->status);
    if (!in_array($status, ['draft', 'preview', 'paused'], true)) $status = $c->status === 'live' ? 'live' : 'draft'; // "live" can only be set by a separate approval, never from this form
    $s = $this->build($d, $old, 'Settings saved' . ($status !== $c->status ? ' · status ' . $c->status . ' → ' . $status : ''), $this->changed($old, $d));
    if (($s['access_key'] ?? '') === '') $s['access_key'] = Str::random(24);
    if ($status === 'live' && $r->has('orders_open_present')) { // Cash-on-delivery orders on a Live channel: one switch, recorded in the history
      $want = $r->boolean('orders_open');
      if ((bool)($s['orders_open'] ?? false) !== $want) { $s['orders_open'] = $want; array_unshift($s['history'], ['at' => now()->toDateTimeString(), 'by' => Auth::user()->name ?? 'staff', 'what' => 'Orders (Cash on delivery) ' . ($want ? 'OPENED' : 'CLOSED')]); $s['history'] = array_slice($s['history'], 0, 40); }
    }
    DB::table('sales_channels')->where('id', $id)->update(['name' => $d['name'], 'slug' => $c->slug ?: $this->slug($d['name']), 'domain' => $d['domain'], 'status' => $status, 'active' => $status === 'live', 'settings' => json_encode($s, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
    ChannelScope::forget((int)$id);
    return back()->with('ok', 'Saved. The storefront of this channel now follows the ticked categories and price rules.');
  }
  /** Side-by-side check BEFORE saving: main price vs channel price for one category with the rule typed in the form (read-only, nothing is stored). */
  public function quote(Request $r) {
    $cid = (int)$r->query('cid'); $mode = $r->query('mode') === 'tk' ? 'tk' : 'pct'; $v = (float)$r->query('v', 0);
    $c = (object)['adj' => [$cid => $v], 'mode' => [$cid => $mode]];
    $rows = StockGuard::listable(DB::table('products')->where('published', true)->whereNull('parent_product_id')->where('category_id', $cid))->orderByDesc('id')->limit(5)->get(['name', 'price', 'compare_price', 'image']);
    return response()->json(['rows' => $rows->map(fn($p) => ['name' => $p->name, 'img' => $p->image, 'base' => (float)$p->price, 'now' => (float)ChannelScope::price($p->price, $cid, $c)])->all()]);
  }
  /** Only a Draft channel can be deleted (a Preview / Live / Paused one has been opened by someone). */
  public function delete($id) {
    $c = DB::table('sales_channels')->find($id); abort_unless($c, 404);
    if ($c->status !== 'draft') return back()->withErrors(['name' => 'Only a Draft channel can be deleted. Set it to Draft first.']);
    DB::table('sales_channels')->where('id', $id)->delete(); ChannelScope::forget((int)$id);
    return redirect('/admin/channels')->with('ok', 'Draft channel deleted.');
  }
  /** What the storefront of this channel would show, from the same rules the storefront uses: per category the number of published products and a few examples with main price → channel price. */
  private function preview($c, array $s): array {
    $ch = ChannelScope::load((int)$c->id); if (!$ch) return [];
    $out = [];
    foreach ($s['cats'] as $x) {
      $cid = (int)$x['id']; $name = DB::table('categories')->where('id', $cid)->value('name'); if (!$name) continue;
      $q = fn() => StockGuard::listable(DB::table('products')->where('published', true)->whereNull('parent_product_id')->where('category_id', $cid));
      $rows = $q()->orderByDesc('id')->limit(6)->get(['id', 'name', 'price', 'compare_price', 'image', 'category_id']);
      $out[] = ['name' => $name, 'adj' => (float)($x['adj'] ?? 0), 'mode' => ($x['mode'] ?? 'pct'), 'n' => (int)$q()->count(), 'rows' => $rows->map(fn($p) => ['name' => $p->name, 'img' => $p->image, 'base' => (float)$p->price, 'now' => (float)ChannelScope::price($p->price, $cid, $ch)])->all()];
    }
    return $out;
  }
  private function slug(string $n): string { $b = Str::slug($n) ?: 'channel'; $s = $b; $i = 2; while (DB::table('sales_channels')->where('slug', $s)->exists()) $s = $b . '-' . $i++; return $s; }
  private function validated(Request $r, ?int $id): array {
    $hex = ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'];
    $d = $r->validate(['name' => 'required|string|max:120', 'domain' => 'required|string|max:200', 'logo_url' => 'nullable|string|max:400', 'favicon_url' => 'nullable|string|max:400', 'primary' => $hex, 'accent' => $hex, 'theme' => 'nullable|in:light,dark,premium',
      'hero_image_url' => 'nullable|string|max:400', 'hero_title' => 'nullable|string|max:120', 'hero_subtitle' => 'nullable|string|max:200', 'hero_cta_text' => 'nullable|string|max:40', 'hero_cta_url' => 'nullable|string|max:300',
      'cats' => 'nullable|array', 'cats.*' => 'integer', 'adj' => 'nullable|array', 'adj.*' => 'nullable|numeric|min:-100000|max:100000', 'mode' => 'nullable|array', 'mode.*' => 'nullable|in:pct,tk']);
    $dom = StoreScanner::cleanDomain($d['domain']);
    if ($dom === '') abort(back()->withErrors(['domain' => 'Enter a valid domain such as example.com'])->withInput());
    if (DB::table('sales_channels')->where('domain', $dom)->when($id, fn($q) => $q->where('id', '!=', $id))->exists()) abort(back()->withErrors(['domain' => 'This domain is already a channel'])->withInput());
    $d['domain'] = $dom;
    $valid = $this->cats()->pluck('id')->map(fn($v) => (int)$v)->all(); $cats = [];
    foreach (array_unique(array_map('intval', $d['cats'] ?? [])) as $cid) if (in_array($cid, $valid, true)) {
      $m = ($d['mode'][$cid] ?? 'pct') === 'tk' ? 'tk' : 'pct'; $v = round((float)(($d['adj'][$cid] ?? 0) ?: 0), 2);
      if ($m === 'pct' && ($v < -50 || $v > 200)) abort(back()->withErrors(['name' => 'Percent rule must be between -50% and +200%'])->withInput());
      $cats[] = ['id' => $cid, 'mode' => $m, 'adj' => $v];
    }
    $d['cats'] = $cats;
    return $d;
  }
  private function build(array $d, array $old, string $what, array $fields = []): array {
    $s = $old;
    $s['brand'] = ['logo_url' => trim((string)($d['logo_url'] ?? '')), 'favicon_url' => trim((string)($d['favicon_url'] ?? '')), 'primary' => $d['primary'] ?? '#F95505', 'accent' => $d['accent'] ?? '#0F1B33', 'theme' => $d['theme'] ?? 'light'];
    $s['hero'] = ['image_url' => trim((string)($d['hero_image_url'] ?? '')), 'title' => trim((string)($d['hero_title'] ?? '')), 'subtitle' => trim((string)($d['hero_subtitle'] ?? '')), 'cta_text' => trim((string)($d['hero_cta_text'] ?? '')), 'cta_url' => trim((string)($d['hero_cta_url'] ?? ''))];
    $s['cats'] = $d['cats'];
    $h = $old['history'] ?? [];
    array_unshift($h, ['at' => now()->toDateTimeString(), 'by' => Auth::user()->name ?? 'staff', 'what' => $what . ($fields ? ' (' . implode('; ', $fields) . ')' : '')]);
    $s['history'] = array_slice($h, 0, 40);
    return $s;
  }
  private function changed(array $o, array $d): array {
    $n = []; $names = DB::table('categories')->pluck('name', 'id');
    $b = $o['brand']; $hh = $o['hero'];
    if (($b['logo_url'] ?? '') !== trim((string)($d['logo_url'] ?? ''))) $n[] = 'logo';
    if (($b['primary'] ?? '') . ($b['accent'] ?? '') . ($b['theme'] ?? '') !== ($d['primary'] ?? '') . ($d['accent'] ?? '') . ($d['theme'] ?? '')) $n[] = 'colours/theme';
    if (json_encode(array_values($hh)) !== json_encode([trim((string)($d['hero_image_url'] ?? '')), trim((string)($d['hero_title'] ?? '')), trim((string)($d['hero_subtitle'] ?? '')), trim((string)($d['hero_cta_text'] ?? '')), trim((string)($d['hero_cta_url'] ?? ''))])) $n[] = 'hero';
    $om = []; foreach ($o['cats'] as $x) $om[(int)$x['id']] = (float)($x['adj'] ?? 0) . (($x['mode'] ?? 'pct') === 'tk' ? 'tk' : '%');
    $nm = []; foreach ($d['cats'] as $x) $nm[(int)$x['id']] = (float)$x['adj'] . ($x['mode'] === 'tk' ? 'tk' : '%');
    foreach (array_diff_key($nm, $om) as $k => $v) $n[] = 'allowed ' . ($names[$k] ?? $k) . ((float)$v != 0 ? ' (' . $v . ')' : '');
    foreach (array_diff_key($om, $nm) as $k => $v) $n[] = 'removed ' . ($names[$k] ?? $k);
    foreach (array_intersect_key($nm, $om) as $k => $v) if ($v !== $om[$k]) $n[] = ($names[$k] ?? $k) . ' price ' . $om[$k] . ' → ' . $v;
    return $n;
  }
}
