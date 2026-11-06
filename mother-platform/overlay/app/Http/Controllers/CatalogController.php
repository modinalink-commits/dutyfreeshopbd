<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
class CatalogController extends Controller {
  public function products(Request $r) {
    $q = DB::table('products as p')->leftJoin('categories as c','c.id','=','p.category_id')->leftJoin('brands as b','b.id','=','p.brand_id')->select('p.*','c.name as category','b.name as brand')->orderByDesc('p.id');
    if ($r->filled('q')) $q->where(fn($w)=>\App\Support\SkuSearch::orSku($w->where('p.name','ilike','%'.$r->q.'%')->orWhere('p.sku','ilike','%'.$r->q.'%'), (string)$r->q, 'p.sku'));
    if ($r->filled('published')) $q->where('p.published', $r->published === '1');
    if ($r->boolean('missing_image')) $q->where(fn($w)=>$w->whereNull('p.image')->orWhere('p.image',''));
    if ($r->input('stock') === 'low') $q->whereBetween('p.stock',[1,5]);
    if ($r->input('stock') === 'out') $q->where('p.stock','<=',0);
    if ($r->filled('category_id')) $q->where('p.category_id', (int)$r->category_id);
    if ($r->filled('brand_id')) $q->where('p.brand_id', (int)$r->brand_id);
    if ($r->filled('sub')) $q->where('p.sub', (string)$r->sub);
    if ($r->filled('sub_sub')) $q->where('p.sub_sub', (string)$r->sub_sub);
    $all = DB::table('products')->count(); $act = DB::table('products')->where('published',true)->count();
    $stats = [['Products', number_format($all), 'all stores'], ['Active', number_format($act), $all ? round($act/$all*100).'%' : '0%'], ['Low stock', DB::table('products')->whereBetween('stock',[1,5])->count(), 'needs buying'], ['Out of stock', DB::table('products')->where('stock','<=',0)->count(), 'hidden on site'], ['Reviews waiting', DB::table('reviews')->where('status','pending')->count(), 'approve']];
    $smsOn = (bool) DB::table('settings')->where('key','owner_phone')->value('value');
    return view('products', ['products'=>$q->paginate(\App\Support\Pg::per(50))->withQueryString(), 'stats'=>$stats, 'cats'=>DB::table('categories')->orderBy('name')->get(), 'brands'=>DB::table('brands')->orderBy('position')->get(), 'smsOn'=>$smsOn, 'subTree'=>$this->subTree()]);
  }
  /** Sub and child category names per main category, for the catalog filter (category tree nodes + names really used on products). */
  private function subTree(): array {
    $t = [];
    $nd = DB::table('category_nodes')->where('active', true)->orderBy('position')->orderBy('name')->get(['id', 'category_id', 'parent_id', 'level', 'name']);
    foreach ($nd->where('level', 2) as $n) { $t[$n->category_id][$n->name] = $nd->where('parent_id', $n->id)->pluck('name')->all(); }
    foreach (DB::table('products')->whereNotNull('sub')->where('sub', '!=', '')->select('category_id', 'sub', 'sub_sub')->distinct()->get() as $r) {
      $t[$r->category_id][$r->sub] = $t[$r->category_id][$r->sub] ?? [];
      if ($r->sub_sub && !in_array($r->sub_sub, $t[$r->category_id][$r->sub], true)) $t[$r->category_id][$r->sub][] = $r->sub_sub;
    }
    return $t;
  }
  public function productForm($id = null) {
    $p = $id ? DB::table('products')->find($id) : null;
    if ($id) abort_unless($p, 404);
    $cats = DB::table('categories')->orderBy('position')->orderBy('name')->get();
    $mains = $cats->whereNull('parent_id')->values();
    if ($p && $p->category_id && !$mains->contains('id', $p->category_id)) $mains->push($cats->firstWhere('id', $p->category_id));
    $map = [];
    foreach ($mains->filter() as $m) {
      $subs = [];
      foreach ($cats->where('parent_id', $m->id) as $ch) {
        $subs[$ch->name] = $cats->where('parent_id', $ch->id)->pluck('name')->all();
      }
      foreach (DB::table('products')->where('category_id', $m->id)->whereNotNull('sub')->where('sub', '!=', '')->select('sub', 'sub_sub')->distinct()->get() as $r) {
        $subs[$r->sub] = $subs[$r->sub] ?? [];
        if ($r->sub_sub && !in_array($r->sub_sub, $subs[$r->sub], true)) $subs[$r->sub][] = $r->sub_sub;
      }
      $nd = DB::table('category_nodes')->where('category_id', $m->id)->orderBy('position')->orderBy('name')->get(); $ord = [];
      foreach ($nd->where('level', 2)->where('active', true) as $n2) { $ord[$n2->name] = $nd->where('parent_id', $n2->id)->where('active', true)->pluck('name')->all(); foreach ($subs[$n2->name] ?? [] as $x) if (!in_array($x, $ord[$n2->name], true)) $ord[$n2->name][] = $x; }
      foreach ($subs as $k => $v) if (!isset($ord[$k])) $ord[$k] = $v;
      $subs = $ord;
      $map[$m->id] = ['slug' => $m->slug, 'subs' => $subs];
    }
    $imgs = $id ? DB::table('product_images')->where('product_id', $id)->orderBy('position')->orderBy('id')->pluck('path')->all() : [];
    if (!$imgs && $p && $p->image) $imgs = [$p->image];
    $vars = $id ? DB::table('product_variants')->where('product_id', $id)->orderBy('id')->get()->map(function ($v) {
      $o = json_decode($v->options ?? '', true) ?: []; $k = array_key_first($o);
      return ['attr' => $k ?? 'Size', 'value' => $k !== null ? (string)$o[$k] : '', 'sku' => $v->sku, 'price' => $v->price, 'stock' => $v->stock];
    })->all() : [];
    $ai = null;
    if (!$id && request()->filled('ai')) { $sc = DB::table('ai_product_scans')->find((int)request('ai')); if ($sc && ((int)$sc->user_id === (int)auth()->id() || \App\Support\ProfitFloor::admin()) && in_array($sc->status, ['accepted', 'scanned', 'saved'], true) && !$sc->product_id) $ai = \App\Support\ProductAutoFillService::prefill($sc) + ['_scan'=>$sc->id]; }
    $canon = ($p && $p->published) ? url(\App\Http\Controllers\StoreController::url($p)) : null;
    return view('product_form', ['p'=>$p, 'mains'=>$mains->filter()->values(), 'map'=>$map, 'brands'=>DB::table('brands')->orderBy('position')->orderBy('name')->get(), 'imgs'=>$imgs, 'vars'=>$vars, 'ai'=>$ai, 'canon'=>$canon, 'host'=>request()->getHost()]);
  }
  public function productSave(Request $r, $id = null) {
    $m = fn($min = 0) => 'nullable|numeric|min:'.$min;
    $d = $r->validate([
      'name'=>'required|string|max:200', 'sku'=>'nullable|string|max:60|unique:products,sku'.($id ? ','.$id : ''),
      'category_id'=>'required|integer|exists:categories,id', 'sub'=>'nullable|string|max:120', 'sub_sub'=>'nullable|string|max:120', 'brand_id'=>'nullable|integer',
      'video_url'=>'nullable|string|max:500', 'affiliate_commission'=>'nullable|numeric|min:0|max:100',
      'regular_price'=>'required|numeric|min:0', 'sale_price'=>'nullable|numeric|min:0|lte:regular_price',
      'bundle_rule'=>'nullable|in:global,custom,off', 'buy1_price'=>$m(), 'buy2_price'=>$m(), 'buy3_price'=>$m(), 'buy4_price'=>$m(), 'buy5_price'=>$m(),
      'stock'=>'required|integer|min:0', 'stock_status'=>'required|in:in_stock,out_of_stock,pre_order', 'low_stock_alert'=>'nullable|integer|min:0',
      'description'=>'nullable|string|max:60000', 'slug'=>'nullable|string|max:200', 'meta_title'=>'nullable|string|max:200', 'meta_description'=>'nullable|string|max:500', 'meta_keywords'=>'nullable|string|max:500',
      'status'=>'nullable|in:draft,published', 'act'=>'nullable|in:draft,publish,preview',
      'images'=>'nullable|array|max:6', 'images.*'=>'string|max:500',
      'has_variants'=>'nullable', 'v'=>'nullable|array|max:100',
    ]);
    $regular = (float)$d['regular_price']; $sale = ($d['sale_price'] ?? null) !== null && (float)$d['sale_price'] < $regular ? (float)$d['sale_price'] : null;
    $row = [
      'name'=>trim($d['name']), 'sku'=>($d['sku'] ?? null) ?: null, 'category_id'=>(int)$d['category_id'],
      'sub'=>trim((string)($d['sub'] ?? '')) ?: null, 'sub_sub'=>trim((string)($d['sub_sub'] ?? '')) ?: null, 'brand_id'=>($d['brand_id'] ?? null) ?: null,
      'video_url'=>($d['video_url'] ?? null) ?: null, 'affiliate_commission'=>$d['affiliate_commission'] ?? null,
      'price'=>$sale ?? $regular, 'compare_price'=>$sale !== null ? $regular : null,
      'stock'=>$d['stock_status'] === 'out_of_stock' ? 0 : (int)$d['stock'], 'stock_status'=>$d['stock_status'], 'low_stock_alert'=>$d['low_stock_alert'] ?? null,
      'description'=>\App\Support\Html::clean($d['description'] ?? ''),
      'meta_title'=>($d['meta_title'] ?? null) ?: null, 'meta_description'=>($d['meta_description'] ?? null) ?: null, 'meta_keywords'=>($d['meta_keywords'] ?? null) ?: null,
      'updated_at'=>now(),
    ];
    $__g = \App\Support\Seo::gen((object)['name'=>$row['name'], 'price'=>$row['price'], 'sub'=>$row['sub']], DB::table('categories')->where('id', $row['category_id'])->value('name'), $row['brand_id'] ? DB::table('brands')->where('id', $row['brand_id'])->value('name') : null);
    foreach ($__g as $__k => $__v) if (empty($row[$__k])) $row[$__k] = $__v;
    foreach ([1,2,3,4,5] as $n) $row["buy{$n}_price"] = ($d["buy{$n}_price"] ?? null) !== null && $d["buy{$n}_price"] !== '' ? (float)$d["buy{$n}_price"] : null;
    $old = $id ? DB::table('products')->find($id) : null;
    if ($id) abort_unless($old, 404);
    // Bundle pricing rule: global (automatic, default) | custom (typed Buy 2-5 totals) | off. Only the Admin may change it; with the global/off rule the Buy 2-5 prices are calculated, never typed.
    $bpOn = \App\Support\BundlePricing::ready(); $bpOld = $old->bundle_rule ?? 'global'; $bpRule = $bpOld;
    if ($bpOn && \App\Support\ProfitFloor::admin() && in_array($d['bundle_rule'] ?? '', ['global', 'custom', 'off'], true)) $bpRule = $d['bundle_rule'];
    if ($bpOn) { $row['bundle_rule'] = $bpRule; if ($bpRule !== 'custom') foreach ([2,3,4,5] as $n) $row["buy{$n}_price"] = $old->{"buy{$n}_price"} ?? null; }
    // Protected 15% company profit: purchase price x 1.15 is the floor for the selling price and the Buy 2-5 pack prices. An old product whose prices are untouched can still be saved; it stays on the price-review list.
    $pfCost = $old ? \App\Support\ProfitFloor::base($old) : 0.0; $pfPacks = []; if (!$bpOn || $bpRule === 'custom') foreach ([2,3,4,5] as $n) $pfPacks[$n] = $row["buy{$n}_price"];
    $pfSame = $old && abs((float)$old->price - (float)$row['price']) < 0.005 && (!$pfPacks || collect([2,3,4,5])->every(fn($n) => abs((float)($old->{"buy{$n}_price"} ?? 0) - (float)($row["buy{$n}_price"] ?? 0)) < 0.005));
    if (!$pfSame && ($pfE = \App\Support\ProfitFloor::productError($pfCost, (float)$row['price'], $pfPacks))) {
      \App\Support\ProfitFloor::audit('Profit floor blocked price', 'Product '.($old->name ?? $row['name']), $old ? ['price'=>$old->price] : null, ['price'=>$row['price'], 'cost'=>$pfCost]);
      return back()->withInput($r->except(['description', '_token']))->withErrors(['regular_price'=>$pfE]);
    }
    if ($old && abs((float)$old->price - (float)$row['price']) > 0.004) \App\Support\ProfitFloor::audit('Selling price changed', 'Product '.$old->name, ['price'=>$old->price, 'buy'=>[$old->buy2_price, $old->buy3_price, $old->buy4_price, $old->buy5_price]], ['price'=>$row['price'], 'buy'=>[$row['buy2_price'], $row['buy3_price'], $row['buy4_price'], $row['buy5_price']]]);
    // AI-created product: second duplicate check right before saving (a repeated Save with dup_ok=1 confirms it is a different product)
    if (!$id && $r->filled('ai_scan') && \App\Support\ProductAutoFillService::on('dup') && $r->input('dup_ok') !== '1') {
      $dm = \App\Support\ProductMatchingService::strong(['name'=>$row['name'], 'sku'=>$row['sku'], 'brand'=>$row['brand_id'] ? DB::table('brands')->where('id', $row['brand_id'])->value('name') : null]);
      if ($dm) return back()->withInput(array_merge($r->except(['description', '_token']), ['dup_ok'=>'1']))->withErrors(['name'=>'Possible duplicate: '.implode('; ', array_map(fn($m) => $m['name'].' ('.($m['sku'] ?: 'no SKU').', '.$m['status'].')', $dm)).'. If this is a different product, press Save again.']);
    }
    // URL slug, unique inside main category + sub category
    $base = \App\Support\Slugs::slug(($d['slug'] ?? null) ?: $row['name']) ?: 'product';
    $subSlug = \App\Support\Slugs::subSlug($row['sub']); $slug = $base; $i = 2;
    while (DB::table('products')->where('category_id', $row['category_id'])->when($id, fn($q) => $q->where('id', '!=', $id))->whereRaw(\App\Support\Slugs::subSql().' = ?', [$subSlug])->where('path_slug', $slug)->exists()) $slug = $base.'-'.$i++;
    $row['slug'] = $base; $row['path_slug'] = $slug;
    // publish state
    $act = $d['act'] ?? 'publish';
    $pub = $act === 'draft' ? false : ($act === 'publish' && !$id ? true : (($d['status'] ?? ($old && $old->published ? 'published' : 'draft')) === 'published'));
    if ($act === 'preview' && !$id) $pub = false;
    $row['published'] = $pub; $row += \App\Support\StockGuard::lockCol($pub); // manual unpublish lock: a hand-made unpublish is never undone by stock-in
    // images
    $imgs = array_values(array_filter(array_map('trim', $d['images'] ?? []), fn($u) => preg_match('#^(https?://|/)#', $u)));
    $row['image'] = $imgs[0] ?? ($old->image ?? null);
    if ($d && empty($imgs) && $id) $row['image'] = null;
    DB::transaction(function () use (&$id, $row, $imgs, $old, $r, $pub) {
      if ($id) DB::table('products')->where('id', $id)->update($row);
      else { $row['created_at'] = now(); $id = DB::table('products')->insertGetId($row); }
      DB::table('product_images')->where('product_id', $id)->delete();
      foreach ($imgs as $k => $u) DB::table('product_images')->insert(['product_id'=>$id, 'path'=>$u, 'position'=>$k, 'created_at'=>now(), 'updated_at'=>now()]);
      DB::table('product_variants')->where('product_id', $id)->delete();
      if ($r->boolean('has_variants')) foreach ((array)$r->input('v', []) as $v) {
        $val = trim((string)($v['value'] ?? '')); if ($val === '') continue;
        $attr = ($v['attr'] ?? 'Size') === 'Custom' ? (trim((string)($v['custom'] ?? '')) ?: 'Option') : (string)($v['attr'] ?? 'Size');
        DB::table('product_variants')->insert(['product_id'=>$id, 'sku'=>($v['sku'] ?? null) ?: null, 'options'=>json_encode([$attr => $val], JSON_UNESCAPED_UNICODE), 'price'=>($v['price'] ?? '') !== '' ? (float)$v['price'] : null, 'stock'=>(int)($v['stock'] ?? 0), 'created_at'=>now(), 'updated_at'=>now()]);
      }
      $was = $old ? (bool)$old->published : false;
      if ($pub !== $was) DB::table('product_status_logs')->insert(['product_id'=>$id, 'user_id'=>Auth::id(), 'user_name'=>Auth::user()->name, 'action'=>$pub ? 'Published' : 'Unpublished', 'created_at'=>now(), 'updated_at'=>now()]);
    });
    try { if ($bpOn) { \App\Support\BundlePricing::sync([(int)$id]); if ($old && $bpOld !== $bpRule) \App\Support\ProfitFloor::audit('Bundle rule changed', 'Product '.$row['name'], ['rule'=>$bpOld], ['rule'=>$bpRule, 'by'=>Auth::user()->name ?? null]); } } catch (\Throwable $e) { report($e); }
    try { if ($old && (int)$old->stock !== (int)$row['stock']) { $d0 = \App\Support\Costing::sync((int)$id); if ($d0 !== 0) \App\Support\Procure::audit('costing.manual_adjustment', 'Manual stock edit: '.$row['name'], ['stock'=>(int)$old->stock], ['stock'=>(int)$row['stock'], 'batches_adjusted'=>$d0, 'by'=>Auth::user()->name ?? null]); } elseif (!$old && (int)$row['stock'] > 0) \App\Support\Costing::sync((int)$id); } catch (\Throwable $e) { report($e); }
    if (!$old && $r->filled('ai_scan')) { try {
      $sc = DB::table('ai_product_scans')->find((int)$r->input('ai_scan'));
      if ($sc && !$sc->product_id) {
        $pf = \App\Support\ProductAutoFillService::prefill($sc); $edits = []; foreach (['name', 'sku', 'brand_id', 'category_id', 'sub', 'sub_sub', 'description', 'meta_title', 'meta_description', 'meta_keywords'] as $k) { $fv = $row[$k] ?? null; $pv = $pf[$k] ?? null; if (trim((string)$fv) !== trim((string)$pv)) $edits[$k] = ['ai'=>$pv, 'staff'=>$fv]; }
        DB::table('ai_product_scans')->where('id', $sc->id)->update(['status'=>'saved', 'product_id'=>$id, 'final'=>json_encode(['name'=>$row['name'], 'sku'=>$row['sku'], 'category_id'=>$row['category_id'], 'sub'=>$row['sub'], 'sub_sub'=>$row['sub_sub'], 'brand_id'=>$row['brand_id'], 'price'=>$row['price'], 'published'=>$pub], JSON_UNESCAPED_UNICODE), 'edits'=>$edits ? json_encode($edits, JSON_UNESCAPED_UNICODE) : $sc->edits, 'saved_at'=>now(), 'updated_at'=>now()]);
        \App\Support\Procure::audit('ai.product_created', 'Product created from image: '.$row['name'].' ('.($row['sku'] ?: 'no SKU').')', ['scan'=>$sc->id, 'detected'=>json_decode((string)$sc->detected, true)['fields'] ?? null], ['product_id'=>$id, 'staff_edits'=>array_keys($edits)]);
      }
    } catch (\Throwable $e) { report($e); } }
    if ((bool)($old->published ?? false) !== $pub && ($old || $pub)) $this->notifyStatus([$row['name']], $pub);
    \Illuminate\Support\Facades\Cache::forget('store.home6'); \Illuminate\Support\Facades\Cache::forget('store.sitemap5');
    if ($act === 'preview') return redirect('/admin/products/'.$id.'/preview');
    return redirect($act === 'draft' ? '/admin/products/'.$id.'/edit?saved=1' : '/admin/products/'.$id.'/edit?saved=1');
  }
  public function productPreview($id) {
    abort_unless(DB::table('products')->find($id), 404);
    return app(\App\Http\Controllers\StoreController::class)->previewProduct((int)$id);
  }
  public function productToggle($id) {
    $p = DB::table('products')->find($id); abort_unless($p, 404);
    $new = !$p->published;
    DB::transaction(function () use ($id, $new) {
      DB::table('products')->where('id',$id)->update(['published'=>$new,'updated_at'=>now()] + \App\Support\StockGuard::lockCol($new));
      DB::table('product_status_logs')->insert(['product_id'=>$id,'user_id'=>Auth::id(),'user_name'=>Auth::user()->name,'action'=>$new?'Published':'Unpublished','created_at'=>now(),'updated_at'=>now()]);
    });
    $this->notifyStatus([$p->name], $new);
    return back();
  }
  private function notifyStatus(array $names, bool $published) {
    $set = DB::table('settings')->whereIn('key',['owner_phone','manager_phones','store_name'])->pluck('value','key');
    $to = array_filter(array_map('trim', array_merge([$set['owner_phone'] ?? ''], explode(',', $set['manager_phones'] ?? ''))));
    if (!$to) return;
    $what = count($names) === 1 ? '"'.$names[0].'"' : count($names).' products';
    $body = "Product $what was ".($published ? 'PUBLISHED' : 'UNPUBLISHED').' by '.Auth::user()->name.' at '.now('Asia/Dhaka')->format('H:i').'. Store: '.($set['store_name'] ?? 'DutyFreeBD').'.';
    foreach ($to as $ph) \App\Support\Sms::queue($ph, $body, $published ? 'product_published' : 'product_unpublished');
  }
  public function productBulk(Request $r) {
    $d = $r->validate(['ids'=>'required|array|max:500','ids.*'=>'integer','action'=>'required|in:publish,unpublish']);
    $new = $d['action'] === 'publish'; $names = [];
    DB::transaction(function () use ($d, $new, &$names) {
      foreach (DB::table('products')->whereIn('id',$d['ids'])->where('published','!=',$new)->get() as $p) {
        DB::table('products')->where('id',$p->id)->update(['published'=>$new,'updated_at'=>now()] + \App\Support\StockGuard::lockCol($new));
        DB::table('product_status_logs')->insert(['product_id'=>$p->id,'user_id'=>Auth::id(),'user_name'=>Auth::user()->name,'action'=>$new?'Published':'Unpublished','created_at'=>now(),'updated_at'=>now()]);
        $names[] = $p->name;
      }
    });
    if ($names) $this->notifyStatus($names, $new);
    return back()->with('ok', count($names).' products '.($new?'published':'unpublished'));
  }
  // Fill missing/invalid Buy 2-5 bundle prices from the sale price (2/3/4/5 %). Valid bundle prices already set are kept. Dry run unless apply=1; old values saved to storage/logs for rollback.
  public function bundleFill(Request $r) {
    abort_unless(Auth::check(), 403);
    $apply = $r->boolean('apply'); $force = $r->boolean('force'); $pc = [2=>2.0, 3=>3.0, 4=>4.0, 5=>5.0];
    $stat = ['total'=>0, 'kept'=>0, 'filled'=>0, 'skipped'=>0]; $undo = []; $sample = [];
    $rows = DB::table('products')->get(['id','name','price','buy2_price','buy3_price','buy4_price','buy5_price']);
    foreach ($rows as $p) {
      $stat['total']++; $u = (float)$p->price;
      if ($u < 20 || $p->id == 1326) { $stat['skipped']++; continue; }
      $new = []; $prev = null;
      foreach ($pc as $n => $x) {
        $t = (float)$p->{"buy{$n}_price"};
        if ($force || !($t > 0 && $t < $u * $n)) {
          $tot = $u * $n; $raw = $tot * (1 - $x / 100); $step = $tot >= 2000 ? 10 : ($tot >= 500 ? 5 : 1);
          $t = (float)(floor($raw / $step) * $step);
          if ($prev !== null) $t = min($t, floor($prev / ($n - 1) * $n));
          $new["buy{$n}_price"] = $t;
        }
        $prev = $t;
      }
      if (!$new) { $stat['kept']++; continue; }
      $stat['filled']++;
      if (count($sample) < 6) $sample[] = ['id'=>$p->id, 'name'=>mb_substr($p->name, 0, 30), 'price'=>$u] + $new;
      if ($apply) {
        $undo[$p->id] = ['buy2_price'=>$p->buy2_price, 'buy3_price'=>$p->buy3_price, 'buy4_price'=>$p->buy4_price, 'buy5_price'=>$p->buy5_price];
        DB::table('products')->where('id', $p->id)->update($new + ['updated_at'=>now()]);
      }
    }
    if ($apply && $undo) @file_put_contents(storage_path('logs/bundle-fill-'.date('Ymd-His').'.json'), json_encode($undo));
    return response()->json(['applied'=>$apply] + $stat + ['sample'=>$sample]);
  }
  public function productImport(Request $r) {
    $r->validate(['file'=>'required|file|max:10240']);
    $fh = fopen($r->file('file')->getRealPath(), 'r'); $head = fgetcsv($fh); if (!$head) return back()->withErrors(['file'=>'Empty file']);
    $head = array_map(fn($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', $h))), $head);
    $n = 0; $bad = 0;
    $cat = fn($name) => $name === '' ? null : (DB::table('categories')->where('name',$name)->value('id') ?? DB::table('categories')->insertGetId(['name'=>$name,'created_at'=>now(),'updated_at'=>now()]));
    $brand = fn($name) => $name === '' ? null : (DB::table('brands')->where('name',$name)->value('id') ?? DB::table('brands')->insertGetId(['name'=>$name,'created_at'=>now(),'updated_at'=>now()]));
    while (($row = fgetcsv($fh)) !== false) {
      if (count($row) < count($head)) { $bad++; continue; }
      $x = array_combine($head, array_slice($row, 0, count($head)));
      if (empty($x['name']) || !is_numeric($x['price'] ?? null)) { $bad++; continue; }
      $data = ['name'=>mb_substr($x['name'],0,200), 'price'=>$x['price'], 'cost'=>is_numeric($x['cost'] ?? null) ? $x['cost'] : 0, 'offer_price'=>is_numeric($x['offer_price'] ?? null) ? $x['offer_price'] : null, 'stock'=>(int)($x['stock'] ?? 0), 'barcode'=>$x['barcode'] ?? null, 'size'=>$x['size'] ?? null, 'category_id'=>$cat(trim($x['category'] ?? '')), 'brand_id'=>$brand(trim($x['brand'] ?? '')), 'updated_at'=>now()];
      if (\App\Support\ProfitFloor::hasAvg() && is_numeric($x['cost'] ?? null) && (float)$x['cost'] > 0) $data['avg_cost'] = (float)$x['cost']; // a cost typed in an import is the owner's stated average
      $sku = trim($x['sku'] ?? '');
      if ($sku !== '' && DB::table('products')->where('sku',$sku)->exists()) DB::table('products')->where('sku',$sku)->update($data);
      else DB::table('products')->insert($data + ['sku'=>$sku ?: null, 'slug'=>\Illuminate\Support\Str::slug($data['name']), 'published'=>false, 'created_at'=>now()]);
      $n++;
    }
    fclose($fh); \App\Support\Slugs::assignAll();
    return back()->with('ok', "$n products imported or updated".($bad ? ", $bad rows skipped" : '').'. Imported products stay unpublished until you publish them.');
  }
  public function importTemplate() {
    return response()->streamDownload(function () { $o = fopen('php://output','w'); fwrite($o, "\xEF\xBB\xBF"); fputcsv($o, ['name','sku','barcode','category','brand','size','cost','price','offer_price','stock']); fputcsv($o, ['Sample product','SKU-001','','Perfume','Brand X','100 ml','3100','4400','4299','10']); fclose($o); }, 'product-import-template.csv', ['Content-Type'=>'text/csv; charset=utf-8']);
  }
  public function taxonomy() {
    $pc = DB::table('products')->select('category_id', DB::raw('count(*) c'))->groupBy('category_id')->pluck('c','category_id');
    $bc = DB::table('products')->select('brand_id', DB::raw('count(*) c'))->groupBy('brand_id')->pluck('c','brand_id');
    return view('taxonomy', ['cats'=>DB::table('categories')->orderBy('position')->orderBy('name')->get(), 'brands'=>DB::table('brands')->orderBy('position')->get(), 'pc'=>$pc, 'bc'=>$bc]);
  }
  public function categorySave(Request $r) {
    $d = $r->validate(['name'=>'required|string|max:120','parent_id'=>'nullable|integer','image'=>'nullable|string|max:500','position'=>'nullable|integer']);
    if (!empty($d['parent_id'])) { $depth = 1; $pid = $d['parent_id']; while ($pid && $depth < 5) { $pid = DB::table('categories')->where('id',$pid)->value('parent_id'); $depth++; } if ($depth > 3) return back()->withErrors(['name'=>'Only 3 levels: category, sub category, sub sub category']); }
    $d['position'] = $d['position'] ?? 0;
    DB::table('categories')->insert($d + ['created_at'=>now(),'updated_at'=>now()]); return back();
  }
  public function categoryToggle($id) { DB::table('categories')->where('id',$id)->update(['active'=>DB::raw('not active'),'updated_at'=>now()]); return back();
  }
  public function brandToggle($id) { DB::table('brands')->where('id',$id)->update(['active'=>DB::raw('not active'),'updated_at'=>now()]); return back();
  }
  public function brandSave(Request $r) {
    $d = $r->validate(['name'=>'required|string|max:120','position'=>'nullable|integer']);
    DB::table('brands')->insert(['name'=>$d['name'],'position'=>$d['position']??0,'created_at'=>now(),'updated_at'=>now()]); return back();
  }
  public function productsCsv() {
    return response()->streamDownload(function () {
      $f = fopen('php://output','w'); fputcsv($f,['ID','Name','SKU','Price','Cost','Stock','Published']);
      foreach (DB::table('products')->orderBy('id')->cursor() as $p) fputcsv($f,[$p->id,$p->name,$p->sku,$p->price,$p->cost,$p->stock,$p->published?'yes':'no']);
      fclose($f);
    }, 'products.csv', ['Content-Type'=>'text/csv']);
  }
  public function seoAutofill(Request $r) {
    $res = \App\Support\Seo::fillAll($r->input('mode') === 'all');
    DB::table('audit_logs')->insert(['user_id'=>auth()->id(), 'user_name'=>auth()->user()->name, 'action'=>'seo.autofill', 'subject'=>'Auto-filled SEO for '.$res['updated'].' products ('.($r->input('mode') === 'all' ? 'overwrite' : 'empty only').')', 'ip'=>$r->ip(), 'created_at'=>now(), 'updated_at'=>now()]);
    return back()->with('ok', 'SEO auto-filled for '.$res['updated'].' of '.$res['total'].' products ('.($r->input('mode') === 'all' ? 'all fields rewritten' : 'only empty fields filled').'); '.$res['slugs'].' missing URL slugs created.');
  }
}
