@php $__a = collect($deals ?? [])->take(6); @endphp
@if($__a->count())
<style>
.dfa{position:relative;margin:18px 0;border-radius:14px;overflow:hidden;background:#050B17;color:#fff}
.dfa-stage{position:relative;aspect-ratio:1600/800;width:100%}
.dfa-stage>svg{position:absolute;inset:0;width:100%;height:100%;display:block}
.dfa-ppl{pointer-events:none}
.dfa-head{position:absolute;left:3%;top:5%;max-width:40%;z-index:3}
.dfa-k{display:inline-block;font-size:11px;font-weight:800;letter-spacing:.14em;color:#FFB07A;border:1px solid rgba(255,176,122,.5);border-radius:20px;padding:4px 11px;background:rgba(5,11,23,.45)}
.dfa-head h2{margin:10px 0 6px;font-size:clamp(17px,2.2vw,32px);line-height:1.12;font-weight:800;text-shadow:0 2px 14px rgba(0,0,0,.55)}.dfa-head h2 b{color:#FF7A2E}
.dfa-head p{margin:0;font-size:clamp(12px,1.05vw,15px);color:#E3EAF6;line-height:1.55;text-shadow:0 1px 8px rgba(0,0,0,.6)}
.dfa-board{position:absolute;right:2.5%;top:6%;width:30%;z-index:3;background:#04070D;border:2px solid #1E2A44;border-radius:8px;box-shadow:0 10px 30px rgba(0,0,0,.5);font-family:Inter,'Bornomala',sans-serif;overflow:hidden}
.dfa-bh{display:flex;justify-content:space-between;align-items:center;background:#0E1B34;padding:7px 11px;font-size:clamp(9px,.85vw,12px);font-weight:700;letter-spacing:.08em;color:#FFC56B}.dfa-bh i{font-style:normal;color:#8FD3FF}
.dfa-rows{height:calc(5 * clamp(24px,2.5vw,36px))}
.dfa-row{display:grid;grid-template-columns:3.6em minmax(0,1fr) auto;gap:8px;align-items:center;height:clamp(24px,2.5vw,36px);padding:0 11px;border-top:1px solid #131D33;color:#FFC56B;text-decoration:none;font-size:clamp(9px,.9vw,13px);white-space:nowrap;animation:dfaIn .6s ease}
.dfa-row span:nth-child(2){overflow:hidden;text-overflow:ellipsis;color:#F2F6FF}.dfa-row b{color:#6EE7A0;animation:dfaBlink 1.4s steps(2,jump-none) infinite}.dfa-row:hover span:nth-child(2){color:#FFC56B}
@keyframes dfaIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}@keyframes dfaBlink{50%{opacity:.25}}
.dfa-shop{position:absolute;left:2.5%;top:49%;width:30%;z-index:2;background:linear-gradient(180deg,rgba(255,196,120,.20),rgba(20,28,48,.88) 38%);border:1px solid rgba(255,200,140,.45);border-radius:10px;padding:0 0 8px;box-shadow:0 0 40px rgba(255,150,60,.28),inset 0 0 30px rgba(255,190,120,.12)}
.dfa-sign{display:flex;align-items:center;gap:8px;padding:6px 12px;border-bottom:1px solid rgba(255,200,140,.35);font-weight:800;letter-spacing:.16em;font-size:clamp(10px,1vw,14px);color:#FFE2B8;text-shadow:0 0 12px rgba(255,170,80,.8)}.dfa-sign b{color:#FF7A2E}
.dfa-it{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:6px;padding:8px 8px 0}
.dfa-p{display:block;position:relative;background:#fff;border-radius:6px;aspect-ratio:1/1;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.4);transition:transform .3s,box-shadow .3s}
.dfa-p img{width:100%;height:100%;object-fit:contain;display:block}.dfa-p em{position:absolute;left:0;bottom:0;right:0;background:rgba(11,18,32,.88);color:#FFB48A;font-style:normal;font-weight:800;font-size:clamp(8px,.8vw,12px);text-align:center;padding:2px 0}
.dfa-p.hot{transform:translateY(-3px) scale(1.05);box-shadow:0 0 0 2px #FF7A2E,0 6px 18px rgba(255,122,46,.55)}
.dfa-m{display:none}
.w{animation:dfaWalk linear infinite}.lg1{animation:dfaLeg .62s ease-in-out infinite alternate;transform-origin:0 -42px}.lg2{animation:dfaLeg .62s ease-in-out infinite alternate-reverse;transform-origin:0 -42px}.bd{animation:dfaBob .31s ease-in-out infinite alternate}
@keyframes dfaWalk{from{transform:translateX(-140px)}to{transform:translateX(1740px)}}@keyframes dfaLeg{from{transform:rotate(24deg)}to{transform:rotate(-24deg)}}@keyframes dfaBob{from{transform:translateY(0)}to{transform:translateY(-2px)}}
.pl{transform-box:view-box}
.pl.tk{animation:dfaTk 16s cubic-bezier(.45,.05,.6,1) infinite}.pl.ld{animation:dfaLd 22s linear infinite 5s}.pl.tx{animation:dfaTx 60s linear infinite}
@keyframes dfaTk{0%{transform:translate(200px,438px) scale(1) rotate(0)}48%{transform:translate(940px,438px) scale(1) rotate(0)}62%{transform:translate(1200px,418px) scale(.95) rotate(-9deg)}100%{transform:translate(1850px,150px) scale(.55) rotate(-15deg)}}
@keyframes dfaLd{0%{transform:translate(1850px,130px) scale(.5) scaleX(-1) rotate(-6deg)}38%{transform:translate(1300px,350px) scale(.8) scaleX(-1) rotate(-3deg)}52%{transform:translate(1010px,438px) scale(1) scaleX(-1) rotate(0)}100%{transform:translate(-300px,438px) scale(1) scaleX(-1) rotate(0)}}
@keyframes dfaTx{from{transform:translate(1700px,408px) scale(.42) scaleX(-1)}to{transform:translate(-200px,408px) scale(.42) scaleX(-1)}}
.dfa .bl{animation:dfaBlink 1.1s steps(2,jump-none) infinite}
@media (max-width:860px){.dfa-stage{aspect-ratio:4/3}.dfa-board,.dfa-shop{display:none}.dfa-head{max-width:70%;top:6%}.dfa-m{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;padding:12px;background:linear-gradient(180deg,#0B1220,#050B17)}.dfa-m a{display:flex;gap:8px;align-items:center;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);border-radius:10px;padding:6px;color:#fff;text-decoration:none}.dfa-m img{width:44px;height:44px;object-fit:contain;background:#fff;border-radius:6px;flex:none}.dfa-m i{display:block;font-style:normal;font-size:11.5px;font-weight:600;line-height:1.25;max-height:2.5em;overflow:hidden}.dfa-m b{display:block;font-size:12px;color:#FFB48A;margin-top:2px}.dfa-ppl{display:none}.dfa-head h2{font-size:21px}}
@media (max-width:420px){.dfa-m{grid-template-columns:1fr}}
@media (prefers-reduced-motion:reduce){.dfa *{animation:none!important}}
</style>
<section class="dfa" aria-label="Dubai airport duty free offers">
<div class="dfa-stage">
<svg viewBox="0 0 1600 800" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
<defs>
<linearGradient id="dfaSky" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#050B17"/><stop offset=".45" stop-color="#14233F"/><stop offset=".78" stop-color="#7A3A26"/><stop offset="1" stop-color="#FF7A2E"/></linearGradient>
<radialGradient id="dfaSun" cx="50%" cy="50%" r="50%"><stop offset="0" stop-color="#FFD9A0" stop-opacity=".9"/><stop offset=".4" stop-color="#FF8A3D" stop-opacity=".35"/><stop offset="1" stop-color="#FF5A00" stop-opacity="0"/></radialGradient>
<linearGradient id="dfaFloor" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#23314F"/><stop offset=".5" stop-color="#121C33"/><stop offset="1" stop-color="#070C17"/></linearGradient>
<linearGradient id="dfaApron" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#1A2035"/><stop offset="1" stop-color="#0A0F1B"/></linearGradient>
<g id="dfaPlane"><path d="M8 30C8 22 40 18 90 18L150 18C175 18 195 24 198 30C195 34 175 38 150 38L40 38C20 38 8 36 8 30Z" fill="#F4F7FB"/><path d="M20 21L6 -8L26 -8L50 20Z" fill="#FF5A00"/><path d="M88 36L58 58L78 58L120 37Z" fill="#DDE5F0"/><ellipse cx="102" cy="45" rx="12" ry="6" fill="#C5D0E0"/><path d="M168 24L184 24L192 29L168 29Z" fill="#2A3B5C"/><g fill="#7FA6D8"><circle cx="60" cy="27" r="1.7"/><circle cx="72" cy="27" r="1.7"/><circle cx="84" cy="27" r="1.7"/><circle cx="96" cy="27" r="1.7"/><circle cx="108" cy="27" r="1.7"/><circle cx="120" cy="27" r="1.7"/><circle cx="132" cy="27" r="1.7"/><circle cx="144" cy="27" r="1.7"/></g><circle cx="199" cy="30" r="3" fill="#fff" class="bl"/><circle cx="12" cy="-8" r="2.2" fill="#FF3B3B" class="bl"/></g>
</defs>
<rect width="1600" height="800" fill="url(#dfaSky)"/>
<circle cx="930" cy="395" r="330" fill="url(#dfaSun)"/>
<g id="dfaStars" fill="#fff"></g>
<g id="dfaCity" fill="#0B1220"></g>
<rect y="400" width="1600" height="400" fill="url(#dfaApron)"/>
<rect y="436" width="1600" height="44" fill="#0F1524"/>
<line x1="0" y1="458" x2="1600" y2="458" stroke="#E8EEF8" stroke-width="2" stroke-dasharray="34 26" opacity=".7"/>
<g id="dfaLights"></g>
<use href="#dfaPlane" class="pl tx"/>
<use href="#dfaPlane" class="pl tk"/>
<use href="#dfaPlane" class="pl ld"/>
<rect y="0" width="1600" height="30" fill="#05080F"/><g id="dfaCeil" fill="#FFE2B8"></g>
<g fill="#06090F" opacity=".92"><rect x="0" width="16" height="520"/><rect x="528" width="9" height="520"/><rect x="1064" width="9" height="520"/><rect x="1584" width="16" height="520"/></g>
<rect y="518" width="1600" height="282" fill="url(#dfaFloor)"/><rect y="516" width="1600" height="5" fill="#0A0F1A"/>
<g id="dfaRefl" opacity=".35"></g>
</svg>
<svg class="dfa-ppl" id="dfaPpl" viewBox="0 0 1600 800" preserveAspectRatio="xMidYMid slice"></svg>
<div class="dfa-head"><span class="dfa-k">✈ DUBAI AIRPORT · DUTY FREE</span><h2>দুবাই এয়ারপোর্টের <b>ডিউটি ফ্রি</b> পণ্য, এখন আপনার দরজায়</h2><p>বিমানে ওঠার আগে যে অফারগুলোর জন্য মানুষ অপেক্ষা করে, সেগুলো এখন আমাদের কাছে।</p></div>
<div class="dfa-board" aria-label="Offers board"><div class="dfa-bh"><span>DUTY FREE OFFERS</span><i id="dfaClock">DUBAI --:--</i></div><div class="dfa-rows" id="dfaRows">
@foreach($__a as $__i => $q)@php $off = \App\Http\Controllers\StoreController::off($q); @endphp
<a class="dfa-row" href="{{ \App\Http\Controllers\StoreController::url($q) }}"><span>DF{{ str_pad((string)(($__i + 1) * 117 % 1000), 3, '0', STR_PAD_LEFT) }}</span><span>{{ \Illuminate\Support\Str::limit($q->name, 26, '…') }}</span><span>{{ number_format($q->price) }}@if($off > 0) <b>-{{ $off }}%</b>@endif</span></a>
@endforeach
</div></div>
<div class="dfa-shop"><div class="dfa-sign"><b>✦</b> DUTY FREE <b>SHOP</b></div><div class="dfa-it" id="dfaShop">
@foreach($__a as $q)@php $off = \App\Http\Controllers\StoreController::off($q); @endphp
<a class="dfa-p" href="{{ \App\Http\Controllers\StoreController::url($q) }}" title="{{ $q->name }}"><img src="{{ $q->image ?: '/product-placeholder' }}" alt="{{ $q->name }}" width="110" height="110" loading="lazy" decoding="async" referrerpolicy="no-referrer" onerror="this.onerror=null;this.src='/product-placeholder'"><em>{{ number_format($q->price) }} TK</em></a>
@endforeach
</div></div>
</div>
<div class="dfa-m">
@foreach($__a as $q)@php $off = \App\Http\Controllers\StoreController::off($q); @endphp
<a href="{{ \App\Http\Controllers\StoreController::url($q) }}"><img src="{{ $q->image ?: '/product-placeholder' }}" alt="" width="44" height="44" loading="lazy" decoding="async" referrerpolicy="no-referrer" onerror="this.onerror=null;this.src='/product-placeholder'"><span><i>{{ $q->name }}</i><b>{{ number_format($q->price) }} TK @if($off > 0)-{{ $off }}%@endif</b></span></a>
@endforeach
</div>
</section>
<script>
(function(){
var N='http://www.w3.org/2000/svg',$=function(i){return document.getElementById(i)},R=function(a,b){return a+Math.random()*(b-a)};
function el(t,a,p){var e=document.createElementNS(N,t);for(var k in a)e.setAttribute(k,a[k]);if(p)p.appendChild(e);return e}
var sky=$('dfaStars');if(!sky)return;
for(var i=0;i<46;i++)el('circle',{cx:R(0,1600),cy:R(10,250),r:R(.5,1.5),opacity:R(.3,.9)},sky);
var c=$('dfaCity'),x=0;
/* skyline with a tall spire tower */
el('path',{d:'M960 400L960 250L966 200L970 120L973 60L976 120L980 200L986 250L986 400Z'},c);el('path',{d:'M946 400L946 300L960 270L960 400Z'},c);el('path',{d:'M986 400L986 280L1002 310L1002 400Z'},c);
while(x<1600){var w=R(26,70),h=R(30,150);if(x>880&&x<1100){x+=w+4;continue}el('rect',{x:x,y:400-h,width:w,height:h},c);
 for(var j=0;j<(h/16|0);j++)if(Math.random()<.45)el('rect',{x:x+R(4,w-8),y:400-h+6+j*14,width:3,height:4,fill:'#FFD08A',opacity:R(.4,.9)},c);x+=w+R(0,6)}
var L=$('dfaLights');for(var k=0;k<40;k++){var lx=k*42+10;el('circle',{cx:lx,cy:436,r:2.2,fill:'#FFD08A',class:k%2?'bl':''},L);el('circle',{cx:lx,cy:480,r:2.2,fill:'#7FD1FF',class:k%2?'':'bl'},L)}
var cl=$('dfaCeil');for(k=0;k<16;k++)el('rect',{x:30+k*98,y:12,width:48,height:5,rx:2,opacity:.85},cl);
var rf=$('dfaRefl');for(k=0;k<9;k++)el('rect',{x:80+k*180,y:540,width:60,height:120,fill:'#FFC47A',opacity:R(.08,.22)},rf);
/* travellers */
var P=$('dfaPpl'),col=['#05080F','#0B1220','#16223D','#2A1F1A','#102018','#05080F'],n=window.innerWidth<860?0:20;
for(i=0;i<n;i++){
 var lane=Math.random(),y=570+lane*190,s=.62+lane*.9,dir=Math.random()<.5?1:-1,dur=44-lane*22,delay=-Math.random()*dur,tint=Math.random()<.12?'#FF5A00':col[(Math.random()*col.length)|0];
 var g=el('g',{transform:'translate(0,'+y+')'},P),m=el('g',{class:'w',style:'animation-duration:'+dur+'s;animation-delay:'+delay+'s;'+(dir<0?'animation-direction:reverse':'')},g),f=el('g',{transform:'scale('+(dir<0?-s:s)+','+s+')'},m);
 el('ellipse',{cx:0,cy:1,rx:16,ry:3.2,fill:'#000',opacity:.45},f);
 el('rect',{x:-6.5,y:-44,width:5.5,height:44,rx:2.5,fill:'#05080F',class:'lg1'},f);el('rect',{x:1,y:-44,width:5.5,height:44,rx:2.5,fill:'#05080F',class:'lg2'},f);
 var b=el('g',{class:'bd'},f);el('path',{d:'M-10 -74Q0 -81 10 -74L12 -40L-12 -40Z',fill:tint,stroke:'rgba(255,190,130,.45)','stroke-width':1},b);el('circle',{cx:0,cy:-85,r:8.5,fill:'#05080F',stroke:'rgba(255,190,130,.45)','stroke-width':1},b);
 var r=Math.random();if(r<.45){el('rect',{x:-26,y:-34,width:15,height:34,rx:3,fill:'#1B2A4A',stroke:'rgba(255,190,130,.5)','stroke-width':1},f);el('line',{x1:-18,y1:-34,x2:-4,y2:-62,stroke:'#0B1220','stroke-width':1.6},f)}
 else if(r<.7)el('rect',{x:-16,y:-72,width:9,height:26,rx:3,fill:'#2A1F1A',stroke:'rgba(255,190,130,.4)','stroke-width':1},b);
}
/* offers board and shop highlight */
var rows=$('dfaRows');setInterval(function(){if(!rows||document.hidden)return;var f=rows.firstElementChild;if(f){rows.appendChild(f);f.style.animation='none';void f.offsetWidth;f.style.animation=''}},3200);
var it=[].slice.call(document.querySelectorAll('#dfaShop .dfa-p')),h=-1;setInterval(function(){if(document.hidden||!it.length)return;if(h>=0)it[h].classList.remove('hot');h=(h+1)%it.length;it[h].classList.add('hot')},2200);
var ck=$('dfaClock');function tick(){try{ck.textContent='DUBAI '+new Intl.DateTimeFormat('en-GB',{hour:'2-digit',minute:'2-digit',hour12:false,timeZone:'Asia/Dubai'}).format(new Date())}catch(e){ck.textContent='DUBAI'}}tick();setInterval(tick,20000);
})();
</script>
@endif
