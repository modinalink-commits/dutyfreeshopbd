@verbatim
<style>
#dfmcO{position:fixed;inset:0;background:rgba(11,18,32,.45);z-index:9990;opacity:0;pointer-events:none;transition:opacity .22s}
#dfmcO.on{opacity:1;pointer-events:auto}
#dfmc{position:fixed;top:0;right:0;bottom:0;width:400px;max-width:100vw;background:#fff;z-index:9991;display:flex;flex-direction:column;transform:translateX(104%);transition:transform .25s ease;box-shadow:-10px 0 30px rgba(11,18,32,.18);font-family:inherit}
#dfmc.on{transform:none}
#dfmc .h{display:flex;align-items:center;gap:8px;padding:14px 16px;border-bottom:1px solid #E5E7EB}#dfmc .h b{font-size:17px;color:#0C161F;flex:1}#dfmc .h b span{color:#F95505}
#dfmc .x{border:0;background:#F3F4F6;width:32px;height:32px;border-radius:50%;font-size:18px;line-height:1;color:#0C161F}
#dfmc .bd{flex:1;overflow:auto;padding:10px 14px;background:#FAFAFA}
#dfmc .it{display:flex;gap:10px;background:#fff;border:1px solid #EEF0F3;border-radius:12px;padding:8px;margin-bottom:8px;box-shadow:0 1px 3px rgba(0,0,0,.04)}
#dfmc .it img{width:58px;height:58px;object-fit:contain;border-radius:8px;background:#fff;flex:none}
#dfmc .it .m{flex:1;min-width:0}#dfmc .it a{display:block;font-size:12.5px;font-weight:600;color:#0C161F;line-height:1.3;max-height:2.6em;overflow:hidden}
#dfmc .it .r{display:flex;align-items:center;justify-content:space-between;margin-top:6px}#dfmc .it .pr{font-weight:800;color:#0C161F;font-size:14px}
#dfmc .q{display:flex;align-items:center;border:1px solid #E5E7EB;border-radius:8px;overflow:hidden}#dfmc .q button{border:0;background:#fff;width:26px;height:26px;font-size:15px;color:#0C161F}#dfmc .q span{min-width:26px;text-align:center;font-size:12.5px;font-weight:700}
#dfmc .rm{border:0;background:none;color:#B91C1C;font-size:12px;margin-left:8px;padding:4px}
#dfmc .sm{padding:12px 16px;border-top:1px solid #E5E7EB;font-size:13px;background:#fff}#dfmc .sm div{display:flex;justify-content:space-between;margin:3px 0;color:#374151}#dfmc .sm .t{font-size:16px;font-weight:800;color:#0C161F;background:#FFF1E8;border-radius:10px;padding:9px 10px;margin-top:8px}#dfmc .sm .g{color:#166534;font-weight:700}
#dfmc .ac{display:grid;grid-template-columns:1fr 1.2fr;gap:8px;padding:0 16px 14px;background:#fff}#dfmc .ac a{text-align:center;border-radius:10px;padding:12px 6px;font-weight:800;font-size:14px}
#dfmc .vc{border:1.5px solid #0B1F3A;color:#0C161F;background:#fff}#dfmc .co{background:linear-gradient(135deg,#FF8A00,#F95505);color:#fff}
#dfmc .em{text-align:center;padding:50px 20px;color:#374151}#dfmc .em b{display:block;font-size:17px;color:#0C161F;margin-bottom:14px}#dfmc .em a{display:inline-block;background:#F95505;color:#fff;font-weight:800;border-radius:10px;padding:11px 22px}
#dfsb{display:none}#dfsb .go{display:none}
#dfat{position:fixed;left:50%;top:18px;transform:translate(-50%,-80px);background:#0C161F;color:#fff;border-radius:999px;padding:10px 18px;font-size:13.5px;font-weight:700;z-index:9999;box-shadow:0 8px 24px rgba(0,0,0,.28);transition:transform .3s;pointer-events:none;border:1px solid rgba(255,138,0,.6)}#dfat.on{transform:translate(-50%,0)}
@media(max-width:760px){
#dfmc{top:auto;left:0;width:100%;height:82vh;border-radius:18px 18px 0 0;transform:translateY(104%)}#dfmc.on{transform:none}
/* mobile (owner 20 Nov 2026): a cart bar above the bottom menu, left of the chat buttons - item count, total and Checkout. It replaced the round button, which sat on top of the product cards' buttons */
#dfsb.on{display:flex;align-items:center;gap:9px;position:fixed;left:10px;right:78px;bottom:82px;z-index:48;height:52px;box-sizing:border-box;padding:0 6px;border-radius:14px;background:#0C161F;color:#fff;box-shadow:0 8px 22px rgba(11,18,32,.32);cursor:pointer;-webkit-tap-highlight-color:transparent}
#dfsb span{display:block;order:2;flex:1;min-width:0;font:700 13.5px/1.2 Inter,Bornomala,sans-serif;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
#dfsb button{order:1;position:relative;flex:none;display:flex;align-items:center;justify-content:center;width:40px;height:40px;padding:0;border:0;border-radius:10px;background:rgba(255,255,255,.12);color:#fff;font-size:0;cursor:pointer}
#dfsb button svg{width:22px;height:22px}
#dfsb .bn{display:block;position:absolute;left:auto;bottom:auto;top:-6px;right:-6px;box-shadow:none;z-index:auto;min-width:19px;height:19px;padding:0 4px;border-radius:10px;background:#F95505;color:#fff;border:2px solid #0C161F;font:800 10.5px/15px Inter,Bornomala,sans-serif;text-align:center;box-sizing:border-box}
#dfsb .go{order:3;flex:none;text-decoration:none;display:flex;align-items:center;height:40px;padding:0 14px;border-radius:10px;background:#F95505;color:#fff;font:800 13px/1 Inter,Bornomala,sans-serif}
body.dfsb-on{padding-bottom:142px!important}
#dfat{top:auto;bottom:144px;transform:translate(-50%,40px);opacity:0}#dfat.on{transform:translate(-50%,0);opacity:1}}
</style>
<div id="dfmcO"></div>
<aside id="dfmc" role="dialog" aria-label="Your cart" aria-hidden="true"><div class="h"><b>Your Cart <span id="dfmcN">(0)</span></b><button type="button" class="x" id="dfmcX" aria-label="Close">×</button></div><div class="bd" id="dfmcB"></div><div id="dfmcF"></div></aside>
<div id="dfsb"><span id="dfsbT"></span><button type="button" id="dfsbB" aria-label="View cart"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2.5 3.5h2.7l2.4 11.2a1.6 1.6 0 0 0 1.6 1.3h8.1a1.6 1.6 0 0 0 1.6-1.2l1.4-7.1H6.1"/></svg><i class="bn" id="dfsbN">0</i></button><a class="go" href="/checkout">Checkout</a></div>
<div id="dfat" role="status" aria-live="polite">Added to Cart ✓</div>
<script>
(function(){
var $=function(i){return document.getElementById(i)},tk=function(x){return '৳'+Math.round(+x).toLocaleString('en-US')},mc=$('dfmc'),ov=$('dfmcO'),seq=0;
var onCo=/^\/(checkout|order|success)/.test(location.pathname);
function ids(){var c=DF.cart(),a=[];for(var k in c)if(c[k].q>0)a.push(k+':'+c[k].q);return a.join(',')}
function totals(){var c=DF.cart(),n=0,t=0;for(var k in c){n+=c[k].q;t+=c[k].q*c[k].p}return{n:n,t:t}}
function open(){mc.classList.add('on');ov.classList.add('on');mc.setAttribute('aria-hidden','false');document.documentElement.style.overflow='hidden';render()}
function close(){mc.classList.remove('on');ov.classList.remove('on');mc.setAttribute('aria-hidden','true');document.documentElement.style.overflow=''}
window.dfOpenCart=open;window.dfCloseCart=close;
function el(t,c,h){var e=document.createElement(t);if(c)e.className=c;if(h!=null)e.textContent=h;return e}
function render(){var my=++seq,s=ids(),B=$('dfmcB'),F=$('dfmcF');
 if(!s){$('dfmcN').textContent='(0)';B.innerHTML='<div class="em"><b>Your Cart is Empty</b><a href="/">Start Shopping</a></div>';F.innerHTML='';return}
 fetch('/cart-items?ids='+encodeURIComponent(s)+'&area=1',{headers:{Accept:'application/json'}}).then(function(r){return r.json()}).then(function(q){if(my!==seq)return;
  var n=0;B.innerHTML='';F.innerHTML='';
  q.items.forEach(function(p){n+=p.qty;var row=el('div','it'),img=el('img');img.src=p.image||'';img.alt='';img.loading='lazy';var m=el('div','m'),a=el('a','',p.name);a.href=p.url||'#';var r=el('div','r'),pr=el('span','pr',tk(p.line)),w=el('div');w.style.cssText='display:flex;align-items:center';
   var qd=el('div','q'),mi=el('button','','−'),qs=el('span','',p.qty),pl=el('button','','+');mi.type=pl.type='button';
   mi.onclick=function(){set(p,p.qty-1)};pl.onclick=function(){set(p,p.qty+1)};qd.append(mi,qs,pl);var rm=el('button','rm','Remove');rm.type='button';rm.onclick=function(){set(p,0)};w.append(qd,rm);r.append(pr,w);m.append(a,r);row.append(img,m);B.appendChild(row)});
  $('dfmcN').textContent='('+n+')';
  var sm=el('div','sm');function ln(l,v,c){var d=el('div',c||'');d.append(el('span','',l),el('span','',v));sm.appendChild(d)}
  ln('Subtotal ('+n+' item'+(n>1?'s':'')+')',tk(q.sub));if(q.disc>0)ln('Discount','− '+tk(q.disc));ln('Delivery Charge',q.ship>0?tk(q.ship):'Free',q.ship>0?'':'g');ln('Total Payable',tk(q.total),'t');F.appendChild(sm);
  var ac=el('div','ac'),v=el('a','vc','View Cart'),c=el('a','co','Checkout Now');v.href=c.href='/checkout';ac.append(v,c);F.appendChild(ac)}).catch(function(){B.innerHTML='<div class="em"><b>Could not load cart</b><a href="/checkout">Go to Checkout</a></div>'})}
function set(p,qty){var c=DF.cart(),k=String(p.id);if(qty<=0){delete c[k];DF.p('dfc',c)}else{c[k]={q:Math.min(50,qty),p:p.price};DF.p('dfc',c)}DF.tab()}
var T=DF.tab;DF.tab=function(){T();bar();var h=document.querySelector('.hi .cl');var x=totals();if(h)h.textContent=x.n?tk(x.t):'Cart';if(mc.classList.contains('on'))render()};
function bar(){var x=totals(),b=$('dfsb');if(!b)return;var on=x.n>0&&!onCo;b.classList.toggle('on',on);document.body.classList.toggle('dfsb-on',on);var tx=x.n+' Item'+(x.n>1?'s':'')+' · '+tk(x.t);$('dfsbT').textContent=tx;$('dfsbN').textContent=x.n>99?'99+':x.n;$('dfsbB').setAttribute('aria-label','View cart: '+tx)}
DF.nt=function(){var t=$('dfat');t.classList.add('on');clearTimeout(DF._nt);DF._nt=setTimeout(function(){t.classList.remove('on')},1800);document.querySelectorAll('.cn,#bnC').forEach(function(e){e.classList.remove('dfnb');void e.offsetWidth;e.classList.add('dfnb')});if(!onCo&&innerWidth>760&&!DF._noOpen)setTimeout(open,350)};
$('dfmcX').onclick=close;ov.onclick=close;document.addEventListener('keydown',function(e){if(e.key==='Escape')close()});$('dfsb').onclick=function(e){if(e.target.closest&&e.target.closest('a'))return;open()};
document.querySelectorAll('.dfcb').forEach(function(b){b.addEventListener('click',function(e){e.preventDefault();open()})});
window.addEventListener('storage',function(e){if(e.key==='dfc')DF.tab()});
DF.tab();
})();
</script>
<style>
/* 20 Nov 2026 (owner): product cards get a cart button beside ORDER NOW; once in the cart it becomes a quantity stepper, so several products can be taken without leaving the page */
.pc .cab{display:flex;align-items:stretch;gap:5px;margin:2px 2px 0}
.pc .cab .on{flex:1;min-width:0;margin:0}
.pc .cab button{font-family:inherit;cursor:pointer;-webkit-tap-highlight-color:transparent}
.pc .cab .cb1{flex:none;width:44px;min-height:40px;border:1.5px solid #0C161F;border-radius:8px;background:#fff;color:#0C161F;display:flex;align-items:center;justify-content:center;padding:0}
.pc .cab .cb1:hover{background:#0C161F;color:#fff}
.pc .cab:not(:has(.on)) .cb1{flex:1;width:auto}
.pc .cab .cq{display:none;flex:1;min-width:0;min-height:40px;box-sizing:border-box;border:1.5px solid #0C161F;border-radius:8px;align-items:center;justify-content:space-between;padding:0 3px;background:#fff}
.pc .cab .cq b{font-size:14px;font-weight:800;color:#0C161F;min-width:18px;text-align:center}
.pc .cab .cq button{width:32px;height:32px;border:0;border-radius:6px;background:#F3F4F6;color:#0C161F;font-size:18px;font-weight:700;line-height:1;padding:0}
.pc .cab .cq .cp{background:#0C161F;color:#fff}
.pc .cab.in .cb1{display:none}.pc .cab.in .cq{display:flex}
.pc .cab.in .on{flex:0 0 38%}.pc .cab.in .on svg{display:none}
.pc{position:relative}
.pc .cbd{position:absolute;right:6px;top:6px;z-index:4;background:#067647;color:#fff;font-size:10px;font-weight:800;border-radius:6px;padding:3px 7px;pointer-events:none}
@media(max-width:760px){.pc .cab{gap:4px}.pc .cab .cb1{width:44px;min-height:44px}.pc .cab .cq{min-height:44px}.pc .cab .on{min-height:44px}.pc .cab .cq button{width:30px;height:34px}.pc .cab.in .on{flex:0 0 36%}.pc .cbd{right:4px;top:4px;font-size:9px;padding:2px 5px}}
</style>
<script>
(function(){
if(!window.DF||!DF.cart)return;
function qty(id){var c=DF.cart();return c[id]?(parseInt(c[id].q)||0):0}
function paint(){var ws=document.querySelectorAll('.pc .cab');for(var i=0;i<ws.length;i++){var w=ws[i],id=w.getAttribute('data-id'),n=qty(id);w.classList.toggle('in',n>0);var b=w.querySelector('.cq b');if(b)b.textContent=n||1;
 var a=w.querySelector('a.on');if(a){if(!a._h)a._h=a.getAttribute('href');a.setAttribute('href',n>0?'/checkout':a._h);var t=a.querySelector('i');if(t)t.textContent=n>0?'ORDER':'ORDER NOW'}
 var pc=w.closest('.pc'),bd=pc&&pc.querySelector('.cbd');if(bd){bd.hidden=!(n>0);bd.textContent='In cart: '+n}}}
document.addEventListener('click',function(e){var t=e.target.closest&&e.target.closest('.pc .cab button');if(!t)return;e.preventDefault();e.stopPropagation();
 var w=t.closest('.cab'),id=w.getAttribute('data-id'),p=+w.getAttribute('data-p')||0,x=w.getAttribute('data-x')||id,n=qty(id);
 if(t.classList.contains('cb1')){DF._noOpen=1;try{DF.add(id,1,p,false,x);if(DF.ev)DF.ev('add_to_cart',{value:p,items:[{item_id:String(x),price:p,quantity:1}]})}finally{DF._noOpen=0}}
 else if(t.classList.contains('cp')){if(n<50)DF.add(id,n+1,p,true,x)}
 else if(t.classList.contains('cm')){if(n<=1){var c=DF.cart();delete c[id];DF.p('dfc',c);DF.tab()}else DF.add(id,n-1,p,true,x)}
 paint()});
var T=DF.tab;DF.tab=function(){T.apply(this,arguments);try{paint()}catch(e){}};
window.addEventListener('pageshow',paint);paint();
try{var mt=0;new MutationObserver(function(){clearTimeout(mt);mt=setTimeout(paint,300)}).observe(document.body,{childList:true,subtree:true})}catch(e){} /* home sections that load later */
})();
</script>
@endverbatim
