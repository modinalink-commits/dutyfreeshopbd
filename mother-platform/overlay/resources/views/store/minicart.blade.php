@verbatim
<style>
#dfmcO{position:fixed;inset:0;background:rgba(11,18,32,.45);z-index:9990;opacity:0;pointer-events:none;transition:opacity .22s}
#dfmcO.on{opacity:1;pointer-events:auto}
#dfmc{position:fixed;top:0;right:0;bottom:0;width:400px;max-width:100vw;background:#fff;z-index:9991;display:flex;flex-direction:column;transform:translateX(104%);transition:transform .25s ease;box-shadow:-10px 0 30px rgba(11,18,32,.18);font-family:inherit}
#dfmc.on{transform:none}
#dfmc .h{display:flex;align-items:center;gap:8px;padding:14px 16px;border-bottom:1px solid #E5E7EB}#dfmc .h b{font-size:17px;color:#0B1F3A;flex:1}#dfmc .h b span{color:#FF5A00}
#dfmc .x{border:0;background:#F3F4F6;width:32px;height:32px;border-radius:50%;font-size:18px;line-height:1;color:#0B1F3A}
#dfmc .bd{flex:1;overflow:auto;padding:10px 14px;background:#FAFAFA}
#dfmc .it{display:flex;gap:10px;background:#fff;border:1px solid #EEF0F3;border-radius:12px;padding:8px;margin-bottom:8px;box-shadow:0 1px 3px rgba(0,0,0,.04)}
#dfmc .it img{width:58px;height:58px;object-fit:contain;border-radius:8px;background:#fff;flex:none}
#dfmc .it .m{flex:1;min-width:0}#dfmc .it a{display:block;font-size:12.5px;font-weight:600;color:#0B1F3A;line-height:1.3;max-height:2.6em;overflow:hidden}
#dfmc .it .r{display:flex;align-items:center;justify-content:space-between;margin-top:6px}#dfmc .it .pr{font-weight:800;color:#0B1F3A;font-size:14px}
#dfmc .q{display:flex;align-items:center;border:1px solid #E5E7EB;border-radius:8px;overflow:hidden}#dfmc .q button{border:0;background:#fff;width:26px;height:26px;font-size:15px;color:#0B1F3A}#dfmc .q span{min-width:26px;text-align:center;font-size:12.5px;font-weight:700}
#dfmc .rm{border:0;background:none;color:#DC2626;font-size:12px;margin-left:8px;padding:4px}
#dfmc .sm{padding:12px 16px;border-top:1px solid #E5E7EB;font-size:13px;background:#fff}#dfmc .sm div{display:flex;justify-content:space-between;margin:3px 0;color:#374151}#dfmc .sm .t{font-size:16px;font-weight:800;color:#0B1F3A;background:#FFF1E8;border-radius:10px;padding:9px 10px;margin-top:8px}#dfmc .sm .g{color:#15803D;font-weight:700}
#dfmc .ac{display:grid;grid-template-columns:1fr 1.2fr;gap:8px;padding:0 16px 14px;background:#fff}#dfmc .ac a{text-align:center;border-radius:10px;padding:12px 6px;font-weight:800;font-size:14px}
#dfmc .vc{border:1.5px solid #0B1F3A;color:#0B1F3A;background:#fff}#dfmc .co{background:linear-gradient(135deg,#FF8A00,#FF5A00);color:#fff}
#dfmc .em{text-align:center;padding:50px 20px;color:#374151}#dfmc .em b{display:block;font-size:17px;color:#0B1F3A;margin-bottom:14px}#dfmc .em a{display:inline-block;background:#FF5A00;color:#fff;font-weight:800;border-radius:10px;padding:11px 22px}
#dfsb{display:none}
#dfat{position:fixed;left:50%;top:18px;transform:translate(-50%,-80px);background:#0B1F3A;color:#fff;border-radius:999px;padding:10px 18px;font-size:13.5px;font-weight:700;z-index:9999;box-shadow:0 8px 24px rgba(0,0,0,.28);transition:transform .3s;pointer-events:none;border:1px solid rgba(255,138,0,.6)}#dfat.on{transform:translate(-50%,0)}
@media(max-width:760px){
#dfmc{top:auto;left:0;width:100%;height:82vh;border-radius:18px 18px 0 0;transform:translateY(104%)}#dfmc.on{transform:none}
/* mobile: a small round cart button in the middle (Cart centre, Chat right), with the item count */
#dfsb.on{display:block;position:fixed;left:50%;bottom:88px;transform:translateX(-50%);z-index:48;width:54px;height:54px}
#dfsb span{display:none}
#dfsb button{position:relative;display:flex;align-items:center;justify-content:center;width:54px;height:54px;padding:0;border:2.5px solid #0B1220;border-radius:50%;background:linear-gradient(135deg,#FF8A00,#FF5A00);color:#fff;font-size:0;box-shadow:0 6px 16px rgba(255,90,0,.42);cursor:pointer}
#dfsb button svg{width:25px;height:25px}
#dfsb .bn{position:absolute;top:-7px;right:-7px;min-width:21px;height:21px;padding:0 5px;border-radius:11px;background:#0B1220;color:#fff;border:2px solid #fff;font:800 11px/17px system-ui,-apple-system,Roboto,Arial,sans-serif;text-align:center}
#dfat{top:auto;bottom:128px;transform:translate(-50%,40px);opacity:0}#dfat.on{transform:translate(-50%,0);opacity:1}}
</style>
<div id="dfmcO"></div>
<aside id="dfmc" role="dialog" aria-label="Your cart" aria-hidden="true"><div class="h"><b>Your Cart <span id="dfmcN">(0)</span></b><button type="button" class="x" id="dfmcX" aria-label="Close">×</button></div><div class="bd" id="dfmcB"></div><div id="dfmcF"></div></aside>
<div id="dfsb"><span id="dfsbT"></span><button type="button" id="dfsbB" aria-label="View cart"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2.5 3.5h2.7l2.4 11.2a1.6 1.6 0 0 0 1.6 1.3h8.1a1.6 1.6 0 0 0 1.6-1.2l1.4-7.1H6.1"/></svg><i class="bn" id="dfsbN">0</i></button></div>
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
DF.nt=function(){var t=$('dfat');t.classList.add('on');clearTimeout(DF._nt);DF._nt=setTimeout(function(){t.classList.remove('on')},1800);document.querySelectorAll('.cn,#bnC').forEach(function(e){e.classList.remove('dfnb');void e.offsetWidth;e.classList.add('dfnb')});if(!onCo&&innerWidth>760)setTimeout(open,350)};
$('dfmcX').onclick=close;ov.onclick=close;document.addEventListener('keydown',function(e){if(e.key==='Escape')close()});$('dfsbB').onclick=open;
document.querySelectorAll('.dfcb').forEach(function(b){b.addEventListener('click',function(e){e.preventDefault();open()})});
window.addEventListener('storage',function(e){if(e.key==='dfc')DF.tab()});
DF.tab();
})();
</script>
@endverbatim
