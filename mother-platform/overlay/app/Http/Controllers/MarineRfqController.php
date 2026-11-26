<?php
namespace App\Http\Controllers;

use App\Support\MarineRfq as Q;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Ship Provisions (quotation-only items): import with preflight and dry run, desk review (port checks, packshot, publish), and the public list for the marine site.
 *  Nothing reaches the public list or the requisition form until an item is Published AND the catalogue is released. A preview key shows drafts to reviewers. */
class MarineRfqController extends Controller {
  const EXPECT = ['rows' => 160, 'sections' => 12];
  private static function who(): string { return mb_substr((string)(optional(auth()->user())->name ?? 'staff'), 0, 120); }
  private static function audit(string $act, string $what, $old = null, $new = null): void { try { \App\Support\Procure::audit($act, $what, $old, $new); } catch (\Throwable $e) {} }
  private static function js(string $k): array { try { $v = DB::table('settings')->where('key', $k)->value('value'); $a = $v ? json_decode($v, true) : null; return is_array($a) ? $a : []; } catch (\Throwable $e) { return []; } }
  private static function put(string $k, $v): void { DB::table('settings')->updateOrInsert(['key' => $k], ['value' => is_string($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]); }
  private static function today(): string { return now('Asia/Dhaka')->toDateString(); }

  /** Reads the bundled set and checks it. Returns the preflight result with the sourcing notes merged into each valid row. */
  private static function load(): array {
    $get = fn(string $f) => is_file(Q::dir($f)) ? (string)file_get_contents(Q::dir($f)) : null;
    $csv = $get('import.csv'); if ($csv === null) return ['ok' => false, 'file' => ['The import file is not on the server yet.'], 'rows' => [], 'good' => [], 'stats' => ['rows' => 0, 'unique_skus' => 0, 'sections' => 0, 'valid' => 0, 'with_problems' => 0]];
    [$rows, $fp] = Q::parseCsv($csv, Q::HEAD); $man = null; $m = $get('manifest.csv'); if ($m === null) $fp[] = 'The image manifest is not on the server.'; else { [$man, $mp] = Q::parseCsv($m); foreach ($mp as $x) $fp[] = 'Image manifest: ' . $x; }
    $pf = Q::preflight($rows, $fp, $man, fn(string $f) => is_file(Q::dir('img/' . $f)) && filesize(Q::dir('img/' . $f)) > 0, self::EXPECT);
    $s = $get('sourcing.csv'); if ($s !== null) { [$srows] = Q::parseCsv($s); foreach ($srows as $x) { $sku = $x['sku'] ?? ''; if (!isset($pf['good'][$sku])) continue; unset($x['_line'], $x['sku']); $pf['good'][$sku]['sourcing'] = json_encode(array_filter($x, fn($v) => $v !== ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); } }
    return $pf;
  }

  // ---------------- admin ----------------
  public function index(Request $r) {
    if (!Q::ready()) return view('marine.rfq', ['ready' => false]);
    $last = self::js('marine_rfq_last'); $wrote = self::js('marine_rfq_last_write'); if ($r->query('report') === 'csv' && $r->query('run') === 'write') $last = $wrote;
    if ($r->query('report') === 'csv') {
      $out = fopen('php://temp', 'r+'); fputcsv($out, ['sku', 'result', 'detail'], ',', '"', '');
      foreach (($last['file'] ?? []) as $x) fputcsv($out, ['(file)', 'problem', $x], ',', '"', '');
      foreach (($last['rows'] ?? []) as $x) fputcsv($out, [$x[0], $x[1], $x[2]], ',', '"', '');
      rewind($out); return response(stream_get_contents($out), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="provisions-import-report.csv"', 'Cache-Control' => 'no-store, private']);
    }
    $st = isset(Q::STATES[$r->query('state')]) ? (string)$r->query('state') : 'all'; $sec = preg_match('/^[a-z0-9-]{1,60}$/', (string)$r->query('section')) ? (string)$r->query('section') : ''; $q = mb_substr(trim((string)$r->query('q')), 0, 60);
    $rows = DB::table('marine_rfq_items')->when($st !== 'all', fn($w) => $w->where('state', $st))->when($sec !== '', fn($w) => $w->where('section_slug', $sec))
      ->when($q !== '', function ($w) use ($q) { $l = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%'; $w->where(fn($x) => $x->where('sku', 'ilike', $l)->orWhere('name_en', 'ilike', $l)->orWhere('name_bn', 'ilike', $l)->orWhere('brand', 'ilike', $l)); })
      ->orderBy('sku')->paginate(40)->withQueryString();
    $ids = collect($rows->items())->pluck('id')->all();
    return view('marine.rfq', ['ready' => true, 'rows' => $rows, 'st' => $st, 'sec' => $sec, 'q' => $q, 'last' => $last, 'wrote' => $wrote, 'runs' => self::js('marine_rfq_runs'),
      'checks' => DB::table('marine_rfq_checks')->whereIn('item_id', $ids ?: [0])->orderBy('port')->get()->groupBy('item_id'), 'today' => self::today(),
      'n' => DB::table('marine_rfq_items')->selectRaw('state, count(*) c')->groupBy('state')->pluck('c', 'state'), 'sections' => DB::table('marine_rfq_items')->selectRaw('section_slug, min(section_name) n, count(*) c')->groupBy('section_slug')->orderBy('n')->get(),
      'packshots' => DB::table('marine_rfq_items')->whereNotNull('packshot_path')->count(), 'checked' => DB::table('marine_rfq_checks')->where('valid_until', '>=', self::today())->distinct()->count('item_id'),
      'released' => Q::released(), 'token' => Q::token(), 'site' => MarineController::SITE, 'ports' => Q::PORTS, 'states' => Q::STATES, 'handling' => Q::HANDLING, 'open' => (int)$r->query('open')]);
  }

  /** Preflight then a dry run (nothing written), or the import itself (drafts). The import stops before writing when the preflight finds any problem. */
  public function run(Request $r) {
    if (!Q::ready()) return back(); $apply = $r->input('mode') === 'apply'; $by = self::who();
    $pf = self::load(); $skus = array_keys($pf['good']);
    $existing = []; foreach (DB::table('marine_rfq_items')->whereIn('sku', $skus ?: ['-'])->get() as $e) $existing[$e->sku] = (array)$e;
    $taken = []; foreach (array_chunk($skus, 200) as $ch) foreach (DB::table('products')->whereIn('sku', $ch)->get(['id', 'sku']) as $p) $taken[$p->sku] = 'This SKU is already used by store product #' . $p->id . '. Nothing was written; choose which one keeps the code.';
    $plan = Q::plan($pf['good'], $existing, $taken); $c = Q::counts($plan, $pf['rows']); $wrote = false;
    if ($apply && $pf['ok']) {
      DB::transaction(function () use ($plan, $by) { $now = now();
        foreach ($plan as $p) {
          if ($p['action'] === 'created') DB::table('marine_rfq_items')->insert($p['set'] + ['state' => 'draft', 'sale_mode' => 'RFQ', 'currency' => 'USD', 'price_usd' => null, 'created_by' => $by, 'updated_by' => $by, 'created_at' => $now, 'updated_at' => $now]);
          elseif ($p['action'] === 'updated') DB::table('marine_rfq_items')->where('sku', $p['sku'])->update($p['set'] + ['updated_by' => $by, 'updated_at' => $now]);
        } });
      $wrote = true; self::audit('marine.rfq.import', 'Ship provisions import: ' . $c['created'] . ' created as drafts, ' . $c['updated'] . ' updated, ' . $c['conflict'] . ' conflicts, ' . $c['skipped'] . ' unchanged', null, $c);
    }
    $rows = []; foreach ($pf['rows'] as $k => $ps) $rows[] = [(string)$k, 'rejected', implode(' ', $ps)]; foreach ($plan as $p) $rows[] = [$p['sku'], $p['action'], $p['detail']];
    $mode = $apply ? ($wrote ? 'import' : 'import stopped') : 'dry run'; $at = now('Asia/Dhaka')->format('d M Y, H:i');
    self::put('marine_rfq_last', ['at' => $at, 'by' => $by, 'mode' => $mode, 'ok' => $pf['ok'], 'stats' => $pf['stats'], 'counts' => $c, 'file' => $pf['file'], 'rows' => $rows]);
    if ($wrote && ($c['created'] || $c['updated'] || $c['conflict'])) self::put('marine_rfq_last_write', ['at' => $at, 'by' => $by, 'mode' => $mode, 'ok' => true, 'stats' => $pf['stats'], 'counts' => $c, 'file' => [], 'rows' => $rows]);   // the row report of the last import that changed something is kept apart, so a later dry run does not replace it
    $runs = self::js('marine_rfq_runs'); array_unshift($runs, ['at' => $at, 'by' => $by, 'mode' => $mode, 'ok' => $pf['ok'], 'counts' => $c]); self::put('marine_rfq_runs', array_slice($runs, 0, 12));
    $sum = $c['created'] . ' created, ' . $c['updated'] . ' updated, ' . $c['conflict'] . ' conflicted, ' . $c['skipped'] . ' skipped, ' . $c['rejected'] . ' rejected by the preflight';
    if ($apply && !$pf['ok']) return redirect('/admin/marine/provisions')->with('bad', 'Nothing was imported: the preflight found problems. Fix the rows in the report and run it again. (' . $sum . ')');
    return redirect('/admin/marine/provisions')->with($pf['ok'] && !$c['conflict'] ? 'ok' : 'bad', ($apply ? 'Import finished: ' : 'Dry run, nothing written: ') . $sum . '.');
  }

  public function act(Request $r, $id) {
    if (!Q::ready()) return back(); $it = DB::table('marine_rfq_items')->where('id', (int)$id)->first(); if (!$it) abort(404);
    $a = (string)$r->input('action'); $by = self::who(); $now = now(); $back = fn(string $k, string $m) => redirect('/admin/marine/provisions?' . http_build_query(array_filter(['q' => $it->sku, 'open' => $it->id])))->with($k, $m);
    if ($a === 'check') {
      $port = isset(Q::PORTS[$r->input('port')]) ? (string)$r->input('port') : null; $sup = mb_substr(trim(strip_tags((string)$r->input('supplier'))), 0, 160); $qty = mb_substr(trim(strip_tags((string)$r->input('qty_available'))), 0, 60); $until = (string)$r->input('valid_until');
      if (!$port || $sup === '' || $qty === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $until) || !strtotime($until)) return $back('bad', 'A port check needs the port, the supplier or branch, the quantity available and the date it is valid until.');
      if ($until < self::today()) return $back('bad', 'The valid-until date is already past.');
      if ($until > now('Asia/Dhaka')->addDays(90)->toDateString()) return $back('bad', 'A port check can be valid for 90 days at most.');
      $d = ['supplier' => $sup, 'pack_checked' => mb_substr(trim(strip_tags((string)$r->input('pack_checked'))), 0, 80) ?: null, 'qty_available' => $qty, 'order_unit' => in_array($r->input('order_unit'), Q::ORDER_UOM, true) ? $r->input('order_unit') : null, 'handling_ok' => (bool)$r->input('handling_ok'), 'checked_at' => $now, 'checked_by' => $by, 'valid_until' => $until, 'note' => mb_substr(trim(strip_tags((string)$r->input('note'))), 0, 300) ?: null, 'updated_at' => $now];
      $old = DB::table('marine_rfq_checks')->where('item_id', $it->id)->where('port', $port)->first();
      if ($old) DB::table('marine_rfq_checks')->where('id', $old->id)->update($d); else DB::table('marine_rfq_checks')->insert($d + ['item_id' => $it->id, 'port' => $port, 'created_at' => $now]);
      self::audit('marine.rfq.check', 'Port check for ' . $it->sku . ' at ' . Q::PORTS[$port] . ': ' . $sup . ', ' . $qty . ', valid until ' . $until, $old ? (array)$old : null, $d);
      return $back('ok', 'Port check saved. Availability at ' . Q::PORTS[$port] . ' is shown until ' . $until . ' once the item is published.');
    }
    if ($a === 'check_delete') { $c = DB::table('marine_rfq_checks')->where('id', (int)$r->input('check_id'))->where('item_id', $it->id)->first(); if ($c) { DB::table('marine_rfq_checks')->where('id', $c->id)->delete(); self::audit('marine.rfq.check_delete', 'Port check removed for ' . $it->sku . ' at ' . (Q::PORTS[$c->port] ?? $c->port), (array)$c, null); } return $back('ok', 'Port check removed.'); }
    if ($a === 'publish') { if ($it->state === 'published') return $back('ok', 'Already published.'); $p = Q::publishProblems($it); if ($p) return $back('bad', 'Cannot publish. ' . implode(' ', $p));
      DB::table('marine_rfq_items')->where('id', $it->id)->update(['state' => 'published', 'published_by' => $by, 'published_at' => $now, 'updated_by' => $by, 'updated_at' => $now]); self::audit('marine.rfq.publish', 'Ship provisions item published: ' . $it->sku . ' ' . $it->name_en, ['state' => $it->state], ['state' => 'published', 'by' => $by]);
      return $back('ok', 'Published.' . (Q::released() ? ' The marine site shows it within a few minutes.' : ' It appears on the marine site when the catalogue is released.')); }
    if ($a === 'unpublish') { if ($it->state !== 'published') return $back('ok', 'Already a draft.');
      DB::table('marine_rfq_items')->where('id', $it->id)->update(['state' => 'draft', 'published_by' => null, 'published_at' => null, 'updated_by' => $by, 'updated_at' => $now]); self::audit('marine.rfq.unpublish', 'Ship provisions item back to draft: ' . $it->sku, ['state' => 'published'], ['state' => 'draft', 'by' => $by]); return $back('ok', 'Back to Draft. It is off the marine site.'); }
    if ($a === 'packshot') {
      $f = $r->file('packshot'); $ref = mb_substr(trim(strip_tags((string)$r->input('packshot_ref'))), 0, 200);
      if (!$f || !$f->isValid()) return $back('bad', 'Choose the packshot image.'); $bad = Q::imageProblem((string)$f->getClientOriginalExtension(), $f->getMimeType(), (int)$f->getSize()); if ($bad) return $back('bad', $bad);
      if ($ref === '') return $back('bad', 'Enter the rights reference: who supplied the photo and the permission to use it.');
      $path = $f->storeAs('marine-rfq', $it->sku . '-' . bin2hex(random_bytes(6)) . '.' . strtolower((string)$f->getClientOriginalExtension()), 'local'); if (!$path) return $back('bad', 'The image could not be saved.');
      DB::table('marine_rfq_items')->where('id', $it->id)->update(['packshot_path' => $path, 'packshot_ref' => $ref, 'packshot_by' => $by, 'packshot_at' => $now, 'updated_by' => $by, 'updated_at' => $now]);
      if ($it->packshot_path && $it->packshot_path !== $path) { try { Storage::disk('local')->delete($it->packshot_path); } catch (\Throwable $e) {} }
      self::audit('marine.rfq.packshot', 'Approved packshot set for ' . $it->sku . ' (rights: ' . $ref . ')', ['packshot' => (bool)$it->packshot_path], ['by' => $by]); return $back('ok', 'Packshot approved by ' . $by . '. The SKU is unchanged and the "Illustrative image" label is no longer shown for this item.');
    }
    if ($a === 'packshot_remove') { if (!$it->packshot_path) return $back('ok', 'This item uses the illustrative image.');
      DB::table('marine_rfq_items')->where('id', $it->id)->update(['packshot_path' => null, 'packshot_ref' => null, 'packshot_by' => null, 'packshot_at' => null, 'updated_by' => $by, 'updated_at' => $now]); try { Storage::disk('local')->delete($it->packshot_path); } catch (\Throwable $e) {}
      self::audit('marine.rfq.packshot_remove', 'Packshot removed for ' . $it->sku . ', back to the illustrative image', null, ['by' => $by]); return $back('ok', 'Packshot removed. The illustrative image and its label are shown again.'); }
    return back();
  }

  /** Release switch for the whole catalogue, and a new preview key. Only an administrator. */
  public function release(Request $r) {
    if (!Q::ready()) return back(); if (!\App\Support\ProfitFloor::admin()) abort(403); $a = (string)$r->input('action');
    if ($a === 'newkey') { Q::token(true); self::audit('marine.rfq.key', 'Ship provisions preview key replaced', null, ['by' => self::who()]); return back()->with('ok', 'New preview link made. The old link no longer works.'); }
    if ($a === 'on' || $a === 'off') { $was = Q::released(); self::put('marine_rfq_released', $a === 'on' ? '1' : '0'); self::audit('marine.rfq.release', 'Ship provisions catalogue ' . ($a === 'on' ? 'released to the marine site' : 'taken off the marine site'), ['released' => $was], ['released' => $a === 'on', 'by' => self::who()]);
      return back()->with('ok', $a === 'on' ? 'Released. Published items are now on the marine site and in the requisition search.' : 'Taken off the marine site. Items and their states are kept.'); }
    return back();
  }

  // ---------------- public ----------------
  /** Published items after release. With a valid preview key: every item, drafts marked, never cached. */
  public function publicList(Request $r) {
    $prev = Q::ready() && Q::tokenOk($r->query('preview')); $rel = Q::ready() && Q::released(); $items = []; $secs = [];
    if ($prev || $rel) {
      $rows = DB::table('marine_rfq_items')->when(!$prev, fn($w) => $w->where('state', 'published'))->orderBy('id')->get(); $today = self::today();
      $chk = DB::table('marine_rfq_checks')->whereIn('item_id', $rows->pluck('id')->all() ?: [0])->where('valid_until', '>=', $today)->get()->groupBy('item_id');
      foreach ($rows as $it) {
        if (Q::publishProblems($it)) continue;
        $img = 'https://dutyfreebd.com/marine-api/rfq-image/' . $it->sku . '?v=' . strtotime((string)($it->packshot_at ?: $it->created_at)) . ($prev && !($rel && $it->state === 'published') ? '&preview=' . $r->query('preview') : '');
        $items[] = Q::publicRow($it, ($chk[$it->id] ?? collect())->all(), $img, $today);
        $s = &$secs[$it->section_slug]; if (!$s) $s = ['slug' => $it->section_slug, 'name' => $it->section_name, 'kind' => Q::isSelection($it->section_slug) ? 'selection' : 'category', 'count' => 0, 'subs' => []]; $s['count']++; if (!in_array($it->sub_name, $s['subs'], true)) $s['subs'][] = $it->sub_name; unset($s);
      }
    }
    return response()->json(['released' => $rel, 'preview' => $prev, 'total' => count($items), 'sections' => array_values($secs), 'items' => $items])
      ->header('Access-Control-Allow-Origin', MarineController::SITE)->header('Vary', 'Origin')->header('Cache-Control', $prev ? 'no-store, private' : 'public, max-age=180')->header('X-Robots-Tag', 'noindex');
  }
  /** One image per item: the approved packshot when there is one, otherwise the illustrative image. A draft image needs the preview key. */
  public function image(Request $r, $sku) {
    if (!Q::ready()) abort(404); $it = DB::table('marine_rfq_items')->where('sku', (string)$sku)->first(['state', 'image_file', 'packshot_path']); if (!$it) abort(404);
    $open = $it->state === 'published' && Q::released(); if (!$open && !Q::tokenOk($r->query('preview'))) abort(404);
    $h = ['Cache-Control' => $open ? 'public, max-age=86400' : 'private, max-age=600', 'X-Content-Type-Options' => 'nosniff', 'Access-Control-Allow-Origin' => MarineController::SITE];
    if ($it->packshot_path && Storage::disk('local')->exists($it->packshot_path)) return response(Storage::disk('local')->get($it->packshot_path), 200, $h + ['Content-Type' => Q::IMG_EXT[strtolower(pathinfo($it->packshot_path, PATHINFO_EXTENSION))] ?? 'image/jpeg']);
    $f = Q::dir('img/' . basename((string)$it->image_file)); if (!$it->image_file || !is_file($f)) abort(404);
    return response()->file($f, $h + ['Content-Type' => 'image/webp']);
  }
}
