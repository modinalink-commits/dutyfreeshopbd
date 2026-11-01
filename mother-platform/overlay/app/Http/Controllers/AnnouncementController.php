<?php
namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Announcement Bar: offers and notices that rotate up/down in the store's top strip. */
class AnnouncementController extends Controller
{
  /** Active lines right now (cached 60 s); empty array when none, so the layout falls back to the old ticker text. */
  public static function live(): array {
    return StoreController::rem('store.ann', 60, function () {
      $now = now();
      return DB::table('announcements')->where('active', true)
        ->where(fn($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
        ->where(fn($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
        ->orderBy('sort')->orderBy('id')->get(['text', 'link', 'icon', 'kind'])->map(fn($r) => (array)$r)->all();
    });
  }
  private static function dt($v) { return $v ? Carbon::parse($v, 'Asia/Dhaka')->utc() : null; }
  private function rules(): array { return ['text' => 'required|string|max:200', 'link' => ['nullable', 'string', 'max:300', 'regex:#^(/|https?://)#'], 'icon' => 'nullable|string|max:8', 'kind' => 'required|in:offer,notice', 'sort' => 'nullable|integer|min:0|max:9999', 'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date|after:starts_at']; }
  public function index() {
    return view('announcements', ['rows' => DB::table('announcements')->orderByDesc('active')->orderBy('sort')->orderBy('id')->get(), 'live' => count(self::live()), 'edit' => null]);
  }
  public function edit($id) {
    return view('announcements', ['rows' => DB::table('announcements')->orderByDesc('active')->orderBy('sort')->orderBy('id')->get(), 'live' => count(self::live()), 'edit' => DB::table('announcements')->find($id)]);
  }
  public function save(Request $r, $id = null) {
    $d = $r->validate($this->rules());
    $row = ['kind' => $d['kind'], 'text' => trim($d['text']), 'link' => $d['link'] ?? null, 'icon' => $d['icon'] ?? null, 'sort' => (int)($d['sort'] ?? 0),
      'active' => $r->boolean('active'), 'starts_at' => self::dt($d['starts_at'] ?? null), 'ends_at' => self::dt($d['ends_at'] ?? null), 'updated_at' => now()];
    if ($id) DB::table('announcements')->where('id', $id)->update($row); else DB::table('announcements')->insert($row + ['created_at' => now()]);
    Cache::forget('store.ann');
    return redirect('/admin/announcements')->with('ok', 'Saved. The bar updates on the store within a minute.');
  }
  public function toggle($id) { DB::table('announcements')->where('id', $id)->update(['active' => DB::raw('not active'), 'updated_at' => now()]); Cache::forget('store.ann'); return back(); }
  public function destroy($id) { DB::table('announcements')->where('id', $id)->delete(); Cache::forget('store.ann'); return back()->with('ok', 'Removed'); }
}
