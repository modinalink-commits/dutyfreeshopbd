<?php
namespace App\Http\Controllers;
use App\Support\Img;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
/** Image Optimization tool + Media Library (admin). Existing files are optimised in small batches so memory stays low. */
class MediaController extends Controller {
  private function files(): array {
    $d = Img::dir(); if (!is_dir($d)) return [];
    $out = []; foreach (scandir($d) as $f) if ($f[0] !== '.' && is_file($d.'/'.$f) && Img::isOriginal($f)) $out[] = $f;
    return $out;
  }
  public function index(Request $r) {
    $files = $this->files(); $total = count($files);
    $rows = DB::table('media_images')->whereIn('path', $files)->get()->keyBy('path');
    $opt = $rows->where('status', 'optimized')->count(); $failed = $rows->where('status', 'failed')->count();
    $before = (int)$rows->where('status', 'optimized')->sum('orig_bytes'); $after = (int)$rows->where('status', 'optimized')->sum('opt_bytes');
    $q = trim((string)$r->query('q', '')); $page = max(1, (int)$r->query('page', 1)); $per = 24;
    $list = collect($files)->filter(fn($f) => $q === '' || stripos($f, $q) !== false)->sortByDesc(fn($f) => filemtime(Img::dir().'/'.$f))->values();
    $pages = max(1, (int)ceil($list->count() / $per)); $slice = $list->slice(($page - 1) * $per, $per)->values();
    $used = [];
    if ($slice->isNotEmpty()) {
      $urls = $slice->map(fn($f) => '/uploads/'.$f)->all();
      foreach (DB::table('products')->whereIn('image', $urls)->get(['id', 'name', 'image']) as $p) $used['/uploads/'.basename($p->image)][] = $p->name;
      foreach (DB::table('product_images')->whereIn('path', $urls)->join('products', 'products.id', '=', 'product_images.product_id')->get(['products.name', 'product_images.path']) as $p) $used[$p->path][] = $p->name;
      foreach (DB::table('sliders')->get(['image', 'mobile_image', 'title']) as $s) foreach (['image', 'mobile_image'] as $k) if ($s->$k && in_array($s->$k, $urls)) $used[$s->$k][] = 'Slider'.($s->title ? ': '.$s->title : '');
    }
    $items = $slice->map(function ($f) use ($rows, $used) {
      $abs = Img::dir().'/'.$f; $m = $rows[$f] ?? null; $sz = @getimagesize($abs); $now = filesize($abs);
      return ['name'=>$f, 'url'=>'/uploads/'.$f, 'now'=>$now, 'orig'=>$m && $m->status === 'optimized' ? (int)$m->orig_bytes : $now, 'w'=>$sz[0] ?? null, 'h'=>$sz[1] ?? null, 'fmt'=>strtoupper(pathinfo($f, PATHINFO_EXTENSION)),
        'status'=>$m->status ?? 'pending', 'error'=>$m->error ?? null, 'webp'=>is_file($abs.'.webp') || str_ends_with($f, '.webp'), 'used'=>array_values(array_unique($used['/uploads/'.$f] ?? []))];
    })->all();
    $ext = DB::table('products')->where('image', 'like', 'http%')->count();
    return view('media', ['gd'=>Img::ok(), 'total'=>$total, 'opt'=>$opt, 'failed'=>$failed, 'pending'=>$total - $opt - $failed, 'saved'=>max(0, $before - $after), 'before'=>$before, 'items'=>$items, 'q'=>$q, 'page'=>$page, 'pages'=>$pages, 'backup'=>Img::backupOn(), 'ext'=>$ext]);
  }
  public function settings(Request $r) {
    DB::table('settings')->updateOrInsert(['key'=>'img_backup'], ['value'=>$r->boolean('backup') ? '1' : '0', 'updated_at'=>now(), 'created_at'=>now()]);
    return response()->json(['ok'=>true]);
  }
  /** One batch: optimise up to N pending files (and retry failed ones when asked). */
  public function batch(Request $r) {
    if (!Img::ok()) return response()->json(['ok'=>false, 'error'=>'GD is not enabled on the server yet'], 503);
    @set_time_limit(50); $t0 = microtime(true);
    $files = $this->files(); $done = DB::table('media_images')->whereIn('path', $files)->pluck('path')->all();
    $todo = array_values(array_diff($files, $done)); $n = 0; $saved = 0; $fail = 0; $backup = Img::backupOn();
    foreach ($todo as $f) {
      if ($n >= 12 || microtime(true) - $t0 > 35) break;
      $abs = Img::dir().'/'.$f; if ($backup) Img::backup($abs);
      $kind = DB::table('sliders')->where('image', '/uploads/'.$f)->exists() ? 'banner' : (DB::table('sliders')->where('mobile_image', '/uploads/'.$f)->exists() ? 'mobile' : ((\Illuminate\Support\Facades\Schema::hasColumn('categories', 'image') && DB::table('categories')->where('image', '/uploads/'.$f)->exists()) ? 'category' : (str_starts_with($f, 'c-') ? 'auto' : 'product')));
      try { $res = Img::process($abs, $kind); } catch (\Throwable $e) { $res = ['ok'=>false, 'error'=>mb_substr($e->getMessage(), 0, 150)]; }
      $row = ['orig_bytes'=>$res['before'] ?? 0, 'opt_bytes'=>$res['after'] ?? ($res['before'] ?? 0), 'width'=>$res['w'] ?? null, 'height'=>$res['h'] ?? null, 'format'=>$res['format'] ?? null, 'status'=>$res['ok'] ? 'optimized' : 'failed', 'error'=>$res['ok'] ? null : mb_substr((string)$res['error'], 0, 150), 'optimized_at'=>now(), 'updated_at'=>now()];
      DB::table('media_images')->updateOrInsert(['path'=>$f], $row + ['created_at'=>now()]);
      if ($res['ok']) $saved += max(0, ($res['before'] ?? 0) - ($res['after'] ?? 0)); else $fail++;
      $n++;
    }
    $left = max(0, count($todo) - $n);
    return response()->json(['ok'=>true, 'processed'=>$n, 'failed'=>$fail, 'saved'=>$saved, 'remaining'=>$left]);
  }
  public function retry() { DB::table('media_images')->where('status', 'failed')->delete(); return response()->json(['ok'=>true]); }
}
