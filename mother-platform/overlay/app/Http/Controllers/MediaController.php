<?php
namespace App\Http\Controllers;
use App\Support\Img;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
/** Image Optimization tool + Media Library (admin). Existing files are optimised in small batches so memory stays low. */
class MediaController extends Controller {
  private function files(): array { return Img::localFiles(); }
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
    @set_time_limit(50); return response()->json(['ok'=>true] + Img::runBatch(12, 35));
  }
  /** Product photos with big empty margins -> crop to the product, centre on a 600x600 frame, rebuild webp + thumbs. Cursor on product id; originals are backed up first. dry=1 only reports. */
  public function trim(Request $r) {
    if (!Img::ok()) return response()->json(['ok'=>false, 'error'=>'GD is not enabled'], 503);
    @set_time_limit(55); @ini_set('memory_limit', '768M');
    $after = (int)$r->input('after', 0); $dry = $r->boolean('dry'); $lim = max(1, min(60, (int)$r->input('limit', 12))); $t0 = microtime(true);
    $rows = DB::table('products')->where('id', '>', $after)->where('image', 'like', '/uploads/%')->orderBy('id')->limit($lim)->get(['id', 'image']);
    $out = ['ok'=>true, 'seen'=>0, 'trim'=>0, 'skip'=>0, 'fail'=>0, 'next'=>$after, 'samples'=>[]]; static $doneFiles = [];
    foreach ($rows as $row) {
      if (microtime(true) - $t0 > 38) break;
      $out['seen']++; $out['next'] = $row->id; $rel = ltrim(strtok($row->image, '?'), '/'); $abs = public_path($rel);
      if (isset($doneFiles[$rel]) || !is_file($abs) || !preg_match('/\.(jpe?g|png|webp)$/i', $abs)) { $out['skip']++; continue; }
      $doneFiles[$rel] = 1;
      try {
        if ($dry) {
          $info = @getimagesize($abs); if (!$info || $info[0] * $info[1] > 60000000) { $out['skip']++; continue; }
          $src = match ($info[2]) { IMAGETYPE_JPEG => @imagecreatefromjpeg($abs), IMAGETYPE_PNG => @imagecreatefrompng($abs), default => @imagecreatefromwebp($abs) };
          if (!$src) { $out['fail']++; continue; }
          $bx = Img::trimBox($src, $info[0], $info[1]); imagedestroy($src);
          if ($bx) { $out['trim']++; if (count($out['samples']) < 8) $out['samples'][] = [$rel, $info[0].'x'.$info[1], $bx]; } else $out['skip']++;
          continue;
        }
        $bk = storage_path('app/img-originals'); if (!is_dir($bk)) @mkdir($bk, 0775, true);
        $tgt = $bk.'/'.str_replace('/', '__', $rel); $had = is_file($tgt); if (!$had) @copy($abs, $tgt);
        $res = Img::process($abs, 'product');
        if (!empty($res['ok']) && !empty($res['trimmed'])) { $out['trim']++; if (count($out['samples']) < 8) $out['samples'][] = [$rel, ($res['w'] ?? 0).'x'.($res['h'] ?? 0)]; }
        else { $out['skip']++; if (!$had) @unlink($tgt); if (empty($res['ok'])) $out['fail']++; }
      } catch (\Throwable $e) { $out['fail']++; }
    }
    $out['left'] = DB::table('products')->where('id', '>', $out['next'])->where('image', 'like', '/uploads/%')->count();
    return response()->json($out);
  }
  public function retry() { DB::table('media_images')->where('status', 'failed')->delete(); return response()->json(['ok'=>true]); }
}
