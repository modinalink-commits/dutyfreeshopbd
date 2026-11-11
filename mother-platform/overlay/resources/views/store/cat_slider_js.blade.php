@verbatim<script>(function(){var S=[].slice.call(document.querySelectorAll('[data-cs2]'));if(!S.length)return;var RM=window.matchMedia&&matchMedia('(prefers-reduced-motion:reduce)').matches,IV=5000;
function init(el){var vp=el.querySelector('.cs2-vp'),tr=el.querySelector('.cs2-tr'),n=+el.getAttribute('data-n'),pg=el.querySelector('.cs2-pg i'),bg=el.querySelector('.cs2-bg'),page=0,timer=0,vis=false,hov=false,sx=null,dx=0;
function per(){return Math.max(1,Math.round(parseFloat(getComputedStyle(vp).getPropertyValue('--v'))||6))}
function pages(){return Math.ceil(n/per())}
function mark(){el.classList.toggle('multi',pages()>1)}
function go(p){var t=pages();page=((p%t)+t)%t;var v=per(),off=Math.min(page*v,n-v);tr.style.transform='translate3d('+(-off*100/v)+'%,0,0)';rst()}
function rst(){clearTimeout(timer);el.classList.remove('run');if(pg){void pg.offsetWidth}if(pages()<2||!vis||RM)return;el.classList.add('run');timer=setTimeout(function(){if(hov||sx!==null){rst();return}go(page+1)},IV)}
el.style.setProperty('--iv',IV+'ms');
el.querySelector('.cs2-ar.p').onclick=function(){go(page-1)};el.querySelector('.cs2-ar.n').onclick=function(){go(page+1)};
el.addEventListener('mouseenter',function(){hov=true;el.classList.add('hov')});el.addEventListener('mouseleave',function(){hov=false;el.classList.remove('hov');rst()});
vp.addEventListener('pointerdown',function(e){if(e.pointerType==='mouse')return;sx=e.clientX;dx=0});
vp.addEventListener('pointermove',function(e){if(sx!==null)dx=e.clientX-sx});
function end(){if(sx===null)return;var d=dx;sx=null;if(Math.abs(d)>40)go(page+(d<0?1:-1));else rst()}
vp.addEventListener('pointerup',end);vp.addEventListener('pointercancel',end);
var rz;window.addEventListener('resize',function(){clearTimeout(rz);rz=setTimeout(function(){mark();go(page)},150)});
function par(){if(!vis||RM||!bg)return;var r=el.getBoundingClientRect(),h=innerHeight;bg.style.setProperty('--py',(((r.top+r.height/2)-h/2)*-.06).toFixed(1)+'px')}
var raf=0;window.addEventListener('scroll',function(){if(raf)return;raf=requestAnimationFrame(function(){raf=0;par()})},{passive:true});
mark();
if('IntersectionObserver' in window){new IntersectionObserver(function(es){es.forEach(function(x){vis=x.isIntersecting;if(vis){el.classList.add('in');par()}rst()})},{threshold:.25}).observe(el)}else{vis=true;el.classList.add('in');rst()}}
S.forEach(init);if(!RM&&'IntersectionObserver' in window){var R=[].slice.call(document.querySelectorAll('.sec-lv'));R.forEach(function(u){u.classList.add('cs2r')});var io=new IntersectionObserver(function(es){es.forEach(function(x){if(x.isIntersecting){x.target.classList.add('in');io.unobserve(x.target)}})},{threshold:.12});R.forEach(function(u){io.observe(u)})}})();</script>@endverbatim
