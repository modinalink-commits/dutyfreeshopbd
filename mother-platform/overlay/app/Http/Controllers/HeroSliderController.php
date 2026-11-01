<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Hero Media Slider admin: image + YouTube slides in one ordered list with drag and drop sorting. */
class HeroSliderController extends Controller
{
  private static function bust(): void { foreach (['store.home5', 'store.home6'] as $k) Cache::forget($k); }
  public function index() {
    $rows = DB::table('sliders')->where(fn($q) => $q->whereNull('type')->orWhere('type', 'main'))->orderBy('position')->orderBy('id')->get();
    return view('hero_slider', ['rows' => $rows]);
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
