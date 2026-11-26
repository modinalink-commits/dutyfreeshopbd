<?php
namespace App\Http\Controllers;

use App\Support\MarineMoves as M;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/** Port Arrivals & Departures: Marine Desk entry (admin) and the public list for the marine site.
 *  Only Published visits with a confirmed actual time leave this controller. */
class MarineMovesController extends Controller {
  private static function ready(): bool { try { return Schema::hasTable('marine_port_visits') && Schema::hasTable('marine_vessel_photos'); } catch (\Throwable $e) { return false; } }
  private static function who(): string { return mb_substr((string)(optional(auth()->user())->name ?? 'staff'), 0, 120); }
  private static function now(): string { return gmdate('Y-m-d H:i:s'); }
  private static function audit(string $act, string $what, $old = null, $new = null): void { try { \App\Support\Procure::audit($act, $what, $old, $new); } catch (\Throwable $e) {} }
  private static function photoUrl($p): ?string { if (!$p) return null; return $p->path ? 'https://dutyfreebd.com/marine-api/vessel-photo/' . $p->imo . '?v=' . strtotime((string)$p->updated_at) : ($p->source_url ?: null); }

  // ---------------- admin ----------------
  public function index(Request $r) {
    if (!self::ready()) return view('marine.moves', ['ready' => false]);
    $st = $r->query('status'); $st = isset(M::STATUS[$st]) ? $st : 'all'; $q = mb_substr(trim((string)$r->query('q')), 0, 40);
    $rows = DB::table('marine_port_visits')->when($st !== 'all', fn($w) => $w->where('status', $st))
      ->when($q !== '', fn($w) => ctype_digit($q) ? $w->where('imo', 'like', $q . '%') : $w->where('vessel', 'ilike', '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%'))
      ->orderByRaw('coalesce(departed_at, arrived_at) desc')->orderByDesc('id')->paginate(40)->withQueryString();
    $imos = collect($rows->items())->pluck('imo')->unique()->all(); $ids = collect($rows->items())->pluck('id')->all();
    $edit = $r->query('edit') ? DB::table('marine_port_visits')->where('id', (int)$r->query('edit'))->first() : null;
    if ($edit) { $imos[] = $edit->imo; $ids[] = $edit->id; }
    return view('marine.moves', ['ready' => true, 'rows' => $rows, 'st' => $st, 'q' => $q, 'edit' => $edit, 'new' => (bool)$r->query('new'),
      'photos' => DB::table('marine_vessel_photos')->whereIn('imo', $imos ?: ['-'])->get()->keyBy('imo'), 'docs' => DB::table('marine_documents')->whereIn('visit_id', $ids ?: [0])->orderBy('id')->get()->groupBy('visit_id'),
      'n' => DB::table('marine_port_visits')->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status'), 'ports' => M::PORTS, 'sources' => M::SOURCES, 'status' => M::STATUS]);
  }
  public function save(Request $r, $id = null) {
    if (!self::ready()) return back();
    [$d, $e] = M::clean($r->all(), self::now());
    $old = $id ? DB::table('marine_port_visits')->where('id', (int)$id)->first() : null; if ($id && !$old) abort(404);
    $credit = mb_substr(trim(strip_tags((string)$r->input('photo_credit'))), 0, 160); $f = $r->file('photo'); $link = trim((string)$r->input('photo_url')); $url = M::sourceUrl($link);
    if ($f) { if (!$f->isValid()) $e[] = 'The photo did not arrive completely.'; else { $bad = M::photoProblem((string)$f->getClientOriginalExtension(), $f->getMimeType(), (int)$f->getSize()); if ($bad) $e[] = $bad; } }
    if ($link !== '' && !$url) $e[] = 'The photo link must be an https link that ends in .jpg, .png or .webp.';
    if (($f || $url) && $credit === '') $e[] = 'Enter the photo credit (who the photo belongs to).';
    $docs = array_slice(array_filter((array)$r->file('docs')), 0, 6);
    foreach ($docs as $x) { $bad = (!$x->isValid()) ? 'A document did not arrive completely.' : \App\Support\MarineOrders::fileProblem((string)$x->getClientOriginalExtension(), $x->getMimeType(), (int)$x->getSize()); if ($bad) { $e[] = $bad; break; } }
    if ($e) return back()->withInput()->with('bad', implode(' ', array_unique($e)));
    $by = self::who(); $now = now(); $msg = 'Saved as Draft.';
    $vid = DB::transaction(function () use ($d, $old, $by, $now, &$msg) {
      if (!$old) {   // a new entry may belong to a visit that is already recorded
        $same = DB::table('marine_port_visits')->where('imo', $d['imo'])->where('port', $d['port'])->lockForUpdate()->get()->map(fn($x) => (array)$x)->all();
        $hit = M::match($same, $d); if ($hit) { $old = DB::table('marine_port_visits')->where('id', $hit)->first(); $d = M::merge($old, $d); $msg = 'This visit was already recorded, so it was updated instead of added twice.'; }
      }
      if ($old) {
        $u = $d + ['updated_by' => $by, 'updated_at' => $now];
        if ($old->status !== 'draft' && M::factsChanged($old, $d)) { $u += ['status' => 'draft', 'verified_by' => null, 'verified_at' => null, 'published_by' => null, 'published_at' => null]; $msg .= ' A confirmed fact changed, so the entry is Draft again and must be verified before it is shown.'; }
        elseif ($old->status !== 'draft') $msg = 'Saved. Status unchanged: ' . M::STATUS[$old->status] . '.';
        DB::table('marine_port_visits')->where('id', $old->id)->update($u); self::audit('marine.moves.update', 'Port visit updated: ' . $d['vessel'] . ' IMO ' . $d['imo'] . ' at ' . M::PORTS[$d['port']], (array)$old, $u); return (int)$old->id;
      }
      $nid = DB::table('marine_port_visits')->insertGetId($d + ['status' => 'draft', 'created_by' => $by, 'updated_by' => $by, 'created_at' => $now, 'updated_at' => $now]);
      self::audit('marine.moves.create', 'Port visit added: ' . $d['vessel'] . ' IMO ' . $d['imo'] . ' at ' . M::PORTS[$d['port']], null, $d); return (int)$nid;
    });
    if ($f || $url) {
      $path = null; if ($f) { $path = $f->storeAs('marine-vessels', $d['imo'] . '-' . bin2hex(random_bytes(6)) . '.' . strtolower((string)$f->getClientOriginalExtension()), 'local'); }
      if ($f && !$path) $msg .= ' The photo could not be saved.';
      else { $prev = DB::table('marine_vessel_photos')->where('imo', $d['imo'])->first();
        DB::table('marine_vessel_photos')->updateOrInsert(['imo' => $d['imo']], ['path' => $path, 'source_url' => $path ? null : $url, 'credit' => $credit, 'approved_by' => $by, 'approved_at' => $now, 'updated_at' => $now] + ($prev ? [] : ['created_at' => $now]));
        if ($prev && $prev->path && $prev->path !== $path) { try { Storage::disk('local')->delete($prev->path); } catch (\Throwable $x) {} }
        self::audit('marine.moves.photo', 'Vessel photo approved for IMO ' . $d['imo'] . ' (credit: ' . $credit . ')', null, ['imo' => $d['imo'], 'by' => $by]); }
    } elseif ($credit !== '') DB::table('marine_vessel_photos')->where('imo', $d['imo'])->update(['credit' => $credit, 'updated_at' => $now]);
    foreach ($docs as $x) { $ext = strtolower((string)$x->getClientOriginalExtension()); $p = $x->storeAs('marine-moves/' . $vid, bin2hex(random_bytes(12)) . '.' . $ext, 'local'); if (!$p) continue;
      DB::table('marine_documents')->insert(['visit_id' => $vid, 'name' => mb_substr(preg_replace('/[^\w .()\-]+/u', '_', (string)$x->getClientOriginalName()), 0, 190) ?: 'file.' . $ext, 'path' => $p, 'mime' => $x->getMimeType(), 'size' => (int)$x->getSize(), 'by_side' => 'desk', 'created_at' => $now, 'updated_at' => $now]); }
    return redirect('/admin/marine/movements?edit=' . $vid)->with('ok', $msg);
  }
  public function act(Request $r, $id) {
    if (!self::ready()) return back(); $v = DB::table('marine_port_visits')->where('id', (int)$id)->first(); if (!$v) abort(404);
    $a = (string)$r->input('action'); $by = self::who(); $now = now();
    if ($a === 'verify') { $p = M::verifyProblems($v); if ($p) return back()->with('bad', 'Cannot verify yet. ' . implode(' ', $p));
      DB::table('marine_port_visits')->where('id', $v->id)->update(['status' => 'verified', 'verified_by' => $by, 'verified_at' => $now, 'updated_by' => $by, 'updated_at' => $now]); self::audit('marine.moves.verify', 'Port visit verified: ' . $v->vessel . ' IMO ' . $v->imo, ['status' => $v->status], ['status' => 'verified', 'by' => $by]); return back()->with('ok', 'Verified by ' . $by . '.'); }
    if ($a === 'publish') { if ($v->status !== 'verified') return back()->with('bad', 'Only a Verified entry can be published.'); $p = M::verifyProblems($v); if ($p) return back()->with('bad', implode(' ', $p));
      DB::table('marine_port_visits')->where('id', $v->id)->update(['status' => 'published', 'published_by' => $by, 'published_at' => $now, 'updated_by' => $by, 'updated_at' => $now]); self::audit('marine.moves.publish', 'Port visit published: ' . $v->vessel . ' IMO ' . $v->imo, ['status' => $v->status], ['status' => 'published', 'by' => $by]); return back()->with('ok', 'Published. The marine site shows it within a minute.'); }
    if ($a === 'unpublish') { if ($v->status !== 'published') return back();
      DB::table('marine_port_visits')->where('id', $v->id)->update(['status' => 'verified', 'published_by' => null, 'published_at' => null, 'updated_by' => $by, 'updated_at' => $now]); self::audit('marine.moves.unpublish', 'Port visit taken off the site: ' . $v->vessel . ' IMO ' . $v->imo, ['status' => 'published'], ['status' => 'verified', 'by' => $by]); return back()->with('ok', 'Taken off the site. It stays Verified.'); }
    return back();
  }
  public function doc($id) {
    $d = DB::table('marine_documents')->where('id', (int)$id)->whereNotNull('visit_id')->first(); if (!$d || !Storage::disk('local')->exists($d->path)) abort(404);
    self::audit('marine.moves.document', 'Port visit document opened: ' . $d->name . ' (visit #' . $d->visit_id . ')', null, ['id' => (int)$d->id]);
    return Storage::disk('local')->download($d->path, $d->name, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store, private']);
  }

  // ---------------- public ----------------
  private static function pub(array $d, int $age = 60) { return response()->json($d)->header('Access-Control-Allow-Origin', MarineController::SITE)->header('Vary', 'Origin')->header('Cache-Control', 'public, max-age=' . $age); }
  /** Confirmed, published arrivals or departures. Latest 24 hours unless a date range is given. Newest first. */
  public function publicList(Request $r) {
    $type = $r->query('type') === 'departures' ? 'departures' : 'arrivals'; $col = $type === 'departures' ? 'departed_at' : 'arrived_at';
    if (!self::ready()) return self::pub(['type' => $type, 'items' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'feed' => null]);
    $port = (string)$r->query('port'); $port = isset(M::PORTS[$port]) ? $port : ''; $q = mb_substr(trim((string)$r->query('q')), 0, 40); $page = max(1, min(200, (int)$r->query('page', 1)));
    [$a, $b] = M::range($r->query('from'), $r->query('to'), self::now());
    $w = DB::table('marine_port_visits')->where('status', 'published')->whereNotNull($col)->whereBetween($col, [$a, $b])->when($port !== '', fn($x) => $x->where('port', $port))
      ->when($q !== '', fn($x) => ctype_digit($q) ? $x->where('imo', 'like', $q . '%') : $x->where('vessel', 'ilike', '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%'));
    $total = (clone $w)->count(); $rows = $w->orderByDesc($col)->orderByDesc('id')->offset(($page - 1) * M::PER)->limit(M::PER)->get();
    $ph = DB::table('marine_vessel_photos')->whereIn('imo', $rows->pluck('imo')->unique()->all() ?: ['-'])->get()->keyBy('imo');
    $items = $rows->map(function ($v) use ($type, $ph) { $p = $ph[$v->imo] ?? null; $u = self::photoUrl($p); return M::item($v, $type, $u ? ['url' => $u, 'credit' => (string)$p->credit] : null); })->all();
    $cfg = null; try { $j = DB::table('settings')->where('key', 'marine_moves_feed')->value('value'); $cfg = $j ? json_decode($j, true) : null; } catch (\Throwable $e) {}
    return self::pub(['type' => $type, 'from' => M::iso($a), 'to' => M::iso($b), 'tz' => 'UTC+6', 'items' => $items, 'total' => $total, 'page' => $page, 'pages' => max(1, (int)ceil($total / M::PER)), 'feed' => M::feed(is_array($cfg) ? $cfg : null, self::now())]);
  }
  /** The approved photo of a vessel, by IMO. Served only while that vessel has a published visit. */
  public function photo($imo) {
    if (!self::ready()) abort(404); $p = DB::table('marine_vessel_photos')->where('imo', (string)$imo)->first();
    if (!$p || !$p->path || !Storage::disk('local')->exists($p->path) || !DB::table('marine_port_visits')->where('imo', (string)$imo)->where('status', 'published')->exists()) abort(404);
    $ext = strtolower(pathinfo($p->path, PATHINFO_EXTENSION));
    return response(Storage::disk('local')->get($p->path), 200, ['Content-Type' => M::PHOTO_EXT[$ext] ?? 'image/jpeg', 'Cache-Control' => 'public, max-age=86400', 'X-Content-Type-Options' => 'nosniff', 'Access-Control-Allow-Origin' => MarineController::SITE]);
  }
}
