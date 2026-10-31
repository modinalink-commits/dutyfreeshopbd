@extends('store.layout')
@section('main')
<style>
body{background:#F7F5F2}
.co{padding-top:36px;padding-bottom:40px;display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:16px;align-items:start}
.cf,.cc{background:#fff;border-radius:12px}.cf{padding:24px 24px 18px}.cc{padding:20px 20px 16px}
.cf .h{text-align:center;font-size:18px;font-weight:600;margin-bottom:22px}.fl{margin-bottom:14px}.fl .l{font-size:11px;font-weight:600;margin-bottom:6px;display:block}
.ip{height:30px;border:1px solid #E5E5E5;background:#F5F5F5;border-radius:6px;display:flex;align-items:center;padding:0 10px;gap:8px}.ip input,.ip textarea{flex-grow:1;border:0;background:transparent;font-size:12px;outline:0;min-width:0;padding:0}.ip span{color:#111;font-size:11px}.ip.ta{height:auto;min-height:30px;padding:6px 10px}.ip.ta textarea{resize:vertical;min-height:18px}
.ar{height:32px;border:1px solid #E5E5E5;background:#F5F5F5;border-radius:6px;display:flex;align-items:center;gap:8px;padding:0 10px;font-size:11px;margin-bottom:6px;cursor:pointer}.ar input{display:none}.ar .rc{width:10px;height:10px;border-radius:50%;border:1.5px solid #9CA3AF;flex:none}.ar .ch2{margin-left:auto;color:#6B7280}.ar.on{border-color:#FF5A00;background:#FFF1EA}.ar.on .rc{border-color:#FF5A00;background:#FF5A00}
.pm{display:flex;justify-content:center;gap:10px;margin-bottom:14px}.pm span{border:1.5px solid #0B1220;border-radius:4px;padding:6px 10px;font-size:11px;font-weight:800;color:#DC2626}.pm span.cd2{border:1px solid #E5E5E5;color:#1D4ED8;font-weight:700}
.sbm{display:block;width:100%;background:#0B1220;color:#fff;text-align:center;font-size:12px;font-weight:700;border-radius:6px;padding:12px 0;border:0}.sbm:disabled{opacity:.6}
.cp{display:flex;gap:10px;margin-bottom:6px}.cp .ip{flex-grow:1;height:32px}.cp button{background:#0B1220;color:#fff;font-size:10px;font-weight:700;border-radius:6px;padding:0 18px;border:0}.cpm{font-size:10px;margin-bottom:12px;min-height:12px}
.th,.tr{display:grid;grid-template-columns:minmax(0,1fr) 90px 70px 80px 50px;font-size:9px;font-weight:600}.th{color:#6B7280;border-bottom:1px solid #EEE;padding-bottom:6px}.tr{align-items:center;padding:10px 0;border-bottom:1px solid #EEE}
.tr .pn{display:flex;align-items:center;gap:8px;min-width:0}.tr .pn img{width:30px;height:34px;object-fit:contain;background:#F3EDE7;border-radius:3px;flex:none}.tr .pn a{overflow:hidden;text-overflow:ellipsis}.tr .qt{display:flex;justify-content:center;align-items:center;gap:6px}.tr .qt button{width:16px;height:16px;border:1px solid #CCC;border-radius:3px;background:#fff;padding:0;line-height:13px;font-size:11px}.tr .rm{text-align:right;color:#DC2626;background:none;border:0;font-size:12px}.tr .mq{display:none}
.sm{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid #EEE;font-size:10px;font-weight:500}.sm.tt{font-size:12px;font-weight:700}
.emp{font-size:12px;padding:18px 0;text-align:center;color:#6B7280}.er{background:#FEF2F2;color:#B91C1C;border:1px solid #FECACA;border-radius:6px;padding:8px 10px;font-size:12px;margin-bottom:14px}
@media (max-width:760px){.co{grid-template-columns:1fr;padding-top:10px;padding-left:8px!important;padding-right:8px!important;gap:10px}.cf{padding:16px 14px}.cc{padding:14px;order:-1}.cf .h{font-size:15px;margin-bottom:14px}
.th{display:none}.tr{grid-template-columns:minmax(0,1fr) 24px}.tr>.qt,.tr>.up,.tr>.lt{display:none}.tr .mq{display:flex;gap:6px;align-items:center;margin-top:3px;font-weight:500}.tr .mq button{width:16px;height:16px;border:1px solid #CCC;border-radius:3px;background:#fff;padding:0;font-size:11px;line-height:13px}.tr .pn{align-items:flex-start}.tr .pn>span{display:flex;flex-direction:column}}
</style>
<style>
body{background:#fff}.cf,.cc{border:1px solid #EAEAEA;border-radius:20px;box-shadow:0 1px 2px rgba(11,18,32,.04),0 8px 22px rgba(11,18,32,.05)}.cf .h{text-align:left;font-size:22px;font-weight:800;color:var(--n)}.fl .l{font-size:12px;color:#374151}
.ip{height:44px;border-radius:12px;background:#F3F4F6;border-color:#E5E7EB}.ip input,.ip textarea{font-size:14px}.ip span{font-size:13px}.ar{height:44px;border-radius:12px;font-size:13px}.ar.on{border-color:var(--o)}
.sbm{background:var(--o);border-radius:12px;font-size:15px;padding:15px 0}.sbm:hover{background:var(--n)}.cp button{border-radius:10px;font-size:12px;background:var(--n)}.th,.tr{font-size:12px}.sm{font-size:13px}.sm.tt{font-size:16px;color:var(--n)}.tr .pn img{width:44px;height:48px;border-radius:8px}.pm span{border-radius:8px}
@media(max-width:760px){.cf,.cc{border-radius:16px}.cf .h{font-size:18px}.ip{height:42px}}
</style>
<form class="w co" id="cof" method="post" action="/order" novalidate>
<div class="cf"><div class="h">Please Order Now</div>
@if(request('e'))<div class="er">{{ request('e') }}</div>@endif
<input name="website" value="" style="display:none" tabindex="-1" autocomplete="off"><input type="hidden" name="coupon" id="cpv"><div id="itf"></div>
<div class="fl"><label class="l" for="f-ph">Mobile Number</label><div class="ip"><input id="f-ph" name="phone" required inputmode="tel" autocomplete="tel" placeholder="Mobile Number" maxlength="14"><span>☎</span></div></div>
<div class="fl"><label class="l" for="f-nm">Full Name</label><div class="ip"><input id="f-nm" name="name" required autocomplete="name" placeholder="Full Name" maxlength="120"><span>👤</span></div></div>
<div class="fl"><label class="l" for="f-em">Email(Optional)</label><div class="ip"><input id="f-em" name="email" type="email" autocomplete="email" placeholder="ex: abc@example.com" maxlength="120"><span>✉</span></div></div>
<div class="fl"><label class="l" for="f-ad">Full Address</label><div class="ip ta"><textarea id="f-ad" name="address" rows="1" required autocomplete="street-address" placeholder="Full Address" maxlength="500"></textarea><span>⌖</span></div></div>
<span class="l" style="font-size:11px;font-weight:600;margin-bottom:6px;display:block">Delivery Area</span>
@foreach($areas as $i => $a)
<label class="ar {{ $loop->first ? 'on' : '' }}"><input type="radio" name="area" value="{{ $i }}" {{ $loop->first ? 'checked' : '' }}><span class="rc"></span>{{ $a['n'] }}<span class="ch2">{{ $a['c'] > 0 ? number_format($a['c']).' TK' : 'Free' }}</span></label>
@endforeach
<div class="fl" style="margin:8px 0 14px"><label class="l" for="f-nt">Delivery Note</label><div class="ip"><input id="f-nt" name="note" placeholder="Delivery Note" maxlength="500"></div></div>
<div class="pm">@if($s['co_cod']==='1')<span>CASH ON DELIVERY</span>@endif @if($s['co_card']==='1')<span class="cd2">VISA · MASTERCARD</span>@endif</div>
<button class="sbm" id="sbm">ORDER SUBMIT</button></div>
<div class="cc">@if($s['co_coupon']==='1')<div class="cp"><div class="ip"><input id="cpc" placeholder="Coupon Code" maxlength="40" aria-label="Coupon Code"></div><button type="button" id="cpb">Apply</button></div><div class="cpm" id="cpm"></div>@endif
<div class="th"><span>PRODUCT</span><span style="text-align:center">QUANTITY</span><span>PRICE</span><span>TOTAL PRICE</span><span style="text-align:right">ACTION</span></div>
<div id="rows"><div class="emp">Loading…</div></div>
<div class="sm"><span>Sub Total :</span><span id="sSub">0 TK</span></div><div class="sm"><span>Shipping Charge :</span><span id="sShip">0 TK</span></div><div class="sm"><span>Discount Amount :</span><span id="sDisc">0 TK</span></div><div class="sm tt"><span>Payable Amount :</span><span id="sTot">0 TK</span></div></div>
</form>
@endsection
@section('js')
<script>
(function(){var u=new URLSearchParams(location.search),a=u.get('add');if(a){if(!DF.cart()[a])DF.add(a,1,u.get('p'),true);history.replaceState(null,'','/checkout'+(u.get('e')?'?e='+encodeURIComponent(u.get('e')):''))}
var code='',f=function(x){return (+x).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})+' TK'},g=function(x){return Math.round(+x).toLocaleString('en-US')+' TK'},E=function(s){var d=document.createElement('div');d.textContent=s;return d.innerHTML};
function area(){var r=document.querySelector('input[name=area]:checked');return r?r.value:1}
function load(){var c=DF.cart(),ids=Object.keys(c).map(function(k){return k+':'+c[k].q}).join(',');
 if(!ids){document.getElementById('rows').innerHTML='<div class="emp">Your cart is empty. <a href="/" style="color:#FF5A00;font-weight:700">Continue Shopping</a></div>';['sSub','sShip','sDisc','sTot'].forEach(function(i){document.getElementById(i).textContent='0 TK'});return}
 fetch('/cart-items?ids='+encodeURIComponent(ids)+'&area='+area()+'&code='+encodeURIComponent(code)).then(function(r){return r.json()}).then(function(q){
  var h='',seen={};q.items.forEach(function(it){seen[it.id]=1;c[it.id]={q:it.qty,p:it.price};
   h+='<div class="tr"><span class="pn"><img src="'+E(it.image||'/icon.png')+'" alt=""><span><a href="'+E(it.url)+'">'+E(it.name)+'</a><span class="mq"><button type="button" data-d="'+it.id+'">−</button>'+it.qty+'<button type="button" data-i="'+it.id+'">+</button> · '+f(it.line)+'</span></span></span><span class="qt"><button type="button" data-d="'+it.id+'">−</button>'+it.qty+'<button type="button" data-i="'+it.id+'">+</button></span><span class="up">'+f(it.price)+'</span><span class="lt">'+f(it.line)+'</span><button type="button" class="rm" data-r="'+it.id+'" aria-label="Remove">🗑</button></div>'});
  Object.keys(c).forEach(function(k){if(!seen[k])delete c[k]});DF.p('dfc',c);DF.tab();
  document.getElementById('rows').innerHTML=h||'<div class="emp">These products are no longer available.</div>';
  document.getElementById('sSub').textContent=f(q.sub);document.getElementById('sShip').textContent=g(q.ship);document.getElementById('sDisc').textContent=g(q.disc);document.getElementById('sTot').textContent=g(q.total);
  var m=document.getElementById('cpm');if(m){m.textContent=q.coupon_msg||'';m.style.color=q.coupon?'#16A34A':'#B91C1C'}document.getElementById('cpv').value=q.coupon||'';
 })}
document.getElementById('rows').onclick=function(e){var t=e.target,c=DF.cart(),k;if(k=t.getAttribute('data-i')){c[k].q=Math.min(50,c[k].q+1)}else if(k=t.getAttribute('data-d')){c[k].q=Math.max(1,c[k].q-1)}else if(k=t.getAttribute('data-r')){delete c[k]}else return;DF.p('dfc',c);load()};
document.querySelectorAll('.ar').forEach(function(l){l.onclick=function(){document.querySelectorAll('.ar').forEach(function(x){x.classList.remove('on')});l.classList.add('on');l.querySelector('input').checked=true;load()}});
var cb=document.getElementById('cpb');if(cb)cb.onclick=function(){code=document.getElementById('cpc').value.trim();load()};
document.getElementById('cof').onsubmit=function(e){var c=DF.cart(),h='';for(var k in c)h+='<input type="hidden" name="items['+(+k)+']" value="'+(+c[k].q)+'">';document.getElementById('itf').innerHTML=h;
 var ph=document.getElementById('f-ph').value.replace(/\D/g,'').slice(-11),er='';if(!h)er='Your cart is empty';else if(!/^01[3-9]\d{8}$/.test(ph))er='সঠিক মোবাইল নম্বর লিখুন (01XXXXXXXXX)';else if(!document.getElementById('f-nm').value.trim()||!document.getElementById('f-ad').value.trim())er='মোবাইল নম্বর, নাম ও ঠিকানা লিখুন।';
 if(er){e.preventDefault();alertBox(er);return}document.getElementById('sbm').disabled=true;if(window.fbq)fbq('track','InitiateCheckout');};
function alertBox(m){var b=document.querySelector('.er');if(!b){b=document.createElement('div');b.className='er';var h=document.querySelector('.cf .h');h.parentNode.insertBefore(b,h.nextSibling)}b.textContent=m;b.scrollIntoView({block:'center'})}
load();})();
</script>
@endsection
