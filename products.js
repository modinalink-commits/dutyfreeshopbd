(function(){
var API='https://dutyfreebd.com/marine-api/products',grid=document.getElementById('grid'),info=document.getElementById('info'),more=document.getElementById('more'),qi=document.getElementById('q'),cs=document.getElementById('cat'),page=1,total=0,shown=0,busy=false,t;
function el(t,c,x){var e=document.createElement(t);if(c)e.className=c;if(x!=null)e.textContent=x;return e}
function card(p){var a=el('article','pc'),im=el('div','pi');if(p.image&&p.image.indexOf('https://')===0){var g=document.createElement('img');g.src=p.image;g.alt=p.name;g.loading='lazy';g.onerror=function(){im.textContent=''};im.appendChild(g)}a.appendChild(im);
var b=el('div','pb');b.appendChild(el('span','ps',(p.sku||'')+(p.category?' · '+p.category:'')));b.appendChild(el('b','pn',p.name));if(p.size)b.appendChild(el('span','pz','Pack: '+p.size));
var f=el('div','pf');f.appendChild(el('b','pp','USD '+p.usd));var l=el('a','pq','Request quote');l.href='/request-access.html';f.appendChild(l);b.appendChild(f);a.appendChild(b);return a}
function load(reset){if(busy)return;busy=true;if(reset){page=1;shown=0;grid.textContent=''}info.textContent='Loading...';
var u=API+'?page='+page+'&q='+encodeURIComponent(qi.value.trim())+'&category='+encodeURIComponent(cs.value);var x=new XMLHttpRequest();x.open('GET',u);
x.onload=function(){busy=false;try{var d=JSON.parse(x.responseText);total=d.total;if(reset&&cs.options.length<2){d.categories.forEach(function(c){var o=el('option',null,c.name);o.value=c.id;cs.appendChild(o)})}
d.items.forEach(function(p){grid.appendChild(card(p));shown++});info.textContent=total?('Showing '+shown+' of '+total+' products'):'No products found.';more.hidden=shown>=total}catch(e){info.textContent='The product list could not be loaded. Please try again.'}};
x.onerror=function(){busy=false;info.textContent='The product list could not be loaded. Please try again.'};x.send()}
qi.addEventListener('input',function(){clearTimeout(t);t=setTimeout(function(){load(true)},350)});cs.addEventListener('change',function(){load(true)});more.addEventListener('click',function(){page++;load(false)});load(true);
})();