@extends('layout')@section('title','Create Order')
@section('body')<h1>Create Order</h1><form class="card" method="post" action="/admin/orders" style="max-width:640px" id="onf">@csrf
<label>Phone (type the mobile number first — saved customers fill in automatically)</label><input name="phone" id="onp" required inputmode="tel" autocomplete="off" value="{{ old('phone') }}" placeholder="01XXXXXXXXX">
<div id="onx" style="display:none;margin-top:8px;border-radius:10px;padding:9px 12px;font-size:13px"></div>
<label>Customer name</label><input name="customer_name" id="onn" required value="{{ old('customer_name') }}">
<label>Address</label><textarea name="address" id="ona" rows="2">{{ old('address') }}</textarea><label>District</label><input name="district" id="ond" list="ondl" value="{{ old('district') }}"><datalist id="ondl">@foreach(\DB::table('districts')->orderBy('name')->pluck('name') as $dn)<option value="{{ $dn }}">@endforeach</datalist>
<label>Order source</label><select name="source">@foreach(['Web Site','messenger','Whatsapp','FB','youtube','google','tiktok','instagram','Landing Page'] as $s)<option>{{ $s }}</option>@endforeach</select>
<label>Product</label><input name="item_name" required value="{{ old('item_name') }}"><label>Quantity</label><input name="qty" type="number" min="1" value="1" required>
<label>Total (TK)</label><input name="total" type="number" step="0.01" min="0" required value="{{ old('total') }}"><label>Shipping (TK)</label><input name="shipping" type="number" step="0.01" min="0" value="0">
<label>Note</label><textarea name="note" rows="2"></textarea>@if($errors->any())<div class="err">{{ $errors->first() }}</div>@endif
<div style="margin-top:14px"><button>Save order</button></div></form>@endsection
@section('js')
@verbatim
<script>(function(){
var p=document.getElementById('onp'),box=document.getElementById('onx'),F={name:document.getElementById('onn'),address:document.getElementById('ona'),district:document.getElementById('ond')},tm,last='';
function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
function fill(k,v){var el=F[k];if(!el||!v)return;if(el.value.trim()===''||el.dataset.auto==='1'){el.value=v;el.dataset.auto='1'}}
Object.keys(F).forEach(function(k){F[k].addEventListener('input',function(){this.dataset.auto='0'})});
function look(){var d=p.value.replace(/\D/g,'');if(d.length<10){box.style.display='none';return}if(d===last)return;last=d;
 fetch('/admin/orders/lookup?phone='+encodeURIComponent(p.value),{headers:{Accept:'application/json'},credentials:'same-origin'}).then(function(r){return r.json()}).then(function(j){
  box.style.display='block';
  if(!j.found){box.style.background='#F3F4F6';box.style.color='#374151';box.innerHTML='New customer: no earlier orders for this number. Fill in the details below and the customer will be saved with this order.';return}
  fill('name',j.name);fill('address',j.address);fill('district',j.district);
  var bad=j.blocked||j.risk==='High',warn=j.risk==='Medium';
  box.style.background=bad?'#FEE2E2':(warn?'#FEF3C7':'#DCFCE7');box.style.color=bad?'#991B1B':(warn?'#92400E':'#166534');
  box.innerHTML='<b>Saved customer · '+esc(j.cat)+'</b> · '+j.orders+' order'+(j.orders===1?'':'s')+' · '+esc(j.risk)+' risk'+(j.blocked?' · <b>BLOCKED</b>':'')+(j.returned?' · '+j.returned+' returned':'')+' — details filled in automatically.'+(j.last?'<br>Last order: <a href="/admin/orders/'+j.last.id+'" target="_blank" style="color:inherit;text-decoration:underline">'+esc(j.last.invoice)+'</a> · '+esc(j.last.status)+' · ৳'+Math.round(j.last.total).toLocaleString():'')
 }).catch(function(){})}
p.addEventListener('input',function(){clearTimeout(tm);tm=setTimeout(look,350)});p.addEventListener('blur',look);if(p.value)look();
})();</script>
@endverbatim
@endsection
