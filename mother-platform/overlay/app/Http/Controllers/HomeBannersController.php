<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** One page with an On/Off switch for every banner area of the home page. Takes effect straight away. */
class HomeBannersController extends Controller
{
  const ITEMS = [
    ['home_hero', 'Hero banner area (the big top banner)', 'Turns the whole top banner area off or on. Everything below stays.', '/admin/hero-slider', 'Edit slides'],
    ['home_globe', 'Globe slide: "Duty Free Exceptional Offers"', 'The world-map slide with the offers list. OFF shows the normal slider slides instead.', null, null],
    ['hero_offers_slide', 'Slide: Exceptional Offers list', 'Offer list slide inside the hero banner.', null, null],
    ['hero_watch_slide', 'Slide: Watch collection', 'Animated slide with Watch category products sliding across.', '/watch/', 'Open Watch'],
    ['hero_text_slide', 'Slide: Headline text banner', 'The plain text banner with the sale headline (used when the globe slide is off).', null, null],
    ['hero_member_slide', 'Slide: Membership', 'Membership slide inside the hero banner.', null, null],
    ['hero_district_slide', 'Slide: District delivery', 'District delivery slide inside the hero banner.', null, null],
    ['home_promos', 'Three promo cards under the brand strip', 'Pay Online Save More, Buy 2-5 and Save, Carton Discount.', null, null],
    ['home_showcase', 'Product showcase strip (6 animated products)', 'The six animated cards between Recently ordered and Cigarette and Vape.', '/admin/showcase', 'Edit the six cards'],
    ['home_offer_banners', 'Old offer banners (555 / Dunhill)', 'The two earlier wide banners. Kept off while the showcase strip is in use.', '/admin/m/banners', 'Edit banners'],
  ];
  public function index() {
    $cur = DB::table('settings')->pluck('value', 'key'); $rows = [];
    foreach (self::ITEMS as [$k, $t, $d, $u, $ul]) $rows[] = ['k' => $k, 't' => $t, 'd' => $d, 'u' => $u, 'ul' => $ul, 'on' => ($cur[$k] ?? '1') !== '0'];
    return view('home_banners', ['rows' => $rows]);
  }
  public function save(Request $r) {
    $now = now(); $n = 0; $cur = DB::table('settings')->pluck('value', 'key');
    foreach (self::ITEMS as [$k]) {
      $v = $r->input('sw.' . $k) ? '1' : '0';
      if (($cur[$k] ?? '1') === $v && isset($cur[$k])) continue;
      DB::table('settings')->updateOrInsert(['key' => $k], ['value' => $v, 'updated_at' => $now, 'created_at' => $now]); $n++;
    }
    try { DB::table('audit_logs')->insert(['user_id' => Auth::id(), 'user_name' => Auth::user()->name, 'action' => 'home.banners', 'subject' => "$n banner switches", 'meta' => json_encode([]), 'ip' => $r->ip(), 'created_at' => $now, 'updated_at' => $now]); } catch (\Throwable $e) {}
    foreach (['store.settings2', 'store.home11', 'store.home10', 'store.home8', 'store.home5', 'store.home6', 'store.home4'] as $c) { try { Cache::forget($c); } catch (\Throwable $e) {} }
    return redirect('/admin/home-banners')->with('ok', 'Saved. The home page shows this now.');
  }
}
