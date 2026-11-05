@php
 $__bn = fn($v) => strtr((string)$v, ['0'=>'০','1'=>'১','2'=>'২','3'=>'৩','4'=>'৪','5'=>'৫','6'=>'৬','7'=>'৭','8'=>'৮','9'=>'৯']);
 $__on = []; $__amt = 100; $__deal = true; $__cp = false;
 try { $__on = \Illuminate\Support\Facades\DB::table('discount_rules')->where('enabled', true)->pluck('key')->all(); } catch (\Throwable $e) {}
 try { $__amt = (int)\App\Support\AppReward::cfg()['amt']; } catch (\Throwable $e) {}
 try { $__cp = \Illuminate\Support\Facades\DB::table('coupons')->where('active', true)->exists(); } catch (\Throwable $e) {}
 $__all = [
  ['flash','ফ্ল্যাশ সেল','Flash Sale'],
  ['deal','আজকের ডিল',"Today's Deal"],
  ['appinstall','অ্যাপ ইনস্টল ওয়েলকাম ডিসকাউন্ট ৳'.$__bn($__amt),'App Install Welcome Discount'],
  ['first','প্রথম অর্ডার ডিসকাউন্ট','First Order Discount'],
  ['freedelivery','ফ্রি ডেলিভারি','Free Delivery'],
  ['membership','মেম্বারশিপ ডিসকাউন্ট','Membership Discount'],
  ['payment','পেমেন্ট মেথড ডিসকাউন্ট','Payment Method Discount'],
  ['amount','অর্ডার অ্যামাউন্ট ডিসকাউন্ট','Order Amount Discount'],
  ['bundle','বান্ডেল ডিসকাউন্ট','Bundle Discount'],
  ['bulk','কার্টন (বাল্ক) ডিসকাউন্ট','Quantity Discount'],
  ['bogo','কিনুন আর ফ্রি পান','Buy X Get Y'],
  ['loyalty','লয়ালটি পয়েন্ট','Loyalty Points'],
  ['referral','রেফারেল অফার','Referral Offer'],
  ['birthday','বার্থডে ও ফেস্টিভাল ডিসকাউন্ট','Birthday & Festival Discount'],
  ['catalog','ক্যাটাগরি ও ব্র্যান্ড ডিসকাউন্ট','Category & Brand Discount'],
  ['coupon','কুপন অফার','Coupon Offers'],
 ];
 $__of = array_values(array_filter($__all, fn($o) => $o[0]==='deal' ? $__deal : ($o[0]==='coupon' ? $__cp : in_array($o[0], $__on, true))));
 $__n = count($__of); $__dur = 2.6; $__p = $__n ? 100 / $__n : 100;
@endphp
@if($__n >= 2)
<a class="hxa hxof" href="/#flash" aria-label="DutyFreeBD অফারসমূহ: {{ implode(', ', array_column($__of, 1)) }}">
<style>
@font-face{font-family:'Anek Bangla';font-weight:500;font-style:normal;font-display:swap;src:url(/fonts/anek-bangla-500.woff2) format('woff2')}
@font-face{font-family:'Anek Bangla';font-weight:700;font-style:normal;font-display:swap;src:url(/fonts/anek-bangla-700.woff2) format('woff2')}
.hxof{container-type:inline-size;position:relative;display:block;overflow:hidden;color:#fff;text-decoration:none;background:linear-gradient(120deg,#0B1220 0%,#1F2A44 58%,#3A2A2A 100%);font-family:'Anek Bangla','Hind Siliguri',Inter,system-ui,sans-serif}
.hxof *{box-sizing:border-box}
.hxof .og{position:absolute;right:-6cqw;top:-12cqw;width:60cqw;height:50cqw;background:radial-gradient(closest-side,rgba(255,90,0,.42),rgba(255,90,0,0) 70%);pointer-events:none;animation:ofGlow 6s ease-in-out infinite alternate}
.hxof .os{position:absolute;top:0;bottom:0;left:-30%;width:22%;background:linear-gradient(100deg,transparent,rgba(255,255,255,.10),transparent);transform:skewX(-18deg);animation:ofSweep 7s cubic-bezier(.4,0,.2,1) infinite;pointer-events:none}
.hxof .ol{position:absolute;left:5cqw;top:0;bottom:7cqw;width:38cqw;display:flex;flex-direction:column;justify-content:center;gap:1.2cqw}
.hxof .ok{font-size:max(9px,.9cqw);letter-spacing:.2em;font-weight:600;color:#FDC2A5}
.hxof .oh{margin:0;font-size:max(15px,3.5cqw);line-height:1.2;font-weight:700;letter-spacing:-.01em;color:#fff}.hxof .oh em{display:block;font-style:normal;color:#FF8A4C}
.hxof .on2{align-self:flex-start;display:inline-flex;align-items:center;gap:.7cqw;height:max(24px,2.6cqw);padding:0 max(12px,1.3cqw);border-radius:999px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.22);font-size:max(10px,1.15cqw);font-weight:700;color:#fff}
.hxof .on2:before{content:"";width:.7cqw;height:.7cqw;min-width:6px;min-height:6px;border-radius:50%;background:#FF5A00;box-shadow:0 0 0 0 rgba(255,90,0,.6);animation:ofPing 1.8s ease-out infinite}
.hxof .ost{position:absolute;left:47cqw;width:48cqw;top:3.2cqw;height:20cqw}
.hxof .oi{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;gap:1cqw;padding:0 2cqw;border-radius:2cqw;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.18);box-shadow:0 2cqw 4cqw rgba(0,0,0,.28);opacity:0;animation:ofK {{ round($__n * $__dur, 2) }}s infinite both}
.hxof .oi b{font-size:max(15px,3.2cqw);line-height:1.2;font-weight:700;color:#fff;letter-spacing:-.005em}
.hxof .oi i{font-style:normal;font-size:max(10px,1.2cqw);letter-spacing:.14em;text-transform:uppercase;color:#FDC2A5;font-weight:600}
.hxof .oi u{position:absolute;right:1.6cqw;top:1.2cqw;text-decoration:none;font:600 max(9px,.95cqw)/1 Inter,system-ui,sans-serif;color:rgba(255,255,255,.55);letter-spacing:.08em}
.hxof .oi s{position:absolute;left:0;bottom:0;height:.35cqw;width:100%;background:#FF5A00;transform-origin:left;transform:scaleX(0);border-radius:0 0 2cqw 2cqw;animation:ofBar {{ round($__n * $__dur, 2) }}s linear infinite both}
.hxof .om{position:absolute;left:0;right:0;bottom:1.6cqw;height:max(26px,3.6cqw);overflow:hidden;-webkit-mask-image:linear-gradient(90deg,transparent,#000 6%,#000 94%,transparent);mask-image:linear-gradient(90deg,transparent,#000 6%,#000 94%,transparent)}
.hxof .omt{display:flex;gap:1.2cqw;width:max-content;animation:ofMar {{ max(30, $__n * 4) }}s linear infinite}
.hxof .omt span{flex:none;display:inline-flex;align-items:center;height:max(26px,3.6cqw);padding:0 max(12px,1.6cqw);border-radius:999px;background:rgba(255,90,0,.16);border:1px solid rgba(255,138,76,.5);font-size:max(11px,1.4cqw);font-weight:700;color:#FFD9C2;white-space:nowrap}
@keyframes ofK{0%{opacity:0;transform:translateY(18%) scale(.96)}2.5%{opacity:1;transform:none}{{ round($__p - 3, 2) }}%{opacity:1;transform:none}{{ round($__p, 2) }}%{opacity:0;transform:translateY(-18%) scale(.96)}100%{opacity:0;transform:translateY(-18%) scale(.96)}}
@keyframes ofBar{0%{transform:scaleX(0)}{{ round($__p, 2) }}%{transform:scaleX(1)}{{ round($__p + .01, 2) }}%,100%{transform:scaleX(0)}}
@keyframes ofMar{to{transform:translateX(-50%)}}
@keyframes ofSweep{0%{left:-30%}60%,100%{left:130%}}
@keyframes ofGlow{to{opacity:.65;transform:scale(1.08)}}
@keyframes ofPing{70%{box-shadow:0 0 0 .9cqw rgba(255,90,0,0)}100%{box-shadow:0 0 0 0 rgba(255,90,0,0)}}
@media(max-width:760px){
.hxof,.hxs .sl-t>a.hxof{aspect-ratio:auto;display:flex!important;flex-direction:column;justify-content:center;align-items:center;gap:3cqw;padding:4cqw 0 4cqw}
.hxof .ol{position:static;width:100%;padding:0 5cqw;align-items:center;text-align:center;gap:2.2cqw}
.hxof .ok,.hxof .on2{display:none}.hxof .oh{font-size:5.6cqw;line-height:1.2}.hxof .oh em{display:inline;margin-left:1.2cqw}
.hxof .on2{align-self:center;height:7cqw;padding:0 3.6cqw;font-size:max(10px,3cqw)}.hxof .on2:before{width:2cqw;height:2cqw}
.hxof .ost{display:block;position:relative;left:auto;top:auto;width:88cqw;height:24cqw;flex:none}
.hxof .oi{border-radius:4cqw;gap:1.4cqw}.hxof .oi b{font-size:max(15px,5.4cqw)}.hxof .oi i{font-size:max(8px,2.4cqw)}.hxof .oi u{font-size:max(8px,2.2cqw);right:3cqw;top:2.4cqw}.hxof .oi s{height:.9cqw;border-radius:0 0 4cqw 4cqw}
.hxof .om{display:block;position:relative;bottom:auto;height:8cqw;flex:none;width:100%}
.hxof .omt{gap:2.4cqw}.hxof .omt span{height:8cqw;padding:0 3.6cqw;font-size:max(11px,3.1cqw)}
.hxof .og{width:100cqw;height:80cqw}
}
@media(prefers-reduced-motion:reduce){.hxof .og,.hxof .os,.hxof .on2:before,.hxof .oi,.hxof .oi s,.hxof .omt{animation:none!important}.hxof .oi{opacity:0}.hxof .oi:first-child{opacity:1}.hxof .oi s{display:none}}
</style>
<span class="og" data-pwa="0"></span><span class="os"></span>
<span class="ol"><span class="ok">DUTYFREEBD OFFERS</span><span class="oh">সব অফার<em>এক জায়গায়</em></span><span class="on2">{{ $__bn($__n) }}টি অফার চলছে</span></span>
<span class="ost" aria-hidden="true">@foreach($__of as $i => $o)<span class="oi" style="animation-delay:{{ round($i * $__dur, 2) }}s"><u>{{ $__bn(str_pad((string)($i+1), 2, '0', STR_PAD_LEFT)) }} / {{ $__bn(str_pad((string)$__n, 2, '0', STR_PAD_LEFT)) }}</u><b>{{ $o[1] }}</b><i>{{ $o[2] }}</i><s style="animation-delay:{{ round($i * $__dur, 2) }}s"></s></span>@endforeach</span>
<span class="om" aria-hidden="true"><span class="omt">@foreach([0,1] as $__r)@foreach($__of as $o)<span>{{ $o[1] }}</span>@endforeach @endforeach</span></span>
</a>
@endif
