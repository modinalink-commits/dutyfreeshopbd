(function(){
var box=document.getElementById('pvh'),g=document.getElementById('pvh-g');if(!box||!g)return;
if(/(^|[#&])mc=/.test(location.hash)){var st=document.getElementById('store');if(st)st.hidden=true;return}
function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
function okImg(u){return /^https:\/\/dutyfreebd\.com\/marine-api\/rfq-image\/[A-Z0-9-]+\?[a-z0-9=&]+$/.test(u||'')}
var x=new XMLHttpRequest();x.open('GET','https://dutyfreebd.com/marine-api/rfq');x.timeout=15000;
x.onload=function(){try{var d=JSON.parse(x.responseText),it=d.items||[],sc=(d.sections||[]).slice().sort(function(a,b){return (a.kind==='selection')-(b.kind==='selection')});if(!it.length||!sc.length)return;
 g.innerHTML=sc.map(function(s){var f=it.filter(function(i){return i.section===s.slug})[0],sel=s.kind==='selection';
  return '<a class="pvx'+(sel?' sel':'')+'" href="/provisions.html#'+esc(s.slug)+'"><i>'+(f&&okImg(f.img)?'<img src="'+esc(f.img)+'" alt="" loading="lazy" width="300" height="225">':'')+'</i><span>'+(sel?'<em>Selection from Chattogram suppliers</em>':'')+'<b>'+esc(s.name)+'</b><small>'+esc((s.subs||[]).join(' · '))+'</small><small>'+s.count+' product'+(s.count===1?'':'s')+'</small></span></a>'}).join('');box.hidden=false}catch(e){}};
x.send();
})();
