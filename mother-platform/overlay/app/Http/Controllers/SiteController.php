<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
class SiteController extends Controller {
  /** [key, label, type, default, group] — the storefront reads these settings. */
  public static function schema(): array {
    return [
      ['home_hero','Hero slider','bool','1','Home page sections'], ['home_categories','Category cards','bool','1','Home page sections'], ['home_flash','Flash Sale and Plus Sale','bool','1','Home page sections'], ['home_live_orders','Live Orders: Recently ordered (real orders, names masked)','bool','1','Home page sections'], ['home_new','New Arrival (auto: newest 6)','bool','1','Home page sections'], ['home_top','Top Selling (auto: best sellers)','bool','1','Home page sections'], ['home_offer_banners','Offer banners','bool','1','Home page sections'], ['home_cat_sections','Category sections with brand tabs','bool','1','Home page sections'], ['home_blog','Blog or news block','bool','0','Home page sections'],
      ['ticker','Top strip offer ticker (one per line)','textarea',"Special Offer — Membership Discount\nBuy More Save More\nCard Payment Discount\nFREE Delivery\nCashback\nNew Customer Offer\nVIP Benefits",'Top strip and offer ticker'], ['site_address','Site address shown at top left','text','www.dutyfreeshopbd.com','Top strip and offer ticker'], ['show_tracking','"Order Tracking" button','bool','1','Top strip and offer ticker'],
      ['logo_url','Logo (PNG, transparent) URL','text','','Header and main menu'], ['call_number','"Call us now" number','text','09613828081','Header and main menu'], ['search_placeholder','Search placeholder','text','Search for ...','Header and main menu'], ['show_wishlist','Wishlist and account icons','bool','1','Header and main menu'],
      ['per_row_desktop','Products per row (desktop)','number','6','Category and brand pages'], ['per_row_mobile','Products per row (mobile, minimum 3)','number','3','Category and brand pages'], ['per_page','Products per page','number','24','Category and brand pages'], ['default_sort','Default sort','select:newest=Newest,price_asc=Price low to high,price_desc=Price high to low,popular=Most popular','newest','Category and brand pages'], ['badge_discount','Discount badge on photo (orange)','bool','1','Category and brand pages'], ['badge_free_delivery','FREE DELIVERY badge (green)','bool','1','Category and brand pages'], ['badge_new','NEW ribbon for 30 days','bool','1','Category and brand pages'], ['btn_order_now','ORDER NOW button on every card','bool','1','Category and brand pages'], ['seo_brand_title','SEO title template','text','{Brand} – DutyFreeBD','Category and brand pages'], ['seo_brand_desc','Meta description template','text','Buy original {Brand} online','Category and brand pages'],
      ['pp_bundle','Bundle and Save options (Buy 1, 2, 3)','bool','1','Product page'], ['pp_save_badge','SAVE TK badge','bool','1','Product page'], ['pp_related','Related Products (same category)','bool','1','Product page'], ['pp_call','Call number','text','01871600600','Product page'], ['pp_messenger','Messenger link','text','','Product page'],
      ['co_cod','Cash on delivery','bool','1','Checkout and delivery'], ['co_card','Card payments (gateway)','bool','0','Checkout and delivery'], ['co_wallets','bKash, Nagad, Rocket','bool','0','Checkout and delivery'], ['co_coupon','Coupon box on checkout','bool','1','Checkout and delivery'], ['co_email_optional','Email optional on checkout','bool','1','Checkout and delivery'],
      ['footer_about','About text','textarea','','Footer and payment strip'], ['social_facebook','Facebook','text','','Footer and payment strip'], ['social_youtube','YouTube','text','','Footer and payment strip'], ['social_tiktok','TikTok','text','','Footer and payment strip'], ['social_instagram','Instagram','text','','Footer and payment strip'], ['footer_copyright','Copyright line','text','© DutyFreeBD','Footer and payment strip'],
      ['fab_side_cart','Side cart tab','bool','1','Floating buttons and chat'], ['fab_messenger','Messenger button','bool','1','Floating buttons and chat'], ['fab_call','Call button','bool','1','Floating buttons and chat'], ['whatsapp_number','WhatsApp button number','text','01871600600','Floating buttons and chat'], ['fab_ai_chat','AI chat bubble','bool','0','Floating buttons and chat'],
      ['site_title','Site title','text','DutyFreeBD – Buy Cigarettes, Perfume and more','Cookie notice and SEO'], ['meta_description','Home meta description','textarea','','Cookie notice and SEO'], ['gtag_id','Google tag ID','text','','Cookie notice and SEO'], ['pixel_id','Meta pixel ID','text','','Cookie notice and SEO'], ['cookie_notice','Cookie notice','bool','1','Cookie notice and SEO'],
    ];
  }
  public function show() {
    $cur = DB::table('settings')->pluck('value','key');
    $groups = [];
    foreach (self::schema() as [$k,$l,$t,$d,$g]) $groups[$g][] = ['k'=>$k,'l'=>$l,'t'=>$t,'v'=>$cur[$k] ?? $d];
    $last = DB::table('audit_logs')->where('action','site.publish')->orderByDesc('id')->first();
    return view('site', ['groups'=>$groups, 'last'=>$last, 'banners'=>DB::table('banners')->orderBy('position')->get(), 'sliders'=>DB::table('sliders')->orderBy('position')->get(), 'pages'=>DB::table('pages')->orderBy('id')->get(), 'cats'=>DB::table('categories')->whereNull('parent_id')->orderBy('position')->orderBy('name')->get()]);
  }
  public function save(Request $r) {
    $now = now(); $in = (array)$r->input('s', []); $changed = 0;
    foreach (self::schema() as [$k,$l,$t,$d,$g]) {
      if ($t === 'bool') $v = !empty($in[$k]) ? '1' : '0';
      elseif (!array_key_exists($k, $in)) continue;
      else $v = mb_substr(trim((string)$in[$k]), 0, 5000);
      if ($t === 'number') $v = (string)max(0, (int)$v);
      if ($k === 'per_row_mobile') $v = (string)max(3, (int)$v);
      if (str_starts_with($t, 'select:')) { $opts = collect(explode(',', substr($t,7)))->map(fn($x) => explode('=',$x)[0])->all(); if (!in_array($v, $opts)) $v = $d; }
      DB::table('settings')->updateOrInsert(['key'=>$k], ['value'=>$v, 'updated_at'=>$now, 'created_at'=>$now]); $changed++;
    }
    DB::table('audit_logs')->insert(['user_id'=>Auth::id(),'user_name'=>Auth::user()->name,'action'=>'site.publish','subject'=>"$changed settings",'meta'=>json_encode([]),'ip'=>$r->ip(),'created_at'=>$now,'updated_at'=>$now]);
    return back()->with('ok','Changes published');
  }
}
