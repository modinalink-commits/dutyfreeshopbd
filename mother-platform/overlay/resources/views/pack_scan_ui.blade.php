{{-- Packaging Scan popup (owner rule 14 Nov 2026). Included on the Orders page and on /admin/packing-scan. The scanner types the label number and presses Enter. --}}
<style>
#psOv{position:fixed;inset:0;background:rgba(11,27,51,.55);z-index:9990;display:none;align-items:flex-start;justify-content:center;overflow:auto;padding:14px 12px}
#psOv.on{display:flex}
#psOv .ps{width:min(1040px,100%);background:#fff;border:1px solid #CBD5E1;border-radius:12px;overflow:hidden;color:#0B1B33;font-size:14px}
#psOv .ps-h{display:flex;flex-wrap:wrap;gap:10px;align-items:center;padding:10px 14px;background:#EEF1F5;border-bottom:1px solid #D9DEE6}
#psOv .ps-h b{font-size:16px}
#psOv .ps-k{margin-left:auto;display:flex;gap:8px;flex-wrap:wrap}
#psOv .ps-k span{border:1px solid #D9DEE6;background:#fff;border-radius:8px;padding:4px 10px;font-size:12px;color:#5B6676;font-variant-numeric:tabular-nums}
#psOv .ps-k span b{font-size:15px;color:#0B1B33;margin-left:4px}
#psOv #psX{width:36px;height:36px;border-radius:9px;border:1.5px solid #0B1B33;background:#DC2626;color:#fff;font-size:20px;font-weight:800;line-height:1;cursor:pointer;padding:0}
#psOv #psX:hover{background:#991B1B}
#psOv .ps-b{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(0,1fr);gap:14px;padding:14px}
@media(max-width:820px){#psOv .ps-b{grid-template-columns:minmax(0,1fr)}}
#psOv .ps-f{display:flex;gap:8px}
#psOv #psIn{flex:1;min-width:0;height:50px;border:2px solid #0B1B33;border-radius:10px;padding:0 14px;font:700 20px Inter,sans-serif;letter-spacing:.5px;color:#0B1B33;background:#fff;width:auto;margin:0}
#psOv #psIn:focus{outline:3px solid rgba(255,90,0,.35);border-color:#FF5A00}
#psOv #psGo{height:50px;padding:0 18px;border-radius:10px;border:1.5px solid #0B1B33;background:#FF5A00;color:#fff;font-weight:800;cursor:pointer;width:auto;margin:0}
#psOv .ps-hint{font-size:12px;color:#5B6676;margin:6px 2px 10px}
#psOv #psRes{border-radius:12px;padding:16px;min-height:220px;display:flex;flex-direction:column;gap:8px;border:2px dashed #D9DEE6;background:#FAFBFC}
#psOv #psRes.ok{border:2px solid #15803D;background:#DCFCE7}
#psOv #psRes.bad{border:2px solid #B91C1C;background:#FEE2E2}
#psOv #psRes.warn{border:2px solid #B45309;background:#FEF3C7}
#psOv .ps-big{font-size:28px;font-weight:800;line-height:1.15;color:#5B6676}
#psOv .ok .ps-big{color:#15803D}#psOv .bad .ps-big{color:#B91C1C}#psOv .warn .ps-big{color:#B45309}
#psOv .ps-sub{font-size:14.5px;font-weight:600}
#psOv .ps-o{margin-top:6px;background:#fff;border:1px solid #D9DEE6;border-radius:10px;padding:10px 12px;display:grid;grid-template-columns:auto minmax(0,1fr);gap:4px 12px;font-size:13px}
#psOv .ps-o span{color:#5B6676}#psOv .ps-o b{font-weight:600;overflow-wrap:anywhere}
#psOv .ps-t{font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:#5B6676;font-weight:700;margin:0 0 6px}
#psOv .ps-w{overflow:auto;max-height:420px;border:1px solid #D9DEE6;border-radius:10px}
#psOv table{border-collapse:collapse;width:100%;font-size:12.5px;font-variant-numeric:tabular-nums;margin:0}
#psOv th,#psOv td{text-align:left;padding:6px 9px;border-bottom:1px solid #E5E7EB;white-space:nowrap}
#psOv th{background:#EEF1F5;font-weight:700;position:sticky;top:0}
#psOv .pp{display:inline-block;border-radius:999px;padding:1px 9px;font-weight:700;font-size:11.5px}
#psOv .pp.ok{background:#DCFCE7;color:#15803D}#psOv .pp.bad{background:#FEE2E2;color:#B91C1C}#psOv .pp.warn{background:#FEF3C7;color:#B45309}
#psOv #psMn{width:36px;height:36px;border-radius:9px;border:1.5px solid #FF5A00;background:#0B1B33;color:#fff;font-size:18px;font-weight:800;line-height:1;cursor:pointer;padding:0}
#psOv #psMn:hover{background:#FF5A00;border-color:#0B1B33}
#psOv #psMl{display:none;flex-basis:100%;font-size:13px;font-weight:700;border-radius:8px;padding:6px 10px;background:#F3F4F6;color:#0B1B33;overflow-wrap:anywhere}
#psOv #psMl.ok{background:#DCFCE7;color:#15803D}#psOv #psMl.bad{background:#FEE2E2;color:#B91C1C}#psOv #psMl.warn{background:#FEF3C7;color:#B45309}
#psOv.min{inset:auto 14px 14px auto;background:none;padding:0;overflow:visible;display:block}
#psOv.min .ps{width:min(520px,calc(100vw - 28px));border:1.5px solid #0B1B33;box-shadow:0 6px 24px rgba(11,27,51,.28)}
#psOv.min .ps-b,#psOv.min .ps-h>span{display:none}#psOv.min #psMl{display:block}
#psOv tr.ps-pd td{background:#FFFBEB}
#psQ{margin:8px 0 0;padding:6px 10px;border:1px solid #D7DCE3;border-radius:8px;background:#F3F4F6;color:#0B1B33;font-size:12.5px;font-weight:700}
#psP{margin-top:10px;border:1.5px solid #B91C1C;border-radius:10px;background:#FEF2F2;padding:8px 10px}
#psP .ps-pt{font-weight:800;color:#B91C1C;font-size:13px;margin-bottom:4px}
#psP .ps-pi{font-size:12.5px;color:#0B1B33;padding:3px 0;border-top:1px solid #FECACA}#psP .ps-pi:first-child{border-top:0}
html body button.ps-open,html body .toolbar-control.ps-open,#ocb#ocb#ocb .r1 .owt .obk button.ps-open{background:#0B1B33!important;background-image:none!important;color:#fff!important;border:1.5px solid #FF5A00!important;font-weight:700!important}
html body button.ps-open:hover,html body .toolbar-control.ps-open:hover,#ocb#ocb#ocb .r1 .owt .obk button.ps-open:hover{background:#FF5A00!important;border-color:#0B1B33!important}
</style>
<div id="psOv" role="dialog" aria-modal="true" aria-label="Packaging Scan" data-keep><div class="ps">
<div class="ps-h"><b>Packaging Scan</b><span style="font-size:12.5px;color:#5B6676">প্রতিটা পার্সেলের লেবেল স্ক্যান করুন, তারপর পার্সেল কুরিয়ারের ব্যাগে দিন।</span>
<div class="ps-k"><span>আজ দেওয়া হয়েছে<b id="psOk">0</b></span><span>থামানো<b id="psBad" style="color:#B91C1C">0</b></span><span>Packaging-এ বাকি<b id="psW">0</b></span></div>
<button type="button" id="psMn" aria-label="Minimize" title="ছোট করুন: এই পেজে স্ক্যান চলতে থাকবে">–</button><button type="button" id="psX" aria-label="Close" title="Close">×</button><div id="psMl">ছোট করা আছে। স্ক্যান করতে থাকুন, প্রতিটা লেবেল চেক হচ্ছে।</div></div>
<div class="ps-b"><div>
<form class="ps-f" id="psF" autocomplete="off"><input id="psIn" inputmode="numeric" autocomplete="off" placeholder="পার্সেলের লেবেল স্ক্যান করুন" aria-label="Parcel label barcode"><button type="submit" id="psGo">Check</button></form>
<div class="ps-hint">স্ক্যানার নম্বর লিখে নিজে Enter দেয়। হাতে ইনভয়েস নম্বর লিখেও Check চাপা যায়।</div>
<div id="psQ" hidden></div>
<div id="psRes"><div class="ps-big">স্ক্যানের জন্য প্রস্তুত</div><div class="ps-sub" style="color:#5B6676">সবুজ এলে পার্সেল কুরিয়ারকে দিন। লাল বা হলুদ এলে পার্সেল রেখে দিন।</div></div>
<div id="psP" hidden><div class="ps-pt">এই পার্সেলগুলো ব্যাগ থেকে বের করে রাখুন</div><div id="psPl"></div></div>
</div><div>
<div class="ps-t">আজকের স্ক্যান</div>
<div class="ps-w no-export" data-no-export data-nogrid data-nofit data-nopg data-nodrag><table class="no-export" data-nogrid data-nofit data-nopg><thead><tr><th>সময়</th><th>ইনভয়েস</th><th>ফল</th><th>স্টাফ</th></tr></thead><tbody id="psLog"><tr><td colspan="4" style="color:#5B6676;padding:12px">এখনো কিছু স্ক্যান হয়নি।</td></tr></tbody></table></div>
</div></div></div></div>
<script>
(function(){
 var ov=document.getElementById('psOv'),inp=document.getElementById('psIn'),res=document.getElementById('psRes'),changed=false,ac=null;
 function tk(){var e=document.querySelector('meta[name=csrf-token]');return e?e.content:''}
 function esc(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
 function tone(f,dur,vol,type,at){try{ac=ac||new (window.AudioContext||window.webkitAudioContext)();if(ac.state==='suspended')ac.resume();var o=ac.createOscillator(),g=ac.createGain(),t=ac.currentTime+(at||0);o.connect(g);g.connect(ac.destination);o.type=type||'sine';o.frequency.value=f;g.gain.setValueAtTime(vol,t);g.gain.setValueAtTime(vol,t+dur-.02);g.gain.linearRampToValueAtTime(0,t+dur);o.start(t);o.stop(t+dur)}catch(e){}}
 // three sounds: a short tick the moment a label is read, two rising notes when the parcel may go, a long low buzz when it must stay
 function beep(good){if(good==='scan'){tone(1900,.07,.35,'square');return}if(good){tone(1050,.12,.4,'sine');tone(1500,.18,.4,'sine',.13)}else{tone(220,.3,.5,'square');tone(180,.55,.5,'square',.34)}}
 var rowsH='',pend={};
 function draw(){var b=document.getElementById('psLog')||ov.querySelector('.ps-w tbody');if(!b)return;var p=Object.keys(pend).reverse().map(function(c){return '<tr class="ps-pd"><td>now</td><td><b>'+esc(c)+'</b></td><td><span class="pp warn">চেক হচ্ছে…</span></td><td></td></tr>'}).join('');b.innerHTML=(p+rowsH)||'<tr><td colspan="4" style="color:#5B6676;padding:12px">এখনো কিছু স্ক্যান হয়নি।</td></tr>'}
 function paint(j){
  if(j.rows){try{document.getElementById('psOk').textContent=j.ok||0;document.getElementById('psBad').textContent=j.stopped||0;document.getElementById('psW').textContent=j.waiting||0}catch(e){}}
  if(j.rows)try{rowsH=j.rows.length?j.rows.map(function(r){var L={ok:'দেওয়া হয়েছে',bad:'থামানো',warn:'পাঠানো হয়নি',run:'কুরিয়ারে যাচ্ছে…'}[r.res]||r.res;return '<tr title="'+esc(r.msg)+'"><td>'+esc(r.t)+'</td><td><b>'+esc(r.inv)+'</b></td><td><span class="pp '+esc(r.res==='run'?'warn':r.res)+'">'+L+'</span></td><td>'+esc(r.by)+'</td></tr>'}).join(''):'';draw();
   }catch(e){}
  if(!j.result)return;
  try{var o=j.order,card=o?'<div class="ps-o"><span>ইনভয়েস</span><b>'+esc(o.invoice)+'</b><span>গ্রাহক</span><b>'+esc(o.name)+(o.phone?' · '+esc(o.phone):'')+'</b><span>পণ্য</span><b>'+esc(o.items)+'</b><span>মোট</span><b>৳'+esc(o.total)+'</b><span>অবস্থা</span><b>'+esc(o.status)+(o.tracking?' · '+esc(o.courier)+' '+esc(o.tracking):'')+'</b></div>':'';
  res.className=j.result;res.innerHTML='<div class="ps-big">'+(j.result==='ok'?'✓ ':j.result==='bad'?'✖ ':'⚠ ')+esc(j.title)+'</div><div class="ps-sub">'+esc(j.message)+'</div>'+card;
  beep(j.result==='ok');if(j.result==='ok')changed=true;
  var ml=document.getElementById('psMl');ml.className=j.result;ml.textContent=(j.result==='ok'?'✓ ':j.result==='bad'?'✖ ':'⚠ ')+(o?o.invoice+' · ':'')+j.title+(j.result==='ok'?'':' · পার্সেল রেখে দিন')}catch(e){try{res.className=j.result;res.textContent=j.title+': '+j.message}catch(e2){}}}
 var q=[],run=0,last={},MAXRUN=2;
 var t0s={},tick=null;
 function qshow(){var n=q.length+run,e=document.getElementById('psQ');e.hidden=n<1;var ks=Object.keys(pend),now=Date.now();e.textContent=n>0?('চেক হচ্ছে: '+ks.map(function(c){return c+' ('+Math.round((now-(t0s[c]||now))/1000)+'s)'}).join(', ')+' … স্ক্যান করতে থাকুন'):'';if(n>0&&!tick)tick=setInterval(qshow,1000);if(n<1&&tick){clearInterval(tick);tick=null}}
 function prob(j,code){if(j.result==='ok')return;var o=j.order,e=document.getElementById('psPl'),d=document.createElement('div');d.className='ps-pi';d.innerHTML='<b>'+esc(o?o.invoice:code)+'</b>'+(o&&o.name?' · '+esc(o.name):'')+' · '+esc(j.title);d.title=j.message||'';e.insertBefore(d,e.firstChild);document.getElementById('psP').hidden=false}
 function done(code,j){run--;delete pend[code];paint(j);if(!j.rows)draw();prob(j,code);qshow();pump()}
 function pump(){while(run<MAXRUN&&q.length){(function(code){run++;qshow();var ab=window.AbortController?new AbortController():null,tm=setTimeout(function(){if(ab)ab.abort()},60000);
   fetch('/admin/packing-scan/scan',{method:'POST',credentials:'same-origin',signal:ab?ab.signal:undefined,headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':tk()},body:JSON.stringify({code:code})})
    .then(function(r){if(r.status===401||r.status===419){var x=new Error('login');x.login=1;throw x}if(r.status===423){var y=new Error('আপনার নামে একটা নোটিশ আছে, আগে কারণ জমা দিন');throw y}if(!r.ok&&r.status!==403)throw new Error('server error '+r.status);return r.json().then(function(j){j.__st=r.status;return j})}).then(function(j){if(j&&!j.result&&j.__st===403){clearTimeout(tm);done(code,{result:'bad',title:'অনুমতি নেই',message:'আপনার আইডিতে Packaging Scan-এর অনুমতি নেই, তাই '+code+' চেক হয়নি। অ্যাডমিনকে বলুন। পার্সেলটা রেখে দিন।'});return}if(!j||!j.result)throw new Error('unexpected answer');clearTimeout(tm);done(code,j)})
    .catch(function(e){clearTimeout(tm);delete last[code];if(e&&e.login){done(code,{result:'bad',title:'লগইন নেই',message:'এই পেজে লগইন নেই বা পেজটা পুরনো, তাই '+code+' চেক হয়নি। Ctrl+F5 চাপুন, আবার লগইন করুন, তারপর পার্সেলটা আবার স্ক্যান করুন। পার্সেলটা রেখে দিন।'});return}done(code,{result:'bad',title:'উত্তর আসেনি',message:code+' নম্বরের উত্তর আসেনি ('+(e&&e.name==='AbortError'?'৬০ সেকেন্ড অপেক্ষার পর':String(e&&e.message||e).slice(0,80))+')। পার্সেলটা রেখে দিন। ইন্টারনেট দেখুন, তারপর অর্ডারটা খুলুন: Shipment-এ থাকলে চলে গেছে, না থাকলে আবার স্ক্যান করুন।'})})})(q.shift())}}
 // scans never wait for the courier: each one is queued and checked in the background, the box is free at once
 function send(code){if(!code)return;var t=Date.now();if(last[code]&&t-last[code]<4000)return;last[code]=t;q.push(code);pend[code]=1;t0s[code]=Date.now();draw();beep('scan');
  if(run<1){res.className='';res.innerHTML='<div class="ps-big">চেক হচ্ছে '+esc(code)+'…</div><div class="ps-sub" style="color:#5B6676">পার্সেলটা কুরিয়ারে পাঠানো হচ্ছে। ফল এখানে আর পাশের লিস্টে দেখাবে।</div>'}pump();qshow()}
 document.getElementById('psF').addEventListener('submit',function(e){e.preventDefault();var v=inp.value.trim();inp.value='';send(v)});
 function open(){ov.classList.add('on');try{ac=ac||new (window.AudioContext||window.webkitAudioContext)();if(ac.state==='suspended')ac.resume()}catch(e){}setMin(false);document.getElementById('psPl').innerHTML='';document.getElementById('psP').hidden=true;res.className='';res.innerHTML='<div class="ps-big">স্ক্যানের জন্য প্রস্তুত</div><div class="ps-sub" style="color:#5B6676">সবুজ এলে পার্সেল কুরিয়ারকে দিন। লাল বা হলুদ এলে পার্সেল রেখে দিন।</div>';
  fetch('/admin/packing-scan/today',{credentials:'same-origin',headers:{'Accept':'application/json'}}).then(function(r){if(r.status===401||r.status===419){res.className='bad';res.innerHTML='<div class="ps-big">✖ লগইন নেই</div><div class="ps-sub">এই পেজে লগইন নেই বা পেজটা পুরনো। স্ক্যানের আগে Ctrl+F5 চেপে আবার লগইন করুন।</div>';throw 0}return r.json()}).then(paint).catch(function(){});setTimeout(function(){inp.focus()},60)}
 function close(){if(q.length+run>0){var e=document.getElementById('psQ');e.hidden=false;e.textContent='একটু অপেক্ষা করুন: '+(q.length+run)+'টা পার্সেল এখনো চেক হচ্ছে।';return}ov.classList.remove('on');ov.classList.remove('min');if(window.__psPage){location.href='/admin/orders?status=Shipment';return}if(changed){changed=false;location.reload()}}
 document.getElementById('psX').addEventListener('click',close);
 var mn=document.getElementById('psMn');
 function setMin(m){ov.classList.toggle('min',m);mn.textContent=m?'▢':'–';mn.title=m?'স্ক্যান জানালা খুলুন':'ছোট করুন: এই পেজে স্ক্যান চলতে থাকবে';if(!m)setTimeout(function(){inp.focus()},60)}
 mn.addEventListener('click',function(){setMin(!ov.classList.contains('min'))});
 // minimized: the scanner still works anywhere on this page, as long as the cursor is not inside another field
 var kb='',kt=0;
 document.addEventListener('keydown',function(e){if(!ov.classList.contains('on')||!ov.classList.contains('min')||e.ctrlKey||e.altKey||e.metaKey)return;
  var a=document.activeElement,tg=a&&a.tagName;if(tg==='INPUT'||tg==='TEXTAREA'||tg==='SELECT'||(a&&a.isContentEditable))return;
  var t=Date.now();if(t-kt>1200)kb='';kt=t;
  if(e.key==='Enter'){if(kb.length>=3){e.preventDefault();e.stopPropagation();send(kb.trim())}kb='';return}
  if(e.key&&e.key.length===1){kb+=e.key;if(kb.length>1){e.preventDefault();e.stopPropagation()}}},true);
 document.addEventListener('click',function(e){var b=e.target.closest&&e.target.closest('.ps-open');if(b){e.preventDefault();open()}});
 // keep the scan box ready: any click inside the window puts the cursor back
 ov.addEventListener('click',function(e){if(ov.classList.contains('min'))return;if(e.target!==inp&&!(e.target.closest&&e.target.closest('button')))inp.focus()});
 window.dfPackScan=open;
})();
</script>
