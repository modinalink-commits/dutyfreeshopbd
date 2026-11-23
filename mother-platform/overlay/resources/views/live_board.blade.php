<!doctype html>
<html lang="bn"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<title>Live Dashboard · DutyFreeBD</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=JetBrains+Mono:wght@500&display=swap">
<style>
/* Live Dashboard (23 Nov 2026): wall screen. Dark on purpose (owner asked for the animated control-room look); read-only. */
:root{--bg:#0a0e18;--panel:#121827;--tile:#0e1422;--line:#232c42;--fg:#e8edf6;--mute:#8591a8;--ok:#2fd27c;--slow:#f2b030;--dead:#ff4d4d;--na:#566274;--accent:#ff7a1a;--f:'Hind Siliguri',system-ui,sans-serif;--m:'JetBrains Mono',ui-monospace,monospace;color-scheme:dark}
*{box-sizing:border-box}
body{background:var(--bg);color:var(--fg);font-family:var(--f);font-size:15px;margin:0;padding:12px 16px}
a{color:inherit;text-decoration:none}
.top{display:flex;flex-wrap:wrap;align-items:center;gap:10px 18px;padding-bottom:10px;border-bottom:1px solid var(--line)}
.brand{display:flex;align-items:center;gap:12px}
.logo{background:#fff;border-radius:8px;padding:5px 10px;display:flex;align-items:center}.logo img{height:30px;width:auto;display:block}
.bt{display:grid;line-height:1.2}.bt strong{font-size:19px;font-weight:700}.bt small{font-size:12px;color:var(--mute)}
.sec{margin:14px 0 6px;font-size:12px;font-weight:600;letter-spacing:.8px;color:var(--mute);display:flex;align-items:center;gap:10px}.sec::after{content:"";flex:1;height:1px;background:var(--line)}
.tag{font-size:12px;color:var(--mute);border:1px solid var(--line);padding:2px 8px;border-radius:4px}
.tag.bad{color:#ff9d9d;border-color:var(--dead)}
.sum{display:flex;gap:8px;flex-wrap:wrap;margin-left:auto}
.pill{display:flex;align-items:center;gap:8px;padding:4px 11px;border:1px solid var(--line);border-radius:6px;background:var(--panel);font-weight:600}
.pill i{width:9px;height:9px;border-radius:50%}.pill.ok i{background:var(--ok)}.pill.slow i{background:var(--slow)}.pill.dead i{background:var(--dead)}.pill.na i{background:var(--na)}
.pill.dead.on{border-color:var(--dead);background:#2a1017}
.clock{font-family:var(--m);font-size:18px;color:var(--fg)}
.tools{display:flex;gap:6px;flex-wrap:wrap}
button,.btn{font:inherit;font-size:13px;background:transparent;color:var(--fg);border:1px solid var(--line);border-radius:6px;padding:4px 10px;cursor:pointer}
button[aria-pressed="true"]{border-color:var(--accent);color:var(--accent)}
button:focus-visible,a:focus-visible{outline:2px solid var(--accent);outline-offset:2px}
.pipe{display:flex;align-items:stretch;flex-wrap:wrap;row-gap:8px}
.stg{flex:1 1 0;background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:7px 10px;min-width:0}
.stg{padding:7px 8px}.wire{flex-basis:14px}.stg b{display:block;font-family:var(--m);font-size:20px;font-variant-numeric:tabular-nums}
.stg span{font-size:12.5px;color:var(--mute);display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.stg.new{border-color:var(--accent)}.stg.new b{color:var(--accent)}.stg.ai{border-color:#3a4c8a}.stg.ai span{color:#b9c4ff}.stg.done{border-color:#1f6e45}.stg.done b{color:var(--ok)}.stg.warn b{color:var(--slow)}.stg.bad b{color:#ff8b8b}
.wire{flex:0 0 22px;position:relative;align-self:center;height:2px;background:var(--line)}
.wire::after{content:"";position:absolute;top:-2px;left:0;width:6px;height:6px;border-radius:50%;background:var(--accent);box-shadow:0 0 8px var(--accent);animation:run 1.6s linear infinite}
.wire.w2::after{animation-delay:.5s}.wire.w3::after{animation-delay:1s}
@keyframes run{0%{left:-3px;opacity:0}15%{opacity:1}85%{opacity:1}100%{left:11px;opacity:0}}
.gap{flex:0 0 14px}
.kp{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px}
@media(min-width:1500px){.kp{grid-template-columns:repeat(var(--kn,8),minmax(0,1fr))}}
@media(max-width:700px){.kp{grid-template-columns:repeat(2,minmax(0,1fr))}.stg{flex:1 1 30%}.wire,.gap{display:none}}
.k{background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:8px 10px;min-width:0}
.k .kl{font-size:12.5px;color:var(--mute);display:flex;align-items:center;gap:6px}
.k .kl i{width:7px;height:7px;border-radius:50%;background:var(--ok);flex:none}
.k.slow{border-color:#6b4f12}.k.slow .kl i{background:var(--slow)}.k.dead{border-color:var(--dead)}.k.dead .kl i{background:var(--dead)}.k.na .kl i{background:var(--na)}
.k .kv{font-family:var(--m);font-size:20px;font-variant-numeric:tabular-nums;margin-top:2px}
.k .kv small{font-family:var(--f);font-size:12px;color:var(--mute);margin-left:5px}
.k .spw{position:relative;height:22px;margin-top:3px;overflow:hidden}.k .sp{display:block;width:100%;height:100%}.k .sd{position:absolute;right:0;width:6px;height:6px;border-radius:50%;margin-top:-3px}
.k .ks{font-size:12px;color:var(--mute);margin-top:1px;overflow-wrap:anywhere}
.main{display:grid;grid-template-columns:minmax(0,1fr) 310px;gap:16px;align-items:start}
.map{position:relative;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:40px 50px;grid-template-areas:"a b c" "d h e" "f g i"}
#cv{position:absolute;inset:0;width:100%;height:100%;pointer-events:none;z-index:0}
.g{position:relative;z-index:1;background:var(--panel);border:1px solid var(--line);border-top:2px solid hsl(var(--h) 80% 60%);border-radius:8px;padding:9px}
.g h2{margin:0 0 7px;font-size:13px;font-weight:600;display:flex;justify-content:space-between;gap:8px;color:hsl(var(--h) 85% 74%)}
.g h2 span:last-child{font-size:12px;white-space:nowrap}
.tiles{display:grid;grid-template-columns:repeat(auto-fill,minmax(86px,1fr));gap:6px}
.tile{position:relative;display:grid;justify-items:center;align-content:start;gap:1px;padding:10px 3px 6px;height:88px;overflow:hidden;border:1px solid var(--line);border-radius:8px;background:var(--tile);text-align:center;transition:opacity .2s}
.tile:hover{border-color:#3a4766}
.em{width:32px;height:32px;border-radius:10px;display:grid;place-items:center;font-size:17px;line-height:1;position:relative;
  background:linear-gradient(145deg,hsl(var(--h) 65% 24%),hsl(var(--h) 70% 11%));border:1px solid hsl(var(--h) 75% 46%);box-shadow:0 0 12px hsla(var(--h),90%,60%,.28),inset 0 0 8px hsla(var(--h),90%,70%,.12)}
.em svg{width:19px;height:19px;fill:none;stroke:hsl(var(--h) 100% 82%);stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
.em::after{content:"";position:absolute;inset:-4px;border-radius:13px;border:1px solid hsla(var(--h),90%,65%,.22)}
.tile.beat .em{animation:chip .9s ease-out}
@keyframes chip{0%{box-shadow:0 0 0 0 hsla(var(--h),95%,65%,.9)}100%{box-shadow:0 0 18px 6px hsla(var(--h),95%,65%,0)}}
.tile.slow .em{--h:40}.tile.dead .em{--h:0}.tile.na .em{background:#161c2a;border-color:#39435a;box-shadow:none}.tile.na .em svg{stroke:#7a869c}
body.fit .em{width:30px;height:30px}.tn{font-size:11.5px;font-weight:600;line-height:1.2;max-height:2.4em;overflow:hidden}.ta{font-size:10.5px;color:var(--mute);font-family:var(--m);white-space:nowrap}
.cnt{position:absolute;top:4px;left:5px;font-family:var(--m);font-size:10.5px;color:var(--fg);background:#1c2740;border-radius:4px;padding:0 4px;line-height:15px}.cnt:empty{display:none}
.dot{position:absolute;top:5px;right:5px;width:7px;height:7px;border-radius:50%;background:var(--ok)}
.tile.beat .dot{animation:ping .9s ease-out}
.tile.slow{border-color:#6b4f12}.tile.slow .dot{background:var(--slow)}.tile.slow .ta{color:var(--slow)}
.tile.dead{border-color:var(--dead);background:#2a1017;animation:glow 1.2s infinite}.tile.dead .dot{background:var(--dead)}.tile.dead .ta{color:#ff9a9a;font-weight:600}
.tile.na .dot{background:var(--na)}
.map.only .tile:not(.slow):not(.dead){opacity:.16}
@keyframes ping{0%{box-shadow:0 0 0 0 rgba(47,210,124,.8)}100%{box-shadow:0 0 0 10px rgba(47,210,124,0)}}
@keyframes glow{0%,100%{box-shadow:0 0 0 0 rgba(255,77,77,0)}50%{box-shadow:0 0 14px 1px rgba(255,77,77,.55)}}
.hub{grid-area:h;position:relative;z-index:1;display:grid;align-content:center;justify-items:center;gap:6px;text-align:center;padding:14px 10px;border:1px solid #3a4c8a;border-radius:12px;background:radial-gradient(circle at 50% 38%,#1d2a52,#111a33 70%)}
.core{width:112px;height:112px;border-radius:50%;border:3px solid #6f86ff;position:relative;box-shadow:0 0 26px rgba(111,134,255,.55)}
.core img{width:100%;height:100%;border-radius:50%;object-fit:cover;object-position:50% 30%;display:block}
.core .halo{position:absolute;inset:-14px;border-radius:50%;border:1px dashed rgba(111,134,255,.55);animation:spin 18s linear infinite}
.core .halo::before,.core .halo::after{content:"";position:absolute;width:7px;height:7px;border-radius:50%;background:#9fb0ff;box-shadow:0 0 10px #9fb0ff;top:-4px;left:50%}
.core .halo::after{top:auto;bottom:-4px;background:var(--accent);box-shadow:0 0 10px var(--accent)}
@keyframes spin{to{transform:rotate(360deg)}}
.score{position:absolute;right:-12px;bottom:-4px;font-family:var(--m);font-size:14px;background:#0e1730;border:2px solid var(--ok);color:var(--ok);border-radius:12px;padding:1px 7px;z-index:2}
.hub.bad .score{border-color:var(--dead);color:#ff9d9d}
.vit{display:flex;flex-wrap:wrap;gap:5px;justify-content:center;margin-top:2px}.vit span{font-size:11.5px;border:1px solid #2c7a52;color:#8fe6b8;border-radius:5px;padding:1px 7px;font-family:var(--m);white-space:nowrap}.vit span.slow{border-color:var(--slow);color:var(--slow)}.vit span.dead{border-color:var(--dead);color:#ff9d9d;background:#2a1017}.vit span.na{border-color:var(--line);color:var(--mute)}
.ht{display:contents}
.hub b{display:block}.hub .who{font-size:12.5px;color:#b9c4ff;letter-spacing:.3px}

.core::before,.core::after{content:"";position:absolute;inset:-2px;border-radius:50%;border:2px solid #6f86ff;animation:ring 2.8s ease-out infinite}.core::after{animation-delay:1.4s}
@keyframes ring{0%{transform:scale(1);opacity:.7}100%{transform:scale(1.8);opacity:0}}
.hub b{font-size:16px}.hub small{color:var(--mute);font-size:12.5px}
.hub.bad{border-color:var(--dead)}.hub.bad .core,.hub.bad .core::before,.hub.bad .core::after{border-color:var(--dead)}.hub.bad .core{box-shadow:0 0 26px rgba(255,77,77,.55)}
.rail{display:grid;gap:12px;min-width:0}
.box{background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:10px 12px;min-width:0}
.box h2{margin:0 0 6px;font-size:13px;font-weight:600;color:var(--mute);display:flex;justify-content:space-between;gap:8px}
.box.att{border-color:#5a1f25}.box.att.clear{border-color:#1f6e45}
.al{padding:7px 0;border-top:1px solid var(--line);font-size:13.5px}.al:first-of-type{border-top:0}
.al b{display:flex;justify-content:space-between;gap:8px;font-weight:600}.al b em{font-style:normal;font-family:var(--m);font-size:12px;white-space:nowrap}
.al.dead b{color:#ff9d9d}.al.slow b{color:var(--slow)}.al p{margin:2px 0 0;font-size:12.5px;color:var(--mute);overflow-wrap:anywhere}
.allok{color:var(--ok);font-size:13.5px}
.sc{display:grid;grid-template-columns:9px minmax(0,1fr) auto;gap:8px;align-items:center;padding:6px 0;border-top:1px solid var(--line)}.sc:first-of-type{border-top:0}
.sc i{width:9px;height:9px;border-radius:50%;background:var(--ok)}.sc .w{font-size:12.5px;color:var(--mute);overflow-wrap:anywhere}.sc .o{font-family:var(--m);font-size:12px;color:var(--mute);text-align:right}
.sc.idle i{background:var(--slow)}.sc.idle .w{color:var(--slow)}.sc.off{opacity:.5}.sc.off i{background:var(--na)}
.ev{display:grid;grid-template-columns:50px minmax(0,1fr);gap:8px;padding:5px 0;border-top:1px solid var(--line);font-size:13px}.ev:first-of-type{border-top:0}
.ev time{font-family:var(--m);font-size:12px;color:var(--mute)}.ev.bad{color:#ff9d9d}.ev.warn{color:var(--slow)}
.empty{color:var(--mute);font-size:13px}
@media(max-width:1180px){.main{grid-template-columns:minmax(0,1fr)}}
@media(max-width:860px){.map{grid-template-columns:minmax(0,1fr);gap:12px;grid-template-areas:"h" "a" "b" "c" "d" "e" "f" "g" "i"}#cv{display:none}}
@media(prefers-reduced-motion:reduce){.core .halo,.tile.beat .dot,.tile.dead,.core::before,.core::after,.wire::after{animation:none}}
/* One-screen mode (owner, 23 Nov): the whole board is drawn on a 1920px-wide stage and scaled to the window, so at 100% zoom nothing scrolls. Phones keep the scrolling layout. */
body.fit{padding:0;overflow:hidden;height:100vh}
body.fit #stage{position:absolute;left:0;top:0;width:1920px;transform-origin:0 0;padding:12px 16px;display:grid;grid-template-rows:auto auto auto minmax(0,1fr);gap:10px}
body.fit .sec{display:none}
body.fit .top{flex-wrap:nowrap;padding-bottom:8px}
body.fit .pipe{flex-wrap:nowrap}
body.fit .kp{grid-template-columns:repeat(var(--kn,8),minmax(0,1fr))}
body.fit .k{padding:7px 8px}body.fit .k .kl{font-size:12px;white-space:nowrap;overflow:hidden}body.fit .k .kl span{overflow:hidden;text-overflow:ellipsis}body.fit .k .kv{font-size:19px;white-space:nowrap;overflow:hidden}body.fit .k .kv small{font-size:11px;margin-left:4px}body.fit .k .ks{font-size:11.5px;line-height:1.3;height:2.6em;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}
body.fit .main{grid-template-columns:minmax(0,1fr) 320px;min-height:0;align-items:stretch}
body.fit .map{grid-template-rows:repeat(3,minmax(0,1fr));gap:24px 44px;min-height:0}
body.fit .g{display:flex;flex-direction:column;min-height:0}
body.fit .tiles{flex:1;grid-template-columns:repeat(5,minmax(0,1fr));grid-template-rows:repeat(2,minmax(0,1fr));min-height:0}
body.fit .tile{height:auto;min-height:0;align-content:center;padding:6px 3px 4px}
body.fit .hub{min-height:0;padding:8px 18px;overflow:hidden;grid-template-columns:minmax(0,1fr) auto minmax(0,1fr);grid-template-areas:"tl av tr" "vit vit vit";align-content:center;align-items:center;column-gap:30px;row-gap:8px}
body.fit .hub .core{grid-area:av;width:100px;height:100px;margin:14px}
body.fit .hub .ht{display:grid;gap:4px;min-width:0}
body.fit .hub .hl{grid-area:tl;text-align:right;justify-items:end}
body.fit .hub .hr{grid-area:tr;text-align:left;justify-items:start}
body.fit .hub .hl b{font-size:20px}
body.fit .hub .hr small:first-child{font-size:14px;color:var(--fg)}
body.fit .hub .vit{grid-area:vit;justify-self:center;margin:0}
body.fit .rail{display:flex;flex-direction:column;min-height:0}
body.fit .rail .box{overflow:hidden;flex:0 0 auto}
body.fit .rail .att{flex:1 1 0;min-height:0}
</style></head><body><div id="stage">
<div class="top">
  <a class="brand" href="/admin" title="অ্যাডমিনে ফিরুন"><span class="logo"><img src="/dutyfree-bd-logo-v3.png" alt="DutyFree BD"></span><span class="bt"><strong>লাইভ ড্যাশবোর্ড</strong><small id="today"></small></span></a>
  <span class="tag" id="conn">সংযোগ হচ্ছে…</span>
  <div class="sum" id="sum"></div>
  <div class="tools"><button id="only" aria-pressed="false">শুধু সমস্যা</button><button id="snd" aria-pressed="false">অ্যালার্ট শব্দ</button><button id="fs">ফুলস্ক্রিন</button></div>
  <div class="clock" id="clock"></div>
</div>
<div class="sec">অর্ডারের ধারা</div>
<div class="pipe" id="pipe"></div>
<div class="sec">আজকের অবস্থা</div>
<div class="kp" id="kp"></div>
<div class="sec">সব কার্যক্রম</div>
<div class="main">
  <div class="map" id="map"><canvas id="cv"></canvas>
    <section class="hub" id="hub"><div class="core"><span class="halo"></span><img src="/live-board-avatar.jpg?v=1" alt="Mohammad Faruk" onerror="this.hidden=true"><span class="score" id="score">…</span></div><div class="ht hl"><b>মাদার কমার্স</b><span class="who">Mohammad Faruk · কেন্দ্রীয় নিয়ন্ত্রণ</span></div><div class="ht hr"><small id="hubsub">তথ্য আসছে</small><small id="hubbeat"></small></div><div class="vit" id="vit"></div></section>
  </div>
  <aside class="rail">
    <section class="box att" id="attbox"><h2><span>এখন নজর দিন</span><span id="attn"></span></h2><div id="att"></div></section>
    <section class="box"><h2><span>স্টাফ · কে কী করছে</span><span id="sstat"></span></h2><div id="sg"></div></section>
    <section class="box"><h2>সর্বশেষ ঘটনা</h2><div id="feed"><div class="empty">বোর্ড খোলার পর থেকে যা বদলাবে এখানে আসবে।</div></div></section>
  </aside>
</div>
</div>
<script>
(function(){
const $=id=>document.getElementById(id);
const bn=n=>String(n).replace(/\d/g,d=>'০১২৩৪৫৬৭৮৯'[d]);
const num=n=>bn(Number(n).toLocaleString('en-IN'));
const esc=s=>String(s==null?'':s).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
const ago=s=>s==null?'':s<60?bn(s)+' সে.':s<3600?bn(Math.floor(s/60))+' মি.':s<86400?bn(Math.floor(s/3600))+' ঘ.':bn(Math.floor(s/86400))+' দিন';
const G={a:['AI কল ও কল সেন্টার',265,'PBX লাইন ও কল ব্যালেন্স দেখুন'],b:['মার্কেটিং ও অ্যাড',28,'Meta টোকেন ও Events Manager দেখুন'],c:['মেসেজ ও চ্যাট',150,'চ্যানেল সংযোগ ও ওয়েবহুক দেখুন'],d:['অর্ডার',205,'অর্ডার পেজ ও ওয়ার্কার দেখুন'],e:['কুরিয়ার ও ডেলিভারি',185,'কুরিয়ার API টোকেন ও ব্যালেন্স দেখুন'],f:['SMS, ইমেইল ও নোটিফিকেশন',340,'SMS ব্যালেন্স ও মেইল সার্ভার দেখুন'],g:['স্টক ও পারচেজ',95,'স্টক ও সাপ্লায়ার পেজ দেখুন'],i:['সার্ভার, হিসাব ও AI',50,'ডিপ্লয় পেজ ও এরর লগ দেখুন']};

// Line icons drawn for this board (one per activity), shown in a glowing chip in the group's colour. Falls back to the plain symbol if a key has none.
const IC={phone:'M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2',
mic:'M12 3a3 3 0 0 0-3 3v6a3 3 0 0 0 6 0V6a3 3 0 0 0-3-3zM5 11a7 7 0 0 0 14 0M12 18v3',
rec:'M12 12m-8 0a8 8 0 1 0 16 0a8 8 0 1 0-16 0M12 12m-3 0a3 3 0 1 0 6 0a3 3 0 1 0-6 0',
building:'M4 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16M16 9h2a2 2 0 0 1 2 2v10M3 21h18M8 7h4M8 11h4M8 15h4',
doc:'M7 3h7l5 5v11a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2zM14 3v5h5M9 13h6M9 17h6',
target:'M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0-18 0M12 12m-5 0a5 5 0 1 0 10 0a5 5 0 1 0-10 0M12 12m-1 0a1 1 0 1 0 2 0a1 1 0 1 0-2 0',
compass:'M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0-18 0M15.5 8.5l-2 5-5 2 2-5z',
refresh:'M20 11a8 8 0 0 0-14.9-3M4 4v4h4M4 13a8 8 0 0 0 14.9 3M20 20v-4h-4',
users:'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM2 21v-1a6 6 0 0 1 12 0v1M16 3.5a4 4 0 0 1 0 7.5M22 21v-1a6 6 0 0 0-4-5.6',
bell:'M6 16v-5a6 6 0 0 1 12 0v5l2 2H4zM10 20a2 2 0 0 0 4 0',
trophy:'M8 4h8v5a4 4 0 0 1-8 0zM8 6H5a3 3 0 0 0 3 4M16 6h3a3 3 0 0 1-3 4M12 13v4M8 20h8',
gift:'M4 11h16v9H4zM3 7h18v4H3zM12 7v13M12 7C9 7 7.5 6 7.5 4.8S10 3 12 7c2-4 4.5-3.4 4.5-2.2S15 7 12 7',
search:'M11 11m-7 0a7 7 0 1 0 14 0a7 7 0 1 0-14 0M20 20l-4-4',
chat:'M4 5h16v11H9l-5 4z',
chat2:'M3 5h12v8H8l-4 3zM10 16h7l4 3V9h-3',
monitor:'M3 4h18v12H3zM8 20h8M12 16v4',
pen:'M4 20l1-4L16 5l3 3L8 19zM14 7l3 3',
reply:'M9 8l-5 5 5 5M4 13h10a6 6 0 0 1 6 6',
bot:'M5 9h14v10H5zM12 5v4M12 4m-1 0a1 1 0 1 0 2 0a1 1 0 1 0-2 0M9 13v2M15 13v2M2 13v3M22 13v3',
spark:'M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8zM19 16l.8 2.2L22 19l-2.2.8L19 22l-.8-2.2L16 19l2.2-.8z',
cart:'M3 4h2l2.5 11h10.5l2-8H6.5M9 20m-1.5 0a1.5 1.5 0 1 0 3 0a1.5 1.5 0 1 0-3 0M17 20m-1.5 0a1.5 1.5 0 1 0 3 0a1.5 1.5 0 1 0-3 0',
check:'M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0-18 0M8 12l3 3 5-6',
user:'M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21v-1a8 8 0 0 1 16 0v1',
clock:'M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0-18 0M12 7v5l3 2',
mega:'M4 10v4h3l8 4V6l-8 4zM18 9a4 4 0 0 1 0 6',
eye:'M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12zM12 12m-3 0a3 3 0 1 0 6 0a3 3 0 1 0-6 0',
basket:'M4 9h16l-2 11H6zM8 9l4-6 4 6',
shield:'M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6zM9 12l2 2 4-4',
scan:'M4 8V5h3M17 5h3v3M20 16v3h-3M7 19H4v-3M8 9v6M11 9v6M14 9v6M16.5 9v6',
truck:'M2 6h11v10H2zM13 9h5l3 4v3h-8zM6.5 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0-4 0M17 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0-4 0',
box:'M12 3l8 4v10l-8 4-8-4V7zM4 7l8 4 8-4M12 11v10',
mobile:'M8 3h8a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1zM11 18h2',
send:'M21 3L3 10l7 3 3 7zM10 13l5-5',
mail:'M3 6h18v12H3zM3 7l9 6 9-6',
factory:'M3 21V10l6 4v-4l6 4V5h4v16zM7 17h2M12 17h2',
list:'M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01',
up:'M4 19h16M5 15l4-4 4 3 6-7M15 7h4v4',
down:'M4 19h16M5 8l4 4 4-3 6 7M15 16h4v-4',
dollar:'M12 3v18M16 7.5C16 6 14.5 5 12 5S8 6 8 8s1.5 2.5 4 3 4 1.5 4 3.5-1.5 3-4 3-4-1-4-2.5',
star:'M12 3l2.7 5.6 6.3.9-4.5 4.3 1.1 6.2L12 17l-5.6 3 1.1-6.2L3 9.5l6.3-.9z',
image:'M3 5h18v14H3zM3 16l5-5 4 4 3-3 6 6M9 9m-1.5 0a1.5 1.5 0 1 0 3 0a1.5 1.5 0 1 0-3 0',
globe:'M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0-18 0M3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18',
db:'M5 6c0-1.7 3-3 7-3s7 1.3 7 3-3 3-7 3-7-1.3-7-3zM5 6v12c0 1.7 3 3 7 3s7-1.3 7-3V6M5 12c0 1.7 3 3 7 3s7-1.3 7-3',
gear:'M12 12m-3 0a3 3 0 1 0 6 0a3 3 0 1 0-6 0M12 2v3M12 19v3M2 12h3M19 12h3M5 5l2 2M17 17l2 2M19 5l-2 2M7 17l-2 2',
pulse:'M2 12h4l3-7 4 14 3-7h6',
lock:'M6 11h12v9H6zM9 11V8a3 3 0 0 1 6 0v3',
rocket:'M12 15l-3-3c1-5 4-8 9-9-1 5-4 8-9 9zM9 12H5l2-4h3M12 15v4l4-2v-3M6 18l-2 2',
card:'M3 6h18v12H3zM3 10h18M7 15h3',
calc:'M6 3h12v18H6zM9 7h6M9 12h.01M12 12h.01M15 12h.01M9 16h.01M12 16h.01M15 16h.01',
chip:'M8 8h8v8H8zM5 5h14v14H5zM10 2v3M14 2v3M10 19v3M14 19v3M2 10h3M2 14h3M19 10h3M19 14h3',
alert:'M12 3l10 18H2zM12 10v5M12 18h.01'};
const IK={calls:'phone',ordercall:'check',voice:'mic',wacalls:'mobile',warec:'rec',pbx:'building',calldaily:'doc',
capi:'target',arrivals:'compass',winback:'refresh',referral:'users',push:'bell',bscamp:'trophy',appreward:'gift',seo:'search',autoblog:'pen',
wa:'chat',fb:'chat2',ig:'image',web:'monitor',reply:'pen',fbcomments:'reply',aichat:'bot',chatcare:'spark',
orders:'cart',aiapprove:'chip',manapprove:'user',sched:'clock',ordernotify:'mega',orderwatch:'eye',incomplete:'basket',fraud:'shield',scan:'scan',
csync:'refresh',pathao:'truck',steadfast:'box',redx:'send',carrybee:'truck',
sms:'mobile',smscamp:'mega',email:'mail',waupdate:'chat',pubsms:'bell',supsms:'factory',
reorder:'list',restock:'up',purchaselist:'doc',autoprice:'dollar',bsstock:'star',aiimg:'image',single:'box',stock:'down',
website:'globe',database:'db',queue:'gear',scheduler:'pulse',ssl:'lock',deploy:'rocket',svc:'card',recurring:'calc',aibrief:'chip',alerts:'alert'};
const icon=n=>{const d=IC[IK[n.k]];return d?'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="'+d+'"/></svg>':n.i};
const map=$('map'),E={},GE={},prev={};const prevSys={};let healSeen=null,D=null,got=0,built=false,first=true,fails=0,prevBeat=null;

function build(nodes){
  Object.keys(G).forEach(a=>{const s=document.createElement('section');s.className='g';s.style.gridArea=a;s.style.setProperty('--h',G[a][1]);
    s.innerHTML='<h2><span>'+G[a][0]+'</span><span class="gs"></span></h2><div class="tiles"></div>';map.appendChild(s);GE[a]={el:s,gs:s.querySelector('.gs'),tiles:s.querySelector('.tiles')}});
  nodes.forEach(n=>{if(!GE[n.g])return;const e=document.createElement('a');e.className='tile';e.href=n.h||'#';e.target='_blank';e.rel='noopener';
    e.innerHTML='<span class="dot"></span><span class="cnt"></span><span class="em">'+icon(n)+'</span><span class="tn">'+esc(n.n)+'</span><span class="ta"></span>';GE[n.g].tiles.appendChild(e);E[n.k]={e,ta:e.querySelector('.ta'),cnt:e.querySelector('.cnt')}});
  built=true;layout();
}
// Live trend lines. One real sample per card every second (owner, 23 Nov): the page asks the server for a small "pulse" each second and plots
// what came back, so a line goes up or down only when the real number does. The speed card plots how long this screen waited for that answer.
// If the pulse does not answer, the cards fall back to one sample per full refresh (5 s). Google SEO keeps its own daily line.
const HB={},SPN=60;let rtMs=null,lastSample=0,sampMs=5000,pulseOk=0;
function seed(d){d.kpi.forEach(k=>{if(k.fixed)HB[k.l]=k.h||[];else if(!HB[k.l])HB[k.l]=[]})}
function sample(d){
  d.kpi.forEach((k,i)=>{if(k.fixed)return;const h=HB[k.l]||(HB[k.l]=[]);let v=k.v;if(i===0&&rtMs!=null)v=rtMs;
    if(v==null)return;h.push(v);if(h.length>SPN)h.shift()});
  lastSample=performance.now();
}
function drawKp(){
  if(!D)return;if(rtMs!=null&&D.kpi[0]){D.kpi[0].v=rtMs;D.kpi[0].u='ms সাড়া (লাইভ)';D.kpi[0].st=D.kpi[0].st==='dead'?'dead':rtMs>1500?'slow':'ok'}
  const kp=$('kp');kp.style.setProperty('--kn',D.kpi.length);if(kp.children.length!==D.kpi.length){kp.innerHTML=D.kpi.map((k,i)=>'<div class="k"><div class="kl"><i></i><span></span></div><div class="kv"><b style="font-weight:inherit"></b><small></small></div><div class="ks"></div><div class="spw"><svg class="sp" id="sp'+i+'" viewBox="0 0 100 22" preserveAspectRatio="none"></svg><i class="sd" id="sd'+i+'" hidden></i></div></div>').join('')}
  D.kpi.forEach((k,i)=>{const e=kp.children[i];e.className='k '+(k.st==='ok'?'':k.st);e.style.cursor=k.href?'pointer':'';e.onclick=k.href?()=>window.open(k.href,'_blank'):null;
    const set=(q,t)=>{const n=e.querySelector(q);if(n.textContent!==t)n.textContent=t};set('.kl span',k.l);set('.kv b',k.v==null?'–':num(k.v));set('.kv small',k.u||'');set('.ks',k.s||'');e.title=k.s||''});
}
function drawVit(){const sys=(D&&D.sys)||[];$('vit').innerHTML=sys.map(x=>'<span class="'+(x.s==='ok'?'':x.s)+'" title="'+esc(x.t)+'">'+esc(x.l)+' '+(x.v==null?'–':bn(x.v))+'</span>').join('')}
let pulsing=false;
function pulse(){
  if(!D||document.hidden||pulsing)return;pulsing=true;const t0=performance.now();
  fetch('/admin/live-dashboard/pulse',{headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},credentials:'same-origin',cache:'no-store'})
   .then(r=>{if(!r.ok||r.redirected)throw new Error('pulse');return r.json()})
   .then(p=>{rtMs=Math.round(performance.now()-t0);pulseOk=Date.now();sampMs=1000;
     D.kpi.forEach(k=>{const x=p.k&&p.k[k.l];if(x){if(x.v!=null)k.v=x.v;if(x.s)k.s=x.s;if(x.st)k.st=x.st}});
     (D.sys||[]).forEach(x=>{const y=p.sys&&p.sys[x.k];if(y)x.v=y[0]});// the colour (and the alert in the event list) still comes from the full refresh, so a number hovering on a limit does not flood the list
     sample(D);drawKp();drawVit();$('conn').textContent='লাইভ · প্রতি সেকেন্ডে'})
   .catch(()=>{}).finally(()=>{pulsing=false});
}
setInterval(pulse,1000);
function drawSparks(now){
  if(D){const f=Math.min(1,(now-lastSample)/sampMs),step=100/(SPN-2);
    D.kpi.forEach((k,i)=>{const el=document.getElementById('sp'+i),dot=document.getElementById('sd'+i);if(!el)return;const h=HB[k.l]||[],n=h.length;
      const col=k.st==='dead'?'#ff4d4d':k.st==='slow'?'#f2b030':'#ff7a1a';
      if(n<2){el.innerHTML='<path d="M0 16H100" stroke="#232c42" stroke-width="1.5" fill="none" vector-effect="non-scaling-stroke"/>';if(dot)dot.hidden=true;return}
      const mn=Math.min(...h),mx=Math.max(...h),r=(mx-mn)||1,Y=v=>19-(v-mn)/r*15;
      if(k.fixed){const L=h.map((v,j)=>(j?'L':'M')+(j*100/(n-1)).toFixed(2)+' '+Y(v).toFixed(2)).join('');el.innerHTML='<path d="'+L+'L100 22L0 22Z" fill="'+col+'" opacity=".15"/><path d="'+L+'" stroke="'+col+'" stroke-width="1.6" fill="none" vector-effect="non-scaling-stroke"/>';if(dot){dot.style.top=(Y(h[n-1])/22*100)+'%';dot.style.background=col;dot.hidden=false}return}
      const pts=[];for(let j=0;j<=n-2;j++)pts.push([100-(n-1-j)*step+(1-f)*step,Y(h[j])]);
      const cur=[100,Y(h[n-2])+(Y(h[n-1])-Y(h[n-2]))*f];pts.push(cur);
      const line=pts.map((q,j)=>(j?'L':'M')+q[0].toFixed(2)+' '+q[1].toFixed(2)).join('');
      el.innerHTML='<path d="'+line+'L100 22L'+pts[0][0].toFixed(2)+' 22Z" fill="'+col+'" opacity=".15"/><path d="'+line+'" stroke="'+col+'" stroke-width="1.6" fill="none" vector-effect="non-scaling-stroke"/>';
      if(dot){dot.style.top=(cur[1]/22*100)+'%';dot.style.background=col;dot.style.boxShadow='0 0 8px '+col;dot.hidden=false}});
  }
  requestAnimationFrame(drawSparks);
}
requestAnimationFrame(drawSparks);
function log(t,cls){const f=$('feed');const em=f.querySelector('.empty');if(em)em.remove();const d=new Date(),hh=bn(String(d.getHours()).padStart(2,'0')+':'+String(d.getMinutes()).padStart(2,'0'));
  f.insertAdjacentHTML('afterbegin','<div class="ev '+(cls||'')+'"><time>'+hh+'</time><span>'+esc(t)+'</span></div>');while(f.children.length>5)f.lastChild.remove()}
let ac=null;
function beep(){if(!ac)return;try{const o=ac.createOscillator(),g=ac.createGain();o.frequency.value=660;g.gain.setValueAtTime(.12,ac.currentTime);g.gain.exponentialRampToValueAtTime(.001,ac.currentTime+.5);o.connect(g).connect(ac.destination);o.start();o.stop(ac.currentTime+.5)}catch(e){}}
$('snd').onclick=e=>{if(ac){ac.close();ac=null}else{try{ac=new (window.AudioContext||window.webkitAudioContext)();beep()}catch(_){ac=null}}e.currentTarget.setAttribute('aria-pressed',ac?'true':'false')};
$('only').onclick=e=>{e.currentTarget.setAttribute('aria-pressed',map.classList.toggle('only'))};
$('fs').onclick=()=>{try{const p=document.fullscreenElement?document.exitFullscreen():document.documentElement.requestFullscreen();p&&p.catch&&p.catch(()=>{})}catch(e){}};

function apply(d){
  if(!built)build(d.nodes);
  (d.heal&&d.heal.log||[]).slice().reverse().forEach(h=>{if(healSeen!==null&&h.t>healSeen)log('🔧 '+h.m,h.l==='warn'?'warn':'')});healSeen=d.heal&&d.heal.log&&d.heal.log.length?Math.max(healSeen||0,d.heal.log[0].t):(healSeen||0);
  const beatFresh=prevBeat!==null&&d.beat!==null&&d.beat<prevBeat;prevBeat=d.beat;
  d.nodes.forEach(n=>{const x=E[n.k];if(!x)return;const p=prev[n.k];
    let pulse=0;
    if(p){if(n.today!=null&&p.today!=null&&n.today>p.today)pulse=Math.min(5,n.today-p.today);else if(n.last!=null&&p.last!=null&&n.last+4<p.last&&!n.beat)pulse=1;
      if(n.beat&&beatFresh&&n.s==='ok')pulse=Math.max(pulse,1);
      if(p.s!==n.s){if(n.s==='dead'){log(n.n+' থেমে গেছে'+(n.t?' · '+n.t:''),'bad');beep()}else if(n.s==='slow')log(n.n+' ধীর বা সতর্কতা'+(n.t?' · '+n.t:''),'warn');else if(n.s==='ok'&&(p.s==='dead'||p.s==='slow'))log(n.n+' আবার ঠিক হয়েছে')}}
    if(pulse){x.e.classList.remove('beat');void x.e.offsetWidth;x.e.classList.add('beat');for(let i=0;i<pulse;i++)setTimeout(()=>spawn(n.g,true),i*220)}
    prev[n.k]={today:n.today,last:n.last,s:n.s};
    x.e.title=n.n+(n.t?' · '+n.t:'')+(n.today!=null?' · আজ '+n.today:'');
  });
  // pipeline
  let h='',flow=true,wi=0;d.pipe.forEach((p,i)=>{if(!p){h+='<div class="gap"></div>';flow=false;return}if(i&&d.pipe[i-1])h+=flow?'<div class="wire w'+(++wi%3+1)+'"></div>':'<div class="gap" style="flex-basis:6px"></div>';
    h+='<div class="stg '+p.c+'"><b>'+(p.n==null?'–':num(p.n))+'</b><span>'+p.l+'</span></div>'});$('pipe').innerHTML=h;
  seed(d);if(Date.now()-pulseOk>6000){sampMs=5000;sample(d)}
  drawKp();
  // staff
  const st=d.staff||{rows:[]};let idle=0;
  const offN=st.rows.filter(s=>s.state==='Offline').map(s=>esc(s.name));
  $('sg').innerHTML=st.rows.length?st.rows.filter(s=>s.state!=='Offline').map(s=>{const off=s.state==='Offline',aw=s.state==='Away'||s.state==='On break';if(aw)idle++;
    const w=off?'অফলাইন':(s.state==='On break'?'বিরতিতে':(aw?'দূরে':'')+(s.page?(aw?' · ':'')+esc(s.page):''))+(s.work_min!=null?' · শেষ কাজ '+bn(s.work_min)+' মি. আগে':'');
    return '<div class="sc '+(off?'off':aw?'idle':'')+'"><i></i><div style="min-width:0"><b>'+esc(s.name)+'</b><div class="w">'+w+(s.waiting?' · অপেক্ষায় '+bn(s.waiting)+' চ্যাট':'')+'</div></div><div class="o">'+(off?'':bn(s.orders)+' অর্ডার<br>'+bn(s.calls)+' কল · '+bn(s.chats)+' চ্যাট')+'</div></div>'}).join('')+(offN.length?'<div class="sc off"><i></i><div class="w">অফলাইন: '+offN.join(', ')+'</div><div></div></div>':''):'<div class="empty">স্টাফের তথ্য পাওয়া যায়নি।</div>';
  $('sstat').textContent=(st.unassigned?'কারো নয় এমন চ্যাট '+bn(st.unassigned)+' · ':'')+(idle?bn(idle)+' জন দূরে':'');
  render();
}
function render(){
  if(!D)return;const el=Math.floor((Date.now()-got)/1000),c={ok:0,slow:0,dead:0,na:0},bad=[];
  D.nodes.forEach(n=>{const x=E[n.k];if(!x)return;c[n.s]++;if(n.s==='dead'||n.s==='slow')bad.push(n);
    x.e.className='tile '+(n.s==='ok'?'':n.s)+(x.e.classList.contains('beat')?' beat':'');
    const a=n.last==null?null:n.last+el;
    x.ta.textContent=n.s==='dead'?'থেমে আছে':n.s==='na'&&a==null?'সংকেত নেই':a==null?'':n.last>=259200&&!n.beat?'৩ দিন+ আগে':ago(a)+' আগে';
    x.cnt.textContent=n.today?bn(n.today):'';
  });
  Object.keys(GE).forEach(a=>{const ns=D.nodes.filter(n=>n.g===a),dd=ns.filter(n=>n.s==='dead').length,ss=ns.filter(n=>n.s==='slow').length;
    GE[a].gs.textContent=dd?bn(dd)+' টি থেমে আছে':ss?bn(ss)+' টি ধীর':'সব ঠিক';GE[a].gs.style.color=dd?'var(--dead)':ss?'var(--slow)':'var(--ok)';GE[a].st=dd?'dead':ss?'slow':'ok'});
  $('sum').innerHTML='<div class="pill ok"><i></i>চলছে '+bn(c.ok)+'</div><div class="pill slow"><i></i>ধীর '+bn(c.slow)+'</div><div class="pill dead '+(c.dead?'on':'')+'"><i></i>থেমে আছে '+bn(c.dead)+'</div><div class="pill na"><i></i>সংকেত নেই '+bn(c.na)+'</div>';
  const known=c.ok+c.slow+c.dead;$('score').textContent=known?bn(Math.round(c.ok/known*100))+'%':'–';
  $('hub').classList.toggle('bad',c.dead>0||fails>2);
  $('hubsub').textContent=c.dead?bn(c.dead)+' টি কাজ থেমে আছে':c.slow?bn(c.slow)+' টি কাজে সতর্কতা':'সব কাজ চালু';
  $('hubbeat').textContent=D.beat==null?'ওয়ার্কারের সংকেত নেই':'ওয়ার্কার সংকেত '+ago(D.beat+el)+' আগে'+(D.disk!=null?' · ডিস্ক খালি '+bn(D.disk)+'%':'')+' · অটো-রিকভারি আজ '+bn(D.heal?D.heal.today:0)+' বার';
  const sys=D.sys||[],sysBad=sys.filter(x=>x.s==='dead'||x.s==='slow');
  drawVit();
  sys.forEach(x=>{const o=prevSys[x.k];if(o&&o!==x.s){if(x.s==='dead'){log('সার্ভার: '+x.l+' বিপজ্জনক মাত্রায় ('+x.v+')','bad');beep()}else if(x.s==='slow')log('সার্ভার: '+x.l+' বেশি ('+x.v+')','warn');else if(x.s==='ok')log('সার্ভার: '+x.l+' স্বাভাবিক হয়েছে')}prevSys[x.k]=x.s});
  if(sysBad.some(x=>x.s==='dead'))$('hub').classList.add('bad');
  bad.sort((p,q)=>(q.s==='dead')-(p.s==='dead'));
  const iss=(D.issues||[]).map(i=>'<div class="al '+(i.severity==='critical'||i.severity==='high'?'dead':'slow')+'"><b><span>🚨 '+esc(i.title)+'</span><em>'+esc(i.service)+'</em></b><p>'+esc(i.action)+'</p></div>');
  const nb=bad.slice(0,5).map(n=>'<div class="al '+n.s+'"><b><span>'+esc(n.n)+'</span><em>'+(n.s==='dead'?'থেমে আছে':'সতর্কতা')+'</em></b><p>'+esc(n.t||'')+(n.t?' · ':'')+G[n.g][2]+'</p></div>');
  const sb=sysBad.map(x=>'<div class="al '+x.s+'"><b><span>সার্ভার · '+esc(x.l)+'</span><em>'+bn(x.v)+'</em></b><p>'+esc(x.t)+(x.t?' · ':'')+esc(x.fix)+'</p></div>');
  const all=sb.concat(iss,nb);$('attbox').classList.toggle('clear',!all.length);$('attn').textContent=all.length?bn(all.length)+' টি':'';
  $('att').innerHTML=all.length?all.join(''):'<div class="allok">সব ঠিক আছে। কোনো কাজ থেমে নেই।</div>';
}
function poll(){
  const t0=performance.now();
  fetch('/admin/live-dashboard/status',{headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},credentials:'same-origin',cache:'no-store'})
   .then(r=>{if(r.status===401||r.status===419||r.redirected)throw new Error('login');if(!r.ok)throw new Error('http '+r.status);return r.json()})
   .then(d=>{rtMs=Math.round(performance.now()-t0);D=d;got=Date.now();if(fails>2)log('সার্ভারের সঙ্গে সংযোগ ফিরে এসেছে');fails=0;if(Date.now()-pulseOk>6000)$('conn').textContent='লাইভ · প্রতি ৫ সেকেন্ডে';$('conn').className='tag';apply(d);first=false})
   .catch(e=>{fails++;const m=e.message==='login'?'লগইন শেষ হয়েছে, আবার লগইন করুন':'সার্ভার থেকে তথ্য আসছে না';$('conn').textContent=m;$('conn').className='tag bad';if(fails===3){log(m,'bad');beep()}render()});
}
setInterval(poll,5000);poll();
setInterval(()=>{render();const nd=new Date();$('clock').textContent=bn(nd.toLocaleTimeString('en-GB'));$('today').textContent=nd.toLocaleDateString('bn-BD',{weekday:'long',day:'numeric',month:'long',year:'numeric'})},1000);

// wires + travelling dots (one dot = one real event seen since the last refresh)
const cv=$('cv'),cx=cv.getContext('2d');let P={},W=0,H=0;
let S=1;
function fit(){const on=innerWidth>=1000,st=$('stage');document.body.classList.toggle('fit',on);
  if(!on){S=1;st.style.transform='';st.style.height='';st.style.left='';layout();return}
  let sc=innerWidth/1920,h=innerHeight/sc;if(h<940){h=940;sc=innerHeight/940}
  S=sc;st.style.height=h+'px';st.style.transform='scale('+sc+')';st.style.left=Math.max(0,(innerWidth-1920*sc)/2)+'px';layout()}
function rel(el){const r=el.getBoundingClientRect(),m=map.getBoundingClientRect();return{left:(r.left-m.left)/S,right:(r.right-m.left)/S,top:(r.top-m.top)/S,bottom:(r.bottom-m.top)/S,w:r.width/S,h:r.height/S}}
function layout(){
  const dpr=(window.devicePixelRatio||1)*S;W=map.offsetWidth;H=map.offsetHeight;cv.width=Math.round(W*dpr);cv.height=Math.round(H*dpr);cx.setTransform(dpr,0,0,dpr,0,0);
  if(!built||getComputedStyle(cv).display==='none'){P={};return}
  const hub=rel($('hub')),hy=hub.top+hub.h/2,hx=hub.left+hub.w/2,R={};Object.keys(GE).forEach(a=>R[a]=rel(GE[a].el));
  const g1=(R.d.right+hub.left)/2,g2=(hub.right+R.e.left)/2,mid=r=>r.top+r.h/2;
  P={d:[[R.d.right,hy],[hub.left,hy]],e:[[R.e.left,hy],[hub.right,hy]],b:[[hx,R.b.bottom],[hx,hub.top]],g:[[hx,R.g.top],[hx,hub.bottom]],
    a:[[R.a.right,mid(R.a)],[g1-9,mid(R.a)],[g1-9,hy-20],[hub.left,hy-20]],f:[[R.f.right,mid(R.f)],[g1+9,mid(R.f)],[g1+9,hy+20],[hub.left,hy+20]],
    c:[[R.c.left,mid(R.c)],[g2+9,mid(R.c)],[g2+9,hy-20],[hub.right,hy-20]],i:[[R.i.left,mid(R.i)],[g2-9,mid(R.i)],[g2-9,hy+20],[hub.right,hy+20]]};
  Object.values(P).forEach(p=>{let L=0;p.cum=[0];for(let k=1;k<p.length;k++){L+=Math.hypot(p[k][0]-p[k-1][0],p[k][1]-p[k-1][1]);p.cum.push(L)}p.len=L});
}
function at(p,t){const d=t*p.len;let k=1;while(k<p.length-1&&p.cum[k]<d)k++;const f=(d-p.cum[k-1])/((p.cum[k]-p.cum[k-1])||1);return[p[k-1][0]+(p[k][0]-p[k-1][0])*f,p[k-1][1]+(p[k][1]-p[k-1][1])*f]}
addEventListener('resize',fit);setInterval(layout,1500);
const still=matchMedia('(prefers-reduced-motion:reduce)').matches,parts=[];
function spawn(a,big){if(parts.length<160)parts.push({a,t:0,s:.3+Math.random()*.25,dir:big?(Math.random()<.3?-1:1):(tog[a]=!tog[a])?1:-1,r:big?4.2:2.6})}
// steady traffic (owner, 23 Nov): one small dot per second on every group that is alive, going out and coming back in turn.
// It stops on a group that is red and on the whole board when the server stops answering, so "no dots" still means "stopped". Real events use a bigger dot.
const tog={};setInterval(()=>{if(!D||fails>2||document.hidden)return;Object.keys(P).forEach(a=>{if((GE[a]||{}).st!=='dead'&&D.nodes.some(n=>n.g===a&&n.s==='ok'))spawn(a,false)})},1000);
let last=performance.now();
function draw(now){
  const dt=Math.min(.05,(now-last)/1000);last=now;cx.clearRect(0,0,W,H);
  Object.keys(P).forEach(a=>{const p=P[a],s=(GE[a]||{}).st,h=G[a][1];cx.save();cx.lineWidth=2;cx.lineJoin='round';
    cx.strokeStyle=s==='dead'?'rgba(255,77,77,.6)':s==='slow'?'rgba(242,176,48,.5)':'hsla('+h+',80%,62%,.38)';cx.setLineDash(s==='dead'?[6,6]:[]);
    cx.beginPath();p.forEach((q,k)=>k?cx.lineTo(q[0],q[1]):cx.moveTo(q[0],q[1]));cx.stroke();cx.setLineDash([]);cx.fillStyle=cx.strokeStyle;
    const e=p[p.length-1];cx.beginPath();cx.arc(e[0],e[1],4,0,7);cx.fill();cx.beginPath();cx.arc(p[0][0],p[0][1],3,0,7);cx.fill();cx.restore()});
  if(!still)for(let k=parts.length-1;k>=0;k--){const q=parts[k];q.t+=q.s*dt;if(q.t>=1){parts.splice(k,1);continue}const p=P[q.a];if(!p)continue;
    const xy=at(p,q.dir>0?q.t:1-q.t),h=G[q.a][1];cx.save();cx.shadowBlur=12;cx.shadowColor='hsl('+h+' 90% 65%)';cx.fillStyle='hsl('+h+' 95% 80%)';cx.beginPath();cx.arc(xy[0],xy[1],q.r||3.2,0,7);cx.fill();cx.restore()}
  requestAnimationFrame(draw);
}
fit();requestAnimationFrame(draw);document.fonts&&document.fonts.ready.then(fit);
})();
</script>
</body></html>
