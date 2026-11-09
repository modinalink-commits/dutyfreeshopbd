@verbatim
<style>
.pp .ph{cursor:zoom-in;touch-action:manipulation}.pp .ph #pmain{transition:transform .28s ease-in-out;transform-origin:50% 50%;will-change:transform;user-select:none;-webkit-user-drag:none}
.pp .ph.zin{cursor:zoom-out}.pp .ph.zdrag #pmain{transition:none}
.zc{position:absolute;top:10px;right:10px;display:flex;gap:6px;z-index:3}.zc button{width:34px;height:34px;border-radius:10px;border:1px solid #E5E7EB;background:rgba(255,255,255,.95);color:#0B1220;font-size:17px;font-weight:700;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 1px 4px rgba(0,0,0,.12)}.zc button:hover{background:#FFF1E8;color:#FF5A00}
#dfLb{position:fixed;inset:0;background:rgba(8,15,30,.94);z-index:9999;display:none;touch-action:none;-webkit-user-select:none;user-select:none}#dfLb.on{display:block}
#dfLb .st{position:absolute;inset:0;overflow:hidden;display:flex;align-items:center;justify-content:center}#dfLb img{max-width:100%;max-height:100%;transform-origin:0 0;will-change:transform;transition:transform .25s ease-in-out;-webkit-user-drag:none;pointer-events:none}#dfLb img.nt{transition:none}
#dfLb .lc{position:absolute;top:12px;right:12px;display:flex;gap:8px;z-index:2}#dfLb .lc button,#dfLb .ar{width:42px;height:42px;border-radius:50%;border:0;background:rgba(255,255,255,.16);color:#fff;font-size:20px;cursor:pointer}#dfLb .ar{position:absolute;top:50%;margin-top:-21px;z-index:2}#dfLb .ar.l{left:10px}#dfLb .ar.r{right:10px}
#dfLb .cn{position:absolute;left:0;right:0;bottom:14px;text-align:center;color:#fff;font-size:13px;opacity:.85;z-index:2}
@media(prefers-reduced-motion:reduce){.pp .ph #pmain,#dfLb img{transition:none}}
</style>
<script>
(function(){
var ph=document.querySelector('.pp .ph'),im=document.getElementById('pmain');if(!ph||!im)return;
var fine=window.matchMedia&&matchMedia('(hover:hover) and (pointer:fine)').matches,z=1,over=false;
var bar=document.createElement('div');bar.className='zc';bar.innerHTML='<button type="button" data-a="in" aria-label="Zoom in" title="Zoom in">+</button><button type="button" data-a="out" aria-label="Zoom out" title="Zoom out">−</button><button type="button" data-a="reset" aria-label="Reset zoom" title="Reset">⟲</button><button type="button" data-a="fs" aria-label="Fullscreen view" title="Fullscreen">⛶</button>';ph.appendChild(bar);
function apply(h){var s=h?Math.max(z,2.2):z;im.style.transform=s>1?'scale('+s+')':'none';ph.classList.toggle('zin',s>1)}
function origin(e){var r=im.getBoundingClientRect(),x=Math.min(100,Math.max(0,(e.clientX-r.left)/r.width*100)),y=Math.min(100,Math.max(0,(e.clientY-r.top)/r.height*100));im.style.transformOrigin=x+'% '+y+'%'}
if(fine){
 ph.addEventListener('mouseenter',function(e){over=true;origin(e);apply(true)});
 ph.addEventListener('mousemove',function(e){if(e.target.closest('.zc,.gth')){return}origin(e);if(over)apply(true)});
 ph.addEventListener('mouseleave',function(){over=false;apply(false);if(z<=1)im.style.transformOrigin='50% 50%'});
}
bar.addEventListener('click',function(e){e.stopPropagation();var b=e.target.closest('button');if(!b)return;var a=b.dataset.a;
 if(a==='in')z=Math.min(4,+(z+.5).toFixed(2));if(a==='out')z=Math.max(1,+(z-.5).toFixed(2));if(a==='reset'){z=1;im.style.transformOrigin='50% 50%'}
 if(a==='fs'){openLb();return}
 if(!fine&&z>1&&a==='in'){}apply(over)});
function urls(){var b=document.querySelectorAll('.gth button[data-u]'),o=[];b.forEach(function(x){o.push(x.dataset.u)});if(!o.length)o=[im.currentSrc||im.src];return o}
var lb=null,L={i:0,list:[],s:1,x:0,y:0},lim=null;
function build(){lb=document.createElement('div');lb.id='dfLb';lb.innerHTML='<div class="lc"><button data-a="in" aria-label="Zoom in">+</button><button data-a="out" aria-label="Zoom out">−</button><button data-a="reset" aria-label="Reset">⟲</button><button data-a="x" aria-label="Close">✕</button></div><button class="ar l" data-a="prev" aria-label="Previous">‹</button><button class="ar r" data-a="next" aria-label="Next">›</button><div class="st"><img alt=""></div><div class="cn"></div>';document.body.appendChild(lb);lim=lb.querySelector('img');wireLb()}
function tf(anim){lim.classList.toggle('nt',!anim);lim.style.transform='translate('+L.x+'px,'+L.y+'px) scale('+L.s+')'}
function clampPan(){var st=lb.querySelector('.st'),SW=st.clientWidth,SH=st.clientHeight,W=lim.offsetWidth,H=lim.offsetHeight,w=W*L.s,h=H*L.s;
 if(w>SW){L.x=Math.min((W-SW)/2,Math.max(SW-w-(SW-W)/2,L.x))}else{L.x=(W-w)/2}
 if(h>SH){L.y=Math.min((H-SH)/2,Math.max(SH-h-(SH-H)/2,L.y))}else{L.y=(H-h)/2}}
function zoomTo(s,cx,cy){var ns=Math.min(5,Math.max(1,s));if(ns<=1){L.s=1;L.x=0;L.y=0;tf(true);return}
 var r=lim.getBoundingClientRect(),px=(cx-r.left)/L.s,py=(cy-r.top)/L.s;
 /* keep point under cursor fixed */
 var nx=L.x-(px*(ns-L.s)),ny=L.y-(py*(ns-L.s));L.s=ns;L.x=nx;L.y=ny;clampPan();tf(true)}
function show(i){L.i=(i+L.list.length)%L.list.length;L.s=1;L.x=0;L.y=0;lim.src=L.list[L.i];tf(false);lb.querySelector('.cn').textContent=L.list.length>1?(L.i+1)+' / '+L.list.length:'';lb.querySelectorAll('.ar').forEach(function(a){a.style.display=L.list.length>1?'':'none'})}
function openLb(){if(!lb)build();L.list=urls();var cur=im.currentSrc||im.src,k=L.list.indexOf(cur);if(k<0){k=L.list.map(function(u){return u.split('/').pop()}).indexOf(cur.split('/').pop())}L.list=L.list.length?L.list:[cur];show(k<0?0:k);lb.classList.add('on');document.documentElement.style.overflow='hidden'}
function closeLb(){lb.classList.remove('on');document.documentElement.style.overflow=''}
function wireLb(){
 lb.addEventListener('click',function(e){var b=e.target.closest('button');if(b){var a=b.dataset.a;if(a==='x')closeLb();if(a==='in')zoomTo(L.s+.5,innerWidth/2,innerHeight/2);if(a==='out')zoomTo(L.s-.5,innerWidth/2,innerHeight/2);if(a==='reset'){L.s=1;L.x=0;L.y=0;tf(true)}if(a==='prev')show(L.i-1);if(a==='next')show(L.i+1);return}if(e.target.classList.contains('st')&&L.s<=1)closeLb()});
 document.addEventListener('keydown',function(e){if(!lb.classList.contains('on'))return;if(e.key==='Escape')closeLb();if(e.key==='ArrowLeft')show(L.i-1);if(e.key==='ArrowRight')show(L.i+1);if(e.key==='+'||e.key==='=')zoomTo(L.s+.5,innerWidth/2,innerHeight/2);if(e.key==='-')zoomTo(L.s-.5,innerWidth/2,innerHeight/2)});
 lb.addEventListener('wheel',function(e){e.preventDefault();zoomTo(L.s*(e.deltaY<0?1.2:1/1.2),e.clientX,e.clientY)},{passive:false});
 var tp=[],sx=0,sy=0,d0=0,s0=1,last=0,moved=false,px0=0,py0=0,dr=false;
 function dist(a,b){return Math.hypot(a.clientX-b.clientX,a.clientY-b.clientY)}
 var st=lb.querySelector('.st');
 st.addEventListener('pointerdown',function(e){st.setPointerCapture(e.pointerId);tp.push(e);if(tp.length===2){d0=dist(tp[0],tp[1]);s0=L.s}sx=e.clientX;sy=e.clientY;px0=L.x;py0=L.y;moved=false;dr=true});
 st.addEventListener('pointermove',function(e){if(!dr)return;for(var i=0;i<tp.length;i++)if(tp[i].pointerId===e.pointerId)tp[i]=e;
  if(tp.length===2){var d=dist(tp[0],tp[1]);if(d0>0){var cx=(tp[0].clientX+tp[1].clientX)/2,cy=(tp[0].clientY+tp[1].clientY)/2;var ns=Math.min(5,Math.max(1,s0*d/d0));var r=lim.getBoundingClientRect(),px=(cx-r.left)/L.s,py=(cy-r.top)/L.s;L.x-=px*(ns-L.s);L.y-=py*(ns-L.s);L.s=ns;clampPan();tf(false)}moved=true;return}
  var dx=e.clientX-sx,dy=e.clientY-sy;if(Math.abs(dx)+Math.abs(dy)>6)moved=true;if(L.s>1){L.x=px0+dx;L.y=py0+dy;clampPan();tf(false)}});
 function up(e){tp=tp.filter(function(t){return t.pointerId!==e.pointerId});if(tp.length)return;dr=false;
  var dx=e.clientX-sx,dy=e.clientY-sy;
  if(L.s<=1&&moved&&Math.abs(dx)>50&&Math.abs(dx)>Math.abs(dy)){show(L.i+(dx<0?1:-1));return}
  if(!moved){var now=Date.now();if(now-last<300){L.s>1.05?zoomTo(1,e.clientX,e.clientY):zoomTo(2.5,e.clientX,e.clientY);last=0}else last=now}
  if(L.s<1.02){L.s=1;L.x=0;L.y=0}tf(true)}
 st.addEventListener('pointerup',up);st.addEventListener('pointercancel',up);
 st.addEventListener('dblclick',function(e){e.preventDefault()});
}
/* tap on the product image: open the viewer on touch screens; on desktop a click opens fullscreen too */
if(!fine)ph.addEventListener('click',function(e){if(e.target.closest('.zc,.gth'))return;openLb()});
})();
</script>
@endverbatim
