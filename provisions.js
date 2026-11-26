(function(){
var API='https://dutyfreebd.com/marine-api/rfq',PORTAL='https://dutyfreebd.com/marine-portal/orders/new';
var KEY=(/[?&]preview=([0-9a-f]{32})(?:&|$)/.exec(location.search)||[])[1]||'';
var S={items:[],sections:[],sec:'',q:''};
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
function match(x){if(S.sec&&x.section!==S.sec)return false;if(!S.q)return true;var q=S.q.toLowerCase();return x.sku.toLowerCase().indexOf(q)>=0||x.name.toLowerCase().indexOf(q)>=0||(x.brand||'').toLowerCase().indexOf(q)>=0}
function draw(){var o=S.items.filter(match),g=$('pv-g');
 g.innerHTML=o.map(card).join('')||'<div class="pve card" style="grid-column:1/-1">No product matches'+(S.q?' "'+esc(S.q)+'"':'')+(S.sec?' in this category':'')+'. Search by product code (for example MDP-DP-001) or by name.</div>';
 var sn=S.sec?(S.sections.filter(function(s){return s.slug===S.sec})[0]||{}).name:'All categories';
 $('pv-s').innerHTML='<b>'+o.length+'</b> product'+(o.length===1?'':'s')+' · '+esc(sn)+(S.q?' · search "'+esc(S.q)+'"':'')+' · all prices by USD quotation';
 [].forEach.call(document.querySelectorAll('#pv-c button'),function(b){b.setAttribute('aria-pressed',b.getAttribute('data-s')===S.sec?'true':'false')});$('pv-sec').value=S.sec}
function setSec(s){S.sec=s;try{history.replaceState(null,'',location.pathname+location.search+(s?'#'+s:''))}catch(e){}draw()}
function chips(){var h='<button type="button" data-s="" aria-pressed="true">All<span>'+S.items.length+'</span></button>',o='<option value="">All categories ('+S.items.length+')</option>';
 S.sections.forEach(function(s){var sel=s.kind==='selection';h+='<button type="button" data-s="'+esc(s.slug)+'" aria-pressed="false"'+(sel?' class="sel" title="A selection from Chattogram suppliers"':'')+'>'+esc(s.name)+'<span>'+s.count+'</span></button>';o+='<option value="'+esc(s.slug)+'">'+esc(s.name)+' ('+s.count+')</option>'});
 $('pv-c').innerHTML=h;$('pv-sec').innerHTML=o}
function empty(d){$('pv-tools').hidden=true;$('pv-s').textContent='';
 $('pv-g').innerHTML='<div class="pve card" style="grid-column:1/-1"><b>The provisions catalogue is being prepared.</b><br>Send your store list from your account and our marine desk answers with a quotation in USD.<br><br><a href="/request-access">Request access</a> · <a href="/login">Vessel login</a></div>'}
function fail(){$('pv-g').innerHTML='<div class="pve card" style="grid-column:1/-1">The catalogue could not be loaded just now. Please try again in a minute.</div>'}
function load(){$('pv-g').innerHTML='<div class="sk"></div><div class="sk"></div><div class="sk"></div><div class="sk"></div>';
 var x=new XMLHttpRequest();x.open('GET',API+(KEY?'?preview='+KEY:''));x.timeout=20000;
 x.onload=function(){try{if(x.status!==200)throw 0;var d=JSON.parse(x.responseText);S.items=d.items||[];S.sections=(d.sections||[]).slice().sort(function(a,b){return (a.kind==='selection')-(b.kind==='selection')});
  $('pv-stg').hidden=!d.preview;if(KEY&&!d.preview){$('pv-stg').hidden=false;$('pv-stg').textContent='This preview link is no longer valid. Ask the marine desk for the current link.'}
  if(!S.items.length){empty(d);return}$('pv-tools').hidden=false;chips();var h=decodeURIComponent((location.hash||'').slice(1));S.sec=S.sections.some(function(s){return s.slug===h})?h:'';draw()}catch(e){fail()}};
 x.onerror=x.ontimeout=fail;x.send()}
$('pv-c').addEventListener('click',function(e){var b=e.target.closest('button');if(b)setSec(b.getAttribute('data-s'))});
$('pv-sec').addEventListener('change',function(){setSec(this.value)});
var t=null;$('pv-q').addEventListener('input',function(){clearTimeout(t);var v=this.value;t=setTimeout(function(){S.q=v.trim().slice(0,60);draw()},120)});
$('pv-f').addEventListener('submit',function(e){e.preventDefault()});
load();window.__prov={state:S};
})();
