(function(){
function ok(u){return typeof u==='string'&&u.indexOf('https://dutyfreebd.com/uploads/')===0}
function lk(u){return typeof u==='string'&&(u.indexOf('https://')===0||u.charAt(0)==='/')?u:''}
function apply(d){
var p=[];if(d.email)p.push('Email: '+d.email);if(d.phone)p.push('Phone: '+d.phone);if(d.whatsapp)p.push('WhatsApp: '+d.whatsapp);
if(p.length){var e=document.querySelectorAll('[data-marine-contact]');for(var i=0;i<e.length;i++)e[i].textContent='Marine desk · '+p.join(' · ')}
var s=d.site||{};var st=document.querySelector('.strip')||document.body.firstElementChild;if(s.strip&&st)st.textContent=s.strip;
var app=document.getElementById('app');var h1=app&&app.querySelector('h1');
if(h1&&location.pathname==='/'){if(s.hero_title)h1.textContent=s.hero_title;var sub=h1.nextElementSibling;if(s.hero_sub&&sub&&sub.tagName==='P')sub.textContent=s.hero_sub}
var P=d.ports||{},col={live:['#DCFCE7','#14532D'],soon:['#FDBA74','#431407'],next:['#E6ECF3','#0B2545']};
var cards=document.querySelectorAll('#ports article');for(var c=0;c<cards.length;c++){var a=cards[c],b=a.querySelector('b');if(!b)continue;var k=b.textContent.trim().toLowerCase(),x=P[k];if(!x)continue;
if(x.status==='hidden'){a.style.display='none';continue}var pill=b.nextElementSibling;if(pill){pill.textContent=x.label;var cc=col[x.status]||col.next;pill.style.background=cc[0];pill.style.color=cc[1]}
var note=b.parentNode.nextElementSibling;if(x.note&&note)note.textContent=x.note;
if(ok(x.photo)){var ph=a.firstElementChild;ph.textContent='';var im=document.createElement('img');im.src=x.photo;im.alt='Port of '+x.name;im.style.cssText='width:100%;height:100%;object-fit:cover;display:block';ph.appendChild(im)}}
var m=/^\/ship-supply-([a-z]+)\.html$/.exec(location.pathname);if(m&&P[m[1]]){var t=document.querySelector('.tag');if(t&&P[m[1]].status!=='hidden')t.textContent=P[m[1]].label}
var B=(d.banners||[]).filter(function(x){return ok(x.image)||x.title});var how=document.getElementById('how');
if(B.length&&how&&!document.getElementById('mbn')){var w=document.createElement('section');w.id='mbn';w.style.cssText='max-width:1200px;margin:0 auto;padding:40px 24px 0;display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px';
B.forEach(function(x){var l=lk(x.link),el=document.createElement(l?'a':'div');if(l)el.href=l;el.style.cssText='display:flex;flex-direction:column;border:1px solid #D9E0E8;border-radius:12px;overflow:hidden;text-decoration:none;color:#0F172A;background:#fff';
if(ok(x.image)){var im=document.createElement('img');im.src=x.image;im.alt=x.title||'';im.loading='lazy';im.style.cssText='width:100%;aspect-ratio:12/5;object-fit:cover;display:block';el.appendChild(im)}
if(x.title||x.text){var bx=document.createElement('div');bx.style.cssText='padding:14px 16px;display:flex;flex-direction:column;gap:4px';if(x.title){var tt=document.createElement('b');tt.style.fontSize='16px';tt.textContent=x.title;bx.appendChild(tt)}if(x.text){var tx=document.createElement('span');tx.style.cssText='font-size:14px;line-height:1.5;color:#475569';tx.textContent=x.text;bx.appendChild(tx)}el.appendChild(bx)}
w.appendChild(el)});how.parentNode.insertBefore(w,how)}}
var two=document.getElementById('ports');if(location.pathname==='/'&&two){var y=new XMLHttpRequest();y.open('GET','https://dutyfreebd.com/marine-api/products?home=1');y.onload=function(){try{var d=JSON.parse(y.responseText);if(!d.items||!d.items.length||document.getElementById('mhp'))return;
var w=document.createElement('section');w.id='mhp';w.style.cssText='max-width:1200px;margin:0 auto;padding:40px 24px 24px';var hd=document.createElement('div');hd.style.cssText='display:flex;flex-wrap:wrap;gap:12px;justify-content:space-between;align-items:baseline;margin-bottom:24px';
var h=document.createElement('h2');h.textContent='Products';h.style.cssText="margin:0;font-family:'Space Grotesk',sans-serif;font-size:28px;font-weight:600";var al=document.createElement('a');al.href='/products.html';al.textContent='View all '+d.total+' products →';al.style.cssText='font-size:14px;font-weight:600;text-decoration:none;color:#C2410C';hd.appendChild(h);hd.appendChild(al);w.appendChild(hd);
var g=document.createElement('div');g.style.cssText='display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px';
d.items.forEach(function(p){var a=document.createElement('a');a.href='/products.html';a.style.cssText='display:flex;flex-direction:column;border:1px solid #D9E0E8;border-radius:10px;overflow:hidden;text-decoration:none;color:#0F172A;background:#fff';
var im=document.createElement('div');im.style.cssText='aspect-ratio:1/1;border-bottom:1px solid #EEF1F5';if(p.image&&p.image.indexOf('https://')===0){var i2=document.createElement('img');i2.src=p.image;i2.alt=p.name;i2.loading='lazy';i2.style.cssText='width:100%;height:100%;object-fit:contain;display:block';im.appendChild(i2)}a.appendChild(im);
var bx=document.createElement('div');bx.style.cssText='padding:12px;display:flex;flex-direction:column;gap:6px';var n=document.createElement('b');n.style.cssText='font-size:14px;line-height:1.35';n.textContent=p.name;var pr=document.createElement('b');pr.style.cssText='font-size:15px';pr.textContent='USD '+p.usd;bx.appendChild(n);bx.appendChild(pr);a.appendChild(bx);g.appendChild(a)});
w.appendChild(g);two.parentNode.insertBefore(w,two)}catch(e){}};y.send()}
var x=new XMLHttpRequest();x.open('GET','https://dutyfreebd.com/marine-api/public');x.onload=function(){try{apply(JSON.parse(x.responseText))}catch(e){}};x.send();
})();