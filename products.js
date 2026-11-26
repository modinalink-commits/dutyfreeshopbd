(function(){
var API='https://dutyfreebd.com/marine-api/products',PN={chattogram:'Chattogram',mongla:'Mongla',payra:'Payra'},grid=document.getElementById('grid'),info=document.getElementById('info'),more=document.getElementById('more'),qi=document.getElementById('q'),cs=document.getElementById('cat'),ps=document.getElementById('port'),page=1,total=0,shown=0,busy=false,again=false,t,H={};
location.hash.slice(1).split('&').forEach(function(kv){var i=kv.indexOf('=');if(i>0){try{H[kv.slice(0,i)]=decodeURIComponent(kv.slice(i+1))}catch(e){}}});
if(H.q)qi.value=H.q.slice(0,60);if(PN[H.port])ps.value=H.port;var hc=/^\d+$/.test(H.c||'')?H.c:'';
function el(t,c,x){var e=document.createElement(t);if(c)e.className=c;if(x!=null)e.textContent=x;return e}
function card(p){var a=el('article','pc'),u='/product.html#'+p.id,im=el('a','pi');im.href=u;if(p.image&&p.image.indexOf('https://')===0){var g=document.createElement('img');g.src=p.image;g.alt=p.name;g.loading='lazy';g.onerror=function(){im.textContent=''};im.appendChild(g)}a.appendChild(im);
var b=el('div','pb');b.appendChild(el('span','ps',p.category||'Ship stores'));var n=el('a','pn',p.name);n.href=u;b.appendChild(n);b.appendChild(el('span','pz',(p.size?'Pack: '+p.size:'')+(p.size&&p.sku?' · ':'')+(p.sku?'Code: '+p.sku:'')));
var pl=(p.ports||[]).map(function(k){return PN[k]}).filter(Boolean);b.appendChild(el('span',pl.length?'pa':'pa no',pl.length?'Available at '+pl.join(', '):'Availability on request'));
var f=el('div','pf');f.appendChild(el('b','pp','USD '+p.usd));var l=el('a','pq','View details');l.href=u;f.appendChild(l);b.appendChild(f);a.appendChild(b);return a}
function load(reset){if(busy){again=again||reset;return}busy=true;if(reset){page=1;shown=0;grid.textContent=''}info.textContent='Loading...';
var u=API+'?page='+page+'&q='+encodeURIComponent(qi.value.trim())+'&category='+encodeURIComponent(hc||cs.value)+'&port='+encodeURIComponent(ps.value);var x=new XMLHttpRequest();x.open('GET',u);
function done(){busy=false;if(again){again=false;load(true)}}
x.onload=function(){try{var d=JSON.parse(x.responseText);total=d.total;if(cs.options.length<2){d.categories.forEach(function(c){var o=el('option',null,c.name);o.value=c.id;cs.appendChild(o)});if(hc){cs.value=hc;hc=''}}
d.items.forEach(function(p){grid.appendChild(card(p));shown++});var w=ps.value?' at '+PN[ps.value]:'';info.textContent=total?('Showing '+shown+' of '+Number(total).toLocaleString('en-US')+' products'+w):('No products found'+w+'. Try another port or a shorter search.');more.hidden=shown>=total}catch(e){info.textContent='The product list could not be loaded. Please try again.'}done()};
x.onerror=function(){info.textContent='The product list could not be loaded. Please try again.';done()};x.send()}
qi.addEventListener('input',function(){clearTimeout(t);t=setTimeout(function(){load(true)},350)});cs.addEventListener('change',function(){hc='';load(true)});ps.addEventListener('change',function(){load(true)});more.addEventListener('click',function(){page++;load(false)});load(true);
})();