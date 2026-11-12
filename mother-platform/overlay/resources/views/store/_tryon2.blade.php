{{-- tryon v3 marker --}}<!--tryon-v3-->
@php $__to = \App\Support\TryOn::ready((int)$p->id); @endphp
@if($__to)
<button type="button" id="tryBtn" style="display:inline-flex;align-items:center;gap:6px;margin:2px 0 10px;padding:8px 14px;border:1px solid #111827;border-radius:10px;background:#fff;color:#111827;font-weight:700;font-size:13px;cursor:pointer"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M8 11h8M9 15c1.5 1 4.5 1 6 0"/></svg>Try On · নিজের মুখে দেখুন</button>
<div id="tryM" hidden style="position:fixed;inset:0;z-index:99999;background:rgba(17,24,39,.6);display:none;align-items:center;justify-content:center;padding:12px">
<div style="background:#fff;border-radius:14px;max-width:560px;width:100%;max-height:94vh;overflow:auto;padding:14px;box-shadow:0 10px 40px rgba(0,0,0,.3)">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px"><b style="font-size:15px">Virtual Try-On</b><button type="button" id="tryX" aria-label="Close" style="border:0;background:none;font-size:22px;line-height:1;cursor:pointer">×</button></div>
<div id="tryPick" style="display:flex;gap:8px;flex-wrap:wrap"><button type="button" id="tryCam" style="flex:1;min-width:150px;padding:12px;border:1px solid #FF5A00;background:#FF5A00;color:#fff;border-radius:10px;font-weight:700;cursor:pointer">📷 ক্যামেরা খুলুন</button><label style="flex:1;min-width:150px;padding:12px;border:1px solid #D1D5DB;border-radius:10px;font-weight:700;text-align:center;cursor:pointer">🖼 ছবি আপলোড<input type="file" id="tryFile" accept="image/jpeg,image/png,image/webp" hidden></label></div>
<p style="font-size:12px;color:#6B7280;margin:8px 0 0">আপনার ছবি আপনার ব্রাউজারেই থাকে। আমরা ছবি এডিট বা বিউটি ফিল্টার করি না, শুধু সানগ্লাস আলাদা লেয়ারে বসাই। আপনার সম্মতি ছাড়া কিছুই সেভ হয় না।</p>
<div id="tryVid" hidden style="margin-top:8px"><video id="tryV" playsinline muted style="width:100%;border-radius:10px;background:#000"></video><button type="button" id="tryShot" style="width:100%;margin-top:6px;padding:11px;border:0;background:#FF5A00;color:#fff;border-radius:10px;font-weight:700;cursor:pointer">ছবি তুলুন</button></div>
<div id="tryStage" hidden style="margin-top:8px"><div id="tryWrap" style="position:relative;line-height:0;touch-action:none"><canvas id="tryBase" style="width:100%;border-radius:10px"></canvas><canvas id="tryOv" style="position:absolute;left:0;top:0;width:100%;height:100%;border-radius:10px;cursor:grab"></canvas></div>
<div id="tryMsg" style="font-size:13px;margin:6px 0;color:#B45309"></div>
<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;font-size:12px"><button type="button" id="tryTog" style="padding:6px 10px;border:1px solid #D1D5DB;border-radius:8px;background:#fff;cursor:pointer">সানগ্লাস খুলুন</button><label style="display:flex;align-items:center;gap:6px">সাইজ <input type="range" id="trySz" min="70" max="140" value="100"></label><button type="button" id="tryAgain" style="padding:6px 10px;border:1px solid #D1D5DB;border-radius:8px;background:#fff;cursor:pointer">অন্য ছবি</button><span style="color:#6B7280">টেনে সরানো যাবে</span></div>
<label style="display:flex;gap:8px;align-items:flex-start;margin-top:10px;font-size:12px;color:#374151"><input type="checkbox" id="tryOk" style="margin-top:2px"><span>আমি সম্মতি দিচ্ছি যে DutyFree BD আমার এই ছবিটি সংরক্ষণ করবে (সর্বোচ্চ {{ \App\Support\TryOn::days() }} দিন, শুধু অ্যাডমিন দেখতে পাবে, বিক্রি/শেয়ার/বিজ্ঞাপন/AI ট্রেনিংয়ে ব্যবহার হবে না)। টিক না দিলে ছবি সেভ হবে না।</span></label>
<div style="display:flex;gap:8px;margin-top:6px;flex-wrap:wrap"><button type="button" id="trySave" disabled style="padding:8px 12px;border:1px solid #111827;border-radius:8px;background:#fff;font-weight:700;opacity:.5;cursor:pointer">ছবি সেভ করুন</button><button type="button" id="tryDel" hidden style="padding:8px 12px;border:1px solid #B91C1C;color:#B91C1C;border-radius:8px;background:#fff;font-weight:700;cursor:pointer">আমার সেভ করা ছবি মুছুন</button></div><div id="trySt" style="font-size:12px;margin-top:6px;color:#065F46"></div></div>
</div></div>
<script>
(function(){
var ASSET={!! json_encode($__to->png) !!}, PID={{ (int)$p->id }}, $=function(i){return document.getElementById(i)};
var M=$('tryM'),orig=null,img=null,face=null,show=true,adj={dx:0,dy:0,s:1},stream=null,loading=null,KEY='dfbd_tryon_tok';
function open(){M.hidden=false;M.style.display='flex';document.body.style.overflow='hidden'}
function close(){M.hidden=true;M.style.display='none';document.body.style.overflow='';stopCam()}
function stopCam(){if(stream){stream.getTracks().forEach(function(t){t.stop()});stream=null}$('tryVid').hidden=true}
function lib(){if(loading)return loading;loading=new Promise(function(ok,no){var s=document.createElement('script');s.src='/vendor/tryon/face-api.js';s.onload=function(){Promise.all([faceapi.nets.tinyFaceDetector.loadFromUri('/vendor/tryon/models'),faceapi.nets.faceLandmark68Net.loadFromUri('/vendor/tryon/models')]).then(ok,no)};s.onerror=no;document.head.appendChild(s)});return loading}
function asset(){return new Promise(function(ok,no){if(img)return ok();var i=new Image();i.onload=function(){img=i;ok()};i.onerror=no;i.src=ASSET})}
$('tryBtn').onclick=function(){open();try{if(localStorage.getItem(KEY))$('tryDel').hidden=false}catch(e){}};
$('tryX').onclick=close;M.addEventListener('click',function(e){if(e.target===M)close()});
$('tryCam').onclick=function(){if(!navigator.mediaDevices||!navigator.mediaDevices.getUserMedia){$('tryMsg').textContent='ক্যামেরা পাওয়া যায়নি, ছবি আপলোড করুন।';return}
 navigator.mediaDevices.getUserMedia({video:{facingMode:'user',width:{ideal:1280}},audio:false}).then(function(s){stream=s;var v=$('tryV');v.srcObject=s;v.play();$('tryVid').hidden=false}).catch(function(){alert('ক্যামেরার অনুমতি পাওয়া যায়নি। ছবি আপলোড করুন।')})};
$('tryShot').onclick=function(){var v=$('tryV'),c=document.createElement('canvas');c.width=v.videoWidth;c.height=v.videoHeight;c.getContext('2d').drawImage(v,0,0);stopCam();c.toBlob(function(b){useBlob(b)},'image/jpeg',0.95)};
$('tryFile').onchange=function(){if(this.files[0])useBlob(this.files[0])};
$('tryAgain').onclick=function(){$('tryStage').hidden=true;$('tryPick').style.display='flex';orig=null;face=null};
function useBlob(b){orig=b;$('tryPick').style.display='none';$('tryStage').hidden=false;$('tryMsg').textContent='মুখ খোঁজা হচ্ছে…';$('trySt').textContent='';adj={dx:0,dy:0,s:1};$('trySz').value=100;
 (window.createImageBitmap?createImageBitmap(b,{imageOrientation:'from-image'}):Promise.reject()).catch(function(){return new Promise(function(ok){var i=new Image();i.onload=function(){ok(i)};i.src=URL.createObjectURL(b)})}).then(function(bm){
  var W=bm.width||bm.naturalWidth,H=bm.height||bm.naturalHeight,sc=Math.min(1,960/Math.max(W,H)),cw=Math.round(W*sc),ch=Math.round(H*sc),base=$('tryBase');base.width=cw;base.height=ch;base.getContext('2d').drawImage(bm,0,0,cw,ch);var ov=$('tryOv');ov.width=cw;ov.height=ch;
  return Promise.all([lib(),asset()]).then(function(){var sizes=[320,416,224,512,160],i=0;function nx(){if(i>=sizes.length)return Promise.resolve(null);return faceapi.detectSingleFace(base,new faceapi.TinyFaceDetectorOptions({inputSize:sizes[i++],scoreThreshold:0.3})).withFaceLandmarks().then(function(r){return r||nx()})}return nx()})}).then(function(d){
  if(!d){face=null;draw();$('tryMsg').textContent='মুখ পাওয়া যায়নি। সামনে থেকে, ভালো আলোতে তোলা ছবি দিন।';return}
  face=d.landmarks.positions;$('tryMsg').textContent='';draw()}).catch(function(){$('tryMsg').textContent='Try-On লোড করা যায়নি। আবার চেষ্টা করুন।'})}
function mean(a,i,j){var x=0,y=0,n=0;for(var k=i;k<=j;k++){x+=a[k].x;y+=a[k].y;n++}return{x:x/n,y:y/n}}
function geo(){var p=face,eL=mean(p,36,41),eR=mean(p,42,47),ed=Math.hypot(eR.x-eL.x,eR.y-eL.y),ang=Math.atan2(eR.y-eL.y,eR.x-eL.x),jw=Math.hypot(p[16].x-p[0].x,p[16].y-p[0].y),fw=Math.max(jw*1.02,ed*1.55),mid={x:(eL.x+eR.x)/2,y:(eL.y+eR.y)/2},ux=Math.cos(ang),uy=Math.sin(ang);
 var jm={x:(p[0].x+p[16].x)/2,y:(p[0].y+p[16].y)/2},r=((p[30].x-jm.x)*ux+(p[30].y-jm.y)*uy)/(jw/2||1);r=Math.max(-1,Math.min(1,r));var sx=Math.max(0.72,Math.cos(Math.asin(r*0.85)));
 return{mid:mid,ed:ed,ang:ang,fw:fw,sx:sx,ux:ux,uy:uy}}
function draw(){var ov=$('tryOv'),c=ov.getContext('2d');c.clearRect(0,0,ov.width,ov.height);if(!show||!face||!img)return;var g=geo(),w=g.fw*adj.s,h=w*img.height/img.width,off=g.ed*0.05;
 c.save();c.translate(g.mid.x-g.uy*off+adj.dx*g.ed,g.mid.y+g.ux*off+adj.dy*g.ed);c.rotate(g.ang);c.scale(g.sx,1);c.drawImage(img,-w/2,-h/2,w,h);c.restore()}
$('tryTog').onclick=function(){show=!show;this.textContent=show?'সানগ্লাস খুলুন':'সানগ্লাস পরুন';draw()};
$('trySz').oninput=function(){adj.s=this.value/100;draw()};
var drag=null;$('tryOv').addEventListener('pointerdown',function(e){drag={x:e.clientX,y:e.clientY,dx:adj.dx,dy:adj.dy};this.setPointerCapture(e.pointerId)});
$('tryOv').addEventListener('pointermove',function(e){if(!drag||!face)return;var r=this.getBoundingClientRect(),k=this.width/r.width,ed=geo().ed;adj.dx=drag.dx+(e.clientX-drag.x)*k/ed;adj.dy=drag.dy+(e.clientY-drag.y)*k/ed;draw()});
$('tryOv').addEventListener('pointerup',function(){drag=null});
$('tryOk').onchange=function(){var b=$('trySave');b.disabled=!this.checked;b.style.opacity=this.checked?1:.5};
$('trySave').onclick=function(){if(!$('tryOk').checked||!orig)return;var fd=new FormData();fd.append('photo',orig,'selfie');fd.append('consent','1');fd.append('product_id',PID);$('trySt').style.color='#374151';$('trySt').textContent='সেভ হচ্ছে…';
 fetch('/tryon/save',{method:'POST',body:fd}).then(function(r){return r.json()}).then(function(j){if(j.ok){try{localStorage.setItem(KEY,j.token)}catch(e){}$('tryDel').hidden=false;$('trySt').style.color='#065F46';$('trySt').textContent='ছবি সেভ হয়েছে ('+j.days+' দিন পর মুছে যাবে)। যেকোনো সময় মুছে ফেলতে পারবেন।'}else{$('trySt').style.color='#B91C1C';$('trySt').textContent=j.error||'সেভ হয়নি'}}).catch(function(){$('trySt').style.color='#B91C1C';$('trySt').textContent='সেভ হয়নি'})};
$('tryDel').onclick=function(){var t='';try{t=localStorage.getItem(KEY)||''}catch(e){}if(!t){this.hidden=true;return}var fd=new FormData();fd.append('token',t);fetch('/tryon/forget',{method:'POST',body:fd}).then(function(){try{localStorage.removeItem(KEY)}catch(e){}$('tryDel').hidden=true;$('trySt').style.color='#065F46';$('trySt').textContent='আপনার সেভ করা ছবি মুছে ফেলা হয়েছে।'})};
})();
</script>
@endif
