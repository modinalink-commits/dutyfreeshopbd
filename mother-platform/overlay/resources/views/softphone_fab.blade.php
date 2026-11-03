<style>
#dfDock{position:fixed;right:16px;top:50%;width:390px;max-width:calc(100vw - 32px);height:min(680px,calc(100vh - 32px));z-index:70;background:#fff;border:1px solid #E5E7EB;border-radius:14px;overflow:hidden;box-shadow:0 12px 36px rgba(15,31,61,.28);transform:translate(calc(100% + 40px),-50%);opacity:0;pointer-events:none;transition:transform .28s cubic-bezier(.22,.8,.3,1),opacity .2s ease;display:flex;flex-direction:column}
#dfDock.on{transform:translate(0,-50%);opacity:1;pointer-events:auto}
#dfDock iframe{border:0;width:100%;flex:1;background:#fff}
#dfEdge{position:fixed;right:0;top:50%;transform:translateY(-50%);z-index:60;background:#0F1F3D;color:#fff;border:0;border-radius:10px 0 0 10px;padding:14px 7px;cursor:pointer;writing-mode:vertical-rl;font:700 12.5px/1 inherit;letter-spacing:.3px;box-shadow:-3px 3px 12px rgba(15,31,61,.25);display:flex;align-items:center;gap:7px}
#dfEdge:hover{background:#F26B1D}#dfEdge i{font-style:normal;writing-mode:horizontal-tb}
#dfEdge::after{content:"";width:9px;height:9px;border-radius:50%;background:#22C55E;order:3}
#dfEdge.st-busy::after{background:#EF4444}
#dfEdge.busy::after{box-shadow:0 0 0 0 rgba(34,197,94,.7);animation:dfp 1.4s infinite}
@keyframes dfp{70%{box-shadow:0 0 0 8px rgba(34,197,94,0)}100%{box-shadow:0 0 0 0 rgba(34,197,94,0)}}
body.dfp-open #dfEdge{display:none}
@media(max-width:800px){#dfDock{right:0;left:0;bottom:0;width:100%;max-width:100%;height:88vh;border-radius:18px 18px 0 0;border-width:1px 0 0}#dfEdge{padding:12px 6px}#dfEdge span{display:none}#dfEdge::after{order:0}}
</style>
<button type="button" id="dfEdge" title="Open Mother Commerce Dialer"><i>📞</i><span>Dialer · <b id="dfSt">Available</b></span></button>
<div id="dfDock" aria-label="Mother Commerce Dialer"></div>
@verbatim
<script>
(function(){
var ch=window.BroadcastChannel?new BroadcastChannel('dfphone'):null,dock=document.getElementById('dfDock'),edge=document.getElementById('dfEdge'),fr=null,busy=false,popHere=false,ls='dfDialerOpen',sawCall=false,sumOpen=false,hideT=0;
function clean(n){return String(n||'').replace(/[^0-9+*#]/g,'')}
function sv(v){try{localStorage.setItem(ls,v?'1':'0')}catch(e){}}
function ld(){try{return localStorage.getItem(ls)==='1'}catch(e){return false}}
function mk(n,x){if(fr)return;fr=document.createElement('iframe');fr.allow='microphone; autoplay';fr.title='Mother Commerce Dialer';fr.src='/admin/call-center/phone?embed=1'+(n?'&n='+encodeURIComponent(n):'')+(x&&x.auto?'&auto=1':'')+(x&&x.o?'&o='+encodeURIComponent(x.o):'')+(x&&x.cn?'&cn='+encodeURIComponent(x.cn):'');dock.appendChild(fr)}
function show(n,x){mk(n,x);dock.classList.add('on');document.body.classList.add('dfp-open');sv(1)}
function hide(){dock.classList.remove('on');document.body.classList.remove('dfp-open');sv(0)}
function kill(){hide();sawCall=false;sumOpen=false;if(fr){fr.remove();fr=null}busy=false;edge.classList.remove('busy')}
if(ch)ch.onmessage=function(m){var d=m.data||{};if(d.type==='here'&&!fr)popHere=true};
window.dfCall=function(n,c){n=clean(n);c=c||{};var m={type:'call',n:n,o:c.order||'',cn:c.name||''};
 if(fr){show();if(ch)ch.postMessage(m);return false}
 popHere=false;if(ch)ch.postMessage({type:'ping'});
 setTimeout(function(){if(popHere&&ch){ch.postMessage(m)}else{show(n,{auto:1,o:c.order,cn:c.name})}},200);return false};
edge.addEventListener('click',function(){show()});
function setSt(v){var b=v==='busy';edge.classList.toggle('st-busy',b);var t=document.getElementById('dfSt');if(t)t.textContent=b?'Busy':'Available'}
try{fetch('/admin/call-center/my-status',{headers:{Accept:'application/json'},credentials:'same-origin'}).then(function(r){return r.json()}).then(function(j){if(j&&j.status)setSt(j.status)}).catch(function(){})}catch(e){}
document.addEventListener('click',function(e){var t=e.target.closest&&e.target.closest('a[href^="tel:"],[data-dfcall]');if(!t||t.hasAttribute('onclick'))return;var n=t.getAttribute('data-dfcall')||t.getAttribute('href').slice(4);if(!clean(n))return;e.preventDefault();window.dfCall(n,{order:t.getAttribute('data-order')||'',name:t.getAttribute('data-name')||''})});
window.addEventListener('message',function(e){if(e.origin!==location.origin||!e.data||!e.data.dfdial)return;var a=e.data.dfdial;
 if(a==='min')hide();
 else if(a==='close'){if(busy&&!confirm('A call is active. Close the dialer and hang up?'))return;kill()}
 else if(a==='pop'){var n=fr?'':'';kill();window.open('/admin/call-center/phone','dfphone','width=400,height=720')}
 else if(a==='status'){setSt(e.data.v)}
 else if(a==='busy'){busy=true;sawCall=true;clearTimeout(hideT);edge.classList.add('busy')}
 else if(a==='idle'){busy=false;edge.classList.remove('busy');if(sawCall){clearTimeout(hideT);hideT=setTimeout(function(){if(!sumOpen&&!busy){sawCall=false;hide()}},1800)}}
 else if(a==='summary'){sumOpen=true;clearTimeout(hideT)}
 else if(a==='done'){sumOpen=false;sawCall=false;clearTimeout(hideT);hideT=setTimeout(function(){if(!busy)hide()},500)}
 else if(a==='incoming'){clearTimeout(hideT);sawCall=true;show()}});
window.addEventListener('beforeunload',function(e){if(busy){e.preventDefault();e.returnValue=''}});
sv(0);popHere=false;if(ch)ch.postMessage({type:'ping'});setTimeout(function(){if(!popHere&&!fr)mk()},600)
})();
</script>
@endverbatim
