<?php
namespace App\Http\Controllers;
use App\Support\StoreScanner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
/** Sales Channels (admin-only, falls under the 'users' permission): add, list and edit the settings of each customer-facing channel site. Channels start as Draft; Preview is staff-only; "Live" is deliberately not switchable here (needs a separate owner approval). Orders and payments stay OFF for every channel in this step. */
class ChannelsController extends Controller {
  const STATUSES = ['draft' => 'Draft', 'preview' => 'Preview', 'live' => 'Live', 'paused' => 'Paused'];
    const THEMES = ['light' => 'Light (white, clean)', 'dark' => 'Dark', 'premium' => 'Premium (navy + gold accent)'];
  public static function defaults(): array {
    return ['brand' => ['logo_url' => '', 'favicon_url' => '', 'primary' => '#F95505', 'accent' => '#0F1B33', 'theme' => 'light'], 'hero' => ['image_url' => '', 'title' => '', 'subtitle' => '', 'cta_text' => '', 'cta_url' => ''], 'categories' => [], 'history' => []];
  }
  private function load($c): array { $s = json_decode((string)$c->settings, true); return array_replace_recursive(self::defaults(), is_array($s) ? $s : []); }
  private function cats() { return DB::table('categories')->whereNull('parent_id')->orderBy('position')->orderBy('id')->pluck('name'); }
  public function index() {
    $rows = DB::table('sales_channels')->orderByRaw("case status when 'live' then 0 when 'preview' then 1 when 'draft' then 2 else 3 end")->orderBy('name')->get();
    $set = []; foreach ($rows as $c) $set[$c->id] = $this->load($c);
    return view('channels', ['rows' => $rows, 'set' => $set, 'statuses' => self::STATUSES]);
  }
  public function create() { return view('channel_form', ['c' => null, 's' => self::defaults(), 'cats' => $this->cats(), 'statuses' => self::STATUSES, 'themes' => self::THEMES]); }
  public function store(Request $r) {
    $d = $this->validated($r, null);
    $s = $this->build($d, self::defaults(), 'Channel created as Draft (no orders, no payments)');
    $id = DB::table('sales_channels')->insertGetId(['name' => $d['name'], 'slug' => $this->slug($d['name']), 'domain' => $d['domain'], 'status' => 'draft', 'active' => false, 'settings' => json_encode($s, JSON_UNESCAPED_UNICODE), 'created_at' => now(), 'updated_at' => now()]);
    return redirect('/admin/channels/' . $id . '/edit')->with('ok', 'Channel added as Draft. Nothing is live and orders / payments are off.');
  }
  public function edit($id) {
    $c = DB::table('sales_channels')->find($id); abort_unless($c, 404);
    return view('channel_form', ['c' => $c, 's' => $this->load($c), 'cats' => $this->cats(), 'statuses' => self::STATUSES, 'themes' => self::THEMES]);
  }
  public function update(Request $r, $id) {
    $c = DB::table('sales_channels')->find($id); abort_unless($c, 404);
    $d = $this->validated($r, (int)$id);
    $old = $this->load($c);
    $status = $r->input('status', $c->status);
    if (!in_array($status, ['draft', 'preview', 'paused'], true)) $status = $c->status === 'live' ? 'live' : 'draft'; // "live" can only be set by a separate approval, never from this form
    $s = $this->build($d, $old, 'Settings saved' . ($status !== $c->status ? ' · status ' . $c->status . ' → ' . $status : ''), $this->changed($old, $d));
    DB::table('sales_channels')->where('id', $id)->update(['name' => $d['name'], 'slug' => $c->slug ?: $this->slug($d['name']), 'domain' => $d['domain'], 'status' => $status, 'active' => $status === 'live', 'settings' => json_encode($s, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
    return back()->with('ok', 'Saved.');
  }
  private function slug(string $n): string { $b = Str::slug($n) ?: 'channel'; $s = $b; $i = 2; while (DB::table('sales_channels')->where('slug', $s)->exists()) $s = $b . '-' . $i++; return $s; }
  private function validated(Request $r, ?int $id): array {
    $hex = ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'];
    $d = $r->validate(['name' => 'required|string|max:120', 'domain' => 'required|string|max:200', 'logo_url' => 'nullable|string|max:400', 'favicon_url' => 'nullable|string|max:400', 'primary' => $hex, 'accent' => $hex, 'theme' => 'nullable|in:light,dark,premium',
      'hero_image_url' => 'nullable|string|max:400', 'hero_title' => 'nullable|string|max:120', 'hero_subtitle' => 'nullable|string|max:200', 'hero_cta_text' => 'nullable|string|max:40', 'hero_cta_url' => 'nullable|string|max:300',
      'categories' => 'nullable|array', 'categories.*' => 'string|max:120']);
    $dom = StoreScanner::cleanDomain($d['domain']);
    if ($dom === '') abort(back()->withErrors(['domain' => 'Enter a valid domain such as example.com'])->withInput());
    if (DB::table('sales_channels')->where('domain', $dom)->when($id, fn($q) => $q->where('id', '!=', $id))->exists()) abort(back()->withErrors(['domain' => 'This domain is already a channel'])->withInput());
    $d['domain'] = $dom;
    $d['categories'] = array_values(array_intersect($d['categories'] ?? [], $this->cats()->all()));
    return $d;
  }
  private function build(array $d, array $old, string $what, array $fields = []): array {
    $s = $old;
    $s['brand'] = ['logo_url' => trim((string)($d['logo_url'] ?? '')), 'favicon_url' => trim((string)($d['favicon_url'] ?? '')), 'primary' => $d['primary'] ?? '#F95505', 'accent' => $d['accent'] ?? '#0F1B33', 'theme' => $d['theme'] ?? 'light'];
    $s['hero'] = ['image_url' => trim((string)($d['hero_image_url'] ?? '')), 'title' => trim((string)($d['hero_title'] ?? '')), 'subtitle' => trim((string)($d['hero_subtitle'] ?? '')), 'cta_text' => trim((string)($d['hero_cta_text'] ?? '')), 'cta_url' => trim((string)($d['hero_cta_url'] ?? ''))];
    $s['categories'] = $d['categories'];
    $h = $old['history'] ?? [];
    array_unshift($h, ['at' => now()->toDateTimeString(), 'by' => Auth::user()->name ?? 'staff', 'what' => $what . ($fields ? ' (' . implode(', ', $fields) . ')' : '')]);
    $s['history'] = array_slice($h, 0, 40);
    return $s;
  }
  private function changed(array $o, array $d): array {
    $n = [];
    $cmp = ['logo' => [$o['brand']['logo_url'], $d['logo_url'] ?? ''], 'colours/theme' => [$o['brand']['primary'] . $o['brand']['accent'] . $o['brand']['theme'], ($d['primary'] ?? '') . ($d['accent'] ?? '') . ($d['theme'] ?? '')], 'hero' => [json_encode(array_values($o['hero'])), json_encode([$d['hero_image_url'] ?? '', $d['hero_title'] ?? '', $d['hero_subtitle'] ?? '', $d['hero_cta_text'] ?? '', $d['hero_cta_url'] ?? ''])], 'categories' => [json_encode($o['categories']), json_encode($d['categories'])]];
    foreach ($cmp as $k => [$a, $b]) if ((string)$a !== (string)$b) $n[] = $k;
    return $n;
  }
}
