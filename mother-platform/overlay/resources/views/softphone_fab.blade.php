<style>
#dfDock{position:fixed;top:0;right:0;bottom:0;width:400px;max-width:100vw;z-index:70;background:#fff;border-left:1px solid #E5E7EB;box-shadow:-8px 0 28px rgba(15,31,61,.14);transform:translateX(104%);transition:transform .22s ease;display:flex;flex-direction:column}
#dfDock.on{transform:none}
#dfDock iframe{border:0;width:100%;flex:1;background:#fff}
#dfEdge{position:fixed;right:0;top:50%;transform:translateY(-50%);z-index:60;background:#0F1F3D;color:#fff;border:0;border-radius:10px 0 0 10px;padding:14px 7px;cursor:pointer;writing-mode:vertical-rl;font:700 12.5px/1 inherit;letter-spacing:.3px;box-shadow:-3px 3px 12px rgba(15,31,61,.25);display:flex;align-items:center;gap:6px}
#dfEdge:hover{background:#F26B1D}#dfEdge i{font-style:normal;writing-mode:horizontal-tb}
#dfEdge.busy::after{content:"";width:9px;height:9px;border-radius:50%;background:#22C55E;box-shadow:0 0 0 0 rgba(34,197,94,.7);animation:dfp 1.4s infinite}
@keyframes dfp{70%{box-shadow:0 0 0 8px rgba(34,197,94,0)}100%{box-shadow:0 0 0 0 rgba(34,197,94,0)}}
body.dfp-open #dfEdge{display:none}
@media(min-width:801px){body.dfp-open{padding-right:400px;transition:padding-right .22s ease}body.dfp-open .df-fixed-r{right:400px}}
@media(max-width:800px){#dfDock{top:auto;left:0;width:100%;height:92vh;border-left:0;border-radius:18px 18px 0 0;transform:translateY(104%)}#dfDock.on{transform:none}
#dfEdge{writing-mode:horizontal-tb;top:auto;bottom:76px;right:14px;transform:none;width:50px;height:50px;border-radius:50%;padding:0;justify-content:center;background:#16A34A;font-size:22px}#dfEdge span{display:none}#dfEdge:hover{background:#15803D}}
</style>
<button type="button" id="dfEdge" title="Open Mother Commerce Dialer"><i>📞</i><span>Open Dialer</span></button>
<div id="dfDock" aria-label="Mother Commerce Dialer"></div>
@verbatim
<script>
(function(){
var ch=window.BroadcastChannel?new BroadcastChannel('dfphone'):null,dock=document.getElementById('dfDock'),edge=document.getElementById('dfEdge'),fr=null,busy=false,popHere=false,ls='dfDialerOpen';
function clean(n){return String(n||'').replace(/[^0-9+*#]/g,'')}
function sv(v){try{localStorage.setItem(ls,v?'1':'0')}catch(e){}}
function ld(){try{return localStorage.getItem(ls)==='1'}catch(e){return false}}
function mk(n){if(fr)return;fr=document.createElement('iframe');fr.allow='microphone; autoplay';fr.title='Mother Commerce Dialer';fr.src='/admin/call-center/phone?embed=1'+(n?'&n='+encodeURIComponent(n):'');dock.appendChild(fr)}
function show(n){mk(n);dock.classList.add('on');document.body.classList.add('dfp-open');sv(1)}
function hide(){dock.classList.remove('on');document.body.classList.remove('dfp-open');sv(0)}
function kill(){hide();if(fr){fr.remove();fr=null}busy=false;edge.classList.remove('busy')}
if(ch)ch.onmessage=function(m){var d=m.data||{};if(d.type==='here'&&!fr)popHere=true};
window.dfCall=function(n){n=clean(n);
 if(fr){show();if(ch)ch.postMessage({type:'dial',n:n});return false}
 popHere=false;if(ch)ch.postMessage({type:'ping'});
 setTimeout(function(){if(popHere&&ch){ch.postMessage({type:'dial',n:n})}else{show(n)}},200);return false};
edge.addEventListener('click',function(){show()});
window.addEventListener('message',function(e){if(e.origin!==location.origin||!e.data||!e.data.dfdial)return;var a=e.data.dfdial;
 if(a==='min')hide();
 else if(a==='close'){if(busy&&!confirm('A call is active. Close the dialer and hang up?'))return;kill()}
 else if(a==='pop'){var n=fr?'':'';kill();window.open('/admin/call-center/phone','dfphone','width=400,height=720')}
 else if(a==='busy'){busy=true;edge.classList.add('busy')}
 else if(a==='idle'){busy=false;edge.classList.remove('busy')}
 else if(a==='incoming'){show()}});
window.addEventListener('beforeunload',function(e){if(busy){e.preventDefault();e.returnValue=''}});
if(ld())show();else{popHere=false;if(ch)ch.postMessage({type:'ping'});setTimeout(function(){if(!popHere&&!fr)mk()},600)}
})();
</script>
@endverbatim
