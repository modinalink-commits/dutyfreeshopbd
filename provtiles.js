(function(){
var boxes=[].slice.call(document.querySelectorAll('section.pvh[data-cat]'));if(!boxes.length)return;
if(/(^|[#&])mc=bonded/.test(location.hash)){var st=document.getElementById('store');if(st)st.hidden=true;return}
function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
function okImg(u){return /^https:\/\/dutyfreebd\.com\/marine-api\/rfq-image\/[A-Z0-9-]+\?[a-z0-9=&]+$/.test(u||'')}
boxes.forEach(function(box){var g=box.querySelector('.pvx-g'),page=box.getAttribute('data-page'),x=new XMLHttpRequest();x.open('GET','https://dutyfreebd.com/marine-api/rfq?catalog='+encodeURIComponent(box.getAttribute('data-cat')));x.timeout=15000;
x.onload=function(){try{var d=JSON.parse(x.responseText),it=d.items||[],sc=(d.sections||[]).slice().sort(function(a,b){return (a.kind==='selection')-(b.kind==='selection')});if(!it.length||!sc.length)return;
 g.innerHTML=sc.map(function(s){var f=it.filter(function(i){return i.section===s.slug})[0],sel=s.kind==='selection';
  return '<a class="pvx'+(sel?' sel':'')+'" href="'+page+'#'+esc(s.slug)+'"><i>'+(f&&okImg(f.img)?'<img src="'+esc(f.img)+'" alt="" loading="lazy" width="300" height="225">':'')+'</i><span>'+(sel?'<em>Selection from Chattogram suppliers</em>':(s.group?'<em>'+esc(s.group)+'</em>':''))+'<b>'+esc(s.name)+'</b>'+((s.subs||[]).length?'<small>'+esc(s.subs.join(' · '))+'</small>':'')+'<small>'+s.count+' product'+(s.count===1?'':'s')+'</small></span></a>'}).join('');box.hidden=false}catch(e){}};
x.send()});
})();
