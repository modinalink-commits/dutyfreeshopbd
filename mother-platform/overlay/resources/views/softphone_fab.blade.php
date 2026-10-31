<a id="dfFab" href="/admin/call-center/phone" title="Open dialer" style="position:fixed;right:16px;top:70px;z-index:60;width:44px;height:44px;border-radius:50%;background:#16A34A;color:#fff;display:flex;align-items:center;justify-content:center;font-size:20px;text-decoration:none;box-shadow:0 4px 14px rgba(0,0,0,.25)">📞</a>
@verbatim
<script>
(function(){
var ch=window.BroadcastChannel?new BroadcastChannel('dfphone'):null,open=false;
if(ch)ch.onmessage=function(m){if(m.data&&m.data.type==='here')open=true};
function clean(n){return String(n||'').replace(/[^0-9+*#]/g,'')}
window.dfCall=function(n){n=clean(n);var url='/admin/call-center/phone'+(n?'?n='+encodeURIComponent(n):'');open=false;if(ch)ch.postMessage({type:'ping'});
 setTimeout(function(){if(open&&ch){ch.postMessage({type:'dial',n:n})}else{window.open(url,'dfphone','width=400,height=720')}},250);return false};
document.getElementById('dfFab').addEventListener('click',function(e){e.preventDefault();dfCall('')});
})();
</script>
@endverbatim
