@extends('store.layout')
@section('ld')<script type="application/ld+json">{!! json_encode([['@'.'context'=>'https://schema.org','@type'=>'OnlineStore','name'=>'DutyFree BD','url'=>url('/').'/','logo'=>url('/icon.png'),'telephone'=>\App\Support\Biz::tel(\App\Support\Biz::hotline()),'email'=>\App\Support\Biz::email(),'address'=>['@type'=>'PostalAddress','streetAddress'=>'Al Sabka','addressLocality'=>'Deira, Dubai','addressCountry'=>'AE'],'openingHours'=>'Mo-Su '.\App\Support\Biz::hoursSchema(),'contactPoint'=>[['@type'=>'ContactPoint','telephone'=>\App\Support\Biz::tel(\App\Support\Biz::hotline()),'contactType'=>'customer service','availableLanguage'=>['bn','en']]],'areaServed'=>'BD'],['@'.'context'=>'https://schema.org','@type'=>'WebSite','name'=>'DutyFree BD','url'=>url('/').'/','inLanguage'=>'bn-BD','potentialAction'=>['@type'=>'SearchAction','target'=>url('/search').'?q={q}','query-input'=>'required name=q']]], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG) !!}</script>@endsection
@section('main')
<h1 style="position:absolute;width:1px;height:1px;margin:-1px;padding:0;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0">DutyFree BD — অরিজিনাল ইমপোর্টেড পারফিউম, স্কিন কেয়ার ও ডেইলি নিডস</h1>
@php [$fEnd,$fFix] = \App\Http\Controllers\StoreController::endAt($s['flash_end_at'] ?? ''); [$dEnd,$dFix] = \App\Http\Controllers\StoreController::endAt($s['deal_end_at'] ?? ''); $fOn = !$fFix || $fEnd > time(); $dOn = !$dFix || $dEnd > time(); $fmt = function($e){ $r = max(0, $e - time()); return sprintf('%02d:%02d:%02d', intdiv($r,3600), intdiv($r%3600,60), $r%60); }; @endphp
@php
  $ini = fn($n) => collect(preg_split('/[\s&\/-]+/', $n))->filter()->map(fn($w) => mb_strtoupper(mb_substr($w, 0, 1)))->take(3)->implode('');
  $flash = $deals; $plus = collect(); // Flash Sale = 2 products from every category
  $hsub = str_replace('{max}', (string)$maxOff, $s['hero_sub']);
@endphp
<style>
.hero{height:555px;background:linear-gradient(120deg,#0B1220 0%,#1F2A44 55%,#FF5A00 140%);display:flex;align-items:center;padding:0 140px;color:#fff;position:relative;padding-left:max(140px,calc((100% - 1440px) / 2 + 140px))}
.hero .in{display:flex;flex-direction:column;gap:18px;max-width:640px}.hero .k{font-size:13px;letter-spacing:.14em;font-weight:600;color:#FDC2A5}.hero h1{font-size:60px;font-weight:700;line-height:1.05}.hero .sb{font-size:20px;color:#E5E7EB}.hero .bt{align-self:flex-start;background:var(--o);color:#fff;font-weight:700;font-size:16px;border-radius:10px;padding:14px 30px}
.hero-i{max-width:1440px;margin:0 auto;display:block}.hero-i img{width:100%;height:auto;display:block}
.hm{display:none}
.fs{border:1.25px solid var(--bd);border-radius:12px;background:#fff;overflow:hidden}.fs .hd2{background:linear-gradient(90deg,#DC2626,#FF5A00);color:#fff;padding:10px 16px;display:flex;align-items:center;gap:14px}.fs .hd2 b.t{font-size:18px;letter-spacing:.02em}.fs .hd2 .e{font-size:11px;opacity:.9}
.tm{display:flex;gap:4px;font-weight:700;font-size:14px}.tm span.b{background:var(--n);border-radius:4px;padding:3px 7px}
.fs .tb{margin-left:auto;display:flex;gap:6px}.fs .tb button,.fs .tb a{font-size:11px;font-weight:700;border-radius:20px;padding:4px 12px;border:1px solid #fff;background:transparent;color:#fff}.fs .tb button.on{background:#fff;color:var(--red)}.fs .tb a{border:0;padding:4px 6px}
.fs .gr{padding:12px}.fs .pc{gap:6px}.fs .pc .bo{background:var(--red)}.fs .pc .ph{height:150px}.fs .pc .pr{font-size:13px}.fs .pc .pr b{color:var(--red);font-weight:700}.fs .pc .pr s{color:var(--mut);text-decoration:line-through;font-size:11px}
.bar{height:6px;background:#FEE2E2;border-radius:3px}.bar i{display:block;height:6px;background:var(--red);border-radius:3px}.sold{font-size:9px;color:var(--mut);margin-top:2px}
.fs .mtb{display:none}
.lvh{display:flex;flex-direction:column;gap:6px}.chip{align-self:flex-start;display:inline-flex;align-items:center;gap:6px;background:#DCFCE7;color:#14594A;font-size:11px;font-weight:700;letter-spacing:.06em;border-radius:20px;padding:4px 12px}.dot{width:7px;height:7px;border-radius:50%;background:#16A34A;display:inline-block}
.lvh h2{font-size:22px;font-weight:700;color:var(--n)}.lvh .su{font-size:13px;color:var(--mut)}
body .w [data-cr]{--pv:6;--g:10px;display:flex;gap:var(--g);padding:0;overflow-x:auto;overflow-y:hidden;scroll-snap-type:x mandatory;scrollbar-width:none;-webkit-overflow-scrolling:touch;overscroll-behavior-x:contain}body .w [data-cr]::-webkit-scrollbar{display:none}body .w [data-cr]>*{flex:0 0 calc((100% - (var(--pv) - 1)*var(--g))/var(--pv));scroll-snap-align:start;min-width:0}body .w [data-cr]>.pc:nth-child(n),body .w [data-cr]>.lo:nth-child(n){display:flex}body .w [data-cr="1"]{--pv:1!important}.fs .crw{padding:12px}.crw{position:relative}.cra{position:absolute;top:42%;z-index:5;width:38px;height:38px;border-radius:50%;border:1px solid #E5E7EB;background:#fff;color:var(--n);font-size:22px;font-weight:800;box-shadow:0 4px 12px rgba(0,0,0,.18);cursor:pointer;display:flex;align-items:center;justify-content:center;line-height:1;padding:0}.cra.l{left:-8px}.cra.r{right:-8px}.cra:hover{background:var(--o);color:#fff}[data-cr="1"]~.cra,.dl~.cra{top:46%}
@media(max-width:1200px){body .w [data-cr]{--pv:5}}@media(max-width:980px){body .w [data-cr]{--pv:4}}@media(max-width:760px){body .w [data-cr]{--pv:3;--g:6px}.cra{width:30px;height:30px;font-size:17px}.cra.l{left:-4px}.cra.r{right:-4px}.fs .crw{padding:6px}}
.lg2{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:11px}.lo{display:flex;flex-direction:column;gap:6px;min-width:0}.lo .tg{align-self:flex-start;display:inline-flex;align-items:center;gap:5px;background:#F0FDF4;color:#14594A;font-size:11px;font-weight:600;border-radius:20px;padding:3px 10px}.lo .tg .dot{width:6px;height:6px}
.lo .bx{border:1.25px solid var(--bd);border-radius:10px;background:#fff;padding:8px;display:flex;flex-direction:column;gap:6px}.lo .im{height:110px;border-radius:8px;background:#F5F5F5;display:flex;align-items:center;justify-content:center;overflow:hidden}.lo .im img{max-width:100%;max-height:100%;object-fit:contain}
.lo .wh{font-size:11px;color:#14594A;font-weight:600}.lo .nm{font-size:12px;font-weight:600;color:var(--n);line-height:1.3;height:32px;overflow:hidden}.lo .rw{display:flex;align-items:center;justify-content:space-between}.lo .rw b{font-size:14px;color:var(--o)}.lo .rw span{font-size:11px;font-weight:600;background:#FFF1E8;color:#C2410C;border-radius:6px;padding:2px 7px}.lo .dl{font-size:10.5px;color:var(--grn);font-weight:600}
.cts{display:flex;flex-direction:column;align-items:center;gap:16px}.cts h2{font-size:18px;font-weight:600}.ctl{display:flex;gap:11px;width:100%;flex-wrap:wrap}.ct{width:144px;height:104px;border:1.25px solid var(--bd);border-radius:10px;background:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px}.ct .ci{width:52px;height:52px;border-radius:50%;background:var(--n);color:#fff;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;overflow:hidden}.ct .ci img{width:100%;height:100%;object-fit:cover}.ct .cn{font-size:11px;font-weight:600;text-transform:uppercase;text-align:center;padding:0 4px}.ct .cn .m{display:none}
.st{display:flex;flex-direction:column;gap:16px}.st h2{font-size:20px;font-weight:600;text-align:center}
.ofr{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0}.of{height:330px;display:flex;flex-direction:column;justify-content:center;gap:10px;padding:0 40px;color:#fff;position:relative;overflow:hidden}.of .k{font-size:12px;font-weight:700;letter-spacing:.12em;color:#FDE68A}.of .t{font-size:34px;font-weight:700;line-height:1.15}.of .s{font-size:15px;color:#F3F4F6}.of .b{display:flex;gap:10px;margin-top:6px}.of .b a{font-weight:700;font-size:13px;padding:8px 14px;border-radius:6px}
.cs{display:flex;flex-direction:column;gap:14px}.cs .hh{display:flex;align-items:center;gap:14px}.cs .hh .t{font-size:18px;font-weight:600;flex-shrink:0;text-transform:uppercase}.cs .hh .sp{flex-grow:1}.cs .hh .tb{font-size:10px;font-weight:500;text-decoration:underline;color:#374151;text-transform:uppercase;white-space:nowrap}.cs .hh .va{background:var(--n);color:#fff;font-size:10px;font-weight:600;padding:4px 8px;white-space:nowrap}
.cs .mt,.cs .mv{display:none}
@media (max-width:1200px){.hero{padding:0 60px}.hero h1{font-size:48px}.lg2{grid-template-columns:repeat(3,minmax(0,1fr))}.cs .hh .tb:nth-of-type(n+4){display:none}}
@media (max-width:760px){
  .hero{display:none}.hero-i{display:none}
  .hm{display:flex;height:140px;background:linear-gradient(120deg,#7C2D12,#FF5A00);color:#fff;flex-direction:column;justify-content:center;padding:0 16px}.hm b{font-size:17px;line-height:1.2}.hm span{font-size:10px;margin-top:6px}.hm.im{height:auto;padding:0;background:none}.hm.im img{width:100%;height:auto;display:block}
  .sec-fs{padding:8px 8px 0!important}.fs{border-radius:10px}.fs .hd2{padding:7px 8px;gap:6px}.fs .hd2 b.t{font-size:12px}.fs .hd2 .e,.fs .tb button{display:none}.fs .tb{gap:0}.fs .tb a{font-size:9px;padding:0}
  .tm{gap:2px;font-size:10px}.tm span.b{border-radius:3px;padding:2px 4px}
  .fs .mtb{display:flex;gap:6px;padding:6px 6px 0}.fs .mtb button{font-size:9px;font-weight:700;border-radius:20px;padding:3px 10px;border:1px solid var(--red);background:#fff;color:var(--red)}.fs .mtb button.on{background:var(--red);color:#fff}
  .fs .gr{padding:6px;gap:5px}.fs .pc{border-radius:8px;padding:3px;gap:3px}.fs .pc:nth-child(n+4){display:none}.fs .pc .bo{left:4px;top:4px;font-size:8px;padding:1px 5px}.fs .pc .bo i{display:none}.fs .pc .ph{height:80px}.fs .pc .nm{font-size:9px;height:22px}.fs .pc .pr{font-size:9.5px}.fs .pc .pr s,.fs .pc .pr br,.fs .pc .sold{display:none}.bar,.bar i{height:4px}
  .sec-lv{padding:10px 8px 0!important}.chip{font-size:9px;padding:3px 9px;gap:5px;letter-spacing:0}.chip .dot{width:6px;height:6px}.lvh{gap:0}.lvh h2{font-size:14px;margin:4px 0 6px}.lvh .su{display:none}
  .lg2{grid-template-columns:repeat(3,minmax(0,1fr));gap:5px}.lo:nth-child(n+4){display:none}.lo{gap:0}.lo .tg{font-size:8px;padding:2px 6px;margin-bottom:3px}.lo .tg .dot{display:none}.lo .bx{border-radius:8px;padding:3px;gap:3px}.lo .im{height:56px;border-radius:6px}.lo .wh{font-size:8px}.lo .nm{font-size:9px;line-height:1.2;height:22px}.lo .rw b{font-size:10px}.lo .rw span,.lo .dl{display:none}
  .sec-ct{padding:0 8px!important}.cts{gap:0}.cts h2{font-size:13px;margin:10px 0 6px}.ctl{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:6px}.ct{width:auto;height:auto;border-radius:8px;padding:6px 2px;gap:4px}.ct .ci{width:36px;height:36px;background:#14261F}.ct .cn{font-size:8px}.ct .cn .d{display:none}.ct .cn .m{display:inline}
  .sec-st{padding:0 8px!important}.st{gap:0}.st h2{font-size:13px;margin:14px 0 4px}.st .gr .pc:nth-child(n+7){display:none}
  .sec-top,.sec-of{display:none}
  .sec-cs{padding:0 8px!important}.cs{gap:0}.cs .hh{display:none}.cs .mt{display:flex;align-items:center;justify-content:space-between;gap:10px;margin:18px 2px 10px}.cs .mt span{position:relative;padding-left:12px;font-size:17px;font-weight:800;color:var(--n);letter-spacing:.01em;line-height:1.2}.cs .mt span::before{content:'';position:absolute;left:0;top:2px;bottom:2px;width:4px;border-radius:4px;background:var(--o)}.cs .mt a{flex:none;font-size:12px;font-weight:700;color:var(--o);background:#FFF1E8;border:1px solid var(--bd);border-radius:999px;padding:6px 12px}.cs .gr .pc:nth-child(n+7){display:none}
  .cs .mv{display:block;text-align:center;margin:14px 0 0}.cs .mv a{display:flex;align-items:center;justify-content:center;gap:6px;background:#fff;color:var(--n);font-size:13px;font-weight:700;padding:12px 18px;border:1.5px solid var(--n);border-radius:12px}.mv a::after{content:'→';color:var(--o);font-size:15px}
}

.pm{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;padding-top:14px}.pm a{display:flex;gap:10px;align-items:center;background:#fff;border:1.25px solid var(--bd);border-radius:10px;padding:10px 14px}.pm i{font-style:normal;width:34px;height:34px;border-radius:50%;background:var(--n);color:#fff;display:flex;align-items:center;justify-content:center;font-size:15px;flex:none}.pm b{display:block;font-size:13px;color:var(--n)}.pm span{font-size:11px;color:var(--mut)}
.fst{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.fst a{background:#fff;border:1.25px solid var(--bd);border-radius:12px;overflow:hidden;display:flex;flex-direction:column}.fst .cv{height:64px;background:linear-gradient(120deg,var(--n),#1F2A44 60%,var(--o))}.fst .bd{padding:0 14px 14px;margin-top:-22px;display:flex;flex-direction:column;gap:4px}.fst .lg{width:44px;height:44px;border-radius:12px;background:var(--o);color:#fff;border:3px solid #fff;display:flex;align-items:center;justify-content:center;font-weight:700}.fst b{font-size:14px;color:var(--n)}.fst .vf{font-size:10px;font-weight:700;color:#14594A;background:#DCFCE7;border-radius:20px;padding:1px 8px;align-self:flex-start}.fst .tp{font-size:11px;color:var(--mut)}.fst .vs{margin-top:6px;background:var(--n);color:#fff;font-size:11px;font-weight:700;border-radius:6px;text-align:center;padding:6px 0}
.td{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.2fr);background:#fff;border:1.25px solid var(--bd);border-radius:14px;overflow:hidden}.td .im{background:#fff;display:flex;align-items:center;justify-content:center;min-height:260px}.td .im img{max-width:80%;max-height:240px;object-fit:contain}.td .tx{padding:26px;display:flex;flex-direction:column;gap:10px;justify-content:center}.td .k{font-size:12px;font-weight:700;letter-spacing:.1em;color:var(--o)}.td h3{font-size:22px;color:var(--n);line-height:1.25}.td .pp{font-size:26px;font-weight:700;color:var(--o)}.td .pp s{font-size:15px;color:var(--mut);font-weight:500;margin-left:8px}.td .pp em{font-style:normal;font-size:12px;background:var(--red);color:#fff;border-radius:20px;padding:2px 10px;margin-left:8px;vertical-align:middle}.td .bt{align-self:flex-start;background:var(--o);color:#fff;font-weight:700;border-radius:10px;padding:12px 28px}
.tr{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px}.tr div{background:#fff;border:1.25px solid var(--bd);border-radius:10px;padding:14px 8px;text-align:center;font-size:12px;font-weight:600;color:var(--n)}.tr i{display:flex;justify-content:center;font-style:normal;color:var(--o);margin-bottom:6px}.tr i svg{width:26px;height:26px}.pm i svg{width:20px;height:20px}
.sl{background:linear-gradient(120deg,var(--n),#1F2A44);border-radius:14px;color:#fff;padding:30px 36px;display:flex;align-items:center;gap:20px;flex-wrap:wrap}.sl div{flex:1;min-width:220px}.sl b{font-size:22px}.sl span{display:block;font-size:13px;color:#CBD5E1;margin-top:4px}.sl a{background:var(--o);color:#fff;font-weight:700;border-radius:10px;padding:12px 26px}
.ap{background:#fff;border:1.25px solid var(--bd);border-radius:14px;padding:22px 28px;display:flex;align-items:center;gap:16px;flex-wrap:wrap}.ap div{flex:1;min-width:200px}.ap b{font-size:18px;color:var(--n)}.ap a{background:var(--n);color:#fff;font-weight:700;font-size:12px;border-radius:8px;padding:10px 18px}
.fq{max-width:860px;margin:0 auto;display:flex;flex-direction:column;gap:8px}.fq details{background:#fff;border:1.25px solid var(--bd);border-radius:10px;padding:12px 16px}.fq summary{font-weight:600;font-size:14px;color:var(--n);cursor:pointer}.fq p{margin:8px 0 0;font-size:13px;color:#374151;line-height:1.55}
@media (max-width:760px){.pm{grid-template-columns:repeat(3,minmax(0,1fr));gap:5px;padding-top:8px}.pm a{padding:6px;gap:5px;flex-direction:column;text-align:center;border-radius:8px}.pm i{width:24px;height:24px;font-size:11px}.pm b{font-size:10.5px;line-height:1.2}.pm span span{display:none}
.fst{display:flex;overflow-x:auto;gap:8px;scrollbar-width:none}.fst a{flex:0 0 150px}.fst::-webkit-scrollbar{display:none}.td{grid-template-columns:1fr}.td .im{min-height:150px}.td .tx{padding:14px}.td h3{font-size:15px}.td .pp{font-size:19px}
.tr{grid-template-columns:repeat(3,minmax(0,1fr));gap:5px}.tr div{padding:8px 3px;font-size:9px;border-radius:8px}.tr i svg{width:18px;height:18px}.sl{padding:16px}.sl b{font-size:15px}.ap{padding:14px}}
.sld.mb{display:none}@media (max-width:760px){.sld.dk{display:none}.sld.mb{display:block}}.sld{position:relative;max-width:1440px;margin:0 auto}.sld .sl-t{display:flex;overflow-x:auto;scroll-snap-type:x mandatory;scrollbar-width:none}.sld .sl-t::-webkit-scrollbar{display:none}.sld .sl-t>a{flex:0 0 100%;scroll-snap-align:start;display:block}.sld .sl-t img{width:100%;height:auto;display:block}.sl-d{position:absolute;left:0;right:0;bottom:10px;display:flex;justify-content:center;gap:6px;pointer-events:none}.sl-d i{width:8px;height:8px;border-radius:50%;background:rgba(255,255,255,.55)}.sl-d i.on{background:var(--o);width:20px;border-radius:6px}
.osl .sl-t>a{flex:0 0 50%;aspect-ratio:576/296;overflow:hidden}.osl .sl-t img{width:100%;height:100%;object-fit:cover}@media (max-width:760px){.osl .sl-t>a{flex:0 0 88%;margin-right:8px;border-radius:12px}}
.hx{display:grid;grid-template-columns:minmax(0,1fr);gap:16px;padding-top:18px}.hx-m{min-width:0;border-radius:18px;overflow:hidden;position:relative;background:#0B1220}.hxs .sl-t>a{flex:0 0 100%}.hxa{position:relative;display:block;aspect-ratio:1440/480}.hxa picture,.hxa img{display:block;width:100%;height:100%}.hxa img{object-fit:cover}.hxt{position:absolute;left:36px;top:50%;transform:translateY(-50%);max-width:48%;display:flex;flex-direction:column;gap:10px;color:#fff;text-shadow:0 1px 8px rgba(0,0,0,.45)}.hxt b{font-size:34px;line-height:1.1}.hxt span{font-size:16px}.hxt em{align-self:flex-start;font-style:normal;background:var(--o);color:#fff;font-weight:700;border-radius:999px;padding:10px 20px;text-shadow:none;font-size:14px}
.sl-a{position:absolute;top:50%;transform:translateY(-50%);width:42px;height:42px;border-radius:50%;border:0;background:rgba(255,255,255,.88);color:#0B1220;font-size:26px;line-height:1;cursor:pointer;box-shadow:0 2px 10px rgba(0,0,0,.2);opacity:0;transition:opacity .2s;z-index:3;padding:0 0 3px}.sl-a.pv{left:14px}.sl-a.nx{right:14px}.hxs:hover .sl-a{opacity:1}.sl-a:hover{background:#fff;color:var(--o)}@media(max-width:760px){.sl-a{display:none}}
.hxd{aspect-ratio:1440/480;display:flex;flex-direction:column;justify-content:center;gap:12px;padding:0 44px;color:#fff;background:linear-gradient(120deg,#0B1220 0%,#1F2A44 55%,#FF5A00 140%)}.hxd .k{font-size:12px;letter-spacing:.14em;font-weight:600;color:#FDC2A5}.hxd h1{font-size:40px;line-height:1.1}.hxd .sb{font-size:16px;color:#E5E7EB}.hxd .bt{align-self:flex-start;background:var(--o);color:#fff;font-weight:700;border-radius:999px;padding:11px 22px}
.hx-s{display:grid;grid-template-rows:1fr 1fr;gap:16px;min-width:0}.hxc{position:relative;display:flex;align-items:center;justify-content:space-between;gap:8px;border-radius:18px;padding:18px 20px;overflow:hidden;color:#0B1220;text-decoration:none;box-shadow:0 1px 3px rgba(0,0,0,.06)}.hxc.c1{background:linear-gradient(135deg,#FFE7D2,#FFC9A0)}.hxc.c2{background:linear-gradient(135deg,#E6EEFF,#C7D7FB)}.hxc .tx{display:flex;flex-direction:column;gap:6px;position:relative;z-index:1;max-width:64%}.hxc b{font-size:20px;line-height:1.15}.hxc .tx>span{font-size:13px;line-height:1.5;color:#374151}.hxc em{align-self:flex-start;margin-top:6px;font-style:normal;background:#fff;color:var(--o);font-weight:700;font-size:13px;border-radius:999px;padding:8px 16px;box-shadow:0 1px 2px rgba(0,0,0,.08)}.hxc.c2 em{color:#1D4ED8}.hxc svg{width:42%;max-width:130px;height:auto;flex:none}
@media (max-width:1200px){.hxt b{font-size:26px}.hxc b{font-size:17px}}
@media (max-width:760px){.hx{grid-template-columns:1fr;gap:10px;padding-top:10px}.hx-m{border-radius:12px}.hxa{aspect-ratio:16/9}.hxd{aspect-ratio:auto;padding:22px 18px}.hxd h1{font-size:24px}.hxt{left:14px;max-width:70%}.hxt b{font-size:18px}.hxt span{font-size:12px}.hxt em{padding:7px 14px;font-size:12px}.hx-s{grid-template-rows:none;grid-template-columns:1fr;gap:10px}.hxc{border-radius:14px;padding:14px 16px}.hxc b{font-size:16px}.hxc svg{width:90px}}
</style>
<style>
/* offer banners: minimal light animations (1st = soft shine, 2nd = gentle sparkle) */
.osl .sl-t>a{position:relative;display:block;border-radius:14px;transition:transform .35s cubic-bezier(.2,.8,.2,1),box-shadow .35s}
.osl .sl-t:not(:has(>a:nth-child(3)))>a{flex-basis:calc(50% - 7px);margin-right:14px;box-shadow:0 4px 14px rgba(15,23,42,.08)}
.osl .sl-t:not(:has(>a:nth-child(3)))>a:last-child{margin-right:0}
.osl .sl-t>a:hover{transform:translateY(-3px);box-shadow:0 10px 24px rgba(15,23,42,.14)}
.osl .sl-t>a::before,.osl .sl-t>a::after{content:"";position:absolute;pointer-events:none;z-index:2}
/* 1 - cigarette: golden light sweep + pulsing glow on the discount badge */
.osl .sl-t>a:nth-child(odd)::before{top:0;bottom:0;left:-40%;width:26%;background:linear-gradient(100deg,transparent,rgba(255,255,255,.38),transparent);transform:skewX(-18deg);animation:oslSweep 8s cubic-bezier(.4,0,.2,1) infinite}
@keyframes oslSweep{0%{left:-40%}38%,100%{left:130%}}
/* 2 - perfume: twinkling sparkles (two groups out of phase) + a slow drift of the picture */
.osl .sl-t>a:nth-child(even)::before{inset:0;background:radial-gradient(circle,#fff 0 1.6px,rgba(255,255,255,.55) 2.4px,transparent 5px) 12% 30%/16px 16px no-repeat,linear-gradient(90deg,transparent,#fff,transparent) 12% 30%/16px 1px no-repeat,linear-gradient(180deg,transparent,#fff,transparent) 12% 30%/1px 16px no-repeat,radial-gradient(circle,#fff 0 1.6px,rgba(255,255,255,.55) 2.4px,transparent 5px) 28% 22%/16px 16px no-repeat,linear-gradient(90deg,transparent,#fff,transparent) 28% 22%/16px 1px no-repeat,linear-gradient(180deg,transparent,#fff,transparent) 28% 22%/1px 16px no-repeat,radial-gradient(circle,#fff 0 1.6px,rgba(255,255,255,.55) 2.4px,transparent 5px) 44% 70%/16px 16px no-repeat,linear-gradient(90deg,transparent,#fff,transparent) 44% 70%/16px 1px no-repeat,linear-gradient(180deg,transparent,#fff,transparent) 44% 70%/1px 16px no-repeat;animation:oslTw 2.8s ease-in-out infinite}
.osl .sl-t>a:nth-child(even)::after{inset:0;background:radial-gradient(circle,#fff 0 1.6px,rgba(255,255,255,.55) 2.4px,transparent 5px) 20% 60%/16px 16px no-repeat,linear-gradient(90deg,transparent,#fff,transparent) 20% 60%/16px 1px no-repeat,linear-gradient(180deg,transparent,#fff,transparent) 20% 60%/1px 16px no-repeat,radial-gradient(circle,#fff 0 1.6px,rgba(255,255,255,.55) 2.4px,transparent 5px) 36% 45%/16px 16px no-repeat,linear-gradient(90deg,transparent,#fff,transparent) 36% 45%/16px 1px no-repeat,linear-gradient(180deg,transparent,#fff,transparent) 36% 45%/1px 16px no-repeat,radial-gradient(circle,#fff 0 1.6px,rgba(255,255,255,.55) 2.4px,transparent 5px) 24% 80%/16px 16px no-repeat,linear-gradient(90deg,transparent,#fff,transparent) 24% 80%/16px 1px no-repeat,linear-gradient(180deg,transparent,#fff,transparent) 24% 80%/1px 16px no-repeat;animation:oslTw 2.8s ease-in-out 1.4s infinite}
.osl .sl-t>a:nth-child(even) img{animation:oslDrift 11s ease-in-out infinite alternate}
@keyframes oslTw{0%,100%{opacity:0;transform:scale(.8)}50%{opacity:.85;transform:scale(1)}}
@keyframes oslDrift{from{transform:scale(1)}to{transform:scale(1.03)}}
/* animated product banners (555 / Dunhill): poster + .mp4 twin share one 1266x650 frame, so nothing is stretched or cropped */
.osl .sl-t>a.osv{aspect-ratio:1266/650;background:#0b1220}
.osl .osv::before,.osl .osv::after{display:none!important}
.osl .osv img,.osl .osvv{position:absolute;left:0;top:0;width:100%;height:100%;object-fit:cover;border-radius:inherit;animation:none!important}
.osl .osvv{opacity:0;transition:opacity .5s;z-index:1}.osl .osvv.on{opacity:1}
@media (max-width:760px){.osl .sl-t:not(:has(>a:nth-child(3)))>a,.osl .sl-t:not(:has(>a:nth-child(3)))>a:last-child{flex-basis:88%;margin-right:8px}}
/* Recently ordered - modern card strip */
body .sec-lv .lvh{position:relative;gap:4px}
body .sec-lv .chip{background:#ECFDF5;color:#047857;border:1px solid #A7F3D0;font-size:11px;letter-spacing:.08em;padding:4px 12px 4px 10px}
body .sec-lv .chip .dot,body .sec-lv .lo .tg .dot{position:relative;background:#10B981}
body .sec-lv .chip .dot:after,body .sec-lv .lo .tg .dot:after{content:"";position:absolute;inset:-3px;border-radius:50%;border:2px solid #10B981;opacity:0;animation:lvRip 1.9s ease-out infinite}
@keyframes lvRip{0%{transform:scale(.5);opacity:.9}100%{transform:scale(1.9);opacity:0}}
body .sec-lv .lvh h2{font-size:28px;font-weight:800;letter-spacing:-.015em;line-height:1.15;margin-top:2px}
body .sec-lv .lvh h2 em{font-style:normal;background:linear-gradient(90deg,var(--o),#FF9A55);-webkit-background-clip:text;background-clip:text;color:transparent}
body .sec-lv .lvc{position:absolute;right:0;bottom:2px;font-size:12px;color:var(--mut);background:#F4F6FA;border-radius:20px;padding:5px 13px}
body .sec-lv .lvc b{color:var(--n);font-weight:800}
body .sec-lv .lo{position:relative;gap:0;animation:lvIn .6s cubic-bezier(.2,.8,.2,1) both}
body .sec-lv .lo:nth-child(2){animation-delay:.06s}body .sec-lv .lo:nth-child(3){animation-delay:.12s}body .sec-lv .lo:nth-child(4){animation-delay:.18s}body .sec-lv .lo:nth-child(5){animation-delay:.24s}body .sec-lv .lo:nth-child(n+6){animation-delay:.3s}
@keyframes lvIn{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:none}}
body .sec-lv .lo .bx{border:1px solid #EDF0F5;border-radius:18px;background:linear-gradient(180deg,#fff,#FAFBFD);padding:10px 10px 12px;gap:7px;box-shadow:0 1px 2px rgba(16,24,40,.04),0 10px 24px -12px rgba(16,24,40,.16);transition:transform .3s cubic-bezier(.2,.8,.2,1),box-shadow .3s,border-color .3s;text-decoration:none}
body .sec-lv .lo .bx:hover{transform:translateY(-4px);border-color:#FFD2B5;box-shadow:0 2px 4px rgba(16,24,40,.05),0 18px 32px -14px rgba(242,107,33,.32)}
body .sec-lv .lo .tg{position:absolute;z-index:2;top:19px;left:19px;margin:0;background:rgba(255,255,255,.94);backdrop-filter:blur(6px);color:#065F46;font-size:11px;font-weight:700;padding:4px 10px 4px 9px;box-shadow:0 3px 10px rgba(16,24,40,.12)}
body .sec-lv .lo .im{height:124px;border-radius:13px;background:radial-gradient(circle at 50% 38%,#fff 0,#F0F4F9 72%)}
body .sec-lv .lo .im img{transition:transform .45s cubic-bezier(.2,.8,.2,1);filter:drop-shadow(0 6px 8px rgba(16,24,40,.16))}
body .sec-lv .lo .bx:hover .im img{transform:scale(1.07) translateY(-2px)}
body .sec-lv .lo .wh{display:inline-flex;align-items:center;gap:5px;font-size:10.5px;font-weight:700;color:#047857;letter-spacing:.01em}
body .sec-lv .lo .wh:before{content:"";width:13px;height:13px;border-radius:50%;background:#10B981 url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23fff' stroke-width='4' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M5 12.5l4.5 4.5L19 7'/%3E%3C/svg%3E") center/9px no-repeat}
body .sec-lv .lo .nm{font-size:13px;font-weight:600;line-height:1.35;height:35px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}
body .sec-lv .lo .rw{margin-top:1px}
body .sec-lv .lo .rw b{font-size:17px;font-weight:800;letter-spacing:-.01em}
body .sec-lv .lo .rw span{background:var(--n);color:#fff;border-radius:20px;padding:2px 9px;font-size:11px;font-weight:700}
body .sec-lv .lo .dl{align-self:flex-start;background:#ECFDF5;color:#047857;font-size:10.5px;font-weight:700;border-radius:20px;padding:3px 10px}
@media (max-width:760px){
 body .sec-lv .lvh h2{font-size:17px;margin:3px 0 7px}body .sec-lv .lvc{display:none}
 body .sec-lv .lo .bx{border-radius:12px;padding:5px 5px 7px;gap:4px}
 body .sec-lv .lo .tg{top:10px;left:10px;font-size:8.5px;padding:2px 7px 2px 6px}body .sec-lv .lo .tg .dot{display:inline-block;width:5px;height:5px}
 body .sec-lv .lo .im{height:auto;aspect-ratio:1/1;border-radius:9px}
 body .sec-lv .lo .wh{display:none}body .sec-lv .lo .nm{font-size:10.5px;height:2.7em;line-height:1.35}
 body .sec-lv .lo .rw b{font-size:12.5px}
}
/* Recently ordered v2 - light, premium, live */
body .sec-lv .lvbox{background:#fff;border:1px solid #E7EDF3;border-radius:20px;padding:22px;box-shadow:0 1px 2px rgba(16,24,40,.03),0 8px 24px -16px rgba(16,24,40,.12);display:flex;flex-direction:column;gap:14px}
body .sec-lv .lvh{position:relative;display:flex;flex-direction:column;align-items:flex-start;gap:4px}
body .sec-lv .lvh h2{font-size:28px;font-weight:700;color:var(--n)}
body .sec-lv .lvh h2 em{background:none;-webkit-text-fill-color:var(--o);color:var(--o)}
body .sec-lv .lvh .su{font-size:13px;font-weight:400;color:#6B7280}
body .sec-lv .lvc{background:#F1F5F9;font-weight:500;border:1px solid #E7EDF3;bottom:4px}
body .sec-lv .lg2[data-cr]{--pv:6;--g:12px;padding:4px 2px 8px}
body .sec-lv .lo{animation:none}
body .sec-lv .lo .bx{border:1px solid #E1E8EF;border-radius:16px;background:#fff;padding:10px 10px 12px;gap:7px;box-shadow:0 1px 2px rgba(16,24,40,.03);height:100%}
body .sec-lv .lo .bx:hover{transform:translateY(-2px);border-color:#C9D6E2;box-shadow:0 10px 22px -12px rgba(16,24,40,.22)}
body .sec-lv .lo .im{position:relative;height:160px;display:flex;align-items:center;justify-content:center;overflow:hidden;border-radius:12px;background:linear-gradient(135deg,#F0FBF5 0%,#DDF5E7 100%)}
body .sec-lv .lo .im img{width:auto;height:auto;max-width:86%;max-height:84%;object-fit:contain;filter:drop-shadow(0 6px 8px rgba(16,24,40,.14))}
body .sec-lv .lo .bx:hover .im img{transform:scale(1.03)}
body .sec-lv .lo .tg{position:absolute;top:8px;left:8px;z-index:2;height:25px;display:inline-flex;align-items:center;gap:4px;background:#fff;border:1px solid #BBF0D2;color:#047857;font-size:10.5px;font-weight:600;padding:0 9px 0 7px;border-radius:14px;box-shadow:0 2px 6px rgba(16,24,40,.08);backdrop-filter:none}
body .sec-lv .lo .tg svg{width:12px;height:12px;flex:none}body .sec-lv .lo .tg i{font-style:normal;white-space:nowrap}
body .sec-lv .lo .nm{color:#111827;font-weight:600;font-size:13.5px}
body .sec-lv .lo .rw{display:flex;align-items:center;justify-content:space-between;gap:6px}
body .sec-lv .lo .rw b{font-size:19px;font-weight:700;color:var(--o);white-space:nowrap}
body .sec-lv .lo .rw span{height:23px;display:inline-flex;align-items:center;border-radius:12px;padding:0 9px;font-size:11px}
body .sec-lv .lo .dl{display:inline-flex;align-items:center;gap:5px;height:27px;border-radius:14px;background:#E8F8EF;color:#047857;font-size:11px;font-weight:600;padding:0 11px 0 9px}
body .sec-lv .lo .dl svg{width:15px;height:15px;flex:none}
body .sec-lv .cra{width:40px;height:40px;border:0;color:var(--n);box-shadow:0 4px 14px rgba(16,24,40,.18);top:44%}
body .sec-lv .cra.l{left:-17px}body .sec-lv .cra.r{right:-17px}body .sec-lv .cra:hover{background:var(--n);color:#fff}
body .sec-lv .lvempty{padding:34px 10px;text-align:center;color:#6B7280;font-size:14px}
body .sec-lv .lvbody{transition:opacity .3s}body .sec-lv .lvbody.sw{opacity:.35}
@media(max-width:1200px){body .sec-lv .lg2[data-cr]{--pv:5}}
@media(max-width:980px){body .sec-lv .lg2[data-cr]{--pv:3}body .sec-lv .lo .im{height:150px}}
@media(max-width:760px){
 body .sec-lv .lvbox{padding:14px 12px;border-radius:16px}
 body .sec-lv .lvh h2{font-size:22px}body .sec-lv .lvh .su{font-size:11.5px}
 body .sec-lv .lvc{display:inline-block;position:static;margin-top:6px;font-size:11px;padding:3px 10px}
 body .sec-lv .lg2[data-cr]{--pv:2;--g:8px}
 body .sec-lv .cra{display:none!important}
 body .sec-lv .lo .bx{padding:6px 6px 9px;border-radius:13px;gap:5px}
 body .sec-lv .lo .im{height:auto;aspect-ratio:1/1;border-radius:10px}
 body .sec-lv .lo .tg{top:5px;left:5px;height:21px;font-size:9px;padding:0 7px 0 5px;gap:3px}body .sec-lv .lo .tg svg{width:10px;height:10px}
 body .sec-lv .lo .wh{display:inline-flex;font-size:10px}body .sec-lv .lo .nm{font-size:12px;height:2.7em}
 body .sec-lv .lo .rw b{font-size:15px}body .sec-lv .lo .rw span{height:20px;padding:0 7px;font-size:10px}
 body .sec-lv .lo .dl{height:23px;font-size:10px;padding:0 8px 0 6px}body .sec-lv .lo .dl svg{width:13px;height:13px}
}
@media (prefers-reduced-motion:reduce){body .sec-lv .lo,body .sec-lv .chip .dot:after,body .sec-lv .lo .tg .dot:after{animation:none}}
@media (max-width:760px){.osl .sl-t>a:hover{transform:none}}
@media (prefers-reduced-motion:reduce){.osl .sl-t>a::before,.osl .sl-t>a::after,.osl .sl-t>a img{animation:none!important}.osl .sl-t>a::before,.osl .sl-t>a::after{opacity:0}}
</style>
<script>/* Slow, smooth slider motion: the current slide leaves on one side while the next one enters from the other (about 1.6 s, eased). At the end it keeps moving the same way into the first slide instead of rewinding. */
(function(){var DUR=1600;function ez(x){return x<.5?4*x*x*x:1-Math.pow(-2*x+2,3)/2}
function mkClone(src){var c=src.cloneNode(true);c.setAttribute('data-slc','1');c.setAttribute('aria-hidden','true');c.classList.remove('hxv','pl','ct');[].slice.call(c.querySelectorAll('.vf,video,iframe,.hxp,.hxm,a,button')).forEach(function(x){if(x.tagName==='A'||x.tagName==='BUTTON'){x.setAttribute('tabindex','-1')}else x.remove()});return c}
window.__slMove=function(t,dir){if(!t||t._an||!t.clientWidth||!t.children.length)return;var real=[].slice.call(t.children).filter(function(k){return !k.hasAttribute('data-slc')}),n=real.length;if(n<2)return;
var gap=parseFloat(getComputedStyle(t).columnGap)||0,c=real[0].offsetWidth+gap,a=Math.round(t.scrollLeft/c),rm=window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches,clone=null,from,to,fin;
if(!t._ab){t._ab=1;['touchstart','wheel','pointerdown'].forEach(function(ev){t.addEventListener(ev,function(){t._stop=1},{passive:true})})}
t.style.scrollSnapType='none';
if(dir>0){if(a>=n-1){clone=mkClone(real[0]);t.appendChild(clone)}from=a*c;to=(a+1)*c;t.scrollLeft=from;fin=function(){if(clone){clone.remove();t.scrollLeft=0}else t.scrollLeft=to}}
else{if(a<=0){clone=mkClone(real[n-1]);t.insertBefore(clone,t.firstChild);from=c;to=0;t.scrollLeft=from;fin=function(){clone.remove();t.scrollLeft=(n-1)*c}}else{from=a*c;to=(a-1)*c;t.scrollLeft=from;fin=function(){t.scrollLeft=to}}}
function done(){t._an=0;t._stop=0;fin();t.style.scrollSnapType='';try{t.dispatchEvent(new Event('scroll'))}catch(e){}}
if(rm){done();return}
t._an=1;t._stop=0;var t0=null;function step(ts){if(t0===null)t0=ts;var x=Math.min(1,(ts-t0)/DUR);t.scrollLeft=from+(to-from)*ez(x);if(t._stop){done();return}if(x<1)requestAnimationFrame(step);else done()}requestAnimationFrame(step)};
document.addEventListener('click',function(e){var b=e.target.closest&&e.target.closest('.sl-a');if(!b)return;var t=b.parentNode.querySelector('.sl-t');if(t)window.__slMove(t,b.classList.contains('nx')?1:-1)})})();</script>
<style>.hxapp{position:absolute;left:0;right:0;bottom:0;z-index:4;display:flex;align-items:center;justify-content:space-between;gap:14px;padding:34px 22px 12px;background:linear-gradient(to top,rgba(5,10,22,.94) 0%,rgba(5,10,22,.72) 55%,rgba(5,10,22,0) 100%);color:#fff;pointer-events:none}.hxapp .ap-t{display:flex;flex-direction:column;min-width:0;text-align:left;text-shadow:0 1px 6px rgba(0,0,0,.5)}.hxapp .ap-t b{font-size:17px;line-height:1.25}.hxapp .ap-t small{font-size:12.5px;opacity:.9;margin-top:2px}.hxapp .ap-b{display:flex;gap:10px;flex:0 0 auto}.hxapp .ap-btn{pointer-events:auto;display:inline-flex;align-items:center;gap:10px;height:52px;padding:0 20px 0 14px;border-radius:13px;background:#fff;color:#0B1220;font:800 15px/1.1 inherit;cursor:pointer;box-shadow:0 4px 16px rgba(0,0,0,.35);border:1px solid rgba(255,255,255,.9);white-space:nowrap;transition:transform .15s}.hxapp .ap-btn:hover{transform:translateY(-2px)}.hxapp .ap-btn.a{background:var(--o,#F97316);color:#fff;border-color:transparent}.hxapp .ap-btn svg{width:26px;height:26px;flex:0 0 auto}.hxapp .ap-btn span{display:flex;flex-direction:column;align-items:flex-start;font-size:15px;font-weight:800}.hxapp .ap-btn small{font-size:10.5px;font-weight:600;opacity:.8}.hxs .dfg-ab button{min-height:50px;padding:6px 20px 6px 14px;border-radius:12px;gap:10px}.hxs .dfg-ab button svg{width:26px;height:26px}.hxs .dfg-ab button b{font-size:15px}.hxs .dfg-ab button small{font-size:10px}.hxapp.mini{background:none;padding:0 14px 12px;justify-content:flex-end}.hxapp.mini .ap-t{display:none}.hxapp.mini .ap-btn{height:44px;padding:0 16px 0 12px}.hxs .hxk{bottom:84px}@media (max-width:760px){.hxs .sl-t>.hxg:not(:only-child),.hxs .sl-t>.dmz:not(:only-child),.hxs .sl-t>.hxmc:not(:only-child){display:none}.hxapp.mini{padding:0 8px 6px}.hxapp.mini .ap-btn{height:34px;padding:0 8px}.hxapp.mini .ap-btn svg{width:17px;height:17px}.hxapp.mini .ap-btn span{font-size:12px}.hxs .dfg-ab button{min-height:40px;padding:5px 10px}.hxs .dfg-ab button svg{width:19px;height:19px}.hxs .dfg-ab button b{font-size:12.5px}.hxapp{padding:18px 8px 6px;gap:8px}.hxapp .ap-t{display:none}.hxapp .ap-b{width:100%}.hxapp .ap-btn{flex:1;justify-content:center;height:34px;padding:0 10px;gap:7px;border-radius:9px}.hxapp .ap-btn svg{width:18px;height:18px}.hxapp .ap-btn span{font-size:13px}.hxapp .ap-btn small{display:none}.hxs .hxk{bottom:58px}}</style>
<script>document.addEventListener('DOMContentLoaded',function(){var box=document.querySelector('.hxs');if(!box)return;try{if(matchMedia('(display-mode: standalone)').matches)return}catch(e){}
var ic=document.querySelectorAll('#dfapp .dfa-i'),aI=ic[0]?ic[0].innerHTML:'',iI=ic[1]?ic[1].innerHTML:'';var ios=/iPad|iPhone|iPod/.test(navigator.userAgent);
var A='<span role="button" tabindex="0" class="ap-btn a" data-appdl="android">'+aI+'<span>Android App<small>ফ্রি ডাউনলোড</small></span></span>',I='<span role="button" tabindex="0" class="ap-btn" data-appdl="ios">'+iI+'<span>iPhone App<small>ফ্রি ডাউনলোড</small></span></span>';
var h0='<span class="hxapp%M%"><span class="ap-t"><b>📱 DutyFreeBD অ্যাপ ডাউনলোড করুন</b><small>দ্রুত অর্ডার · লাইভ ট্র্যাকিং · এক্সক্লুসিভ অফার</small></span><span class="ap-b">'+(ios?I+A:A+I)+'</span></span>';
box.querySelectorAll('.sl-t>*').forEach(function(sl){if(sl.querySelector('.hxapp,[data-pwa]'))return;var mini=/\b(dmz|hxmc)\b/.test(sl.className);sl.insertAdjacentHTML('beforeend',h0.replace('%M%',mini?' mini':''))});
function go(e){var b=e.target.closest&&e.target.closest('[data-appdl]');if(!b)return;e.preventDefault();e.stopPropagation();var k=b.getAttribute('data-appdl');try{if(localStorage.getItem('dfInstalled')==='1'&&!matchMedia('(display-mode: standalone)').matches)localStorage.removeItem('dfInstalled')}catch(x){}if(window.DFPWA&&DFPWA.install)DFPWA.install(k);else location.hash='#dfapp'}
box.addEventListener('click',go,true);box.addEventListener('keydown',function(e){if(e.key==='Enter'||e.key===' ')go(e)},true)});</script>
<style>.hxs .sl-t>.hxa{flex:0 0 100%;scroll-snap-align:start}.hxn{background:#0b1220;overflow:hidden}.hxnv{position:absolute;left:0;top:0;width:100%;height:100%;object-fit:cover;display:block;pointer-events:none;z-index:2}.hxn .hxapp{z-index:4}.hxv{overflow:hidden;background:#000}.hxv .vf{position:absolute;left:0;top:50%;width:100%;aspect-ratio:16/9;transform:translateY(-50%);border:0;pointer-events:none;opacity:0;transition:opacity .4s}.hxv.pl .vf{opacity:1}.hxv.ct .vf{pointer-events:auto}.hxv .hxt,.hxv .hxk{z-index:2}.hxk{position:absolute;bottom:34px;background:var(--o);color:#fff;font-weight:700;border-radius:999px;padding:10px 20px;font-size:14px;text-decoration:none;box-shadow:0 2px 10px rgba(0,0,0,.3)}.hxk.left{left:36px}.hxk.right{right:36px}.hxk.center{left:50%;transform:translateX(-50%)}.hxp{position:absolute;inset:0;margin:auto;width:64px;height:64px;border-radius:50%;border:0;background:rgba(0,0,0,.55);color:#fff;font-size:26px;cursor:pointer;z-index:3}.hxm{position:absolute;right:14px;bottom:30px;width:36px;height:36px;border-radius:50%;border:0;background:rgba(0,0,0,.55);color:#fff;font-size:16px;cursor:pointer;z-index:3}@media(max-width:760px){.hxk{bottom:22px;left:14px;font-size:12px;padding:7px 14px}.hxk.right{left:auto;right:14px}.hxk.center{left:50%}}</style>
<script>document.addEventListener('DOMContentLoaded',function(){var box=document.querySelector('.hxs');if(!box)return;var tr=box.querySelector('.sl-t'),cur=null,yt=null,apiP=null,tm=null,vcap=null,sc=null;function api(){if(window.YT&&YT.Player)return Promise.resolve();if(apiP)return apiP;apiP=new Promise(function(ok){var p=window.onYouTubeIframeAPIReady;window.onYouTubeIframeAPIReady=function(){if(p)try{p()}catch(e){}ok()};var s=document.createElement('script');s.src='https://www.youtube.com/iframe_api';s.async=true;document.head.appendChild(s)});return apiP}
function mob(){return matchMedia('(max-width:760px)').matches}
function stop(){if(vcap){clearTimeout(vcap);vcap=null}if(tm){clearTimeout(tm);tm=null}box.removeAttribute('data-hold');if(cur){try{cur.pl&&cur.pl.destroy&&cur.pl.destroy()}catch(e){}var f=cur.el.querySelector('.vf');if(f)f.remove();cur.el.classList.remove('pl','ct');var m=cur.el.querySelector('.hxm');if(m)m.hidden=true;cur=null}}
function adv(){stop();if(window.__slMove)window.__slMove(tr,1);else{var n=tr.children.length,w=tr.clientWidth,i=Math.round(tr.scrollLeft/w);tr.scrollTo({left:(i+1>=n?0:(i+1)*w),behavior:'smooth'})}}
function start(el){var d=el.dataset;if(mob()&&d.mb!=='1')return;var holder=document.createElement('div');holder.className='vf';el.insertBefore(holder,el.querySelector('picture').nextSibling);cur={el:el,pl:null};var pb=el.querySelector('.hxp');if(pb)pb.hidden=true;if(d.ct==='1')el.classList.add('ct');box.setAttribute('data-hold','1');tm=setTimeout(function(){if(cur&&cur.el===el&&!el.classList.contains('pl')){box.removeAttribute('data-hold')}},9000);vcap=setTimeout(function(){if(cur&&cur.el===el)adv()},10000);
api().then(function(){if(!cur||cur.el!==el)return;var pv={autoplay:1,mute:d.mu==='1'?1:0,controls:d.ct==='1'?1:0,playsinline:1,rel:0,modestbranding:1,iv_load_policy:3,fs:0,disablekb:1,enablejsapi:1,origin:location.origin};if(d.lp==='1'){pv.loop=1;pv.playlist=d.vid}
var pl=new YT.Player(holder,{videoId:d.vid,host:'https://www.youtube-nocookie.com',playerVars:pv,events:{onReady:function(e){if(!cur||cur.el!==el){try{e.target.destroy()}catch(x){}return}cur.pl=e.target;if(d.mu==='1')e.target.mute();e.target.playVideo()},onStateChange:function(e){if(!cur||cur.el!==el)return;if(e.data===1||e.data===3){el.classList.add('pl');box.setAttribute('data-hold','1');var m=el.querySelector('.hxm');if(m&&e.target.isMuted&&e.target.isMuted())m.hidden=false}else if(e.data===0&&d.lp!=='1'){adv()}},onError:function(){stop()}}});if(cur&&cur.el===el)cur.pl=pl}).catch(function(){stop()})}
function active(){var w=tr.clientWidth||1,i=Math.round(tr.scrollLeft/w),el=tr.children[i];if(cur&&(!el||cur.el!==el))stop();if(el&&el.classList.contains('hxv')&&!cur){var d=el.dataset;if(mob()&&d.mb!=='1')return;if(d.ap==='1')start(el);else{var pb=el.querySelector('.hxp');if(pb)pb.hidden=false}}}
tr.addEventListener('scroll',function(){clearTimeout(sc);sc=setTimeout(active,140)},{passive:true});
box.addEventListener('click',function(e){var pb=e.target.closest&&e.target.closest('.hxp');if(pb){var el=pb.closest('.hxv');if(el&&!cur)start(el);return}var mb=e.target.closest&&e.target.closest('.hxm');if(mb&&cur&&cur.pl){try{cur.pl.unMute();cur.pl.setVolume(80)}catch(x){}mb.hidden=true}});
document.addEventListener('visibilitychange',function(){if(!cur||!cur.pl||!cur.pl.pauseVideo)return;try{document.hidden?cur.pl.pauseVideo():cur.pl.playVideo()}catch(e){}});
function first(){var f=tr.children[0];if(f&&f.classList.contains('hxv'))active()}
if(document.readyState==='complete')setTimeout(first,300);else addEventListener('load',function(){setTimeout(first,300)})});</script>
@php $__gp = ($s['home_globe'] ?? '1') === '1' ? \App\Http\Controllers\StoreController::globeProducts() : collect(); @endphp
@php $__ms = ($s['hero_member_slide'] ?? '1') !== '0'; $__dm = ($s['hero_district_slide'] ?? '1') !== '0'; $__om = ($s['hero_offers_slide'] ?? '1') !== '0'; @endphp
@if($__gp->count() && ($__ms || $__dm || $__om))
<div class="w hx"><div class="hx-m"><div class="sld hxs" data-auto="10000"><div class="sl-t">@foreach($slides as $sl)
@include('store.slide_item', ['sl' => $sl, 'first' => $loop->first])
@endforeach<div class="hxg">
@include('store.globe', ['deals' => $__gp])
</div>
@if($__dm)
@include('store.district_slide')
@endif
@if($__ms)
@include('store.member_slide', ['gl' => true])
@endif
@if($__om)
@include('store.offers_slide')
@endif
</div><div class="sl-d"></div><button type="button" class="sl-a pv" aria-label="Previous slide">‹</button><button type="button" class="sl-a nx" aria-label="Next slide">›</button></div></div></div>
@elseif($__gp->count())
<div class="w hx">@include('store.globe', ['deals' => $__gp])</div>
@else
<div class="w hx"><div class="hx-m">
@if($slides->count())
<h1 style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap">DutyFree BD – অরিজিনাল ইমপোর্টেড পারফিউম, স্কিন কেয়ার ও লাইফস্টাইল পণ্য</h1>
<div class="sld hxs" data-auto="10000"><div class="sl-t">@foreach($slides as $sl)@php $mi = $sl->mobile_image ?? null; $mdim = ''; if ($mi && str_starts_with($mi, '/uploads/') && is_file(public_path(ltrim($mi, '/')))) { $__d = @getimagesize(public_path(ltrim($mi, '/'))); if ($__d && $__d[0] > 0 && $__d[1] > 0) $mdim = 'width="'.$__d[0].'" height="'.$__d[1].'"'; } $hasTx = !empty($sl->title) || !empty($sl->subtitle) || !empty($sl->button_text); $isV = (($sl->media_type ?? 'image')==='video' && !empty($sl->video_id)); @endphp
@if($isV)
<div class="hxa hxv" data-vid="{{ $sl->video_id }}" data-ap="{{ (int)($sl->v_autoplay ?? 1) }}" data-mu="{{ (int)($sl->v_mute ?? 1) }}" data-lp="{{ (int)($sl->v_loop ?? 0) }}" data-ct="{{ (int)($sl->v_controls ?? 0) }}" data-mb="{{ (int)($sl->v_mobile ?? 0) }}"><picture>@if($mi)@if($__w=\App\Support\Img::webp($mi))<source media="(max-width:760px)" type="image/webp" srcset="{{ $__w }}">@endif<source media="(max-width:760px)" srcset="{{ $mi }}" {!! $mdim !!}>@endif @if($__w=\App\Support\Img::webp($sl->image))<source type="image/webp" srcset="{{ $__w }}">@endif<img src="{{ $sl->image ?: 'https://i.ytimg.com/vi/'.$sl->video_id.'/hqdefault.jpg' }}" alt="{{ $sl->title ?: 'DutyFree BD video' }}" width="1328" height="472" @if($loop->first) fetchpriority="high" @else loading="lazy" @endif></picture>
@if($hasTx)<span class="hxt"><b>{{ $sl->title }}</b>@if(!empty($sl->subtitle))<span>{{ $sl->subtitle }}</span>@endif</span>@endif
@if(!empty($sl->button_text))<a class="hxk {{ $sl->cta_pos ?? 'left' }}" href="{{ $sl->link ?: '#flash' }}">{{ $sl->button_text }}</a>@endif
<button type="button" class="hxp" aria-label="Play video" hidden>▶</button><button type="button" class="hxm" aria-label="Sound on" hidden>🔇</button></div>
@else
@php $__mp4 = (is_string($sl->image) && str_starts_with($sl->image, '/banners/') && is_file(public_path(preg_replace('/\.\w+$/', '.mp4', ltrim($sl->image, '/'))))) ? preg_replace('/\.\w+$/', '.mp4', $sl->image) : null; @endphp
@php $__mp4m = ($__mp4 && !empty($mi) && is_string($mi) && str_starts_with($mi, '/banners/') && is_file(public_path(preg_replace('/\.\w+$/', '.mp4', ltrim($mi, '/'))))) ? preg_replace('/\.\w+$/', '.mp4', $mi) : null; @endphp
<a class="hxa{{ $__mp4 ? ' hxn' : '' }}" href="{{ $sl->link ?: '#flash' }}"><picture>@if($mi)@if($__w=\App\Support\Img::webp($mi))<source media="(max-width:760px)" type="image/webp" srcset="{{ $__w }}">@endif<source media="(max-width:760px)" srcset="{{ $mi }}" {!! $mdim !!}>@endif @if($__w=\App\Support\Img::webp($sl->image))<source type="image/webp" srcset="{{ $__w }}">@endif<img src="{{ $sl->image }}" alt="{{ $sl->title ?: 'DutyFree BD offer' }}" width="1328" height="472" @if($loop->first) fetchpriority="high" @else loading="lazy" @endif></picture>@if($__mp4)<video class="hxnv" data-d="{{ $__mp4 }}" data-m="{{ $__mp4m ?: $__mp4 }}" autoplay muted loop playsinline preload="metadata" aria-hidden="true" tabindex="-1"></video>@endif
@if($hasTx)<span class="hxt">@if(!empty($sl->title))<b>{{ $sl->title }}</b>@endif @if(!empty($sl->subtitle))<span>{{ $sl->subtitle }}</span>@endif @if(!empty($sl->button_text))<em>{{ $sl->button_text }}</em>@endif</span>@endif</a>
@endif
@if($loop->first && $__dm)
@include('store.district_slide')
@endif
@if($loop->first && $__ms)
@include('store.member_slide')
@endif
@if($loop->first && $__om)
@include('store.offers_slide')
@endif
@endforeach</div>@if($slides->count()+($__ms?1:0)+($__dm?1:0)+($__om?1:0)>1)<div class="sl-d"></div><button type="button" class="sl-a pv" aria-label="Previous slide">‹</button><button type="button" class="sl-a nx" aria-label="Next slide">›</button>@endif</div>
@elseif($__ms || $__dm || $__om)
<div class="sld hxs" data-auto="10000"><div class="sl-t"><div class="hxa hxd"><span class="k" data-end="{{ $fFix ? $fEnd * 1000 : '' }}">{{ $s['hero_kicker'] }} <span data-cd="all">{{ $fmt($fEnd) }}</span></span><h1>{!! nl2br(e(str_replace(', ', ",\n", $s['hero_title']))) !!}</h1><span class="sb">{{ $hsub }}</span><a class="bt" href="#flash">{{ $s['hero_button'] }} →</a></div>
@if($__dm)
@include('store.district_slide')
@endif
@if($__ms)
@include('store.member_slide')
@endif
@if($__om)
@include('store.offers_slide')
@endif
</div><div class="sl-d"></div><button type="button" class="sl-a pv" aria-label="Previous slide">‹</button><button type="button" class="sl-a nx" aria-label="Next slide">›</button></div>
@else
<div class="hxd"><span class="k" data-end="{{ $fFix ? $fEnd * 1000 : '' }}">{{ $s['hero_kicker'] }} <span data-cd="all">{{ $fmt($fEnd) }}</span></span><h1>{!! nl2br(e(str_replace(', ', ",\n", $s['hero_title']))) !!}</h1><span class="sb">{{ $hsub }}</span><a class="bt" href="#flash">{{ $s['hero_button'] }} →</a></div>
@endif
</div>
</div>
@endif

@include('store.brand_slider')
@if($s['home_promos']==='1')<?php $__po = \App\Http\Controllers\StoreController::promoOffers((int)($maxOff ?? 0)); ?>
@if(count($__po) >= 3)
<div class="w pm" id="pmx" data-o='@json($__po)'>@foreach(array_slice($__po, 0, 3) as $__o)<a href="{{ $__o['u'] }}"><i><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $__o['ic'] !!}</svg></i><span><b>{{ $__o['h'] }}</b><span>{{ $__o['s'] }}</span></span></a>@endforeach</div>
<style>#pmx a{transition:opacity .35s ease}#pmx a.fd{opacity:0}</style>
<script>(function(){var w=document.getElementById('pmx');if(!w)return;var o=[];try{o=JSON.parse(w.getAttribute('data-o'))}catch(e){}var n=o.length;if(n<4||(window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches))return;var as=w.querySelectorAll('a'),t=1,hold=false;
w.addEventListener('mouseenter',function(){hold=true});w.addEventListener('mouseleave',function(){hold=false});
function put(a,x){a.setAttribute('href',x.u);a.querySelector('svg').innerHTML=x.ic;a.querySelector('b').textContent=x.h;a.querySelector('span span').textContent=x.s}
setInterval(function(){if(hold||document.hidden)return;as.forEach(function(a,i){setTimeout(function(){a.classList.add('fd');setTimeout(function(){put(a,o[(t*3+i)%n]);a.classList.remove('fd')},360)},i*140)});t++},4200)})();</script>
@else
<div class="w pm"><a href="/delivery-policy/"><i><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h11v10H3zM14 9h4l3 3v4h-7z"/><circle cx="7" cy="18" r="1.8"/><circle cx="17" cy="18" r="1.8"/></svg></i><span><b>Free Delivery</b><span>On selected orders</span></span></a><a href="/about-us/"><i><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l7 3v5c0 4.5-3 8-7 10-4-2-7-5.5-7-10V6z"/><path d="M8.5 12l2.5 2.5L15.5 10"/></svg></i><span><b>100% Original</b><span>Imported and authentic</span></span></a><a href="#flash"><i><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 5L5 19"/><circle cx="7" cy="7" r="2.2"/><circle cx="17" cy="17" r="2.2"/></svg></i><span><b>Special Offers</b><span>Up to {{ $maxOff }}% off</span></span></a></div>
@endif
@endif
<style>
/* Category sections on the home page: same look as the Flash Sale block (framed card, logo-colour animated bar, white pills, aligned cards). Layout/markup unchanged. */
body .cs{gap:0;border:1.25px solid var(--bd);border-radius:12px;background:#fff;position:relative}
body .cs .hh,body .cs .mt{position:relative;background:linear-gradient(90deg,#FF5A00 0%,#8A3008 9%,#0B1220 30%,#0B1220 70%,#8A3008 91%,#FF5A00 100%)}
body .cs .hh::after,body .cs .mt::after{content:"";position:absolute;top:0;bottom:0;left:0;width:30%;background:linear-gradient(90deg,rgba(255,90,0,0),rgba(255,122,48,.5) 50%,rgba(255,90,0,0));animation:csGo 3.4s ease-in-out infinite alternate;pointer-events:none;will-change:transform}
body .cs .hh>*,body .cs .mt>*{position:relative;z-index:1}
@keyframes csGo{from{transform:translateX(0)}to{transform:translateX(233%)}}
body .cs .hh{margin:0;padding:16px 20px;border-radius:18px;gap:10px 14px}
body .cs .hh .t{color:#fff;border:0;padding:0;font-size:18px;font-weight:800;letter-spacing:.02em;text-transform:uppercase;line-height:1.2}
body .cs .vd{position:relative;z-index:20}
body .cs .vd summary{list-style:none;cursor:pointer;display:inline-flex;align-items:center;gap:6px;background:#fff;color:var(--n);font-size:13px;font-weight:800;padding:8px 16px;border-radius:999px;white-space:nowrap;user-select:none}
body .cs .vd summary::-webkit-details-marker{display:none}
body .cs .vd summary::after{content:"\25BE";font-size:12px;color:#C2410C}
body .cs .vd[open] summary::after{content:"\25B4"}
body .cs .vm{position:absolute;right:0;top:calc(100% + 8px);min-width:220px;max-width:min(320px,86vw);max-height:340px;overflow:auto;background:#fff;border:1px solid var(--bd);border-radius:12px;box-shadow:0 10px 28px rgba(11,18,32,.18);padding:6px;display:flex;flex-direction:column}
body .cs .vm a{display:block;padding:9px 12px;border-radius:8px;color:var(--n);font-size:13px;font-weight:600;text-decoration:none}
body .cs .vm a:hover{background:#FFEEE6;color:#C2410C}
body .cs .vm a.vall{font-weight:800;color:#C2410C;border-bottom:1px solid #F3E3DA;border-radius:8px 8px 0 0;margin-bottom:4px}
body .cs .gr:not([data-cr]){padding:12px}body .cs .crw{padding:12px}
body .cs .vl{display:inline-flex;align-items:center;gap:6px;background:#fff;color:var(--n);font-size:13px;font-weight:800;padding:8px 16px;border-radius:999px;white-space:nowrap;text-decoration:none}
body .cs .vl::after{content:"\203A";color:#C2410C}
body .cs .st-t{display:none}
body .cs .pc{border-radius:14px}body .cs .pc .ph{background:#F8F8F8}
body .cs .pc,body .cs .pc:hover{transform:none}
body .cs .pc{transition:box-shadow .15s}
body .cs .pc .ph,body .cs .pc .nm,body .cs .pc .pr,body .cs .pc .ck,body .cs .pc .on{flex:none}
body .cs .pc .pr{align-content:flex-start;min-height:54px}
body .cs .pc .on{margin-top:auto}
@media(max-width:760px){
  body .cs{border-radius:10px}
  body .cs .mt{margin:0;padding:8px 10px;border-radius:14px;gap:8px}
  body .cs .mt span{color:#fff;padding-left:0;font-size:13px;font-weight:800;text-transform:uppercase}
  body .cs .mt span::before{display:none}
  body .cs .vd summary{font-size:11px;padding:5px 11px}
  body .cs .vm{min-width:200px}
  body .cs .gr:not([data-cr]){padding:6px;gap:5px}body .cs .crw{padding:6px}
  body .cs .vl{font-size:11px;padding:5px 11px}
  body .cs .pc .pr{min-height:26px}
  body .cs .mv{margin:0;padding:0 6px 8px}
}
@media(prefers-reduced-motion:reduce){body .cs .hh::after,body .cs .mt::after{animation:none}}
</style>
@if($s['home_flash']==='1' && $flash->count() && $fOn)
<style>
/* Flash Sale bar: logo colours (navy #0B1220 + orange #FF5A00). Both ends are lit orange and a soft glow travels end to end and back, so no part of the bar is left dead. Decoration only; content and timer are untouched. */
.fs .hd2{position:relative;overflow:hidden;background:linear-gradient(90deg,#FF5A00 0%,#8A3008 9%,#0B1220 30%,#0B1220 70%,#8A3008 91%,#FF5A00 100%)}
.fs .hd2::after{content:"";position:absolute;top:0;bottom:0;left:0;width:30%;background:linear-gradient(90deg,rgba(255,90,0,0),rgba(255,122,48,.5) 50%,rgba(255,90,0,0));animation:fsGo 3.4s ease-in-out infinite alternate;pointer-events:none;will-change:transform}
.fs .hd2>*{position:relative;z-index:1}
@keyframes fsGo{from{transform:translateX(0)}to{transform:translateX(233%)}}
@media(prefers-reduced-motion:reduce){.fs .hd2::after{animation:none}}
/* Flash Sale cards: every card has the same internal grid, so rows line up and nothing moves up or down (price can wrap to 2 rows, the name is 2 rows, the sold line is 1 row). */
.fs .pc,.fs .pc:hover{transform:none}
.fs .pc{transition:box-shadow .15s}
.fs .pc .ph,.fs .pc .nm,.fs .pc .pr,.fs .pc .on{flex:none}
.fs .pc .pr{align-content:flex-start;min-height:54px}
.fs .pc .sold{min-height:14px;line-height:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.fs .pc .on{margin-top:auto}
@media(max-width:760px){.fs .pc .pr{min-height:26px}}
</style>
<div class="w sec-fs" id="flash" data-end="{{ $fFix ? $fEnd * 1000 : '' }}" style="padding-top:22px"><div class="fs">
<div class="hd2"><b class="t">⚡ FLASH SALE</b><span class="e">ENDS IN</span><div class="tm"><span class="b" data-cd="h">{{ substr($fmt($fEnd),0,2) }}</span><span>:</span><span class="b" data-cd="m">{{ substr($fmt($fEnd),3,2) }}</span><span>:</span><span class="b" data-cd="s">{{ substr($fmt($fEnd),6,2) }}</span></div>
<div class="tb"><button type="button" class="on" data-ft="f">Flash Sale</button>@if($plus->count())<button type="button" data-ft="p">Plus Sale</button>@endif<a href="/search?deals=1">View All ›</a></div></div>
<div class="mtb"><button type="button" class="on" data-ft="f">Flash Sale</button>@if($plus->count())<button type="button" data-ft="p">Plus Sale</button>@endif</div>
@foreach(['f'=>$flash, 'p'=>$plus] as $fk => $list)
@if($list->count())
<div class="gr" data-cr data-fl="{{ $fk }}" @if($fk==='p') style="display:none" @endif>@foreach($list as $q)
@php $off = \App\Http\Controllers\StoreController::off($q); $u = \App\Http\Controllers\StoreController::url($q); $w = $q->sold > 0 ? max(8, min(100, round($q->sold / max(1, $q->sold + max(0, (int)$q->stock)) * 100))) : max(8, min(90, 100 - (int)$q->stock)); @endphp
<div class="pc"><a class="ph" href="{{ $u }}" style="background:#fff"><img src="{{ $q->image ?: '/product-placeholder' }}" @php $__ss = \App\Support\Img::srcset($q->image); @endphp @if($__ss) srcset="{{ $__ss }}" sizes="150px" @endif alt="{{ $q->name }}" width="150" height="150" loading="lazy" decoding="async" draggable="false"></a>
<a class="nm" href="{{ $u }}">{{ $q->name }}</a><div class="pr"><b>{{ number_format($q->price) }} TK</b> @if($q->compare_price > $q->price)<s>{{ number_format($q->compare_price) }} TK</s>@endif @if((int)$off > 0) <span class="dp">-{{ $off }}%</span>@endif</div>
<div style="padding:0 2px"><div class="bar"><i style="width:{{ $w }}%"></i></div><div class="sold">{{ $q->sold > 0 ? $q->sold.' sold this week' : ((int)$q->stock > 0 ? 'In stock' : 'Limited stock') }}</div></div>
<a class="on" href="/checkout?add={{ $q->id }}&amp;p={{ (float)$q->price }}" rel="nofollow"><svg width="16" height="16" aria-hidden="true"><use href="#dfi-cart"/></svg><i>ORDER NOW</i></a></div>
@endforeach</div>
@endif
@endforeach
</div></div>
@endif

@if($s['home_top']==='1' && $top->count())
<div class="hst-p sec-top" id="top" style="min-height:400px"></div><template class="hst">
@php $__d = min(8, max(2, (int)$s['top_vis_d'])); $__m = min(4, max(1, (int)$s['top_vis_m'])); $__sp = min(15, max(1, (int)$s['top_speed'])) * 1000; @endphp
<style>@media(min-width:1201px){#top [data-cr]{--pv:{{ $__d }}}}@media(min-width:981px) and (max-width:1200px){#top [data-cr]{--pv:{{ min($__d, 5) }}}}@media(min-width:761px) and (max-width:980px){#top [data-cr]{--pv:{{ min($__d, 4) }}}}@media(max-width:760px){#top [data-cr]{--pv:{{ $__m }}}}#top .pc .tsb{margin:0 10px;align-self:flex-start;background:#FFF1E8;color:#C2410C;font-size:10.5px;font-weight:700;border-radius:20px;padding:2px 9px}#top .pc .tsn{margin:0 10px;font-size:11px;color:#374151}@media(max-width:760px){#top .pc .tsb{font-size:8.5px;padding:1px 6px;margin:0 6px}#top .pc .tsn{font-size:9px;margin:0 6px}}</style>
<div class="w sec-st sec-top" id="top" style="padding-top:34px"><div class="st cs"><div class="hh"><span class="t">{{ $topLabel }}</span><span class="sp"></span><a class="vl" href="/search?sort=popular">View All</a></div><div class="mt"><span>{{ $topLabel }}</span><a class="vl" href="/search?sort=popular">View All</a></div>
<div class="gr" data-cr data-sp="{{ $__sp }}" data-auto="{{ $s['top_auto'] === '1' ? '1' : '0' }}">@foreach($top as $q)@include('store.card', ['fd'=>true, 'tb'=>$s['top_badge']==='1' && isset($topSold[$q->id]), 'ts'=>$s['top_show_sold']==='1' ? (int)($topSold[$q->id] ?? 0) : 0])@endforeach</div></div></div>
</template>
@endif

@php $__tb = $s['home_todays_deal']==='1' ? \App\Http\Controllers\StoreController::todayBest($s) : collect(); @endphp
@if($__tb->count() && $dOn)
<div class="w sec-st" id="deal" style="padding-top:26px">@include('store.deal_bp2', ['deals' => $__tb])</div>
@endif

@if($s['home_new']==='1' && $new->count())
<div class="hst-p" id="new" style="min-height:400px"></div><template class="hst">
<div class="w sec-st" id="new" style="padding-top:34px"><div class="st cs"><div class="hh"><span class="t">New Arrival Products</span><span class="sp"></span><a class="vl" href="/search?sort=new">View All</a></div><div class="mt"><span>New Arrival</span><a class="vl" href="/search?sort=new">View All</a></div>
<div class="gr" data-cr>@foreach($new as $q)@include('store.card', ['fd'=>false])@endforeach</div></div></div>
</template>
@endif

@if(($s['home_live_orders'] ?? '') === '1')
<div class="hst-p" style="min-height:420px"></div><template class="hst">
<div class="w sec-lv" style="padding-top:24px"><div class="lvbox"><div class="lvh"><span class="chip"><span class="dot"></span>LIVE ORDERS</span><h2>Recently <em>ordered</em></h2><span class="su">Bought by shoppers across Bangladesh, updated every minute</span><span class="lvc"><b>{{ $live->count() }}</b> latest orders</span></div>
<div class="lvbody">@include('store.live_cards', ['live'=>$live])</div></div></div>
</template>
@endif

@if($s['home_cats_v2']==='1')
<div class="w sec-ct" style="padding-top:24px"><div class="cts"><h2>Categories</h2><div class="ctl">@foreach($cats as $c)
<a class="ct" href="/{{ $c->slug }}/"><span class="ci">@if(!empty($c->image))<img src="{{ $c->image }}" alt="{{ $c->name }}" width="52" height="52" loading="lazy">@else{{ $ini($c->name) }}@endif</span><span class="cn"><span class="d">{{ $c->name }}</span><span class="m">{{ explode(' ', $c->name)[0] }}</span></span></a>
@endforeach</div></div></div>
@endif

@if($s['home_stores_v2']==='1' && count($stores))
<div class="w sec-st" id="stores" style="padding-top:34px"><div class="st"><h2>Featured Stores</h2><div class="fst">
<a href="/search?deals=1"><div class="cv"></div><div class="bd"><span class="lg">DF</span><b>DutyFree BD</b><span class="vf">✔ Verified</span><span class="tp">{{ number_format($count) }} products</span><span class="vs">View Store</span></div></a>
@foreach($stores as $st)<a href="/search?q={{ rawurlencode(parse_url('http://'.$st->domain, PHP_URL_HOST) ?: $st->name) }}"><div class="cv"></div><div class="bd"><span class="lg">{{ $ini($st->name) }}</span><b>{{ $st->name }}</b><span class="vf">✔ Verified</span><span class="tp">{{ number_format((int)$st->scan_published) }} products</span><span class="vs">View Store</span></div></a>@endforeach
</div></div></div>
@endif

@if($offerSlides->count())
<div class="w osl" style="padding-top:34px"><div class="sld" data-auto="5000"><div class="sl-t">@foreach($offerSlides as $os)
@php $__om = (is_string($os->image) && str_starts_with($os->image, '/banners/') && is_file(public_path(preg_replace('/\.\w+$/', '.mp4', ltrim($os->image, '/'))))) ? preg_replace('/\.\w+$/', '.mp4', $os->image) : null; @endphp
<a href="{{ $os->link ?: '#flash' }}"@if($__om) class="osv"@endif><img src="{{ $os->image }}" alt="DutyFree BD offer" loading="lazy" width="1266" height="650">@if($__om)<video class="osvv" data-src="{{ $__om }}" muted loop playsinline preload="none" aria-hidden="true" tabindex="-1"></video>@endif</a>
@endforeach</div></div></div>
<script>(function(){var vs=document.querySelectorAll('.osvv');if(!vs.length||(window.matchMedia&&matchMedia('(prefers-reduced-motion:reduce)').matches))return;function go(v){if(!v.getAttribute('src'))v.src=v.getAttribute('data-src');var p=v.play();if(p&&p.then)p.then(function(){v.classList.add('on')}).catch(function(){})}if('IntersectionObserver' in window){var io=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting)go(e.target);else e.target.pause()})},{rootMargin:'120px'});vs.forEach(function(v){io.observe(v)})}else vs.forEach(go)})();</script>
@endif

@if($s['home_cat_sections']==='1')
@foreach($sec as [$c, $items])
@if($items->count())
@include('store.home_sec', ['lazy'=>true])
@endif
@endforeach
@endif
@if($s['home_trust']==='1')
<div class="w" style="padding-top:34px"><div class="tr"><div><i><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h11v10H3zM14 9h4l3 3v4h-7z"/><circle cx="7" cy="18" r="1.8"/><circle cx="17" cy="18" r="1.8"/></svg></i>Free Delivery</div><div><i><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l7 3v5c0 4.5-3 8-7 10-4-2-7-5.5-7-10V6z"/><path d="M8.5 12l2.5 2.5L15.5 10"/></svg></i>100% Original</div><div><i><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2.5" y="6" width="19" height="12" rx="2"/><circle cx="12" cy="12" r="2.6"/><path d="M6 9v.01M18 15v.01"/></svg></i>Cash on Delivery</div><div><i><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 14L4 9l5-5"/><path d="M4 9h10a6 6 0 010 12h-3"/></svg></i>Easy Return</div><div><i><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 018 0v3"/><path d="M12 15v2"/></svg></i>Secure Payment</div><div><i><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 005 5L15 13l5 2v4a2 2 0 01-2 2A16 16 0 013 6a2 2 0 012-2z"/></svg></i>Customer Support</div></div></div>
@endif
@if($s['home_seller']==='1')
@endif
@if($s['home_app']==='1' && ($s['app_android'] || $s['app_ios']))
<div class="w" style="padding-top:24px"><div class="ap"><div><b>Shop faster with our app</b></div>@if($s['app_android'])<a href="{{ $s['app_android'] }}" rel="noopener">Android</a>@endif @if($s['app_ios'])<a href="{{ $s['app_ios'] }}" rel="noopener">iOS</a>@endif</div></div>
@endif
@if(($s['home_faq_v2'] ?? '0')==='1')
@php $faq = collect(preg_split('/\R/', (string)$s['faq_items']))->map(fn($l) => array_map('trim', explode('|', $l, 2)))->filter(fn($a) => count($a) === 2 && $a[0] !== ''); @endphp
@if($faq->count())<div class="w" style="padding-top:34px"><div class="st"><h2>Frequently Asked Questions</h2><div class="fq">@foreach($faq as [$fqq, $fqa])<details><summary>{{ $fqq }}</summary><p>{{ $fqa }}</p></details>@endforeach</div></div></div>
<script type="application/ld+json">{!! json_encode(['@'.'context'=>'https://schema.org','@type'=>'FAQPage','mainEntity'=>$faq->map(fn($a) => ['@type'=>'Question','name'=>$a[0],'acceptedAnswer'=>['@type'=>'Answer','text'=>$a[1]]])->values()->all()], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!}</script>@endif
@endif
@endsection
@section('js')
<script>document.addEventListener('click',function(e){document.querySelectorAll('details.vd[open]').forEach(function(d){if(!d.contains(e.target))d.open=false})});</script>
<script>(function(){if(!matchMedia('(max-width:760px)').matches)return;var t=document.querySelector('.hxs .sl-t');if(!t)return;var ks=[].slice.call(t.children),tall=ks.filter(function(k){return k.classList.contains('hxg')||k.classList.contains('dmz')||k.classList.contains('hxmc')});if(!tall.length||tall.length===ks.length)return;var s=document.createElement('div');s.hidden=true;s.id='dfOff';document.body.appendChild(s);tall.forEach(function(k){s.appendChild(k)})})();</script>
<script>document.querySelectorAll('[data-ft]').forEach(function(b){b.onclick=function(){var k=b.getAttribute('data-ft');document.querySelectorAll('[data-ft]').forEach(function(x){x.classList.toggle('on',x.getAttribute('data-ft')===k)});document.querySelectorAll('[data-fl]').forEach(function(g){g.style.display=g.getAttribute('data-fl')===k?'':'none'})}});</script>
<script>(function(){var M=60000;function ago(ts){var m=Math.max(0,Math.floor((Date.now()-Date.parse(ts))/M));if(m<1)return'Just now';if(m<60)return m+' minute'+(m>1?'s':'')+' ago';var h=Math.floor(m/60);if(h<24)return h+' hour'+(h>1?'s':'')+' ago';var d=Math.floor(h/24);return d+' day'+(d>1?'s':'')+' ago'}
function tick(){document.querySelectorAll('.sec-lv .lo[data-ts]').forEach(function(o){var i=o.querySelector('.ago');if(i)i.textContent=ago(o.getAttribute('data-ts'))})}
function cur(){var g=document.querySelector('.sec-lv .lg2[data-sig]');return g?g.getAttribute('data-sig'):null}
function refresh(){var b=document.querySelector('.sec-lv .lvbody');if(!b||document.hidden)return;fetch('/live-orders',{headers:{Accept:'application/json'}}).then(function(r){return r.json()}).then(function(j){if(!j||!j.html)return;var c=cur();if(c===j.sig||(c===null&&!j.n))return;b.classList.add('sw');setTimeout(function(){var t=document.createElement('div');t.innerHTML=j.html;var old=b.querySelector('.crw')||b.firstElementChild;var nu=t.firstElementChild;if(!nu)return;if(old){old.replaceWith(nu)}else{b.appendChild(nu)}if(nu.hasAttribute('data-cr')&&window.__crInit)window.__crInit(nu);var n=document.querySelector('.sec-lv .lvc b');if(n)n.textContent=j.n;b.classList.remove('sw');drag()},300)}).catch(function(){})}
function drag(){document.querySelectorAll('.sec-lv .lg2[data-cr]').forEach(function(tr){if(tr.__dg)return;tr.__dg=1;var d=0,x0=0,s0=0,mv=0;tr.addEventListener('mousedown',function(e){if(e.button!==0)return;d=1;mv=0;x0=e.clientX;s0=tr.scrollLeft;tr.style.scrollSnapType='none';tr.style.cursor='grabbing'});addEventListener('mousemove',function(e){if(!d)return;var dx=e.clientX-x0;if(Math.abs(dx)>4)mv=1;tr.scrollLeft=s0-dx});addEventListener('mouseup',function(){if(!d)return;d=0;tr.style.scrollSnapType='';tr.style.cursor=''});tr.addEventListener('click',function(e){if(mv){e.preventDefault();e.stopPropagation();mv=0}},true)})}
setInterval(tick,30000);setInterval(refresh,60000);var n=0,w=setInterval(function(){drag();if(++n>20||document.querySelector('.sec-lv .lg2[data-cr]'))clearInterval(w)},500)})();</script>
<script>(function(){var rm=matchMedia('(prefers-reduced-motion: reduce)').matches;function mk(c,t){var b=document.createElement('button');b.type='button';b.className='cra '+c;b.setAttribute('aria-label',c==='l'?'Previous':'Next');b.textContent=t;return b}
function init(tr){var kids=[].slice.call(tr.children);if(kids.length<2)return;var w=document.createElement('div');w.className='crw';var fl=tr.getAttribute('data-fl');tr.parentNode.insertBefore(w,tr);w.appendChild(tr);if(fl!==null){w.setAttribute('data-fl',fl);tr.removeAttribute('data-fl');w.style.display=tr.style.display;tr.style.display=''}
var L=mk('l','\u2039'),R=mk('r','\u203a');w.appendChild(L);w.appendChild(R);var gap=function(){return parseFloat(getComputedStyle(tr).columnGap)||0},per=function(){return Math.round(parseFloat(getComputedStyle(tr).getPropertyValue('--pv')))||1},loop=kids.length>=per();
if(loop)kids.forEach(function(k){var c=k.cloneNode(true);c.setAttribute('aria-hidden','true');c.querySelectorAll('a,button,input,select,textarea').forEach(function(x){x.setAttribute('tabindex','-1')});tr.appendChild(c)});
function sw(){return tr.children[0].getBoundingClientRect().width+gap()}function pd(){return(tr.scrollWidth+gap())/2}
function jump(d){tr.style.scrollSnapType='none';tr.scrollLeft+=d;requestAnimationFrame(function(){tr.style.scrollSnapType=''})}
function next(){if(tr.clientWidth)tr.scrollBy({left:sw(),behavior:'smooth'})}function prev(){if(!tr.clientWidth)return;if(loop&&tr.scrollLeft<sw())jump(pd());tr.scrollBy({left:-sw(),behavior:'smooth'})}
function upd(){var o=tr.scrollWidth>tr.clientWidth+2;L.style.display=R.style.display=o?'':'none'}
tr.addEventListener('scroll',function(){if(loop&&tr.scrollLeft>=pd()-1)jump(-pd())},{passive:true});L.onclick=prev;R.onclick=next;
var paused=false,vis=true;w.addEventListener('mouseenter',function(){paused=true;upd()});w.addEventListener('mouseleave',function(){paused=false});w.addEventListener('touchstart',function(){paused=true},{passive:true});w.addEventListener('touchend',function(){setTimeout(function(){paused=false},4000)},{passive:true});w.addEventListener('focusin',function(){paused=true});w.addEventListener('focusout',function(){paused=false});
if('IntersectionObserver' in window)new IntersectionObserver(function(e){vis=e[0].isIntersecting}).observe(w);
if(!rm&&tr.getAttribute('data-auto')!=='0')setInterval(function(){if(!paused&&vis&&!document.hidden&&tr.clientWidth&&tr.scrollWidth>tr.clientWidth+2)next()},parseInt(tr.getAttribute('data-sp'),10)||3200);
addEventListener('resize',upd);setTimeout(upd,300);upd()}
window.__crInit=init;document.querySelectorAll('[data-cr]').forEach(init)})();</script>
<script>(function(){document.querySelectorAll('.sld[data-auto]').forEach(function(w){var t=w.querySelector('.sl-t'),n=t.children.length;if(n<2||t.scrollWidth<=t.clientWidth+4&&n<3&&!w.classList.contains('dk')&&!w.classList.contains('mb')&&false)return;var d=w.querySelector('.sl-d');if(d){for(var k=0;k<n;k++)d.appendChild(document.createElement('i'));}var i=0,h=false;function dots(){if(!d)return;var a=Math.round(t.scrollLeft/t.children[0].offsetWidth),cl=t.querySelector('[data-slc]');if(cl){if(cl===t.firstElementChild)a=a-1;else if(a>=n)a=0}if(a<0)a=n-1;[].forEach.call(d.children,function(e,x){e.className=x==a?'on':''})}t.addEventListener('scroll',dots);dots();w.addEventListener('mouseenter',function(){h=true});w.addEventListener('mouseleave',function(){h=false});t.addEventListener('touchstart',function(){h=true},{passive:true});t.addEventListener('touchend',function(){setTimeout(function(){h=false},4000)},{passive:true});setInterval(function(){if(h||w.hasAttribute('data-hold')||!t.offsetWidth||matchMedia('(prefers-reduced-motion: reduce)').matches)return;if(window.__slMove)window.__slMove(t,1);else{var c=t.children[0].offsetWidth+(parseFloat(getComputedStyle(t).columnGap)||0),a=Math.round(t.scrollLeft/c);t.scrollTo({left:(t.scrollLeft+t.clientWidth>=t.scrollWidth-4?0:(a+1)*c),behavior:'smooth'})}},+w.getAttribute('data-auto'))})})();</script>
@verbatim<script>(function(){var mq=window.matchMedia?matchMedia('(max-width:760px)'):null;function fix(){var m=mq&&mq.matches;document.querySelectorAll('.hxnv[data-d]').forEach(function(v){var u=m?v.dataset.m:v.dataset.d;if(v.getAttribute('src')!==u){v.setAttribute('src',u);v.load();var pr=v.play();if(pr&&pr.catch)pr.catch(function(){})}})}fix();document.addEventListener('DOMContentLoaded',fix);if(mq){(mq.addEventListener?mq.addEventListener('change',fix):mq.addListener(fix))}if('MutationObserver' in window){var t;new MutationObserver(function(){clearTimeout(t);t=setTimeout(fix,60)}).observe(document.documentElement,{childList:true,subtree:true})}})();</script>@endverbatim
@verbatim<script>(function(){var ps=[].slice.call(document.querySelectorAll('.hst-p'));function show(p){var t=p.nextElementSibling;if(!t||t.tagName!=='TEMPLATE'){p.remove();return}var ks=[].slice.call(t.content.children);p.replaceWith(t.content);t.remove();ks.forEach(function(k){if(k.nodeType!==1)return;var l=k.matches&&k.matches('[data-cr]')?[k]:[].slice.call(k.querySelectorAll('[data-cr]'));l.forEach(function(x){if(window.__crInit)window.__crInit(x)})})}
if(ps.length){if('IntersectionObserver' in window){var io2=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting){io2.unobserve(e.target);show(e.target)}})},{rootMargin:'300px 0px'});ps.forEach(function(p){io2.observe(p)})}else ps.forEach(show)}})();</script>@endverbatim
@verbatim<script>(function(){var els=[].slice.call(document.querySelectorAll('.hsl[data-hs]'));if(!els.length)return;function ld(el){if(el.__l)return;el.__l=1;fetch('/home-sec/'+encodeURIComponent(el.getAttribute('data-hs')),{credentials:'same-origin'}).then(function(r){if(!r.ok)throw 0;return r.text()}).then(function(h){var t=document.createElement('template');t.innerHTML=h.trim();var n=t.content.firstElementChild;if(n)el.replaceWith(n)}).catch(function(){el.__l=0})}
if('IntersectionObserver' in window){var io=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting){io.unobserve(e.target);ld(e.target)}})},{rootMargin:'300px 0px'});els.forEach(function(e){io.observe(e)})}else els.forEach(ld)})();</script>@endverbatim
@endsection
