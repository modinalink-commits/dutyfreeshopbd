(function(){
var API='https://dutyfreebd.com/marine-api/rfq',PORTAL='https://dutyfreebd.com/marine-portal/orders/new';
var KEY=(/[?&]preview=([0-9a-f]{32})(?:&|$)/.exec(location.search)||[])[1]||'';
var S={items:[],sections:[],sec:'',sub:'',q:''};
function $(i){return document.getElementById(i)}
function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
function okImg(u){return /^https:\/\/dutyfreebd\.com\/marine-api\/rfq-image\/[A-Z0-9-]+\?[a-z0-9=&]+$/.test(u||'')}
function card(x){
 var av=(x.availability||[]).map(function(a){return esc(a.port)+' (confirmed until '+esc(a.until)+')'}).join(', ');
 var btn=x.draft?'<span aria-disabled="true">Draft · quotation opens after release</span>':'<a href="'+PORTAL+'?rfq='+encodeURIComponent(x.id)+'">Request a USD quotation</a>';
 return '<article class="mvc" data-sku="'+esc(x.sku)+'"><div class="pvi">'+(okImg(x.img)?'<img src="'+esc(x.img)+'" alt="'+esc(x.alt)+'" loading="lazy" width="300" height="300">':'')+(x.img_label?'<span class="il">'+esc(x.img_label)+'</span>':'')+(x.draft?'<span class="dr">DRAFT</span>':'')+'</div>'+
 '<div class="pvb"><h2>'+esc(x.name)+'</h2><div class="br">'+(x.brand?esc(x.brand):'Unbranded')+' · '+esc(x.sub)+'</div>'+
 '<dl><dt>Code (SKU)</dt><dd>'+esc(x.sku)+'</dd><dt>Pack size</dt><dd>'+esc(x.pack)+(x.pack_note?' <span class="nt">· '+esc(x.pack_note)+'</span>':'')+'</dd><dt>Order unit</dt><dd>'+esc(x.order_unit)+'</dd><dt>Handling</dt><dd class="nt">'+esc(x.handling)+'</dd></dl>'+
 '<div class="av'+(av?' ok':'')+'">'+(av?'Available at '+av:'Price and availability confirmed in your USD quotation')+'</div>'+
 '<div class="pva">'+btn+'</div></div></article>';
}
/* step 1: the sections as tiles; step 2: a section with its subcategories; step 3: the products */
function tile(s){var f=S.items.filter(function(x){return x.section===s.slug})[0],sel=s.kind==='selection';
 return '<a class="pvx'+(sel?' sel':'')+'" href="#'+esc(s.slug)+'" data-s="'+esc(s.slug)+'"><i>'+(f&&okImg(f.img)?'<img src="'+esc(f.img)+'" alt="" loading="lazy" width="300" height="225">':'')+'</i><span>'+(sel?'<em>Selection from Chattogram suppliers</em>':'')+'<b>'+esc(s.name)+'</b><small>'+esc((s.subs||[]).join(' · '))+'</small><small>'+s.count+' product'+(s.count===1?'':'s')+'</small></span></a>'}
function secOf(slug){return S.sections.filter(function(s){return s.slug===slug})[0]}
function match(x){if(S.sec&&x.section!==S.sec)return false;if(S.sec&&S.sub&&x.sub!==S.sub)return false;if(!S.q)return true;var q=S.q.toLowerCase();return x.sku.toLowerCase().indexOf(q)>=0||x.name.toLowerCase().indexOf(q)>=0||(x.brand||'').toLowerCase().indexOf(q)>=0}
function draw(){var g=$('pv-g'),k=$('pv-k'),c=$('pv-c'),sec=S.sec?secOf(S.sec):null;
 k.hidden=!sec;c.hidden=!sec;
 if(!sec&&!S.q){g.className='pvx-g';g.innerHTML=S.sections.map(tile).join('');$('pv-s').innerHTML='<b>'+S.sections.length+'</b> sections · <b>'+S.items.length+'</b> products · choose a section, or search by code or name';return}
 if(sec){$('pv-kn').textContent=sec.name;var n={};S.items.forEach(function(x){if(x.section===sec.slug)n[x.sub]=(n[x.sub]||0)+1});
  c.innerHTML='<button type="button" data-u="" aria-pressed="'+(S.sub===''?'true':'false')+'">All<span>'+sec.count+'</span></button>'+(sec.subs||[]).map(function(u){return '<button type="button" data-u="'+esc(u)+'" aria-pressed="'+(S.sub===u?'true':'false')+'">'+esc(u)+'<span>'+(n[u]||0)+'</span></button>'}).join('')}
 var o=S.items.filter(match);g.className='pvg';
 g.innerHTML=o.map(card).join('')||'<div class="pve card" style="grid-column:1/-1">No product matches'+(S.q?' "'+esc(S.q)+'"':'')+(sec?' in this section':'')+'. Search by product code (for example MDP-DP-001) or by name.</div>';
 $('pv-s').innerHTML='<b>'+o.length+'</b> product'+(o.length===1?'':'s')+' · '+esc(sec?sec.name+(S.sub?' · '+S.sub:''):'All sections')+(S.q?' · search "'+esc(S.q)+'"':'')+' · all prices by USD quotation'}
function setSec(s,keep){S.sec=secOf(s)?s:'';S.sub='';if(!keep){try{history.pushState(null,'',location.pathname+location.search+(S.sec?'#'+S.sec:''))}catch(e){}}draw();if(!keep){var t=$('pv-top');if(t&&t.scrollIntoView)t.scrollIntoView({block:'start'})}}
function empty(){$('pv-tools').hidden=true;$('pv-s').textContent='';$('pv-g').className='pvg';
 $('pv-g').innerHTML='<div class="pve card" style="grid-column:1/-1"><b>The provisions catalogue is being prepared.</b><br>Send your store list from your account and our marine desk answers with a quotation in USD.<br><br><a href="/request-access">Request access</a> · <a href="/login">Vessel login</a></div>'}
function fail(){$('pv-g').className='pvg';$('pv-g').innerHTML='<div class="pve card" style="grid-column:1/-1">The catalogue could not be loaded just now. Please try again in a minute.</div>'}
function fromHash(){var h='';try{h=decodeURIComponent((location.hash||'').slice(1))}catch(e){}setSec(h,true)}
function load(){$('pv-g').className='pvg';$('pv-g').innerHTML='<div class="sk"></div><div class="sk"></div><div class="sk"></div><div class="sk"></div>';
 var x=new XMLHttpRequest();x.open('GET',API+(KEY?'?preview='+KEY:''));x.timeout=20000;
 x.onload=function(){try{if(x.status!==200)throw 0;var d=JSON.parse(x.responseText);S.items=d.items||[];S.sections=(d.sections||[]).slice().sort(function(a,b){return (a.kind==='selection')-(b.kind==='selection')});
  $('pv-stg').hidden=!d.preview;if(KEY&&!d.preview){$('pv-stg').hidden=false;$('pv-stg').textContent='This preview link is no longer valid. Ask the marine desk for the current link.'}
  if(!S.items.length){empty();return}$('pv-tools').hidden=false;fromHash()}catch(e){fail()}};
 x.onerror=x.ontimeout=fail;x.send()}
$('pv-g').addEventListener('click',function(e){var a=e.target.closest('a.pvx');if(a){e.preventDefault();setSec(a.getAttribute('data-s'))}});
$('pv-c').addEventListener('click',function(e){var b=e.target.closest('button');if(b){S.sub=b.getAttribute('data-u');draw()}});
$('pv-back').addEventListener('click',function(){$('pv-q').value='';S.q='';setSec('')});
window.addEventListener('popstate',fromHash);window.addEventListener('hashchange',fromHash);
var t=null;$('pv-q').addEventListener('input',function(){clearTimeout(t);var v=this.value;t=setTimeout(function(){S.q=v.trim().slice(0,60);draw()},120)});
$('pv-f').addEventListener('submit',function(e){e.preventDefault()});
load();window.__prov={state:S};
})();
