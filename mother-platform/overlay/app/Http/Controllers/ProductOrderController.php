<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
/** Category-wise product position. Scope keys: m:<category id> (Main), n:<category_nodes id> (Sub or Child). */
class ProductOrderController extends Controller {
  public function index() {
    $mains = DB::table('categories')->orderBy('position')->get(['id', 'name']);
    $nodes = DB::table('category_nodes')->where('active', true)->orderBy('position')->get(['id', 'category_id', 'parent_id', 'level', 'name']);
    return view('product_order', ['mains' => $mains, 'nodes' => $nodes]);
  }
  private function products(string $scope) {
    $q = DB::table('products')->where('published', true);
    if (preg_match('/^m:(\d+)$/', $scope, $m)) $q->where('category_id', (int)$m[1]);
    elseif (preg_match('/^n:(\d+)$/', $scope, $m)) {
      $n = DB::table('category_nodes')->find((int)$m[1]); if (!$n) return null;
      $q->where('category_id', $n->category_id);
      if ($n->level == 2) $q->where('sub', $n->name);
      else { $p = DB::table('category_nodes')->find($n->parent_id); $q->where('sub', $p->name ?? '')->where('sub_sub', $n->name); }
    } else return null;
    return $q->leftJoin('product_positions as pp', fn($j) => $j->on('pp.pp_product', '=', 'products.id')->where('pp.pp_scope', '=', $scope))
      ->orderByRaw('pp.pp_pos asc nulls last')->orderByDesc('products.id')->select('products.id', 'products.name', 'products.image', 'products.price', 'pp.pp_pos as pos')->limit(1500)->get();
  }
  public function data(Request $r) {
    $rows = $this->products((string)$r->query('scope')); if ($rows === null) return response()->json(['ok' => false, 'message' => 'Choose a category'], 422);
    return response()->json(['ok' => true, 'items' => $rows->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'image' => $p->image, 'price' => (float)$p->price, 'pos' => $p->pos])]);
  }
  /** Receives the full ordered id list for the scope and stores positions 1..n. */
  public function save(Request $r) {
    $scope = (string)$r->input('scope'); $rows = $this->products($scope); if ($rows === null) return response()->json(['ok' => false, 'message' => 'Choose a category'], 422);
    $ok = $rows->pluck('id')->flip(); $ids = array_values(array_filter(array_map('intval', (array)$r->input('ids', [])), fn($i) => isset($ok[$i])));
    DB::transaction(function () use ($scope, $ids) {
      DB::table('product_positions')->where('pp_scope', $scope)->delete();
      $n = 0; foreach (array_chunk($ids, 500) as $ch) DB::table('product_positions')->insert(array_map(function ($id) use ($scope, &$n) { return ['pp_product' => $id, 'pp_scope' => $scope, 'pp_pos' => ++$n, 'created_at' => now(), 'updated_at' => now()]; }, $ch));
    });
    foreach (['store.cats', 'store.subs', 'store.home6', 'store.home5', 'store.home4'] as $k) Cache::forget($k);
    return response()->json(['ok' => true, 'saved' => count($ids)]);
  }
  public function reset(Request $r) {
    $scope = (string)$r->input('scope'); if (!preg_match('/^[mn]:\d+$/', $scope)) return response()->json(['ok' => false], 422);
    DB::table('product_positions')->where('pp_scope', $scope)->delete(); return response()->json(['ok' => true]);
  }
}
