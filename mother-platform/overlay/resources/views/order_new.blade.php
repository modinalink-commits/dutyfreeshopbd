@extends('layout')@section('title','Create Order')
@section('body')
<style>.onw{display:grid;grid-template-columns:minmax(300px,1fr) 2fr;gap:16px;align-items:start}@media(max-width:900px){.onw{grid-template-columns:1fr}}.onw label{display:block;margin:10px 0 4px;font-size:13px}.onw .card{margin:0}.opt{position:absolute;left:0;right:0;top:100%;z-index:20;background:#fff;border:1px solid #E5E7EB;border-radius:8px;max-height:280px;overflow:auto;box-shadow:0 8px 24px rgba(0,0,0,.12)}.opt div{padding:8px 10px;cursor:pointer;display:flex;justify-content:space-between;gap:8px;font-size:13px}.opt div:hover{background:#F3F4F6}.itb{width:100%;border-collapse:collapse;font-size:13px}.itb th{text-align:left;background:#F3F4F6;padding:8px;color:#6B7280}.itb td{padding:6px 8px;border-top:1px solid #E5E7EB}.itb input{margin:0;padding:6px}</style>
<a class="btn g" href="/admin/orders" style="margin-bottom:10px;display:inline-block">&larr; Back</a>
<form method="post" action="/admin/orders" id="onf" class="onw">@csrf
@if($pre)<input type="hidden" name="inc_id" value="{{ $pre['inc'] }}">@endif
<div class="card"><h3 style="margin:0 0 4px;text-align:center">Customer Information</h3>
<label>Customer Phone</label><input name="phone" id="onp" data-bdphone required inputmode="numeric" maxlength="11" autocomplete="off" value="{{ old('phone') }}" placeholder="Enter customer 11 digit mobile number">
<div id="onx" style="display:none;margin-top:8px;border-radius:10px;padding:9px 12px;font-size:13px"></div>@include('fraud_widget',['fcInput'=>'#onp'])
<label>Name</label><input name="customer_name" id="onn" required value="{{ old('customer_name') }}" placeholder="Name">
<label>Address</label><textarea name="address" id="ona" rows="2" placeholder="address">{{ old('address') }}</textarea>
<label>Courier</label><select name="courier" id="onc"><option value="">Select Courier</option>@foreach(\DB::table('couriers')->orderByDesc('is_default')->orderBy('name')->pluck('name') as $cn)<option {{ old('courier')===$cn ? 'selected' : '' }}>{{ $cn }}</option>@endforeach</select>
<label>City</label><select name="district" id="ond"><option value="">Select City</option>@foreach(\DB::table('districts')->where('active', true)->orderBy('name')->get(['name','delivery_charge']) as $dn)<option data-ch="{{ (float)$dn->delivery_charge }}" {{ old('district')===$dn->name ? 'selected' : '' }}>{{ $dn->name }}</option>@endforeach</select>
<label>Sub City / Area</label><input name="sub_city" value="{{ old('sub_city') }}" placeholder="Thana / area (optional)">
<label>Admins</label><select name="assigned_admin" id="onad"><option value="">Select Assign Admin</option>@foreach(\DB::table('users')->where('active', true)->orderBy('name')->pluck('name') as $un)<option {{ old('assigned_admin')===$un ? 'selected' : '' }}>{{ $un }}</option>@endforeach</select>
<label>Order Source</label><select name="source">@foreach(['Web Site','messenger','Whatsapp','FB','youtube','google','tiktok','instagram','Landing Page'] as $s)<option>{{ $s }}</option>@endforeach</select>
<label>Note <small style="color:#6B7280">(the courier sees this note on the parcel, max 200 characters)</small></label><textarea name="note" rows="2" maxlength="200" placeholder="note for the courier"></textarea>
</div>
<div class="card"><h3 style="margin:0 0 4px;text-align:center">Product information</h3>
<label>Scan Barcode || product code</label><div style="position:relative"><input id="ops" autocomplete="off" placeholder="type product code or name"><div class="opt" id="opo" style="display:none"></div></div>
<div style="margin:6px 0"><button type="button" class="g" id="ocu">+ Add custom item</button></div>
<div style="overflow:auto"><table class="itb"><thead><tr><th>#</th><th>Product</th><th style="width:90px">Quantity</th><th style="width:120px">Price</th><th style="width:110px">Total</th><th style="width:70px">Action</th></tr></thead><tbody id="itb"></tbody></table></div>
@if($errors->any())<div class="err" style="margin-top:8px">{{ $errors->first() }}</div>@endif
<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:8px"><div><label>Delivery Charge (TK)</label><input name="shipping" id="osh" type="number" step="0.01" min="0" value="0"><div id="odz" style="font-size:11.5px;color:#6B7280;margin-top:3px"></div></div><div><label>Subtotal (TK)</label><input id="ost" readonly value="0" style="background:#F9FAFB"></div></div>
<div id="dsp" style="margin-top:12px;border:1px solid #E5E7EB;border-radius:10px;padding:10px 12px;font-size:13px"><b>Discount</b><div id="dsn" style="display:none;margin:6px 0;padding:8px 10px;border-radius:8px;background:#EFF6FF;color:#1E3A8A;font-size:12.5px;line-height:1.6"></div><div id="dsl" style="margin:6px 0;color:#6B7280">Enter the customer phone and add products to check discounts.</div>
<label>Coupon code (optional)</label><input name="coupon" id="ocp" autocomplete="off" value="{{ old('coupon') }}" style="max-width:220px"><div id="dso" style="display:none"><label>Change discount to (TK, replaces the automatic one)</label><input name="disc_manual" id="odm" type="number" step="0.01" min="0" style="max-width:220px"><label>Extra discount (TK)</label><input name="disc_extra" id="ode" type="number" step="0.01" min="0" style="max-width:220px"><p style="font-size:11px;color:#6B7280;margin:4px 0 0">Any manual change is logged with your name, the previous and new discount, and the time.</p></div>
<div id="dss" style="margin-top:10px;font-weight:600"></div></div>
<div style="margin-top:14px"><button>Save order</button></div></div>
</form>
@endsection
@section('js')
<script>window.PRE=@json($pre);</script>
@verbatim
<script>(function(){
var p=document.getElementById('onp'),box=document.getElementById('onx'),F={name:document.getElementById('onn'),address:document.getElementById('ona')},D=document.getElementById('ond'),tm,last='';
function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
function fill(k,v){var el=F[k];if(!el||!v)return;if(el.value.trim()===''||el.dataset.auto==='1'){el.value=v;el.dataset.auto='1'}}
function setSel(el,v){if(!v)return;if(el.value!==''&&el.dataset.auto!=='1')return;for(var i=0;i<el.options.length;i++)if(el.options[i].value.toLowerCase()===String(v).toLowerCase()){el.selectedIndex=i;el.dataset.auto='1';el.dispatchEvent(new Event('change'));return}}
Object.keys(F).forEach(function(k){F[k].addEventListener('input',function(){this.dataset.auto='0'})});
['onc','ond','onad'].forEach(function(id){var e=document.getElementById(id);e.addEventListener('input',function(){this.dataset.auto='0'})});
function look(){var d=p.value.replace(/\D/g,'');if(d.length<10){box.style.display='none';last='';return}if(d===last)return;last=d;
 fetch('/admin/orders/lookup?phone='+encodeURIComponent(p.value),{headers:{Accept:'application/json'},credentials:'same-origin'}).then(function(r){return r.json()}).then(function(j){
  box.style.display='block';
  if(!j.found){Object.keys(F).forEach(function(k){if(F[k].dataset.auto==='1'){F[k].value='';F[k].dataset.auto='0'}});['onc','ond','onad'].forEach(function(id){var e=document.getElementById(id);if(e.dataset.auto==='1'){e.selectedIndex=0;e.dataset.auto='0'}});box.style.background='#F3F4F6';box.style.color='#374151';box.innerHTML='New customer: no earlier orders for this number. Fill in the details and the customer will be saved with this order.';return}
  fill('name',j.name);fill('address',j.address);setSel(D,j.district);setSel(document.getElementById('onc'),j.courier_pref);setSel(document.getElementById('onad'),j.assigned);
  var bad=j.blocked||j.risk==='High',warn=j.risk==='Medium';
  box.style.background=bad?'#FEE2E2':(warn?'#FEF3C7':'#DCFCE7');box.style.color=bad?'#991B1B':(warn?'#92400E':'#166534');
  box.innerHTML='<b>Saved customer · '+esc(j.cat)+'</b> · '+j.orders+' order'+(j.orders===1?'':'s')+' · '+esc(j.risk)+' risk'+(j.blocked?' · <b>BLOCKED</b>':'')+(j.returned?' · '+j.returned+' returned':'')+' — details filled in automatically.'+(j.membership?'<br>Membership: <b>'+esc(j.membership)+'</b>':'')+(j.cnote?'<br>Customer note: '+esc(j.cnote):'')+(j.last?'<br>Last order: <a href="/admin/orders/'+j.last.id+'" target="_blank" style="color:inherit;text-decoration:underline">'+esc(j.last.invoice)+'</a> · '+esc(j.last.status)+' · ৳'+Math.round(j.last.total).toLocaleString():'')
 }).catch(function(){})}
/* products */
var IT=[],TB=document.getElementById('itb'),PS=document.getElementById('ops'),PO=document.getElementById('opo'),ST=document.getElementById('ost'),SH=document.getElementById('osh'),pt;
function tk(n){return '৳'+Math.round(n*100)/100}
function sub(){var s=0;IT.forEach(function(x){s+=(x.qty||0)*(x.price||0)});return Math.round(s*100)/100}
function render(){var h='';IT.forEach(function(x,i){h+='<tr><td>'+(i+1)+'</td><td>'+(x.custom?'<input name="items['+i+'][name]" value="'+esc(x.name)+'" required data-i="'+i+'" data-f="name">':esc(x.name)+'<input type="hidden" name="items['+i+'][name]" value="'+esc(x.name)+'">')+(x.id?'<input type="hidden" name="items['+i+'][product_id]" value="'+x.id+'">':'')+(x.stock!=null?'<br><small style="color:#6B7280">'+esc(x.sku||'')+' · stock '+x.stock+'</small>':'')+'</td><td><input type="number" min="1" name="items['+i+'][qty]" value="'+x.qty+'" data-i="'+i+'" data-f="qty"></td><td><input type="number" min="0" step="0.01" name="items['+i+'][price]" value="'+x.price+'" data-i="'+i+'" data-f="price"></td><td id="lt'+i+'">'+tk(x.qty*x.price)+'</td><td><button type="button" class="g" data-rm="'+i+'">✕</button></td></tr>'});
 TB.innerHTML=h||'<tr><td colspan="6" style="color:#6B7280;padding:14px">No products added yet.</td></tr>';tot()}
function tot(){ST.value=sub();sched();sum()}
TB.addEventListener('input',function(e){var t=e.target,i=t.dataset.i;if(i==null)return;var f=t.dataset.f;IT[i][f]=f==='name'?t.value:(parseFloat(t.value)||0);var c=document.getElementById('lt'+i);if(c)c.textContent=tk(IT[i].qty*IT[i].price);tot()});
TB.addEventListener('click',function(e){var r=e.target.getAttribute('data-rm');if(r!=null){IT.splice(+r,1);render()}});
function addP(x){for(var i=0;i<IT.length;i++)if(x.id&&IT[i].id===x.id){IT[i].qty++;render();return}IT.push({id:x.id||null,name:x.name,sku:x.sku,stock:x.stock,qty:1,price:x.price||0,custom:!x.id});render()}
document.getElementById('ocu').onclick=function(){addP({name:'',price:0})};
PS.addEventListener('input',function(){clearTimeout(pt);var q=PS.value.trim();if(!q){PO.style.display='none';return}pt=setTimeout(function(){fetch('/admin/orders/products?q='+encodeURIComponent(q),{headers:{Accept:'application/json'},credentials:'same-origin'}).then(function(r){return r.json()}).then(function(a){
 PO.innerHTML=a.length?a.map(function(x,i){return '<div data-k="'+i+'"><span>'+esc(x.name)+(x.sku?' <small style="color:#6B7280">'+esc(x.sku)+'</small>':'')+'</span><b>'+tk(x.price)+' · '+x.stock+' in stock</b></div>'}).join(''):'<div style="color:#6B7280">No product found</div>';PO.style.display='block';PO._a=a}).catch(function(){})},250)});
PO.addEventListener('click',function(e){var d=e.target.closest('[data-k]');if(!d)return;addP(PO._a[+d.getAttribute('data-k')]);PO.style.display='none';PS.value='';PS.focus()});
PS.addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();var d=PO.querySelector('[data-k]');if(d&&PO.style.display!=='none'){addP(PO._a[+d.getAttribute('data-k')]);PO.style.display='none';PS.value=''}}});
document.addEventListener('click',function(e){if(!PO.contains(e.target)&&e.target!==PS)PO.style.display='none'});
SH.dataset.auto='1';var DZ=document.getElementById('odz'),dvT,dvAuto=null,dvInfo='';
function dvShow(){var v=parseFloat(SH.value)||0,t=dvInfo;if(dvAuto!==null&&Math.abs(v-dvAuto)>0.009)t+=(t?' · ':'')+'<b style="color:#B45309">Manual adjustment '+(v-dvAuto>0?'+':'-')+tk(Math.abs(v-dvAuto))+' (will be recorded)</b>';DZ.innerHTML=t}
function dvGo(){var ids=IT.filter(function(x){return x.id}).map(function(x){return x.id}).join(','),q='subtotal='+sub()+'&district='+encodeURIComponent(D.value)+'&address='+encodeURIComponent((document.getElementById('ona').value||'')+' '+(document.querySelector('[name=sub_city]').value||''))+'&pids='+ids;
 fetch('/admin/orders/delivery?'+q,{headers:{Accept:'application/json'},credentials:'same-origin'}).then(function(r){return r.json()}).then(function(j){dvAuto=j.charge;dvInfo='Zone: <b>'+esc(j.zone||'—')+'</b>'+(j.free?' · <b style="color:#166534">FREE delivery'+(j.reason?' ('+esc(j.reason)+')':'')+'</b> (normal '+tk(j.original)+')':'');if(SH.dataset.auto==='1'){SH.value=j.charge}dvShow();sum()}).catch(function(){})}
function dvs(){clearTimeout(dvT);dvT=setTimeout(dvGo,300)}
D.addEventListener('change',dvs);document.getElementById('ona').addEventListener('input',dvs);document.querySelector('[name=sub_city]').addEventListener('input',dvs);
SH.addEventListener('input',function(){this.dataset.auto='0';dvShow();sum()});
var _rd=render;render=function(){_rd.apply(this,arguments);dvs()};
/* discount */
var CP=document.getElementById('ocp'),DL=document.getElementById('dsl'),NT=document.getElementById('dsn'),DS=document.getElementById('dss'),DO=document.getElementById('dso'),DM=document.getElementById('odm'),DE=document.getElementById('ode'),dt,lastP='',can=false;
function resetOv(){DM.value='';DE.value=''}
function calc(){var ph=p.value.replace(/\D/g,''),s=sub();
 if(ph.length<10){DL.textContent='Enter the customer phone and add products to check discounts.';DL.dataset.auto=0;DO.style.display='none';NT.style.display='none';sum();return}
 fetch('/admin/orders/discount?phone='+encodeURIComponent(p.value)+'&subtotal='+s+'&qty='+IT.reduce(function(a,x){return a+(x.qty||0)},0)+'&items='+IT.filter(function(x){return x.id}).map(function(x){return x.id+':'+(parseFloat(x.price)||0)+':'+(x.qty||0)}).join(',')+'&coupon='+encodeURIComponent(CP.value),{headers:{Accept:'application/json'},credentials:'same-origin'}).then(function(r){return r.json()}).then(function(j){
  can=!!j.can;var h='';note(j);
  (j.lines||[]).forEach(function(l){h+='<div style="display:flex;gap:8px;align-items:center;padding:3px 0'+(l.applied?'':';opacity:.55')+'">'+(l.applied&&can?'<label style="display:flex;gap:4px;align-items:center;margin:0;font-weight:400"><input type="checkbox" class="dof" name="disc_off[]" value="'+esc(l.key)+'" style="width:auto"> remove</label>':'')+'<span>'+esc(l.label)+(l.pct!=null?' · '+l.pct+'%':'')+'</span><b style="margin-left:auto">'+(l.applied?'-'+tk(l.amount):'not applied')+'</b></div>'});
  if(j.coupon_error)h+='<div style="color:#991B1B">'+esc(j.coupon_error)+'</div>';
  DL.innerHTML=h||'No discount: this customer has no saved or active discount rule.';DL.style.color='#111';
  DO.style.display=can?'block':'none';DL.dataset.auto=j.total;
  var o=DL.querySelectorAll('.dof');for(var i=0;i<o.length;i++)o[i].onchange=sum;sum();
 }).catch(function(){})}
function note(j){var pr=j.profile;if(!pr){NT.style.display='none';return}var ap=(j.lines||[]).filter(function(l){return l.applied}),ot=(j.lines||[]).filter(function(l){return !l.applied});
 var el=ap.length?ap.map(function(l){return esc(l.type)+(l.pct!=null?' '+l.pct+'%':' ৳'+l.amount)}).join(' + '):'None';
 NT.innerHTML='Customer Type: <b>'+esc(pr.category)+'</b> · Membership: <b>'+esc(pr.membership)+(pr.membership_name?' ('+esc(pr.membership_name)+')':'')+'</b><br>Eligible Discount: <b>'+el+'</b>'+(ot.length?'<br>Other Eligible Offer: '+ot.map(function(l){return esc(l.type)+(l.pct!=null?' '+l.pct+'%':' ৳'+l.amount)}).join(', '):'')+'<br>Applied Discount: <b>'+tk(j.total||0)+'</b>'+(ap.length?' · Source: '+ap.map(function(l){return esc(l.source)}).join(', '):'');NT.style.display='block'}
function sum(){var s=sub(),auto=parseFloat(DL.dataset.auto)||0,off=0;
 var ls=DL.querySelectorAll('.dof');for(var i=0;i<ls.length;i++)if(ls[i].checked){var b=ls[i].closest('div').querySelector('b').textContent.replace(/[^0-9.]/g,'');off+=parseFloat(b)||0}
 var d=auto-off;if(DM.value!=='')d=parseFloat(DM.value)||0;d+=parseFloat(DE.value)||0;d=Math.max(0,Math.min(s,d));
 var fin=s-d,sh=parseFloat(SH.value)||0;
 DS.innerHTML='Subtotal '+tk(s)+' · Discount -'+tk(d)+'<br>Final total '+tk(fin)+(sh?' · + shipping '+tk(sh)+' = payable '+tk(fin+sh):'')}
function sched(){clearTimeout(dt);dt=setTimeout(calc,350)}
[DM,DE].forEach(function(e){e.addEventListener('input',sum)});CP.addEventListener('input',sched);
function onPhone(){var d=p.value.replace(/\D/g,'');if(d!==lastP){lastP=d;resetOv();if(d.length<10){CP.value=''}sched()}}
p.addEventListener('input',function(){clearTimeout(tm);tm=setTimeout(look,350);onPhone()});p.addEventListener('blur',look);if(p.value){look();onPhone()}
render();
if(window.PRE){var P0=window.PRE;p.value=P0.phone||'';document.getElementById('onn').value=P0.name||'';document.getElementById('ona').value=P0.address||'';var nt=document.querySelector('textarea[name=note]');if(nt&&P0.note)nt.value=P0.note;setSel(D,P0.district);(P0.items||[]).forEach(function(x){IT.push({id:x.id||null,name:x.name,sku:null,stock:null,qty:x.qty,price:x.price,custom:false})});render();look();onPhone()}
})();</script>
@endverbatim
@endsection
