(function(){
var id=(/^#(\d+)$/.exec(location.hash)||[])[1],box=document.getElementById('pd'),st=document.getElementById('pst');
function el(t,c,x){var e=document.createElement(t);if(c)e.className=c;if(x!=null)e.textContent=x;return e}
function fail(m){st.textContent=m;var a=el('a','b2','Back to products');a.href='/products.html';st.parentNode.appendChild(a)}
if(!id){fail('Product not found.');return}
var x=new XMLHttpRequest();x.open('GET','https://dutyfreebd.com/marine-api/product/'+id);
x.onload=function(){try{var d=JSON.parse(x.responseText);if(!d.found){fail('This product is not available on the marine catalog.');return}var p=d.item;document.title=p.name+' | DutyFree BD Marine Supply';st.hidden=true;
var im=el('div','pdi');if(p.image&&p.image.indexOf('https://')===0){var g=document.createElement('img');g.src=p.image;g.alt=p.name;im.appendChild(g)}box.appendChild(im);
var b=el('div','pdb');b.appendChild(el('span','ps',[p.category,p.brand].filter(Boolean).join(' · ')||'Ship stores'));b.appendChild(el('h1',null,p.name));
var sp=el('dl','spec');function row(k,v){if(!v)return;sp.appendChild(el('dt',null,k));sp.appendChild(el('dd',null,v))}row('Product code',p.sku);row('Pack size',p.size);row('Brand',p.brand);row('Category',p.category);b.appendChild(sp);
var pr=el('div','pdp');pr.appendChild(el('small',null,'USD'));pr.appendChild(document.createTextNode(p.usd));pr.appendChild(el('span',null,'per unit'));b.appendChild(pr);
var pt=el('div','pts');pt.appendChild(el('b',null,'Availability by port'));(p.port_list||[]).forEach(function(o){pt.appendChild(el('span',o.available?'pa':'pa no',o.name+': '+(o.available?('available · delivery '+(o.charge?'USD '+o.charge:'charge on quotation')):'not yet available')))});b.appendChild(pt);
var c=el('div','cta');var q=el('a','b1','Request a Quote');q.href='/request-access.html';q.addEventListener('click',function(){try{var o=JSON.parse(sessionStorage.getItem('mq')||'{}')||{};o.msg='Quotation request: '+p.name+(p.sku?' ('+p.sku+')':'')+', quantity: ';sessionStorage.setItem('mq',JSON.stringify(o))}catch(e){}});c.appendChild(q);var k=el('a','b2','Back to products');k.href='/products.html';c.appendChild(k);b.appendChild(c);
b.appendChild(el('span','muted','Quotations are issued to verified company and crew accounts. The delivery charge depends on the port. The quotation confirms quantity, delivery place and total in USD.'));
if(p.description){b.appendChild(el('h2',null,'Description'));b.appendChild(el('p',null,p.description))}box.appendChild(b)}catch(e){fail('The product could not be loaded. Please try again.')}};
x.onerror=function(){fail('The product could not be loaded. Please try again.')};x.send();
window.addEventListener('hashchange',function(){location.reload()});
})();