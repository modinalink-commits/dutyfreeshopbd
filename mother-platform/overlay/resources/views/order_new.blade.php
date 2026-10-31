@extends('layout')@section('title','Create Order')
@section('body')<h1>Create Order</h1><form class="card" method="post" action="/admin/orders" style="max-width:640px" id="onf">@csrf
<label>Phone (type the mobile number first — saved customers fill in automatically)</label><input name="phone" id="onp" required inputmode="tel" autocomplete="off" value="{{ old('phone') }}" placeholder="01XXXXXXXXX">
<div id="onx" style="display:none;margin-top:8px;border-radius:10px;padding:9px 12px;font-size:13px"></div>
<label>Customer name</label><input name="customer_name" id="onn" required value="{{ old('customer_name') }}">
<label>Address</label><textarea name="address" id="ona" rows="2">{{ old('address') }}</textarea><label>District</label><input name="district" id="ond" list="ondl" value="{{ old('district') }}"><datalist id="ondl">@foreach(\DB::table('districts')->orderBy('name')->pluck('name') as $dn)<option value="{{ $dn }}">@endforeach</datalist>
<label>Order source</label><select name="source">@foreach(['Web Site','messenger','Whatsapp','FB','youtube','google','tiktok','instagram','Landing Page'] as $s)<option>{{ $s }}</option>@endforeach</select>
<label>Product</label><input name="item_name" required value="{{ old('item_name') }}"><label>Quantity</label><input name="qty" type="number" min="1" value="1" required>
<label>Subtotal (TK)</label><input name="total" id="ost" type="number" step="0.01" min="0" required value="{{ old('total') }}"><label>Shipping (TK)</label><input name="shipping" id="osh" type="number" step="0.01" min="0" value="0">
<div id="dsp" style="margin-top:12px;border:1px solid #E5E7EB;border-radius:10px;padding:10px 12px;font-size:13px"><b>Discount</b><div id="dsl" style="margin:6px 0;color:#6B7280">Enter the customer phone and subtotal to check discounts.</div>
<label>Coupon code (optional)</label><input name="coupon" id="ocp" autocomplete="off" value="{{ old('coupon') }}" style="max-width:220px"><div id="dso" style="display:none"><label>Change discount to (TK, replaces the automatic one)</label><input name="disc_manual" id="odm" type="number" step="0.01" min="0" style="max-width:220px"><label>Extra discount (TK)</label><input name="disc_extra" id="ode" type="number" step="0.01" min="0" style="max-width:220px"><p style="font-size:11px;color:#6B7280;margin:4px 0 0">Any manual change is logged with your name, the previous and new discount, and the time.</p></div>
<div id="dss" style="margin-top:10px;font-weight:600"></div></div>
<label>Note</label><textarea name="note" rows="2"></textarea>@if($errors->any())<div class="err">{{ $errors->first() }}</div>@endif
<div style="margin-top:14px"><button>Save order</button></div></form>@endsection
@section('js')
@verbatim
<script>(function(){
var p=document.getElementById('onp'),box=document.getElementById('onx'),F={name:document.getElementById('onn'),address:document.getElementById('ona'),district:document.getElementById('ond')},tm,last='';
function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
function fill(k,v){var el=F[k];if(!el||!v)return;if(el.value.trim()===''||el.dataset.auto==='1'){el.value=v;el.dataset.auto='1'}}
Object.keys(F).forEach(function(k){F[k].addEventListener('input',function(){this.dataset.auto='0'})});
function look(){var d=p.value.replace(/\D/g,'');if(d.length<10){box.style.display='none';last='';return}if(d===last)return;last=d;
 fetch('/admin/orders/lookup?phone='+encodeURIComponent(p.value),{headers:{Accept:'application/json'},credentials:'same-origin'}).then(function(r){return r.json()}).then(function(j){
  box.style.display='block';
  if(!j.found){Object.keys(F).forEach(function(k){if(F[k].dataset.auto==='1'){F[k].value='';F[k].dataset.auto='0'}});box.style.background='#F3F4F6';box.style.color='#374151';box.innerHTML='New customer: no earlier orders for this number. Fill in the details below and the customer will be saved with this order.';return}
  fill('name',j.name);fill('address',j.address);fill('district',j.district);
  var bad=j.blocked||j.risk==='High',warn=j.risk==='Medium';
  box.style.background=bad?'#FEE2E2':(warn?'#FEF3C7':'#DCFCE7');box.style.color=bad?'#991B1B':(warn?'#92400E':'#166534');
  box.innerHTML='<b>Saved customer · '+esc(j.cat)+'</b> · '+j.orders+' order'+(j.orders===1?'':'s')+' · '+esc(j.risk)+' risk'+(j.blocked?' · <b>BLOCKED</b>':'')+(j.returned?' · '+j.returned+' returned':'')+' — details filled in automatically.'+(j.membership?'<br>Membership: <b>'+esc(j.membership)+'</b>':'')+(j.courier_pref?' · Courier preference: <b>'+esc(j.courier_pref)+'</b>':'')+(j.assigned?' · Assigned admin: <b>'+esc(j.assigned)+'</b>':'')+(j.cnote?'<br>Customer note: '+esc(j.cnote):'')+(j.last?'<br>Last order: <a href="/admin/orders/'+j.last.id+'" target="_blank" style="color:inherit;text-decoration:underline">'+esc(j.last.invoice)+'</a> · '+esc(j.last.status)+' · ৳'+Math.round(j.last.total).toLocaleString():'')
 }).catch(function(){})}
var ST=document.getElementById('ost'),SH=document.getElementById('osh'),CP=document.getElementById('ocp'),DL=document.getElementById('dsl'),DS=document.getElementById('dss'),DO=document.getElementById('dso'),DM=document.getElementById('odm'),DE=document.getElementById('ode'),dt,lastP='',can=false;
function tk(n){return '৳'+Math.round(n*100)/100}
function resetOv(){DM.value='';DE.value=''}
function calc(){var ph=p.value.replace(/\D/g,''),sub=parseFloat(ST.value)||0;
 if(ph.length<10||sub<=0){DL.textContent='Enter the customer phone and subtotal to check discounts.';DS.textContent='';DO.style.display='none';return}
 fetch('/admin/orders/discount?phone='+encodeURIComponent(p.value)+'&subtotal='+sub+'&coupon='+encodeURIComponent(CP.value),{headers:{Accept:'application/json'},credentials:'same-origin'}).then(function(r){return r.json()}).then(function(j){
  can=!!j.can;var h='',mode={highest:'highest discount only',priority:'priority order',stack:'multiple allowed'}[j.mode]||j.mode;
  (j.lines||[]).forEach(function(l){h+='<div style="display:flex;gap:8px;align-items:center;padding:3px 0'+(l.applied?'':';opacity:.55')+'">'+(l.applied&&can?'<label style="display:flex;gap:4px;align-items:center;margin:0;font-weight:400"><input type="checkbox" class="dof" name="disc_off[]" value="'+esc(l.key)+'" style="width:auto"> remove</label>':'')+'<span>'+esc(l.label)+(l.pct!=null?' · '+l.pct+'%':'')+'</span><b style="margin-left:auto">'+(l.applied?'-'+tk(l.amount):'not applied')+'</b></div>'});
  if(j.coupon_error)h+='<div style="color:#991B1B">'+esc(j.coupon_error)+'</div>';
  DL.innerHTML=h||'No discount: this customer has no saved or active discount rule.';DL.style.color='#111';
  DO.style.display=can?'block':'none';DL.dataset.auto=j.total;DL.dataset.mode=j.mode;
  var o=DL.querySelectorAll('.dof');for(var i=0;i<o.length;i++)o[i].onchange=sum;sum();
 }).catch(function(){})}
function sum(){var sub=parseFloat(ST.value)||0,auto=parseFloat(DL.dataset.auto)||0,off=0;
 var ls=DL.querySelectorAll('.dof');for(var i=0;i<ls.length;i++)if(ls[i].checked){/* amount of removed line */var b=ls[i].closest('div').querySelector('b').textContent.replace(/[^0-9.]/g,'');off+=parseFloat(b)||0}
 var d=auto-off;if(DM.value!=='')d=parseFloat(DM.value)||0;d+=parseFloat(DE.value)||0;d=Math.max(0,Math.min(sub,d));
 var fin=sub-d,sh=parseFloat(SH.value)||0;
 DS.innerHTML='Subtotal '+tk(sub)+' · Discount -'+tk(d)+'<br>Final total '+tk(fin)+(sh?' · + shipping '+tk(sh)+' = payable '+tk(fin+sh):'')}
function sched(){clearTimeout(dt);dt=setTimeout(calc,350)}
[DM,DE].forEach(function(e){e.addEventListener('input',sum)});SH.addEventListener('input',sum);
ST.addEventListener('input',sched);CP.addEventListener('input',sched);
function onPhone(){var d=p.value.replace(/\D/g,'');if(d!==lastP){lastP=d;resetOv();if(d.length<10){CP.value=''}sched()}}
p.addEventListener('input',function(){clearTimeout(tm);tm=setTimeout(look,350);onPhone()});p.addEventListener('blur',look);if(p.value){look();onPhone()}
})();</script>
@endverbatim
@endsection
