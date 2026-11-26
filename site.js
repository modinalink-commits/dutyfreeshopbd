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
var bd=b.parentNode.parentNode;if(!bd.querySelector('.dch')){var dc=document.createElement('span');dc.className='dch';dc.style.cssText='font-size:13px;font-weight:500;color:#0B2545';dc.textContent='Delivery charge by weight and distance, stated on your quotation';bd.insertBefore(dc,bd.lastElementChild)}
if(ok(x.photo)){var ph=a.firstElementChild;ph.textContent='';var im=document.createElement('img');im.src=x.photo;im.alt='Port of '+x.name;im.style.cssText='width:100%;height:100%;object-fit:cover;display:block';ph.appendChild(im)}}
var m=/^\/ship-supply-([a-z]+)(?:\.html)?$/.exec(location.pathname);if(m&&P[m[1]]){var t=document.querySelector('.tag');if(t&&P[m[1]].status!=='hidden')t.textContent=P[m[1]].label}
var B=(d.banners||[]).filter(function(x){return ok(x.image)||x.title});var how=document.getElementById('how');
if(B.length&&how&&!document.getElementById('mbn')){var w=document.createElement('section');w.id='mbn';w.style.cssText='max-width:1200px;margin:0 auto;padding:40px 24px 0;display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px';
B.forEach(function(x){var l=lk(x.link),el=document.createElement(l?'a':'div');if(l)el.href=l;el.style.cssText='display:flex;flex-direction:column;border:1px solid #D9E0E8;border-radius:12px;overflow:hidden;text-decoration:none;color:#0C161F;background:#fff';
if(ok(x.image)){var im=document.createElement('img');im.src=x.image;im.alt=x.title||'';im.loading='lazy';im.style.cssText='width:100%;aspect-ratio:12/5;object-fit:cover;display:block';el.appendChild(im)}
if(x.title||x.text){var bx=document.createElement('div');bx.style.cssText='padding:14px 16px;display:flex;flex-direction:column;gap:4px';if(x.title){var tt=document.createElement('b');tt.style.fontSize='16px';tt.textContent=x.title;bx.appendChild(tt)}if(x.text){var tx=document.createElement('span');tx.style.cssText='font-size:14px;line-height:1.5;color:#475569';tx.textContent=x.text;bx.appendChild(tx)}el.appendChild(bx)}
w.appendChild(el)});how.parentNode.insertBefore(w,how)}}
function mk(t,c,x){var e=document.createElement(t);if(c)e.className=c;if(x!=null)e.textContent=x;return e}
var how=document.getElementById('how');if(location.pathname==='/'&&how){var y=new XMLHttpRequest();y.open('GET','https://dutyfreebd.com/marine-api/products?home=1');y.onload=function(){try{var d=JSON.parse(y.responseText);if(!d.items||!d.items.length||document.getElementById('mhp'))return;
var PN={chattogram:'Chattogram',mongla:'Mongla',payra:'Payra'},port='chattogram',avs=[];
var w=mk('section','msec');w.id='mhp';var hd=mk('div','msec-h');hd.appendChild(mk('h2',null,'Ship stores and crew supplies'));var al=mk('a',null,'View all '+Number(d.total).toLocaleString('en-US')+' products →');al.href='/products';hd.appendChild(al);w.appendChild(hd);
var tl=mk('div','mtools');tl.appendChild(mk('span','lb','Port of call'));var sg=mk('div','mseg');function setPort(k){port=k;for(var i=0;i<sg.children.length;i++)sg.children[i].className=sg.children[i].value===k?'on':'';avs.forEach(function(f){f()});al.href='/products#port='+k}
Object.keys(PN).forEach(function(k){var bt=mk('button',null,PN[k]);bt.type='button';bt.value=k;bt.addEventListener('click',function(){setPort(k)});sg.appendChild(bt)});tl.appendChild(sg);
var sf=mk('form','msearch');var si=document.createElement('input');si.type='search';si.maxLength=60;si.placeholder='Search by product name, brand or code';si.setAttribute('aria-label','Search products');var sb=mk('button',null,'Search');sb.type='submit';sf.appendChild(si);sf.appendChild(sb);
sf.addEventListener('submit',function(e){e.preventDefault();location.href='/products#port='+port+'&q='+encodeURIComponent(si.value.trim())});tl.appendChild(sf);w.appendChild(tl);
if(d.categories&&d.categories.length){var ch=mk('div','mchips');d.categories.slice(0,10).forEach(function(c){var l=mk('a',null,c.name);l.href='/products#c='+c.id;ch.appendChild(l)});w.appendChild(ch)}
var g=mk('div','mgrid');function card(p){var a=mk('a','mcard');a.href='/product#'+p.id;var im=mk('div','im');if(p.image&&p.image.indexOf('https://')===0){var i2=document.createElement('img');i2.src=p.image;i2.alt=p.name;i2.loading='lazy';i2.onerror=function(){i2.style.visibility='hidden'};im.appendChild(i2)}a.appendChild(im);
var bx=mk('div','bd');bx.appendChild(mk('span','ct',p.category||'Ship stores'));bx.appendChild(mk('span','nm',p.name));bx.appendChild(mk('span','mt',p.size?('Pack: '+p.size):(p.sku?('Code: '+p.sku):' ')));
var av=mk('span','av');avs.push(function(){var ok=(p.ports||[]).indexOf(port)>-1;av.className=ok?'av':'av no';av.textContent=ok?('Available at '+PN[port]):('Not yet at '+PN[port])});bx.appendChild(av);
var pr=mk('span','pr');pr.appendChild(mk('small',null,'USD'));pr.appendChild(document.createTextNode(p.usd));bx.appendChild(pr);a.appendChild(bx);return a}
var two=(d.essentials||[]).length>=3||(d.premium||[]).length>=3;if(!two){d.items.slice(0,8).forEach(function(p){g.appendChild(card(p))});w.appendChild(g)}var first=two;
var red=window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches;
function slider(title,list,more,href,delay){if(!list||list.length<3)return;var ph=mk('div','msec-h');ph.style.marginTop=first?'24px':'44px';first=false;ph.appendChild(mk('h2',null,title));var rt=mk('div','mnav');if(more){var ml=mk('a',null,more);ml.href=href;rt.appendChild(ml)}var b1=mk('button',null,'‹'),b2=mk('button',null,'›');b1.type=b2.type='button';b1.setAttribute('aria-label','Previous products');b2.setAttribute('aria-label','Next products');rt.appendChild(b1);rt.appendChild(b2);ph.appendChild(rt);w.appendChild(ph);
var sl=mk('div','mslide');list.forEach(function(p){sl.appendChild(card(p))});w.appendChild(sl);var hold=0;
function step(n){var c=sl.firstElementChild;if(!c)return;var wd=c.getBoundingClientRect().width+(parseFloat(getComputedStyle(sl).columnGap)||16),max=sl.scrollWidth-sl.clientWidth,to=sl.scrollLeft+n*wd;if(max<8)return;if(to>max+8)to=0;if(to<-8)to=max;sl.scrollTo({left:to,behavior:'smooth'})}
b1.addEventListener('click',function(){hold=Date.now()+9000;step(-1)});b2.addEventListener('click',function(){hold=Date.now()+9000;step(1)});
sl.addEventListener('mouseenter',function(){hold=Infinity});sl.addEventListener('mouseleave',function(){hold=0});sl.addEventListener('touchstart',function(){hold=Date.now()+9000},{passive:true});
if(!red)setTimeout(function(){setInterval(function(){if(Date.now()>hold&&!document.hidden){var r=sl.getBoundingClientRect();if(r.bottom>0&&r.top<innerHeight)step(1)}},3600)},delay)}
slider('Ship and crew essentials',d.essentials,d.essential_total?('View all '+Number(d.essential_total).toLocaleString('en-US')+' →'):'','/products#e=1',0);
slider('Premium selection',d.premium,'','',1800);
setPort(port);how.parentNode.insertBefore(w,how)}catch(e){}};y.send()}
var ff=document.getElementById('ftr-f');if(ff)ff.addEventListener('submit',function(e){e.preventDefault();var v=document.getElementById('ftr-i').value.replace(/\D/g,'');location.href=v.length===7?('/track#'+v):'/track'});
var go=document.getElementById('mq-go');if(go)go.addEventListener('click',function(){try{sessionStorage.setItem('mq',JSON.stringify({imo:document.getElementById('mq-imo').value.replace(/\D/g,'').slice(0,7),port:document.getElementById('mq-port').value}))}catch(e){}});
if(location.pathname==='/request-access'){var vi=document.querySelector('input[name=imo]');if(vi&&!document.getElementById('mtl')){var mt=document.createElement('a');mt.id='mtl';mt.target='_blank';mt.rel='noopener';mt.href='https://www.marinetraffic.com/en/ais/home';mt.textContent='Find your vessel or IMO number on MarineTraffic ↗';mt.style.cssText='font-size:13px;font-weight:600;text-decoration:none;color:#F95505;margin-top:2px';
mt.addEventListener('click',function(){var vn=document.querySelector('input[name=vessel]'),k=(vi.value.replace(/\D/g,'').slice(0,7))||(vn?vn.value.trim().slice(0,60):'');mt.href=k?('https://www.marinetraffic.com/en/ais/index/search/all?keyword='+encodeURIComponent(k)):'https://www.marinetraffic.com/en/ais/home'});vi.parentNode.appendChild(mt)}
try{var q=JSON.parse(sessionStorage.getItem('mq')||'null');if(q){var fi=document.querySelector('input[name=imo]'),fp=document.querySelector('select[name=port]');if(fi&&q.imo&&!fi.value)fi.value=q.imo;if(fp&&q.port)fp.value=q.port;var fm=document.querySelector('[name=message]');if(fm&&q.msg&&!fm.value)fm.value=q.msg}}catch(e){}}
if(location.pathname==='/'&&'IntersectionObserver' in window&&!(window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches)){var io=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting){e.target.classList.add('in');io.unobserve(e.target)}})},{rootMargin:'0px 0px -8% 0px'});
var rs=document.querySelectorAll('#how>div>div,#ports article,#app section:not(.mh):not(.mq):not(#how):not(#ports)>div>div');for(var r=0;r<rs.length;r++){var el=rs[r];if(el.getBoundingClientRect().top>innerHeight*0.9){el.classList.add('rv');el.style.transitionDelay=(r%4)*70+'ms';io.observe(el)}}}
var x=new XMLHttpRequest();x.open('GET','https://dutyfreebd.com/marine-api/public');x.onload=function(){try{apply(JSON.parse(x.responseText))}catch(e){}};x.send();
})();