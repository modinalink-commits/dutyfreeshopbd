<?php
namespace App\Http\Controllers;
use App\Support\AiImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class AiImageController extends Controller {
  private function flush(): void { foreach (['store.home2','store.home4','store.home6','store.home5','store.cats','store.live2','store.live3','store.live4'] as $k) \Illuminate\Support\Facades\Cache::forget($k); }
  public function index(Request $r) {
    $q = trim((string)$r->query('q', '')); $f = $r->query('f', 'all');
    $cat = (string)$r->query('cat', '');
    $cats = DB::table('categories')->orderBy('name')->get(['id', 'slug', 'name']);
    $smokeIds = $cats->filter(fn($x) => \App\Http\Controllers\StoreController::isSmokeCat($x))->pluck('id')->all();
    $catName = $cats->pluck('name', 'id')->all();
    $prods = DB::table('products')->when($q !== '', function ($w) use ($q) { foreach (array_slice(preg_split('/[\s()\[\],]+/u', $q, -1, PREG_SPLIT_NO_EMPTY), 0, 8) as $tk) { if (!preg_match('/[\p{L}\p{N}]/u', $tk)) continue; $l = '%'.str_replace(['\\','%','_'], ['\\\\','\\%','\\_'], $tk).'%'; $w->where(fn($x) => $x->where('name', 'ilike', $l)->orWhere('sku', 'ilike', $l)); } })
      ->when($cat === 'smoke', fn($w) => $w->where(function ($x) use ($smokeIds) { $x->where('group_key', 'smoke'); if ($smokeIds) $x->orWhereIn('category_id', $smokeIds); }))
      ->when($cat !== '' && $cat !== 'smoke' && ctype_digit($cat), fn($w) => $w->where('category_id', (int)$cat))->whereNotNull('image')->where('image', '<>', '')->orderByDesc('id')->paginate(\App\Support\Pg::per(24))->withQueryString();
    $ids = collect($prods->items())->pluck('id')->all();
    $gal = $ids ? DB::table('product_images')->whereIn('product_id', $ids)->orderBy('position')->get()->groupBy('product_id') : collect();
    $recs = $ids ? DB::table('ai_images')->whereIn('product_id', $ids)->get() : collect();
    $slots = [];
    foreach ($prods->items() as $p) {
      $urls = array_values(array_unique(array_filter(array_merge([$p->image], ($gal[$p->id] ?? collect())->pluck('path')->all()))));
      foreach ($urls as $u) {
        $rec = $recs->first(fn($x) => $x->product_id == $p->id && ($x->source_hash === sha1($u) || $x->processed_image === parse_url($u, PHP_URL_PATH)));
        $slots[] = ['p'=>$p, 'url'=>$u, 'rec'=>$rec, 'main'=>$u === $p->image];
      }
    }
    if ($f !== 'all') $slots = array_values(array_filter($slots, fn($s) => ($s['rec']->ai_status ?? 'pending') === $f));
    $counts = DB::table('ai_images')->select('ai_status', DB::raw('count(*) n'))->groupBy('ai_status')->pluck('n', 'ai_status');
    $key = AiImage::key();
    return view('ai_images', ['slots'=>$slots, 'prods'=>$prods, 'q'=>$q, 'f'=>$f, 'counts'=>$counts, 'hasKey'=>(bool)$key, 'keyMask'=>$key ? substr($key, 0, 5).'••••'.substr($key, -4) : '', 'model'=>AiImage::set('ai_img_model', AiImage::MODEL), 'quality'=>AiImage::set('ai_img_quality', 'medium'), 'prompt'=>AiImage::prompt(), 'gd'=>\App\Support\Img::ok(), 'auto'=>\App\Support\AiImageAuto::status(), 'cat'=>$cat, 'cats'=>$cats, 'catName'=>$catName, 'smokeIds'=>$smokeIds]);
  }
  public function settings(Request $r) {
    $d = $r->validate(['openai_api_key'=>'nullable|string|max:300', 'ai_img_model'=>'nullable|string|max:60', 'ai_img_quality'=>'nullable|in:low,medium,high', 'ai_img_prompt'=>'nullable|string|max:3000']);
    foreach (['openai_api_key', 'ai_img_model', 'ai_img_quality', 'ai_img_prompt'] as $k) {
      if (!array_key_exists($k, $d)) continue; $v = trim((string)$d[$k]); if ($k === 'openai_api_key' && $v === '') continue;
      if ($k === 'ai_img_prompt' && $v === '') $v = AiImage::PROMPT;
      DB::table('settings')->updateOrInsert(['key'=>$k], ['value'=>$v, 'updated_at'=>now(), 'created_at'=>now()]);
    }
    return back()->with('ok', 'AI settings saved');
  }
  public function process(Request $r) {
    $d = $r->validate(['product_id'=>'required|integer', 'url'=>'required|string|max:900']);
    abort_unless(DB::table('products')->where('id', $d['product_id'])->exists(), 404);
    $h = sha1($d['url']);
    $row = DB::table('ai_images')->where('product_id', $d['product_id'])->where(fn($w) => $w->where('source_hash', $h)->orWhere('processed_image', parse_url($d['url'], PHP_URL_PATH)))->first();
    if (!$row) { $id = DB::table('ai_images')->insertGetId(['product_id'=>$d['product_id'], 'source_hash'=>$h, 'source_url'=>$d['url'], 'ai_status'=>'pending', 'created_at'=>now(), 'updated_at'=>now()]); }
    else $id = $row->id;
    return response()->json(['id'=>$id] + AiImage::run($id));
  }
  /** Product not saved yet: clean the image anyway; the form then uses the returned file as the cover. */
  public function processNew(Request $r) {
    $d = $r->validate(['url'=>'required|string|max:900']);
    abort_unless(str_starts_with($d['url'], '/uploads/'), 422);
    $id = DB::table('ai_images')->insertGetId(['product_id'=>0, 'source_hash'=>sha1($d['url']), 'source_url'=>$d['url'], 'ai_status'=>'pending', 'created_at'=>now(), 'updated_at'=>now()]);
    return response()->json(['id'=>$id, 'new'=>true] + AiImage::run($id));
  }
  public function setMain(Request $r, $id) {
    $row = DB::table('ai_images')->find($id); abort_unless($row && $row->ai_status === 'completed' && $row->processed_image, 404);
    DB::table('products')->where('id', $row->product_id)->update(['image'=>$row->processed_image, 'updated_at'=>now()]);
    DB::table('ai_images')->where('product_id', $row->product_id)->update(['is_main'=>false]); DB::table('ai_images')->where('id', $id)->update(['is_main'=>true, 'updated_at'=>now()]);
    $this->flush(); return response()->json(['ok'=>true]);
  }
  public function download($id) {
    $row = DB::table('ai_images')->find($id); abort_unless($row && $row->processed_image, 404);
    $abs = public_path(ltrim($row->processed_image, '/')); abort_unless(is_file($abs), 404);
    return response()->download($abs, 'product-'.$row->product_id.'-600x600.jpg');
  }
  public function destroy($id) {
    $row = DB::table('ai_images')->find($id); abort_unless($row, 404);
    if ($row->is_main && $row->original_image) DB::table('products')->where('id', $row->product_id)->where('image', $row->processed_image)->update(['image'=>$row->source_url, 'updated_at'=>now()]);
    foreach ([$row->processed_image, "/uploads/ai/ai-$id-300.jpg", "/uploads/ai/ai-$id-150.jpg", "/uploads/ai/ai-$id.jpg.webp", "/uploads/ai/ai-$id-300.jpg.webp", "/uploads/ai/ai-$id-150.jpg.webp"] as $f) if ($f && is_file(public_path(ltrim($f, '/')))) @unlink(public_path(ltrim($f, '/')));
    DB::table('ai_images')->where('id', $id)->update(['processed_image'=>null, 'thumbnail_image'=>null, 'ai_status'=>'pending', 'is_main'=>false, 'processed_at'=>null, 'processing_error'=>null, 'updated_at'=>now()]);
    $this->flush(); return response()->json(['ok'=>true]);
  }
  public function thumbs(Request $r) {
    $n = 0; foreach (DB::table('ai_images')->where('ai_status', 'completed')->pluck('id') as $id) if (AiImage::thumbs((int)$id)) $n++;
    return response()->json(['ok'=>true, 'n'=>$n]);
  }
}
