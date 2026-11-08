<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Admin: one image slider per category. Slides show on the category page (and its sub category pages) above the heading. */
class CategoryBannerController extends Controller
{
  public static function slides(int $catId) {
    return Cache::remember('catbn.' . $catId, 300, function () use ($catId) {
      try { return DB::table('category_banners')->where('category_id', $catId)->where('active', true)->orderBy('position')->orderBy('id')->get(['image', 'mobile_image', 'link', 'alt'])->all(); } catch (\Throwable $e) { return []; }
    });
  }
  private static function bust(int $id): void { Cache::forget('catbn.' . $id); }
  public function index(Request $r) {
    $cats = DB::table('categories')->orderBy('name')->get(['id', 'name', 'slug']);
    $counts = DB::table('category_banners')->select('category_id', DB::raw('count(*) n'))->groupBy('category_id')->pluck('n', 'category_id');
    $cid = (int)$r->input('cat') ?: (int)($cats->first()->id ?? 0);
    $rows = $cid ? DB::table('category_banners')->where('category_id', $cid)->orderBy('position')->orderBy('id')->get() : collect();
    return view('category_banners', compact('cats', 'counts', 'cid', 'rows'));
  }
  private function save($f, string $tag): ?string {
    if (!$f || !$f->isValid()) return null;
    $ext = strtolower($f->getClientOriginalExtension() ?: 'jpg'); if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) return null;
    $dir = public_path('uploads/catbanners'); if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $name = $tag . '-' . time() . '-' . bin2hex(random_bytes(3)) . '.' . $ext; $f->move($dir, $name);
    return '/uploads/catbanners/' . $name;
  }
  public function store(Request $r) {
    $r->validate(['category_id' => 'required|integer', 'image' => 'required|file|image|max:6144', 'mobile_image' => 'nullable|file|image|max:6144', 'link' => 'nullable|string|max:300', 'alt' => 'nullable|string|max:160']);
    $cid = (int)$r->category_id; $img = $this->save($r->file('image'), 'c' . $cid); if (!$img) return back()->withErrors(['image' => 'Upload a JPG, PNG or WebP image.']);
    $mob = $this->save($r->file('mobile_image'), 'm' . $cid);
    $pos = (int)DB::table('category_banners')->where('category_id', $cid)->max('position') + 1;
    DB::table('category_banners')->insert(['category_id' => $cid, 'image' => $img, 'mobile_image' => $mob, 'link' => trim((string)$r->link) ?: null, 'alt' => trim((string)$r->alt) ?: null, 'position' => $pos, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
    self::bust($cid); return redirect('/admin/category-banners?cat=' . $cid)->with('ok', 'Slide added.');
  }
  public function toggle($id) { $b = DB::table('category_banners')->find($id); if ($b) { DB::table('category_banners')->where('id', $id)->update(['active' => !$b->active, 'updated_at' => now()]); self::bust((int)$b->category_id); } return back(); }
  public function move(Request $r, $id) {
    $b = DB::table('category_banners')->find($id); if (!$b) return back();
    $rows = DB::table('category_banners')->where('category_id', $b->category_id)->orderBy('position')->orderBy('id')->pluck('id')->all();
    $i = array_search((int)$id, array_map('intval', $rows)); $j = $r->input('dir') === 'up' ? $i - 1 : $i + 1;
    if ($i !== false && isset($rows[$j])) { [$rows[$i], $rows[$j]] = [$rows[$j], $rows[$i]]; foreach ($rows as $k => $rid) DB::table('category_banners')->where('id', $rid)->update(['position' => $k + 1]); }
    self::bust((int)$b->category_id); return back();
  }
  public function destroy($id) {
    $b = DB::table('category_banners')->find($id); if ($b) { DB::table('category_banners')->where('id', $id)->delete(); foreach ([$b->image, $b->mobile_image] as $p) if ($p && str_starts_with($p, '/uploads/catbanners/')) @unlink(public_path($p)); self::bust((int)$b->category_id); }
    return back()->with('ok', 'Slide deleted.');
  }
}
