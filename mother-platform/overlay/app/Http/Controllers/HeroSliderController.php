<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Hero Media Slider admin: image + YouTube slides in one ordered list with drag and drop sorting. */
class HeroSliderController extends Controller
{
  /** Owner 13 Nov 2026: Pause/Publish/Order on uploaded slides left the home page cache stale (only old keys were cleared), so paused banners kept showing. Same full bust the built-in toggle uses. */
  private static function bust(): void {
    foreach (['store.settings2', 'store.home11', 'store.home10', 'store.home8', 'store.home5', 'store.home6', 'store.home4'] as $k) { try { Cache::forget($k); } catch (\Throwable $e) {} }
    try { \App\Http\Controllers\StoreController::bust(); } catch (\Throwable $e) {}
  }
  public function index() {
    $rows = DB::table('sliders')->where(fn($q) => $q->whereNull('type')->orWhere('type', 'main'))->orderBy('position')->orderBy('id')->get();
    $bi = [];
    foreach (self::BUILTIN as $k => $label) { $v = DB::table('settings')->where('key', $k)->value('value'); $bi[] = ['key' => $k, 'label' => $label, 'on' => ($v ?? '1') !== '0']; }
    return view('hero_slider', ['rows' => $rows, 'bi' => $bi]);
  }
  /** The built-in hero slides that sit in the same home carousel as the uploaded ones. */
  const BUILTIN = ['hero_text_slide' => 'Text slide (flash sale headline + countdown)', 'home_globe' => 'Globe / world products slide', 'hero_offers_slide' => 'Offers slide', 'hero_watch_slide' => 'Watch slide', 'hero_member_slide' => 'Member / loyalty slide', 'hero_district_slide' => 'District delivery slide'];
  public function toggleBuiltin($key) {
    abort_unless(isset(self::BUILTIN[$key]), 404);
    $cur = DB::table('settings')->where('key', $key)->value('value');
    DB::table('settings')->updateOrInsert(['key' => $key], ['value' => ($cur ?? '1') === '0' ? '1' : '0', 'updated_at' => now(), 'created_at' => now()]);
    foreach (['store.settings2', 'store.home11', 'store.home10', 'store.home8', 'store.home5', 'store.home6', 'store.home4'] as $c) { try { Cache::forget($c); } catch (\Throwable $e) {} }
    try { \App\Http\Controllers\StoreController::bust(); } catch (\Throwable $e) {}
    return back();
  }
  public function order(Request $r) {
    $ids = array_values(array_filter(array_map('intval', (array)$r->input('ids'))));
    foreach ($ids as $i => $id) DB::table('sliders')->where('id', $id)->update(['position' => $i + 1, 'updated_at' => now()]);
    self::bust();
    return response()->json(['ok' => true, 'n' => count($ids)]);
  }
  public function toggle($id) {
    DB::table('sliders')->where('id', $id)->update(['active' => DB::raw('not active'), 'updated_at' => now()]); self::bust();
    return back();
  }
}
