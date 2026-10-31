<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
/** Imports products from the DutyFreeBD Google product feed (same grouping and URLs as the old dutyfreeshopbd.com site). */
class MotherSyncFeed extends Command {
  protected $signature = 'mother:sync-feed';
  protected $description = 'Import products from dutyfreebd.com/products.xml';
  const FEEDS = ['https://dutyfreebd.com/products.xml','https://www.dutyfreebd.com/products.xml','https://dutyfreebd.shop/products.xml','https://dutyfreebd.store/products.xml','http://dutyfreebd.com/products.xml'];
  const PERF = ['Men Perfume','Women Perfume','Atar','Body Spray For Men','Body Spray For Women'];
  public static function groups(): array {
    return [
      'perfume'=>['perfume','Perfume and Atar','অরিজিনাল ইমপোর্টেড পারফিউম ও আতর – দাম ও অর্ডার | DutyFree Shop BD','পুরুষ ও নারীর অরিজিনাল ইমপোর্টেড পারফিউম, বডি স্প্রে ও আতর। দাম দেখুন, অর্ডার করুন, সারা বাংলাদেশে হোম ডেলিভারি।'],
      'smoke'=>['cigarette-vape','Cigarette and Vape','সিগারেট ও ভেপ – কার্টন দামে অরিজিনাল ইমপোর্টেড | DutyFree Shop BD','ইমপোর্টেড সিগারেট ব্র্যান্ড, ন্যানো সিগারেট ও ভেপ। কার্টন দামে অর্ডার করুন, সারা বাংলাদেশে ডেলিভারি।'],
      'skin'=>['skin-care','Skin Care','স্কিন কেয়ার – ফেস ওয়াশ, ক্রিম, সিরাম ও ডিওডোরেন্ট | DutyFree Shop BD','অরিজিনাল ইমপোর্টেড ফেস ওয়াশ, ক্রিম, সিরাম, ডিওডোরেন্ট ও শাওয়ার জেল। দাম দেখুন, অর্ডার করুন।'],
      'hair'=>['hair-care','Hair Care','হেয়ার কেয়ার – শ্যাম্পু, কন্ডিশনার ও হেয়ার অয়েল | DutyFree Shop BD','ইমপোর্টেড শ্যাম্পু, কন্ডিশনার, হেয়ার অয়েল ও হেয়ার ক্রিম। দাম দেখুন, অর্ডার করুন।'],
      'daily'=>['daily-needs','Daily Needs','ডেইলি নিডস – চকলেট, কফি, অলিভ অয়েল ও বেবি ফুড | DutyFree Shop BD','ইমপোর্টেড চকলেট, কফি, অলিভ অয়েল, মধু, গুঁড়া দুধ ও বেবি ফুড। দাম দেখুন, অর্ডার করুন।'],
      'well'=>['wellness','Personal Wellness','পার্সোনাল ওয়েলনেস | DutyFree Shop BD','ইমপোর্টেড পার্সোনাল ওয়েলনেস পণ্য। দাম দেখুন, অর্ডার করুন, সারা বাংলাদেশে ডেলিভারি।'],
      'other'=>['others','Baby Care and Others','বেবি কেয়ার, পেইন রিলিফ ও রুম স্প্রে | DutyFree Shop BD','ইমপোর্টেড বেবি কেয়ার, পেইন রিলিফ ও রুম স্প্রে। দাম দেখুন, অর্ডার করুন।'],
    ];
  }
  public function handle() {
    $xml = null;
    foreach (self::FEEDS as $u) {
      $ctx = stream_context_create(['http'=>['timeout'=>20,'header'=>"User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120 Safari/537.36\r\nAccept: application/xml,text/xml,*/*\r\n"],'ssl'=>['verify_peer'=>true]]);
      $body = @file_get_contents($u, false, $ctx);
      if ($body && strlen($body) > 1000) { $xml = $body; $this->line("FEED ok $u ".strlen($body)); break; }
      $this->line("FEED fail $u");
    }
    if (!$xml && is_file(database_path('products-feed.xml'))) { $xml = file_get_contents(database_path('products-feed.xml')); $this->line('FEED using uploaded products-feed.xml '.strlen($xml)); }
    $rows = [];
    if ($xml && ($doc = @simplexml_load_string($xml))) {
      foreach ($doc->channel->item as $i) {
        $g = $i->children('http://base.google.com/ns/1.0');
        $pid = (int)$g->id; if (!$pid) continue;
        $sale = (float)($g->sale_price ?: $g->price); $price = (float)$g->price; if ($sale <= 0) continue;
        $a = array_map('trim', explode('>', (string)$g->product_type)); $top = strtoupper($a[0] ?? ''); $sec = $a[1] ?? ''; $third = $a[2] ?? '';
        if (str_contains($top,'LIFESTYLE')) { if (in_array($sec, self::PERF)) [$grp,$sub] = ['perfume',$sec]; elseif ($sec==='SKIN CARE') [$grp,$sub] = ['skin',$third ?: 'SKIN CARE']; elseif ($sec==='HAIR CARE') [$grp,$sub] = ['hair',$third ?: 'HAIR CARE']; elseif ($sec==='Protection') [$grp,$sub] = ['well','Protection']; else [$grp,$sub] = ['other',$sec ?: 'Others']; }
        elseif (str_contains($top,'DAILY')) [$grp,$sub] = ['daily',$sec ?: 'Daily'];
        else [$grp,$sub] = ['smoke',$sec ?: ($a[0] ?? 'Smoke')];
        $title = $this->clean((string)$g->title); if ($title === '') $title = $this->clean((string)$i->title);
        $rows[] = [$pid, $title, $grp, $sub, $sale, $price > $sale ? $price : null, (string)$g->image_link ?: null, $this->clean((string)$g->description)];
      }
    } elseif (!DB::table('products')->whereNotNull('ext_id')->exists() && is_file(database_path('products-snapshot.json'))) {
      $this->line('FEED unreachable: using saved snapshot for first import');
      foreach (json_decode(file_get_contents(database_path('products-snapshot.json')), true) ?: [] as $x) {
        $img = str_starts_with($x['m'] ?? '', 'http') ? $x['m'] : 'https://dutyfreebd.com/public/storage/images/products/'.($x['m'] ?? '');
        $rows[] = [(int)$x['i'], $x['n'], $x['g'], $x['s'] ?? '', (float)$x['p'], !empty($x['o']) ? (float)$x['o'] : null, $img, $x['d'] ?? ''];
      }
    } else { $this->line('FEED none reachable, products unchanged'); return 1; }
    $catId = [];
    foreach (self::groups() as $k => [$slug,$name,$title,$desc]) {
      $id = DB::table('categories')->where('slug',$slug)->value('id');
      if (!$id) $id = DB::table('categories')->insertGetId(['name'=>$name,'slug'=>$slug,'title'=>$title,'meta_description'=>$desc,'position'=>count($catId),'active'=>true,'created_at'=>now(),'updated_at'=>now()]);
      $catId[$k] = $id;
    }
    $n = 0; $now = now(); $seen = [];
    foreach ($rows as [$pid, $title, $grp, $sub, $sale, $cmp, $img, $desc]) {
      if (!isset($catId[$grp])) $grp = 'other';
      $data = ['name'=>mb_substr($title,0,200), 'price'=>$sale, 'compare_price'=>$cmp, 'image'=>$img, 'group_key'=>$grp, 'sub'=>mb_substr($sub,0,120), 'category_id'=>$catId[$grp], 'description'=>mb_substr($desc,0,5000), 'published'=>true, 'synced_at'=>$now, 'updated_at'=>$now];
      $ex = DB::table('products')->where('ext_id',$pid)->first();
      if ($ex) DB::table('products')->where('id',$ex->id)->update($data);
      else DB::table('products')->insert($data + ['ext_id'=>$pid, 'sku'=>'DF-'.$pid, 'slug'=>Str::slug($title) ?: 'product', 'stock'=>100, 'cost'=>0, 'created_at'=>$now]);
      $seen[] = $pid; $n++;
    }
    if ($n >= 50) DB::table('products')->whereNotNull('ext_id')->whereNotIn('ext_id',$seen)->update(['published'=>false,'updated_at'=>$now]);
    \App\Support\Slugs::assignAll();
    foreach (['store.home2','store.home4','store.home6','store.home5','store.cats','store.catslugs','store.subs','store.sitemap','store.sitemap2','store.live2','store.live4'] as $k) \Illuminate\Support\Facades\Cache::forget($k);
    $this->line("FEED imported $n");
    return 0;
  }
  private function clean(string $s): string {
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8'); $s = preg_replace('/[\x{200B}\x{FEFF}]/u','',$s);
    $s = preg_replace('/([a-z\)\.])([A-Z])/','$1 $2',$s); return trim(preg_replace('/\s+/u',' ',$s));
  }
}
