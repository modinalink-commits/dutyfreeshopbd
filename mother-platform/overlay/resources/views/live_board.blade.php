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
.brand{font-weight:700;font-size:20px}.brand b{color:var(--accent)}
.tag{font-size:12px;color:var(--mute);border:1px solid var(--line);padding:2px 8px;border-radius:4px}
.tag.bad{color:#ff9d9d;border-color:var(--dead)}
.sum{display:flex;gap:8px;flex-wrap:wrap;margin-left:auto}
.pill{display:flex;align-items:center;gap:8px;padding:4px 11px;border:1px solid var(--line);border-radius:6px;background:var(--panel);font-weight:600}
.pill i{width:9px;height:9px;border-radius:50%}.pill.ok i{background:var(--ok)}.pill.slow i{background:var(--slow)}.pill.dead i{background:var(--dead)}.pill.na i{background:var(--na)}
.pill.dead.on{border-color:var(--dead);background:#2a1017}
.clock{font-family:var(--m);color:var(--mute)}
.tools{display:flex;gap:6px;flex-wrap:wrap}
button,.btn{font:inherit;font-size:13px;background:transparent;color:var(--fg);border:1px solid var(--line);border-radius:6px;padding:4px 10px;cursor:pointer}
button[aria-pressed="true"]{border-color:var(--accent);color:var(--accent)}
button:focus-visible,a:focus-visible{outline:2px solid var(--accent);outline-offset:2px}
.pipe{display:flex;align-items:stretch;margin-top:12px;overflow-x:auto;padding-bottom:2px}
.stg{flex:1 0 108px;background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:7px 10px;min-width:0}
.stg b{display:block;font-family:var(--m);font-size:20px;font-variant-numeric:tabular-nums}
.stg span{font-size:12.5px;color:var(--mute);white-space:nowrap}
.stg.ai{border-color:#3a4c8a}.stg.ai span{color:#b9c4ff}.stg.done{border-color:#1f6e45}.stg.done b{color:var(--ok)}.stg.warn b{color:var(--slow)}.stg.bad b{color:#ff8b8b}
.wire{flex:0 0 26px;position:relative;align-self:center;height:2px;background:var(--line)}
.wire::after{content:"";position:absolute;top:-2px;left:0;width:6px;height:6px;border-radius:50%;background:var(--accent);box-shadow:0 0 8px var(--accent);animation:run 1.6s linear infinite}
.wire.w2::after{animation-delay:.5s}.wire.w3::after{animation-delay:1s}
@keyframes run{0%{left:-3px;opacity:0}15%{opacity:1}85%{opacity:1}100%{left:23px;opacity:0}}
.gap{flex:0 0 14px}
.kp{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:8px;margin-top:10px}
.k{background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:8px 10px;min-width:0}
.k .kl{font-size:12.5px;color:var(--mute);display:flex;align-items:center;gap:6px}
.k .kl i{width:7px;height:7px;border-radius:50%;background:var(--ok);flex:none}
.k.slow{border-color:#6b4f12}.k.slow .kl i{background:var(--slow)}.k.dead{border-color:var(--dead)}.k.dead .kl i{background:var(--dead)}.k.na .kl i{background:var(--na)}
.k .kv{font-family:var(--m);font-size:20px;font-variant-numeric:tabular-nums;margin-top:2px}
.k .kv small{font-family:var(--f);font-size:12px;color:var(--mute);margin-left:5px}
.k .ks{font-size:12px;color:var(--mute);margin-top:1px;overflow-wrap:anywhere}
.main{display:grid;grid-template-columns:minmax(0,1fr) 310px;gap:16px;margin-top:14px;align-items:start}
.map{position:relative;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:40px 50px;grid-template-areas:"a b c" "d h e" "f g i"}
#cv{position:absolute;inset:0;width:100%;height:100%;pointer-events:none;z-index:0}
.g{position:relative;z-index:1;background:var(--panel);border:1px solid var(--line);border-top:2px solid hsl(var(--h) 80% 60%);border-radius:8px;padding:9px}
.g h2{margin:0 0 7px;font-size:13px;font-weight:600;display:flex;justify-content:space-between;gap:8px;color:hsl(var(--h) 85% 74%)}
.g h2 span:last-child{font-size:12px;white-space:nowrap}
.tiles{display:grid;grid-template-columns:repeat(auto-fill,minmax(78px,1fr));gap:6px}
.tile{position:relative;display:grid;justify-items:center;align-content:start;gap:1px;padding:8px 3px 6px;min-height:84px;border:1px solid var(--line);border-radius:8px;background:var(--tile);text-align:center;transition:opacity .2s}
.tile:hover{border-color:#3a4766}
.em{font-size:21px;line-height:1.1}.tn{font-size:11.5px;font-weight:600;line-height:1.2;overflow-wrap:anywhere}.ta{font-size:10.5px;color:var(--mute);font-family:var(--m)}
.dot{position:absolute;top:5px;right:5px;width:7px;height:7px;border-radius:50%;background:var(--ok)}
.tile.beat .dot{animation:ping .9s ease-out}
.tile.slow{border-color:#6b4f12}.tile.slow .dot{background:var(--slow)}.tile.slow .ta{color:var(--slow)}
.tile.dead{border-color:var(--dead);background:#2a1017;animation:glow 1.2s infinite}.tile.dead .dot{background:var(--dead)}.tile.dead .ta{color:#ff9a9a;font-weight:600}.tile.dead .em{filter:grayscale(1);opacity:.7}
.tile.na .dot{background:var(--na)}.tile.na .em{opacity:.55}
.map.only .tile:not(.slow):not(.dead){opacity:.16}
@keyframes ping{0%{box-shadow:0 0 0 0 rgba(47,210,124,.8)}100%{box-shadow:0 0 0 10px rgba(47,210,124,0)}}
@keyframes glow{0%,100%{box-shadow:0 0 0 0 rgba(255,77,77,0)}50%{box-shadow:0 0 14px 1px rgba(255,77,77,.55)}}
.hub{grid-area:h;position:relative;z-index:1;display:grid;align-content:center;justify-items:center;gap:6px;text-align:center;padding:14px 10px;border:1px solid #3a4c8a;border-radius:12px;background:radial-gradient(circle at 50% 38%,#1d2a52,#111a33 70%)}
.core{width:84px;height:84px;border-radius:50%;display:grid;place-items:center;border:2px solid #6f86ff;position:relative;font-family:var(--m);font-size:22px}
.core::before,.core::after{content:"";position:absolute;inset:-2px;border-radius:50%;border:2px solid #6f86ff;animation:ring 2.8s ease-out infinite}.core::after{animation-delay:1.4s}
@keyframes ring{0%{transform:scale(1);opacity:.7}100%{transform:scale(1.8);opacity:0}}
.hub b{font-size:16px}.hub small{color:var(--mute);font-size:12.5px}
.hub.bad{border-color:var(--dead)}.hub.bad .core,.hub.bad .core::before,.hub.bad .core::after{border-color:var(--dead)}
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
@media(prefers-reduced-motion:reduce){.tile.beat .dot,.tile.dead,.core::before,.core::after,.wire::after{animation:none}}
</style></head><body>
<div class="top">
  <a class="brand" href="/admin" title="অ্যাডমিনে ফিরুন">Duty<b>Free</b>BD <span style="font-weight:400;color:var(--mute)">লাইভ ড্যাশবোর্ড</span></a>
  <span class="tag" id="conn">সংযোগ হচ্ছে…</span>
  <div class="sum" id="sum"></div>
  <div class="tools"><button id="only" aria-pressed="false">শুধু সমস্যা</button><button id="snd" aria-pressed="false">অ্যালার্ট শব্দ</button><button id="fs">ফুলস্ক্রিন</button></div>
  <div class="clock" id="clock"></div>
</div>
<div class="pipe" id="pipe"></div>
<div class="kp" id="kp"></div>
<div class="main">
  <div class="map" id="map"><canvas id="cv"></canvas>
    <section class="hub" id="hub"><div class="core" id="score">…</div><b>মাদার কমার্স</b><small id="hubsub">তথ্য আসছে</small><small id="hubbeat"></small></section>
  </div>
  <aside class="rail">
    <section class="box att" id="attbox"><h2><span>এখন নজর দিন</span><span id="attn"></span></h2><div id="att"></div></section>
    <section class="box"><h2><span>স্টাফ · কে কী করছে</span><span id="sstat"></span></h2><div id="sg"></div></section>
    <section class="box"><h2>সর্বশেষ ঘটনা</h2><div id="feed"><div class="empty">বোর্ড খোলার পর থেকে যা বদলাবে এখানে আসবে।</div></div></section>
  </aside>
</div>
<script>
(function(){
const $=id=>document.getElementById(id);
const bn=n=>String(n).replace(/\d/g,d=>'০১২৩৪৫৬৭৮৯'[d]);
const num=n=>bn(Number(n).toLocaleString('en-IN'));
const esc=s=>String(s==null?'':s).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
const ago=s=>s==null?'':s<60?bn(s)+' সে.':s<3600?bn(Math.floor(s/60))+' মি.':s<86400?bn(Math.floor(s/3600))+' ঘ.':bn(Math.floor(s/86400))+' দিন';
const G={a:['AI কল ও কল সেন্টার',265,'PBX লাইন ও কল ব্যালেন্স দেখুন'],b:['মার্কেটিং ও অ্যাড',28,'Meta টোকেন ও Events Manager দেখুন'],c:['মেসেজ ও চ্যাট',150,'চ্যানেল সংযোগ ও ওয়েবহুক দেখুন'],d:['অর্ডার',205,'অর্ডার পেজ ও ওয়ার্কার দেখুন'],e:['কুরিয়ার ও ডেলিভারি',185,'কুরিয়ার API টোকেন ও ব্যালেন্স দেখুন'],f:['SMS, ইমেইল ও নোটিফিকেশন',340,'SMS ব্যালেন্স ও মেইল সার্ভার দেখুন'],g:['স্টক ও পারচেজ',95,'স্টক ও সাপ্লায়ার পেজ দেখুন'],i:['সার্ভার, হিসাব ও AI',50,'ডিপ্লয় পেজ ও এরর লগ দেখুন']};
const map=$('map'),E={},GE={},prev={};let D=null,got=0,built=false,first=true,fails=0,prevBeat=null;

function build(nodes){
  Object.keys(G).forEach(a=>{const s=document.createElement('section');s.className='g';s.style.gridArea=a;s.style.setProperty('--h',G[a][1]);
    s.innerHTML='<h2><span>'+G[a][0]+'</span><span class="gs"></span></h2><div class="tiles"></div>';map.appendChild(s);GE[a]={el:s,gs:s.querySelector('.gs'),tiles:s.querySelector('.tiles')}});
  nodes.forEach(n=>{if(!GE[n.g])return;const e=document.createElement('a');e.className='tile';e.href=n.h||'#';e.target='_blank';e.rel='noopener';
    e.innerHTML='<span class="dot"></span><span class="em">'+n.i+'</span><span class="tn">'+esc(n.n)+'</span><span class="ta"></span>';GE[n.g].tiles.appendChild(e);E[n.k]={e,ta:e.querySelector('.ta')}});
  built=true;layout();
}
function log(t,cls){const f=$('feed');const em=f.querySelector('.empty');if(em)em.remove();const d=new Date(),hh=bn(String(d.getHours()).padStart(2,'0')+':'+String(d.getMinutes()).padStart(2,'0'));
  f.insertAdjacentHTML('afterbegin','<div class="ev '+(cls||'')+'"><time>'+hh+'</time><span>'+esc(t)+'</span></div>');while(f.children.length>8)f.lastChild.remove()}
let ac=null;
function beep(){if(!ac)return;try{const o=ac.createOscillator(),g=ac.createGain();o.frequency.value=660;g.gain.setValueAtTime(.12,ac.currentTime);g.gain.exponentialRampToValueAtTime(.001,ac.currentTime+.5);o.connect(g).connect(ac.destination);o.start();o.stop(ac.currentTime+.5)}catch(e){}}
$('snd').onclick=e=>{if(ac){ac.close();ac=null}else{try{ac=new (window.AudioContext||window.webkitAudioContext)();beep()}catch(_){ac=null}}e.currentTarget.setAttribute('aria-pressed',ac?'true':'false')};
$('only').onclick=e=>{e.currentTarget.setAttribute('aria-pressed',map.classList.toggle('only'))};
$('fs').onclick=()=>{try{const p=document.fullscreenElement?document.exitFullscreen():document.documentElement.requestFullscreen();p&&p.catch&&p.catch(()=>{})}catch(e){}};

function apply(d){
  if(!built)build(d.nodes);
  const beatFresh=prevBeat!==null&&d.beat!==null&&d.beat<prevBeat;prevBeat=d.beat;
  d.nodes.forEach(n=>{const x=E[n.k];if(!x)return;const p=prev[n.k];
    let pulse=0;
    if(p){if(n.today!=null&&p.today!=null&&n.today>p.today)pulse=Math.min(5,n.today-p.today);else if(n.last!=null&&p.last!=null&&n.last+4<p.last&&!n.beat)pulse=1;
      if(n.beat&&beatFresh&&n.s==='ok')pulse=Math.max(pulse,1);
      if(p.s!==n.s){if(n.s==='dead'){log(n.n+' থেমে গেছে'+(n.t?' · '+n.t:''),'bad');beep()}else if(n.s==='slow')log(n.n+' ধীর বা সতর্কতা'+(n.t?' · '+n.t:''),'warn');else if(n.s==='ok'&&(p.s==='dead'||p.s==='slow'))log(n.n+' আবার ঠিক হয়েছে')}}
    if(pulse){x.e.classList.remove('beat');void x.e.offsetWidth;x.e.classList.add('beat');for(let i=0;i<pulse;i++)setTimeout(()=>spawn(n.g),i*220)}
    prev[n.k]={today:n.today,last:n.last,s:n.s};
    x.e.title=n.n+(n.t?' · '+n.t:'')+(n.today!=null?' · আজ '+n.today:'');
  });
  // pipeline
  let h='',flow=true,wi=0;d.pipe.forEach((p,i)=>{if(!p){h+='<div class="gap"></div>';flow=false;return}if(i&&d.pipe[i-1])h+=flow?'<div class="wire w'+(++wi%3+1)+'"></div>':'<div class="gap" style="flex-basis:6px"></div>';
    h+='<div class="stg '+p.c+'"><b>'+(p.n==null?'–':num(p.n))+'</b><span>'+p.l+'</span></div>'});$('pipe').innerHTML=h;
  $('kp').innerHTML=d.kpi.map(k=>'<div class="k '+(k.st==='ok'?'':k.st)+'"><div class="kl"><i></i>'+esc(k.l)+'</div><div class="kv">'+(k.v==null?'–':num(k.v))+'<small>'+esc(k.u)+'</small></div><div class="ks">'+esc(k.s)+'</div></div>').join('');
  // staff
  const st=d.staff||{rows:[]};let idle=0;
  $('sg').innerHTML=st.rows.length?st.rows.map(s=>{const off=s.state==='Offline',aw=s.state==='Away'||s.state==='On break';if(aw)idle++;
    const w=off?'অফলাইন':(s.state==='On break'?'বিরতিতে':(aw?'দূরে':'')+(s.page?(aw?' · ':'')+esc(s.page):''))+(s.work_min!=null?' · শেষ কাজ '+bn(s.work_min)+' মি. আগে':'');
    return '<div class="sc '+(off?'off':aw?'idle':'')+'"><i></i><div style="min-width:0"><b>'+esc(s.name)+'</b><div class="w">'+w+(s.waiting?' · অপেক্ষায় '+bn(s.waiting)+' চ্যাট':'')+'</div></div><div class="o">'+(off?'':bn(s.orders)+' অর্ডার<br>'+bn(s.calls)+' কল · '+bn(s.chats)+' চ্যাট')+'</div></div>'}).join(''):'<div class="empty">স্টাফের তথ্য পাওয়া যায়নি।</div>';
  $('sstat').textContent=(st.unassigned?'কারো নয় এমন চ্যাট '+bn(st.unassigned)+' · ':'')+(idle?bn(idle)+' জন দূরে':'');
  render();
}
function render(){
  if(!D)return;const el=Math.floor((Date.now()-got)/1000),c={ok:0,slow:0,dead:0,na:0},bad=[];
  D.nodes.forEach(n=>{const x=E[n.k];if(!x)return;c[n.s]++;if(n.s==='dead'||n.s==='slow')bad.push(n);
    x.e.className='tile '+(n.s==='ok'?'':n.s)+(x.e.classList.contains('beat')?' beat':'');
    const a=n.last==null?null:n.last+el;
    x.ta.textContent=n.s==='dead'?'থেমে আছে':n.s==='na'&&a==null?'সংকেত নেই':(a==null?'':ago(a)+' আগে')+(n.today!=null&&!n.beat?' · '+bn(n.today):n.today?' · '+bn(n.today):'');
  });
  Object.keys(GE).forEach(a=>{const ns=D.nodes.filter(n=>n.g===a),dd=ns.filter(n=>n.s==='dead').length,ss=ns.filter(n=>n.s==='slow').length;
    GE[a].gs.textContent=dd?bn(dd)+' টি থেমে আছে':ss?bn(ss)+' টি ধীর':'সব ঠিক';GE[a].gs.style.color=dd?'var(--dead)':ss?'var(--slow)':'var(--ok)';GE[a].st=dd?'dead':ss?'slow':'ok'});
  $('sum').innerHTML='<div class="pill ok"><i></i>চলছে '+bn(c.ok)+'</div><div class="pill slow"><i></i>ধীর '+bn(c.slow)+'</div><div class="pill dead '+(c.dead?'on':'')+'"><i></i>থেমে আছে '+bn(c.dead)+'</div><div class="pill na"><i></i>সংকেত নেই '+bn(c.na)+'</div>';
  const known=c.ok+c.slow+c.dead;$('score').textContent=known?bn(Math.round(c.ok/known*100))+'%':'–';
  $('hub').classList.toggle('bad',c.dead>0||fails>2);
  $('hubsub').textContent=c.dead?bn(c.dead)+' টি কাজ থেমে আছে':c.slow?bn(c.slow)+' টি কাজে সতর্কতা':'সব কাজ চালু';
  $('hubbeat').textContent=D.beat==null?'ওয়ার্কারের সংকেত নেই':'ওয়ার্কার সংকেত '+ago(D.beat+el)+' আগে'+(D.disk!=null?' · ডিস্ক খালি '+bn(D.disk)+'%':'');
  bad.sort((p,q)=>(q.s==='dead')-(p.s==='dead'));
  const iss=(D.issues||[]).map(i=>'<div class="al '+(i.severity==='critical'||i.severity==='high'?'dead':'slow')+'"><b><span>🚨 '+esc(i.title)+'</span><em>'+esc(i.service)+'</em></b><p>'+esc(i.action)+'</p></div>');
  const nb=bad.slice(0,8).map(n=>'<div class="al '+n.s+'"><b><span>'+n.i+' '+esc(n.n)+'</span><em>'+(n.s==='dead'?'থেমে আছে':'সতর্কতা')+'</em></b><p>'+esc(n.t||'')+(n.t?' · ':'')+G[n.g][2]+'</p></div>');
  const all=iss.concat(nb);$('attbox').classList.toggle('clear',!all.length);$('attn').textContent=all.length?bn(all.length)+' টি':'';
  $('att').innerHTML=all.length?all.join(''):'<div class="allok">সব ঠিক আছে। কোনো কাজ থেমে নেই।</div>';
}
function poll(){
  fetch('/admin/live-dashboard/status',{headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},credentials:'same-origin',cache:'no-store'})
   .then(r=>{if(r.status===401||r.status===419||r.redirected)throw new Error('login');if(!r.ok)throw new Error('http '+r.status);return r.json()})
   .then(d=>{D=d;got=Date.now();if(fails>2)log('সার্ভারের সঙ্গে সংযোগ ফিরে এসেছে');fails=0;$('conn').textContent='লাইভ · প্রতি ৫ সেকেন্ডে';$('conn').className='tag';apply(d);first=false})
   .catch(e=>{fails++;const m=e.message==='login'?'লগইন শেষ হয়েছে, আবার লগইন করুন':'সার্ভার থেকে তথ্য আসছে না';$('conn').textContent=m;$('conn').className='tag bad';if(fails===3){log(m,'bad');beep()}render()});
}
setInterval(poll,5000);poll();
setInterval(()=>{render();$('clock').textContent=bn(new Date().toLocaleTimeString('en-GB'))},1000);

// wires + travelling dots (one dot = one real event seen since the last refresh)
const cv=$('cv'),cx=cv.getContext('2d');let P={},W=0,H=0;
function rel(el){const r=el.getBoundingClientRect(),m=map.getBoundingClientRect();return{left:r.left-m.left,right:r.right-m.left,top:r.top-m.top,bottom:r.bottom-m.top,w:r.width,h:r.height}}
function layout(){
  const m=map.getBoundingClientRect(),dpr=window.devicePixelRatio||1;W=m.width;H=m.height;cv.width=W*dpr;cv.height=H*dpr;cx.setTransform(dpr,0,0,dpr,0,0);
  if(!built||getComputedStyle(cv).display==='none'){P={};return}
  const hub=rel($('hub')),hy=hub.top+hub.h/2,hx=hub.left+hub.w/2,R={};Object.keys(GE).forEach(a=>R[a]=rel(GE[a].el));
  const g1=(R.d.right+hub.left)/2,g2=(hub.right+R.e.left)/2,mid=r=>r.top+r.h/2;
  P={d:[[R.d.right,hy],[hub.left,hy]],e:[[R.e.left,hy],[hub.right,hy]],b:[[hx,R.b.bottom],[hx,hub.top]],g:[[hx,R.g.top],[hx,hub.bottom]],
    a:[[R.a.right,mid(R.a)],[g1-9,mid(R.a)],[g1-9,hy-20],[hub.left,hy-20]],f:[[R.f.right,mid(R.f)],[g1+9,mid(R.f)],[g1+9,hy+20],[hub.left,hy+20]],
    c:[[R.c.left,mid(R.c)],[g2+9,mid(R.c)],[g2+9,hy-20],[hub.right,hy-20]],i:[[R.i.left,mid(R.i)],[g2-9,mid(R.i)],[g2-9,hy+20],[hub.right,hy+20]]};
  Object.values(P).forEach(p=>{let L=0;p.cum=[0];for(let k=1;k<p.length;k++){L+=Math.hypot(p[k][0]-p[k-1][0],p[k][1]-p[k-1][1]);p.cum.push(L)}p.len=L});
}
function at(p,t){const d=t*p.len;let k=1;while(k<p.length-1&&p.cum[k]<d)k++;const f=(d-p.cum[k-1])/((p.cum[k]-p.cum[k-1])||1);return[p[k-1][0]+(p[k][0]-p[k-1][0])*f,p[k-1][1]+(p[k][1]-p[k-1][1])*f]}
addEventListener('resize',layout);setInterval(layout,1500);
const still=matchMedia('(prefers-reduced-motion:reduce)').matches,parts=[];
function spawn(a){if(parts.length<120)parts.push({a,t:0,s:.28+Math.random()*.22,dir:Math.random()<.3?-1:1})}
let last=performance.now();
function draw(now){
  const dt=Math.min(.05,(now-last)/1000);last=now;cx.clearRect(0,0,W,H);
  Object.keys(P).forEach(a=>{const p=P[a],s=(GE[a]||{}).st,h=G[a][1];cx.save();cx.lineWidth=2;cx.lineJoin='round';
    cx.strokeStyle=s==='dead'?'rgba(255,77,77,.6)':s==='slow'?'rgba(242,176,48,.5)':'hsla('+h+',80%,62%,.38)';cx.setLineDash(s==='dead'?[6,6]:[]);
    cx.beginPath();p.forEach((q,k)=>k?cx.lineTo(q[0],q[1]):cx.moveTo(q[0],q[1]));cx.stroke();cx.setLineDash([]);cx.fillStyle=cx.strokeStyle;
    const e=p[p.length-1];cx.beginPath();cx.arc(e[0],e[1],4,0,7);cx.fill();cx.beginPath();cx.arc(p[0][0],p[0][1],3,0,7);cx.fill();cx.restore()});
  if(!still)for(let k=parts.length-1;k>=0;k--){const q=parts[k];q.t+=q.s*dt;if(q.t>=1){parts.splice(k,1);continue}const p=P[q.a];if(!p)continue;
    const xy=at(p,q.dir>0?q.t:1-q.t),h=G[q.a][1];cx.save();cx.shadowBlur=12;cx.shadowColor='hsl('+h+' 90% 65%)';cx.fillStyle='hsl('+h+' 95% 80%)';cx.beginPath();cx.arc(xy[0],xy[1],3.2,0,7);cx.fill();cx.restore()}
  requestAnimationFrame(draw);
}
requestAnimationFrame(draw);document.fonts&&document.fonts.ready.then(layout);
})();
</script>
</body></html>
