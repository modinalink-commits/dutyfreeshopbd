@once
<script>
(function(){
if(window.__dfkeep)return;window.__dfkeep=1;
var K='dfkeep:'+location.pathname+location.search;
function cands(){var out=[],all=document.body?document.body.getElementsByTagName('*'):[];for(var i=0;i<all.length&&i<5000;i++){var e=all[i];if(e.tagName==='SCRIPT'||e.tagName==='STYLE')continue;var cs;try{cs=getComputedStyle(e)}catch(x){continue}if(/(auto|scroll)/.test(cs.overflowY)||/(auto|scroll)/.test(cs.overflowX))out.push(e)}return out}
function key(e,n){return e.id?'#'+e.id:e.tagName+n}
function scrollers(){var c=cands(),out=[];c.forEach(function(e,n){if(e.scrollTop||e.scrollLeft)out.push([key(e,n),e.scrollTop,e.scrollLeft])});return out}
function save(){try{sessionStorage.setItem(K,JSON.stringify({t:Date.now(),x:window.scrollX,y:window.scrollY,s:scrollers()}))}catch(e){}}
function load(){try{return JSON.parse(sessionStorage.getItem(K)||'null')}catch(e){return null}}
var nav='navigate';try{nav=(performance.getEntriesByType('navigation')[0]||{}).type||'navigate'}catch(e){}
var d=load(),fresh=d&&(Date.now()-d.t<12000);
function apply(){if(!d)return;if(!(nav==='reload'||fresh))return;
 var c=cands(),m={};c.forEach(function(e,n){m[key(e,n)]=e});
 d.s.forEach(function(r){var el=m[r[0]];if(el){el.scrollTop=r[1];el.scrollLeft=r[2]}});
 window.scrollTo(d.x||0,d.y||0)}
function run(){apply();setTimeout(apply,120);setTimeout(apply,500);setTimeout(apply,1400)}
if('scrollRestoration' in history)try{history.scrollRestoration='manual'}catch(e){}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',run);else run();
window.addEventListener('load',apply);
window.addEventListener('pagehide',save);window.addEventListener('beforeunload',save);
document.addEventListener('visibilitychange',function(){if(document.hidden)save()});
var tm;window.addEventListener('scroll',function(){clearTimeout(tm);tm=setTimeout(save,300)},{passive:true});
})();
</script>
@endonce
