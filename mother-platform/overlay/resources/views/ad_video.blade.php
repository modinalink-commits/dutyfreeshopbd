@extends('layout')@section('title','Ad Video Maker')
@section('body')
<style>
@font-face{font-family:'AdAB';font-weight:700;src:url(/fonts/anek-bangla-700.woff2) format('woff2')}
@font-face{font-family:'AdAB';font-weight:500;src:url(/fonts/anek-bangla-500.woff2) format('woff2')}
.av-g{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:10px}.av-g label{display:block;font-size:12px;color:#6B7280;margin-bottom:3px}
.av-g input[type=text],.av-g select{width:100%;padding:8px 10px;border:1px solid #D1D5DB;border-radius:8px;font:inherit}
.av-f{display:flex;gap:8px;flex-wrap:wrap}.av-f label{display:flex;gap:6px;align-items:center;border:1px solid #D1D5DB;border-radius:999px;padding:6px 12px;font-size:13px;color:#111827;margin:0;cursor:pointer}
.av-b{background:#FF5A00;color:#fff;border:0;border-radius:10px;padding:10px 18px;font-weight:700;cursor:pointer}.av-b[disabled]{opacity:.5;cursor:default}
.av-th{display:flex;gap:6px;overflow-x:auto;padding:4px 0}.av-th div{flex:0 0 74px;font-size:10px;line-height:1.2;color:#374151}.av-th img{width:74px;height:74px;object-fit:cover;border-radius:8px;display:block;background:#F3F4F6}
.av-out{display:flex;gap:14px;flex-wrap:wrap;margin-top:10px}.av-out .v{max-width:260px}.av-out video{width:100%;border-radius:10px;background:#000}.av-out a{font-weight:700}
</style>
<div style="margin-bottom:10px"><h1 style="margin:0">Ad Video Maker</h1><div style="font-size:12px;color:#6B7280">Pick a category, pick a voice/music track, and get animated Facebook videos in every size. The video is made in your own browser: keep this tab open and visible while it renders (about as long as the audio, per size).</div></div>
@if(session('ok'))<div class="card" style="color:#166534">{{ session('ok') }}</div>@endif
<div class="card">
 <div class="av-g">
  <div><label>Category page (path or full link)</label><input type="text" id="cat" value="/watch/watch"></div>
  <div><label>How many products</label><select id="cnt"><option>10</option><option>8</option><option>6</option><option>5</option></select></div>
  <div><label>Audio</label><select id="aud">@foreach($audios as $a)<option value="{{ $a['url'] }}">{{ $a['name'] }} ({{ $a['kb'] }} KB)</option>@endforeach</select></div>
  <div><label>Phone shown on the last screen</label><input type="text" id="ph" value="01871600600"></div>
 </div>
 <div style="margin:10px 0 4px;font-size:12px;color:#6B7280">Sizes</div>
 <div class="av-f">
  <label><input type="checkbox" class="sz" value="1080x1080" checked> Feed square 1080×1080</label>
  <label><input type="checkbox" class="sz" value="1080x1350" checked> Feed portrait 1080×1350 (4:5)</label>
  <label><input type="checkbox" class="sz" value="1080x1920" checked> Story / Reels 1080×1920 (9:16)</label>
  <label><input type="checkbox" class="sz" value="1280x720"> Landscape 1280×720 (16:9)</label>
 </div>
 <div style="margin-top:12px;display:flex;gap:8px;align-items:center;flex-wrap:wrap"><button class="av-b" id="load">1. Load products</button><button class="av-b" id="go" disabled>2. Make videos</button><span id="st" style="font-size:13px;color:#374151"></span></div>
 <div class="av-th" id="th"></div>
</div>
<div class="card"><b>Add another audio</b>
 <form method="post" action="/admin/ad-video/audio" enctype="multipart/form-data" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">@csrf<input type="file" name="audio" accept="audio/*" required><button class="av-b">Upload audio</button></form>
 @if($errors->any())<div style="color:#B91C1C;font-size:12px;margin-top:6px">{{ $errors->first() }}</div>@endif</div>
<div class="av-out" id="out"></div>
<script>
(function(){
var P=[],$=function(i){return document.getElementById(i)},st=function(m){$('st').textContent=m};
var FONT='AdAB,"Anek Bangla","Hind Siliguri","Noto Sans Bengali",system-ui,sans-serif';
function bn(n){return String(n).replace(/\d/g,function(d){return '০১২৩৪৫৬৭৮৯'[d]})}
function load(src){return new Promise(function(ok,no){var i=new Image();i.onload=function(){ok(i)};i.onerror=function(){no(new Error('image '+src))};i.src=src})}
function path(v){v=v.trim();try{var u=new URL(v,location.origin);if(u.origin!==location.origin)throw 0;return u.pathname}catch(e){return null}}
$('load').onclick=async function(){
  var p=path($('cat').value);if(!p){st('Use a dutyfreebd.com category link.');return}
  st('Loading products...');$('go').disabled=true;P=[];$('th').innerHTML='';
  try{
    var h=await fetch(p,{credentials:'same-origin'}).then(function(r){return r.text()});
    var d=new DOMParser().parseFromString(h,'text/html'),seen={},n=+$('cnt').value;
    var as=d.querySelectorAll('a[href]');
    for(var i=0;i<as.length&&P.length<n;i++){
      var a=as[i],img=a.querySelector('img');if(!img)continue;
      var href=(a.getAttribute('href')||'').split('?')[0];
      if(href.indexOf(p.replace(/\/$/,'')+'/')!==0||seen[href])continue;seen[href]=1;
      var box=a.closest('article,li,.card,div')||a,txt=(box.innerText||'').replace(/\s+/g,' ');
      var pm=txt.match(/৳\s*([\d,]+)/);var src=img.getAttribute('data-src')||img.getAttribute('src')||'';
      P.push({name:(img.getAttribute('alt')||'').trim()||'Product',price:pm?pm[1].replace(/,/g,''):'',src:src});
    }
    if(!P.length){st('No products found on that page.');return}
    $('th').innerHTML=P.map(function(x){return '<div><img src="'+x.src+'" alt=""><span>'+x.name.slice(0,32).replace(/</g,'')+'</span></div>'}).join('');
    st(P.length+' products ready.');$('go').disabled=false;
  }catch(e){st('Could not load: '+e.message)}
};
function ease(t){t=Math.max(0,Math.min(1,t));return 1-Math.pow(1-t,3)}
function back(t){t=Math.max(0,Math.min(1,t));var c=1.9;return 1+(c+1)*Math.pow(t-1,3)+c*Math.pow(t-1,2)}
function rr(c,x,y,w,h,r){c.beginPath();c.moveTo(x+r,y);c.arcTo(x+w,y,x+w,y+h,r);c.arcTo(x+w,y+h,x,y+h,r);c.arcTo(x,y+h,x,y,r);c.arcTo(x,y,x+w,y,r);c.closePath()}
function wrap(c,s,mw,max){var w=s.split(' '),L=[],cur='';w.forEach(function(x){var t=cur?cur+' '+x:x;if(c.measureText(t).width>mw&&cur){L.push(cur);cur=x}else cur=t});if(cur)L.push(cur);if(L.length>max){L=L.slice(0,max);L[max-1]=L[max-1].replace(/\s*\S*$/,'')+'…'}return L}
function draw(c,W,H,t,D,imgs,phone){
  var N=imgs.length,CTA=3.4,S=(D-CTA)/N,land=W/H>1.3,tall=H/W>1.6,u=Math.min(W,H)/1080;
  var g=c.createLinearGradient(0,0,W,H);g.addColorStop(0,'#0B1220');g.addColorStop(1,'#1F2A44');c.fillStyle=g;c.fillRect(0,0,W,H);
  var gx=W*(.5+.35*Math.sin(t*.6)),gy=H*(.35+.2*Math.cos(t*.5));var rg=c.createRadialGradient(gx,gy,0,gx,gy,Math.max(W,H)*.6);rg.addColorStop(0,'rgba(255,106,0,.30)');rg.addColorStop(1,'rgba(255,106,0,0)');c.fillStyle=rg;c.fillRect(0,0,W,H);
  c.textBaseline='alphabetic';c.fillStyle='#fff';c.font='700 '+(34*u)+'px '+FONT;c.textAlign='left';c.fillText('DUTYFREE BD',56*u,(tall?120:80)*u+(tall?60*u:0));c.fillStyle='#FF7A1A';c.fillRect(56*u,((tall?120:80)+(tall?60:0)+14)*u,70*u,6*u);
  if(t<N*S){
    var k=Math.min(N-1,Math.floor(t/S)),lt=(t-k*S)/S,im=imgs[k],pr=P[k];
    var inn=ease(lt/.14),out=lt>.9?ease((lt-.9)/.1):0,al=Math.min(1,lt/.1)*(1-out),ox=(1-inn)*W*.35-out*W*.2,zm=1+.07*lt;
    var cx,cy,cw,ch,tx,ty,tw;
    if(land){ch=H*.78;cw=ch;cx=W*.06;cy=(H-ch)/2;tx=cx+cw+W*.05;ty=H*.36;tw=W-tx-W*.05}
    else{cw=W*.82;ch=Math.min(cw*(tall?1.0:.92),H*(tall?.44:.5));cx=(W-cw)/2;cy=H*(tall?.2:.17);tx=cx;ty=cy+ch+(tall?90:70)*u;tw=cw}
    c.save();c.globalAlpha=al;c.translate(ox,0);
    c.shadowColor='rgba(0,0,0,.45)';c.shadowBlur=40*u;c.shadowOffsetY=18*u;c.fillStyle='#fff';rr(c,cx,cy,cw,ch,36*u);c.fill();c.shadowColor='transparent';
    c.save();rr(c,cx,cy,cw,ch,36*u);c.clip();var pad=cw*.07,aw=cw-pad*2,ah=ch-pad*2,r=Math.min(aw/im.width,ah/im.height)*zm,iw=im.width*r,ih=im.height*r;c.drawImage(im,cx+(cw-iw)/2,cy+(ch-ih)/2,iw,ih);c.restore();
    var nt=ease((lt-.1)/.15);c.globalAlpha=al*nt;var fs=(land?46:50)*u;c.font='700 '+fs+'px '+FONT;c.fillStyle='#fff';c.textAlign='left';
    var L=wrap(c,pr.name,tw,land?4:2),yy=ty+(1-nt)*30*u;L.forEach(function(s,i){c.fillText(s,tx,yy+i*fs*1.2)});
    if(pr.price){var pp=back((lt-.2)/.2),ptxt='৳ '+bn(Number(pr.price).toLocaleString('en-US')),fh=64*u;c.font='700 '+(46*u)+'px '+FONT;var pw=c.measureText(ptxt).width+70*u,py=yy+L.length*fs*1.2+14*u;c.save();c.translate(tx+pw/2,py+fh/2);c.scale(pp,pp);var pg=c.createLinearGradient(-pw/2,0,pw/2,0);pg.addColorStop(0,'#FF9A1F');pg.addColorStop(1,'#FF5A00');c.fillStyle=pg;rr(c,-pw/2,-fh/2,pw,fh,fh/2);c.fill();c.fillStyle='#fff';c.textAlign='center';c.textBaseline='middle';c.fillText(ptxt,0,3*u);c.restore();c.textBaseline='alphabetic'}
    c.restore();
    var dy=H-70*u,dw=Math.min(W-112*u,N*46*u),x0=(W-dw)/2,step=dw/N;for(var q=0;q<N;q++){c.fillStyle=q<=k?'#FF7A1A':'rgba(255,255,255,.25)';rr(c,x0+q*step,dy,step-8*u,8*u,4*u);c.fill()}
  }else{
    var ct=(t-N*S)/CTA,a2=ease(ct/.2);c.save();c.globalAlpha=a2;c.textAlign='center';
    c.fillStyle='#fff';c.font='700 '+(land?78:92)*u+'px '+FONT;var s1=back(ct/.3);c.save();c.translate(W/2,H*.38);c.scale(s1,s1);c.fillText('এখনই অর্ডার করুন',0,0);c.restore();
    c.font='500 '+(land?40:46)*u+'px '+FONT;c.fillStyle='#FFD2A8';c.fillText('ক্যাশ অন ডেলিভারি · সারা বাংলাদেশে হোম ডেলিভারি',W/2,H*.38+90*u);
    var bw=Math.min(W*.8,820*u),bh=110*u,bx=(W-bw)/2,by=H*.38+150*u;var bg=c.createLinearGradient(bx,0,bx+bw,0);bg.addColorStop(0,'#FF9A1F');bg.addColorStop(1,'#FF5A00');c.fillStyle=bg;rr(c,bx,by,bw,bh,bh/2);c.fill();c.fillStyle='#fff';c.font='700 '+(54*u)+'px '+FONT;c.textBaseline='middle';c.fillText('www.dutyfreebd.com',W/2,by+bh/2+3*u);c.textBaseline='alphabetic';
    c.fillStyle='#fff';c.font='700 '+(50*u)+'px '+FONT;c.fillText('📞 '+phone,W/2,by+bh+90*u);c.restore();
  }
}
function pickMime(){var l=['video/mp4;codecs=avc1.640028,mp4a.40.2','video/mp4;codecs=avc1.42E01E,mp4a.40.2','video/mp4','video/webm;codecs=vp9,opus','video/webm'];for(var i=0;i<l.length;i++)if(window.MediaRecorder&&MediaRecorder.isTypeSupported(l[i]))return l[i];return''}
async function render(W,H,ac,buf,imgs,phone,label){
  var cv=document.createElement('canvas');cv.width=W;cv.height=H;var c=cv.getContext('2d'),D=buf.duration;
  var dest=ac.createMediaStreamDestination(),src=ac.createBufferSource();src.buffer=buf;src.connect(dest);
  var stream=cv.captureStream(30);dest.stream.getAudioTracks().forEach(function(t){stream.addTrack(t)});
  var mime=pickMime(),rec=new MediaRecorder(stream,mime?{mimeType:mime,videoBitsPerSecond:5e6,audioBitsPerSecond:128e3}:{}),ch=[];
  rec.ondataavailable=function(e){if(e.data&&e.data.size)ch.push(e.data)};
  var done=new Promise(function(ok){rec.onstop=ok});
  rec.start(500);var t0=ac.currentTime+.15;src.start(t0);
  await new Promise(function(ok){(function f(){var t=ac.currentTime-t0;if(t>=D){ok();return}draw(c,W,H,Math.max(0,t),D,imgs,phone);st(label+': '+Math.max(0,Math.round(t))+' / '+Math.round(D)+' s');requestAnimationFrame(f)})()});
  draw(c,W,H,D-.01,D,imgs,phone);await new Promise(function(r){setTimeout(r,300)});rec.stop();await done;
  var type=(rec.mimeType||mime||'video/webm').split(';')[0],ext=type.indexOf('mp4')>-1?'mp4':'webm';
  return {blob:new Blob(ch,{type:type}),ext:ext};
}
$('go').onclick=async function(){
  var sizes=[].map.call(document.querySelectorAll('.sz:checked'),function(x){return x.value});if(!sizes.length){st('Tick at least one size.');return}
  if(!$('aud').value){st('Upload an audio first.');return}
  $('go').disabled=true;$('load').disabled=true;$('out').innerHTML='';
  try{
    st('Preparing...');await Promise.all([document.fonts.load('700 40px AdAB'),document.fonts.load('500 40px AdAB')]);
    var imgs=await Promise.all(P.map(function(x){return load(x.src)}));
    var ac=new (window.AudioContext||window.webkitAudioContext)(),buf=await ac.decodeAudioData(await (await fetch($('aud').value)).arrayBuffer());
    for(var i=0;i<sizes.length;i++){
      var wh=sizes[i].split('x'),W=+wh[0],H=+wh[1],r=await render(W,H,ac,buf,imgs,$('ph').value.trim()||'01871600600',sizes[i]);
      var url=URL.createObjectURL(r.blob),fn='dutyfreebd-ad-'+sizes[i]+'.'+r.ext;
      var d=document.createElement('div');d.className='v';d.innerHTML='<video controls playsinline src="'+url+'"></video><div style="font-size:12px;margin:4px 0">'+sizes[i]+' · '+(r.blob.size/1048576).toFixed(1)+' MB · '+r.ext.toUpperCase()+'</div><a download="'+fn+'" href="'+url+'">Download '+fn+'</a>';
      $('out').appendChild(d);
    }
    st('Done. Download each video below.');if(ac.close)ac.close();
  }catch(e){st('Failed: '+e.message)}
  $('go').disabled=false;$('load').disabled=false;
};
})();
</script>
@endsection
