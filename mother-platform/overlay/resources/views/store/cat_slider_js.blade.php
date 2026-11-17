@verbatim<script>(function(){var S=[].slice.call(document.querySelectorAll('[data-cs2]'));if(!S.length)return;var RM=window.matchMedia&&matchMedia('(prefers-reduced-motion:reduce)').matches,IV=9000;
function init(el){if(el._cs2)return;el._cs2=1;/* one slider, one set of timers: a second start is ignored */var vp=el.querySelector('.cs2-vp'),tr=el.querySelector('.cs2-tr'),n=+el.getAttribute('data-n'),pg=el.querySelector('.cs2-pg i'),bg=el.querySelector('.cs2-bg'),page=0,timer=0,vis=false,hov=false,sx=null,sy=0,dx=0,drag=false,lastW=0,lastV=0,iv=(el.hasAttribute('data-iv')?+el.getAttribute('data-iv'):IV);
function per(){return Math.max(1,Math.round(parseFloat(getComputedStyle(vp).getPropertyValue('--v'))||6))}
function pages(){return Math.ceil(n/per())}
function mark(){el.classList.toggle('multi',pages()>1)}
function pos(){var v=per();return Math.max(0,Math.min(page*v,n-v))*100/v}
function go(p){var t=pages(),from=page;page=((p%t)+t)%t;var wrap=t>2&&Math.abs(page-from)===t-1;tr.style.transition=wrap?'none':'';if(wrap){vp.classList.remove('cs2-fd');void vp.offsetWidth;vp.classList.add('cs2-fd')}tr.style.transform='translate3d('+(-pos())+'%,0,0)';if(wrap){void tr.offsetWidth;tr.style.transition=''}rst()}
function rst(){clearTimeout(timer);el.classList.remove('run');if(pg){void pg.offsetWidth}if(pages()<2||!vis||RM||!iv)return;el.classList.add('run');timer=setTimeout(function(){if(hov||sx!==null||document.hidden){rst();return}go(page+1)},iv)}
el.style.setProperty('--iv',iv+'ms');
el.querySelector('.cs2-ar.p').onclick=function(){go(page-1)};el.querySelector('.cs2-ar.n').onclick=function(){go(page+1)};
el.addEventListener('pointerenter',function(e){if(e.pointerType!=='mouse')return;hov=true;el.classList.add('hov')});el.addEventListener('pointerleave',function(e){if(!hov)return;hov=false;el.classList.remove('hov');rst()});
vp.addEventListener('pointerdown',function(e){if(e.pointerType==='mouse')return;sx=e.clientX;sy=e.clientY;dx=0;drag=false});
vp.addEventListener('pointermove',function(e){if(sx===null)return;dx=e.clientX-sx;if(!drag&&Math.abs(dx)>8&&Math.abs(dx)>Math.abs(e.clientY-sy))drag=true;if(drag&&pages()>1){tr.style.transition='none';tr.style.transform='translate3d(calc('+(-pos())+'% + '+Math.round(dx)+'px),0,0)'}});
function end(){if(sx===null)return;var d=dx,was=drag;sx=null;tr.style.transition='';if(was&&Math.abs(d)>40&&pages()>1)go(page+(d<0?1:-1));else go(page);if(was){drag=false;vp._sw=Date.now()}}
vp.addEventListener('click',function(e){if(vp._sw&&Date.now()-vp._sw<350){e.preventDefault();e.stopPropagation()}},true);
vp.addEventListener('pointerup',end);vp.addEventListener('pointercancel',end);
var rz;lastW=vp.clientWidth;lastV=per();window.addEventListener('resize',function(){clearTimeout(rz);rz=setTimeout(function(){var w=vp.clientWidth,v=per();if(w===lastW&&v===lastV)return;lastW=w;lastV=v;mark();if(page>=pages())page=pages()-1;go(page)},150)});
document.addEventListener('visibilitychange',function(){if(!document.hidden)rst()});
function par(){if(!vis||RM||!bg)return;var r=el.getBoundingClientRect(),h=innerHeight;bg.style.setProperty('--py',(((r.top+r.height/2)-h/2)*-.06).toFixed(1)+'px')}
var raf=0;window.addEventListener('scroll',function(){if(raf)return;raf=requestAnimationFrame(function(){raf=0;par()})},{passive:true});
mark();
if('IntersectionObserver' in window){new IntersectionObserver(function(es){es.forEach(function(x){vis=x.isIntersecting;if(vis){el.classList.add('in');par()}rst()})},{threshold:.25}).observe(el)}else{vis=true;el.classList.add('in');rst()}}
S.forEach(init);if(!RM&&'IntersectionObserver' in window){var R=[].slice.call(document.querySelectorAll('.sec-lv'));R.forEach(function(u){u.classList.add('cs2r')});var io=new IntersectionObserver(function(es){es.forEach(function(x){if(x.isIntersecting){x.target.classList.add('in');io.unobserve(x.target)}})},{threshold:.12});R.forEach(function(u){io.observe(u)})}})();</script>@endverbatim
