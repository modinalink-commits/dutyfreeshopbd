<?php
namespace App\Http\Controllers;
use App\Support\Sms;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class SmsController extends Controller {
  public function show() {
    $G = \App\Support\SmsGate::class; $c = $G::cfg();
    $counts = DB::table('sms_logs')->select('status', DB::raw('count(*) n'))->groupBy('status')->pluck('n','status');
    $set = DB::table('settings')->whereIn('key',['sms_order_confirm','owner_phone','manager_phones'])->pluck('value','key');
    $prov = [];
    foreach ($G::NAMES as $k => $n) $prov[$k] = ['name'=>$n, 'on'=>$c['on'][$k], 'key'=>$G::hasKey($k), 'ready'=>$G::ready($k), 'sender'=>$c['sender'][$k], 'bal'=>$G::set('bal_'.$k, '—'), 'bal_ok'=>$G::set('bal_'.$k.'_ok', '') === '1', 'bal_at'=>$G::set('bal_'.$k.'_at', ''), 'down'=>\Illuminate\Support\Facades\Cache::get('smsg_down_'.$k)];
    [$order, $skip] = $G::order($c);
    $att = \Illuminate\Support\Facades\Schema::hasTable('sms_attempts') ? DB::table('sms_attempts')->orderByDesc('id')->limit(25)->get() : collect();
    return view('sms_setup', ['hasKey'=>(bool)Sms::secret('sms_api_key'), 'sender'=>Sms::secret('sms_sender_id'), 'counts'=>$counts, 'set'=>$set, 'ready'=>Sms::configured(), 'balance'=>session('balance'),
      'recent'=>DB::table('sms_logs')->orderByDesc('id')->limit(15)->get(), 'g'=>$c, 'prov'=>$prov, 'order'=>$order, 'skip'=>$skip, 'att'=>$att, 'sim'=>request()->query('sim') ? \Illuminate\Support\Facades\Cache::get('smsg_sim_'.auth()->id()) : null,
      'tests'=>$G::set('test_numbers', '01884808080'), 'content'=>$c['content']]);
  }
  public function save(Request $r) {
    $G = \App\Support\SmsGate::class;
    $d = $r->validate(['api_key'=>'nullable|string|max:200', 'sender_id'=>'nullable|string|max:30', 'owner_phone'=>'nullable|string|max:30', 'manager_phones'=>'nullable|string|max:300',
      'alpha_key'=>'nullable|string|max:200', 'alpha_sender'=>'nullable|string|max:30', 'alpha_content'=>'nullable|string|max:40', 'primary'=>'nullable|in:alpha,bulk', 'mode'=>'nullable|in:auto,single', 'test_numbers'=>'nullable|string|max:200']);
    $before = $G::cfg();
    if (!empty($d['api_key'])) Sms::setSecret('sms_api_key', trim($d['api_key']));
    if (!empty($d['sender_id'])) Sms::setSecret('sms_sender_id', trim($d['sender_id']));
    if (!empty($d['alpha_key'])) Sms::setSecret('sms_alpha_key', trim($d['alpha_key']));
    if ($r->has('primary')) { // the provider block was submitted
      $G::put('primary', $d['primary'] ?? 'alpha'); $G::put('fallback', ($d['mode'] ?? 'auto') === 'auto' ? '1' : '0');
      $G::put('alpha_on', $r->boolean('alpha_on') ? '1' : '0'); $G::put('bulk_on', $r->boolean('bulk_on') ? '1' : '0');
      $G::put('alpha_sender', trim((string)($d['alpha_sender'] ?? ''))); $G::put('alpha_content', trim((string)($d['alpha_content'] ?? '')));
      $nums = array_values(array_unique(array_filter(array_map(fn($x) => Sms::number(trim($x)) ? '0'.substr(Sms::number(trim($x)), 3) : null, preg_split('/[,\s]+/', (string)($d['test_numbers'] ?? ''))))));
      $G::put('test_numbers', implode(', ', $nums ?: ['01884808080']));
      \Illuminate\Support\Facades\Cache::forget('smsg_down_alpha'); \Illuminate\Support\Facades\Cache::forget('smsg_down_bulk');
    }
    foreach (['owner_phone'=>$d['owner_phone'] ?? '', 'manager_phones'=>$d['manager_phones'] ?? '', 'sms_order_confirm'=>$r->boolean('order_confirm') ? '1' : '0'] as $k => $v)
      DB::table('settings')->updateOrInsert(['key'=>$k], ['value'=>$v, 'updated_at'=>now(), 'created_at'=>now()]);
    \Illuminate\Support\Facades\Cache::forget('store.settings2');
    $after = $G::cfg(); $chg = [];
    if ($before['primary'] !== $after['primary']) $chg[] = 'primary '.$before['primary'].' → '.$after['primary'];
    if ($before['fallback'] !== $after['fallback']) $chg[] = 'automatic fall-back '.($after['fallback'] ? 'on' : 'off');
    foreach (['alpha','bulk'] as $k) if ($before['on'][$k] !== $after['on'][$k]) $chg[] = $k.' '.($after['on'][$k] ? 'on' : 'off');
    if (!empty($d['alpha_key'])) $chg[] = 'Alpha SMS API key saved'; if (!empty($d['api_key'])) $chg[] = 'BulkSMSBD API key saved';
    DB::table('audit_logs')->insert(['user_id'=>auth()->id(),'user_name'=>auth()->user()->name,'action'=>'sms.settings','subject'=>mb_substr('SMS gateway settings saved'.($chg ? ': '.implode('; ', $chg) : ''), 0, 250),'ip'=>$r->ip(),'created_at'=>now(),'updated_at'=>now()]);
    return back()->with('ok','SMS settings saved. New messages use the new settings; messages already accepted by a provider are not sent again.');
  }
  public function test(Request $r) {
    $G = \App\Support\SmsGate::class;
    $d = $r->validate(['to'=>'required|string|max:30', 'via'=>'nullable|in:auto,alpha,bulk']);
    $num = Sms::number($d['to']); if (!$num) return back()->withErrors(['to'=>'Invalid phone number.']);
    if (!in_array($num, $G::cfg()['tests'], true)) return back()->withErrors(['to'=>'Test SMS can go only to an approved test number ('.$G::set('test_numbers', '01884808080').'). Add the number under "Approved test numbers" first.']);
    $via = $d['via'] ?? 'auto';
    if ($via === 'auto' && !Sms::configured()) return back()->withErrors(['to'=>'No SMS provider is configured.']);
    $id = DB::table('sms_logs')->insertGetId(['to'=>$num,'body'=>'Test SMS','event'=>'test','status'=>'sending','created_at'=>now(),'updated_at'=>now()]);
    [$ok, $msg] = Sms::send($num, \App\Support\SmsText::test(now('Asia/Dhaka')->format('H:i:s')), ['log_id'=>$id, 'event'=>'test'] + ($via === 'auto' ? [] : ['force'=>$via]));
    $l = Sms::$last ?? []; $up = ['status'=>$ok ? 'sent' : (($l['state'] ?? '') === 'uncertain' ? 'uncertain' : 'failed'),'provider_response'=>mb_substr($msg,0,500),'sent_at'=>$ok?now():null,'updated_at'=>now()];
    if (\Illuminate\Support\Facades\Schema::hasColumn('sms_logs','provider')) $up += ['uid'=>$l['uid'] ?? null,'provider'=>$l['provider'] ?? null,'request_id'=>$l['rid'] ?? null,'delivery'=>$ok ? 'pending' : null,'fallback_reason'=>isset($l['fallback']) ? mb_substr((string)$l['fallback'],0,250) : null,'attempts'=>count($l['trace'] ?? [])];
    DB::table('sms_logs')->where('id',$id)->update($up);
    return back()->with($ok ? 'ok' : 'bad', ($ok ? 'Test SMS accepted by the provider (delivery is confirmed by the report, see "Provider attempts"): ' : 'Test SMS not sent: ').$msg);
  }
  public function balance() {
    $G = \App\Support\SmsGate::class; $out = [];
    foreach ($G::NAMES as $k => $n) if ($G::hasKey($k)) { $b = $G::refreshBalance($k); $out[] = $n.': '.$b['text']; }
    return back()->with('ok', $out ? 'Balance checked — '.implode(' · ', $out) : 'No API key is saved yet.');
  }
  public function flush() { $n = Sms::flush(100); return back()->with('ok', "$n queued messages processed."); }
  /** Dry run of the fall-back rules against made-up provider answers. Sends nothing, stores nothing. */
  public function simulate() { \Illuminate\Support\Facades\Cache::put('smsg_sim_'.auth()->id(), \App\Support\SmsGate::simulate(), 600); return redirect('/admin/sms?sim=1#sim'); } // kept in the cache, not in the session cookie (cookie sessions are small)
  /** Owner 17 Nov 2026: one Bangla screen listing every automatic SMS with a filled-in preview, character count, SMS parts and estimated cost. */
  public function templates() {
    $T = \App\Support\SmsText::class; $W = \App\Support\Winback::class; $P = \App\Support\Procure::class;
    $o = (object)['invoice'=>'105077', 'total'=>1749, 'discount'=>0, 'pay_status'=>''];
    $od = (object)['invoice'=>'105077', 'total'=>1749, 'discount'=>120, 'pay_status'=>''];
    $hot = (string)\App\Support\Biz::hotline(); $base = preg_replace('#^https?://#', '', rtrim((string)$P::base(), '/'));
    $wb = fn($t) => trim(strtr($t, ['{name}'=>'রহিম', '{pct}'=>'2', '{days}'=>'15', '{expiry}'=>'18 Nov', '{code}'=>' কোড: WB-15', '{link}'=>$W::LINK]));
    $sv = ['supplier_name'=>'করিম ট্রেডার্স', 'due'=>'25,000.00', 'total'=>'40,000.00', 'paid'=>'15,000.00', 'amount'=>'15,000.00', 'invoice_no'=>'PUR-1024', 'invoice_link'=>$base.'/purchase/invoice/PUR-1024?t=ab12cd34', 'statement_link'=>$base.'/supplier/statement/ab12cd34ef'];
    $pt = fn($k) => $P::render($P::set('tpl_'.$k, $P::TPL[$k]) ?: $P::TPL[$k], $sv);
    $noans = trim((string)$P::set('cc_noanswer_sms', $T::NO_ANSWER));
    $rows = [
      ['গ্রাহক', 'অর্ডার গ্রহণ', 'গ্রাহক অর্ডার দিলে সাথে সাথে যায়। অন/অফ: SMS Setup।', $T::orderPlaced($o), 'স্বয়ংক্রিয়', '/admin/sms'],
      ['গ্রাহক', 'অর্ডার গ্রহণ (ছাড়সহ)', 'অর্ডারে ছাড় থাকলে ছাড়ের অঙ্কও দেখায়।', $T::orderPlaced($od), 'স্বয়ংক্রিয়', '/admin/sms'],
      ['গ্রাহক', 'অর্ডার নিশ্চিত', 'অর্ডার অনুমোদনের পর গ্রাহকের নম্বরে WhatsApp না থাকলে SMS যায়।', $T::orderApproved($o), 'স্বয়ংক্রিয়', null],
      ['গ্রাহক', 'অর্ডার বাতিল', 'অর্ডার বাতিল হলে কারণসহ যায়। স্টাফ "Other" বাছলে লেখা কারণটিই যায়।', $T::orderCancelled($o, 'we could not reach you by phone to confirm it'), 'স্বয়ংক্রিয়', null],
      ['গ্রাহক', 'অর্ডার আপডেট', 'অর্ডার সংশোধন করে নিশ্চিত করলে যায় (সুইচ চালু থাকলে)।', $T::orderUpdated('105077', 1749), 'স্বয়ংক্রিয়', null],
      ['গ্রাহক', 'পেমেন্ট যাচাই হয়েছে', 'স্টাফ ম্যানুয়াল পেমেন্ট যাচাই করে SMS টিক দিলে যায়।', $T::payVerified('105077'), 'স্টাফের কাজে', '/admin/manual-payments'],
      ['গ্রাহক', 'পেমেন্ট যাচাই হয়নি', 'স্টাফ পেমেন্ট বাতিল করে SMS টিক দিলে যায়।', $T::payRejected('105077'), 'স্টাফের কাজে', '/admin/manual-payments'],
      ['গ্রাহক', 'অসম্পূর্ণ অর্ডার', 'চেকআউট অসম্পূর্ণ রেখে গেলে একবার যায়।', \App\Support\IncompleteNudge::body('রহিম'), 'স্বয়ংক্রিয়', null],
      ['গ্রাহক', 'ওটিপি (লগইন / চেকআউট)', 'গ্রাহকের লগইন ও চেকআউট যাচাইয়ের কোড।', $T::otp('482913'), 'স্বয়ংক্রিয়', null],
      ['গ্রাহক', 'কল ধরেননি', 'কল সেন্টার থেকে কল দিয়ে না পেলে স্টাফ টিক দিলে যায়।', str_replace('{hotline}', $hot, $noans), 'স্টাফের কাজে', null],
      ['অফার', 'Win-Back অফার', 'অনেক দিন অর্ডার না করা গ্রাহককে। লেখা বদলানো যায় Win-Back পাতায়।', $wb($W::set('sms_text', $W::SMS)), 'নির্ধারিত', '/admin/winback'],
      ['অফার', 'Win-Back শেষ ধাপ', 'Win-Back-এর শেষ ধাপের বার্তা।', $wb($W::set('sms_final', $W::SMS_FINAL)), 'নির্ধারিত', '/admin/winback'],
      ['অফার', 'ভয়েস কলের পর অফার', 'ভয়েস ক্যাম্পেইনের কলের পর যায়। প্রতিটি ক্যাম্পেইনে আলাদা লেখা দেওয়া যায়।', strtr(\App\Support\Voice::SMS, ['{product}'=>'Marlboro Gold', '{pct}'=>'2', '{date}'=>'20 Nov 2026', '{name}'=>'রহিম']), 'নির্ধারিত', '/admin/voice-campaigns'],
      ['অফার', 'পণ্য আবার স্টকে', 'পণ্য আবার প্রকাশ হলে আগের ক্রেতাদের যায়। লেখা বদলানো যায়।', strtr(\App\Support\PublishNotice::text(), ['{product}'=>'Marlboro Gold', '{link}'=>$base.'/p/m123', '{price}'=>'1,749']), 'স্বয়ংক্রিয়', '/admin/back-in-store-sms'],
      ['সাপ্লায়ার', 'ক্রয় ইনভয়েস', 'ক্রয় ইনভয়েস তৈরি হলে সাপ্লায়ারকে।', $pt('purchase'), 'স্বয়ংক্রিয়', null],
      ['সাপ্লায়ার', 'পেমেন্ট দেওয়া হয়েছে', 'সাপ্লায়ারকে পেমেন্ট দিলে।', $pt('payment'), 'স্বয়ংক্রিয়', null],
      ['সাপ্লায়ার', 'পাওনার তথ্য', 'সাপ্লায়ারকে পাওনা জানাতে।', $pt('due'), 'স্টাফের কাজে', null],
      ['সাপ্লায়ার', 'পণ্য ফেরত', 'সাপ্লায়ারের পণ্য ফেরত সমন্বয় হলে।', $pt('return'), 'স্বয়ংক্রিয়', null],
      ['সাপ্লায়ার', 'ড্যামেজ ক্লেইম', 'ড্যামেজ ক্লেইম সমন্বয় হলে।', $pt('damage'), 'স্বয়ংক্রিয়', null],
      ['সাপ্লায়ার', 'হিসাব বিবরণী', 'সাপ্লায়ারকে Statement পাঠালে।', $pt('statement'), 'স্টাফের কাজে', null],
      ['সাপ্লায়ার', 'ক্রয় অনুরোধ (কম স্টক)', 'স্টক কমে গেলে সাপ্লায়ারকে দাম চাওয়ার অনুরোধ।', $T::reorder(false, 'Marlboro Gold', 50, $base.'/supplier-portal'), 'স্বয়ংক্রিয়', null],
      ['সাপ্লায়ার', 'পোর্টাল ওটিপি', 'সাপ্লায়ার পোর্টালের লগইন কোড।', $T::supplierOtp('482913'), 'স্বয়ংক্রিয়', null],
      ['সাপ্লায়ার', 'পোর্টাল অ্যাকাউন্ট চালু', 'সাপ্লায়ারকে অ্যাকাউন্ট চালুর লিংক।', $T::supplierInvite('করিম ট্রেডার্স', \App\Support\SupplierPortal::brand(), 'karim01', $base.'/supplier-portal/activate/XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX'), 'স্টাফের কাজে', null],
      ['সাপ্লায়ার', 'পোর্টাল অস্থায়ী পাসওয়ার্ড', 'সাপ্লায়ারের পাসওয়ার্ড রিসেট করলে।', $T::supplierTempPw('করিম ট্রেডার্স', \App\Support\SupplierPortal::brand(), 'karim01', 'Ab12cd34', $base.'/supplier-portal/login'), 'স্টাফের কাজে', null],
      ['স্টাফ', 'পণ্য প্রকাশ / অপ্রকাশ', 'মালিক ও ম্যানেজারের নম্বরে যায়।', $T::productStatus('"Marlboro Gold"', true, 'Niham', '14:30', 'DutyFreeBD'), 'স্বয়ংক্রিয়', '/admin/sms'],
      ['স্টাফ', 'বেতন / কমিশন পরিশোধ', 'স্টাফকে বেতন দিলে।', $T::salary('Niham', 'Nov 2026', 12000), 'স্টাফের কাজে', null],
      ['স্টাফ', 'বেস্ট সেলার স্টক সতর্কতা', 'WhatsApp টেমপ্লেট অনুমোদিত না থাকলে SMS-এ যায়। লেখাটি WhatsApp টেমপ্লেটের সাথে মিল রাখা, তাই বদলানো হয়নি।', \App\Support\BestSellerStock::text('Faruk', 'Marlboro Gold', 80), 'স্বয়ংক্রিয়', '/admin/best-sellers'],
      ['স্টাফ', 'পরীক্ষামূলক SMS', 'SMS Setup পাতার "Send test" বোতাম।', $T::test('14:30'), 'স্টাফের কাজে', '/admin/sms'],
    ];
    // SMS Marketing automations stored in the database (only the active ones send)
    try { if (\Illuminate\Support\Facades\Schema::hasTable('sms_automations')) { $av = ['customer_name'=>'রহিম', 'order_id'=>'105077', 'invoice'=>'105077', 'total'=>'1,749', 'tracking_id'=>'DF1711ABC', 'delivery_status'=>'Delivered', 'discount'=>'120', 'checkout_link'=>$base.'/checkout', 'coupon_code'=>'WB-15', 'membership'=>'Gold', 'expiry_date'=>'20 Nov'];
      foreach (DB::table('sms_automations')->orderBy('id')->get() as $a) $rows[] = ['অটোমেশন', $a->name.($a->active ? ' — চালু' : ' — বন্ধ'), 'SMS Marketing › Automation-এ সংরক্ষিত লেখা। চালু থাকলেই শুধু যায়।', \App\Support\SmsMarketing::render((string)$a->body, $av), $a->active ? 'স্বয়ংক্রিয়' : 'বন্ধ', '/admin/sms-marketing/automation']; } } catch (\Throwable $e) {}
    $unit = \App\Support\SmsMarketing::unit(); $out = [];
    foreach ($rows as $r) { [$parts, $chars, $uni] = $T::count($r[3]); $out[] = ['group'=>$r[0], 'name'=>$r[1], 'desc'=>$r[2], 'text'=>$r[3], 'when'=>$r[4], 'edit'=>$r[5], 'parts'=>$parts, 'chars'=>$chars, 'uni'=>$uni, 'cost'=>$parts * $unit]; }
    return view('sms_templates', ['rows'=>$out, 'unit'=>$unit]);
  }
}
