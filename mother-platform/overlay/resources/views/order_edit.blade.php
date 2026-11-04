@extends('layout')@section('title','Edit '.$o->invoice)
@section('body')
<style>
.oe-t{width:100%;border-collapse:collapse;font-size:13px}.oe-t th{text-align:left;color:#6B7280;font-size:12px;padding:6px 6px}.oe-t td{padding:7px 6px;border-top:1px solid #E5E7EB;vertical-align:middle}
.oe-t img{width:44px;height:44px;object-fit:cover;border-radius:6px;background:#F3F4F6}.oe-q{display:inline-flex;align-items:center;border:1px solid #D1D5DB;border-radius:7px;overflow:hidden}.oe-q button{border:0;background:#F3F4F6;width:28px;height:30px;cursor:pointer;font-weight:800}.oe-q input{width:44px;text-align:center;border:0;padding:0;height:30px}
.oe-a button{border:1px solid #D1D5DB;background:#fff;border-radius:6px;padding:4px 8px;font-size:12px;cursor:pointer;margin:1px}.oe-a .rm{color:#B91C1C;border-color:#FCA5A5}.row-rm{opacity:.45;text-decoration:line-through}
.oe-g{display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:14px;align-items:start}@media(max-width:980px){.oe-g{grid-template-columns:1fr}}
.oe-s div{display:flex;justify-content:space-between;padding:4px 0;font-size:13px}.oe-s .big{border-top:1px solid #E5E7EB;margin-top:4px;padding-top:8px;font-size:16px;font-weight:800}.oe-s small{color:#6B7280}
.oe-res{position:absolute;z-index:20;background:#fff;border:1px solid #D1D5DB;border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.12);width:min(520px,100%);max-height:320px;overflow-y:auto}.oe-res div{padding:7px 10px;border-top:1px solid #F1F3F6;cursor:pointer;font-size:13px}.oe-res div:hover{background:#FFF1E8}
.oe-w{background:#FFFBEB;border:1px solid #FDE68A;border-radius:8px;padding:8px 10px;font-size:12.5px;margin-top:8px}.oe-b{white-space:pre-line;background:#FEF2F2;border:1px solid #FECACA;color:#991B1B;border-radius:8px;padding:10px 12px;font-size:13px;margin-top:10px}
.oe-pos{color:#B45309}.oe-neg{color:#15803D}
</style>
<h1 style="margin:0 0 4px">Edit Order {{ $o->invoice }} <span class="pill s-{{ \Illuminate\Support\Str::slug($o->status) }}">{{ $o->status }}</span> <a class="btn g" href="/admin/orders/{{ $o->id }}" style="font-size:12px">← Back to order</a></h1>
<span style="font-size:13px;color:#6B7280">{{ $o->customer_name }} · {{ $o->phone }} · changes are saved as a revision with who, when, why and the totals before and after.</span>
@if(session('rev_ok'))<div class="card" style="margin-top:10px;background:#ECFDF5;border-color:#A7F3D0">{{ session('rev_ok') }}</div>@endif
@if($errors->any())<div class="oe-b">@foreach($errors->all() as $e){{ $e }}<br>@endforeach</div>@endif
@if($block)<div class="oe-b" style="font-weight:700">{{ $block }}</div>@include('order_revision_block',['o'=>$o])@else
@if($courier)<div class="oe-w">⚠ <b>Courier information may need to be updated.</b> A courier parcel already exists for this order (consignment {{ $o->consignment_id }}). Changing items or the amount to collect does not change the parcel automatically — update the parcel with the courier (or cancel and re-send it) after saving.</div>@endif
<form id="oeF" method="post" action="/admin/orders/{{ $o->id }}/revise" style="margin-top:12px">@csrf
<input type="hidden" name="call_log_id" value="{{ $call ?: '' }}"><input type="hidden" name="mode" id="oeMode" value="save"><div id="oeItems"></div>
<div class="oe-g"><div>
 <div class="card"><div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap"><b>Order items</b><div style="position:relative;flex:1;min-width:240px;max-width:460px"><input id="oeAdd" placeholder="➕ Add product — name, SKU, product code or barcode" autocomplete="off" style="width:100%"><div id="oeAddRes" class="oe-res" style="display:none"></div></div></div>
 <div style="overflow-x:auto"><table class="oe-t"><thead><tr><th>Image</th><th>Product</th><th>Qty</th><th>Unit price</th><th>Discount</th><th>Line total</th><th>Action</th></tr></thead><tbody id="oeBody"></tbody></table></div>
 <div id="oeWarn"></div></div>
 <div class="card"><b>Discount &amp; delivery</b>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;margin-top:8px">
   <label>Discount<select name="discount_mode" id="oeDm"><option value="auto" @selected($suggestDisc==='auto')>Recalculate automatically</option><option value="keep" @selected($suggestDisc==='keep')>Keep original discount (৳{{ number_format($o->discount ?? 0) }})</option>@if($P['discount'])<option value="manual">Manual amount…</option>@endif</select>
    <input type="number" step="0.01" min="0" name="discount_manual" id="oeDman" placeholder="Manual discount ৳" style="display:none;margin-top:6px"><small id="oeDnote" style="color:#6B7280;display:block;margin-top:4px"></small></label>
   <label>Coupon code<input name="coupon" id="oeCp" value="{{ $o->coupon }}" maxlength="40" autocomplete="off" placeholder="No coupon" style="text-transform:uppercase"><small style="color:#6B7280;display:block;margin-top:4px">Change or clear the code. The discount is recalculated with it.</small></label>
   <label>Delivery charge<select name="ship_mode" id="oeSm"><option value="auto" @selected($suggestShip==='auto')>Recalculate automatically</option><option value="keep" @selected($suggestShip==='keep')>Keep previous (৳{{ number_format($o->shipping) }})</option>@if($P['delivery'])<option value="override">Override manually…</option>@endif</select>
    <span id="oeSov" style="display:none"><input type="number" step="0.01" min="0" name="ship_override" id="oeSo" placeholder="Delivery charge ৳" style="margin-top:6px"><input name="ship_reason" id="oeSr" placeholder="Reason for override" style="margin-top:6px"></span><small id="oeSnote" style="color:#6B7280;display:block;margin-top:4px"></small></label>
  </div></div>
 <div class="card"><b>Confirmation</b>
  <label style="margin-top:8px">Reason for Order Change<select name="reason" id="oeRs" required><option value="">— select —</option>@foreach($reasons as $x)<option value="{{ $x }}" @selected(old('reason')===$x)>{{ $x }}</option>@endforeach</select></label>
  <label style="margin-top:8px">Note <small style="color:#6B7280">(required if Other)</small><textarea name="note" rows="2" maxlength="1000" style="width:100%">{{ old('note') }}</textarea></label>
  <label style="display:flex;gap:8px;align-items:center;margin-top:10px;font-weight:700"><input type="checkbox" name="customer_confirmed" value="1" id="oeCc" style="width:auto"> Customer confirmed this order change</label>
  @if($smsOn)<label style="display:flex;gap:8px;align-items:center;margin-top:6px"><input type="checkbox" name="send_sms" value="1" style="width:auto"> Save &amp; Send SMS — tell the customer the updated payable amount</label>@endif
  <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px"><button type="button" class="btn" data-m="save">Save Changes</button>@if(in_array($o->status,['New','Pre Order','Assign','Pending','AI Order']))<button type="button" class="btn" data-m="confirm" style="background:#16A34A">Save &amp; Confirm Order</button>@endif<button type="button" class="btn g" data-m="pending">Save as Pending Revision</button><a class="btn g" href="/admin/orders/{{ $o->id }}">Cancel</a></div>
  <div id="oeErr" style="color:#B91C1C;font-size:13px;margin-top:8px"></div></div>
</div>
<div class="card oe-s" style="position:sticky;top:10px"><b>Live summary</b>
 <div style="margin-top:6px"><span>Original Order Total</span><b>৳<span id="sO">0</span></b></div>
 <div><span>Subtotal</span><b>৳<span id="sSub">0</span></b></div>
 <div><span>Discount <small id="sDo"></small></span><b>−৳<span id="sDis">0</span></b></div>
 <div><span>Delivery Charge <small id="sSo"></small></span><b>৳<span id="sShp">0</span></b></div>
 <div class="big"><span>Grand Total</span><b>৳<span id="sTot">0</span></b></div>
 <div class="big" style="font-size:14px;margin-top:2px;border:0;padding-top:2px"><span>Difference</span><b id="sDif">±৳0</b></div>
 <div id="sLines" style="margin-top:8px;font-size:12px;color:#6B7280"></div></div>
</div></form>
@if($P['settings'])<form method="post" action="/admin/orders/revision-sms" style="margin-top:10px;font-size:13px">@csrf<label style="display:flex;gap:8px;align-items:center"><input type="hidden" name="on" value="0"><input type="checkbox" name="on" value="1" style="width:auto" @checked($smsOn) onchange="this.form.submit()"> Send Order Update SMS (when ON, the "Save & Send SMS" option appears on this page)</label></form>@endif
<div id="oeHist">@include('order_revision_block',['o'=>$o])</div>
@verbatim
<script>
(function(){
var $=function(i){return document.getElementById(i)},F=$('oeF'),T=document.querySelector('meta[name=csrf-token]').content,OID=
@endverbatim
{{ $o->id }};var ROWS=@json($rows),PERM=@json($P);
@verbatim
var qt=null,last=null;
function esc(t){return String(t==null?'':t).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
function money(n){return Math.round(n*100)/100}function fm(n){return Number(money(n)).toLocaleString('en-US',{maximumFractionDigits:2})}
function render(){var b=$('oeBody'),h='';
 ROWS.forEach(function(r,i){var rm=r._rm,total=money(r.qty*r.price),chg=Math.abs(r.price-r.base)>0.009;
  var st=!r.item_id?'<span style="color:#15803D;font-size:11px">NEW</span> ':(r._rep?'<span style="color:#7C3AED;font-size:11px">REPLACED</span> ':'');
  h+='<tr class="'+(rm?'row-rm':'')+'" data-i="'+i+'"><td>'+(r.image?'<img src="'+esc(r.image)+'">':'<img>')+'</td><td>'+st+'<b>'+esc(r.name)+'</b>'+(r._old?'<br><small style="color:#7C3AED">was: '+esc(r._old)+'</small>':'')+(r.sku?'<br><small style="color:#6B7280">'+esc(r.sku)+'</small>':'')+(r.stock!=null?'<br><small style="display:inline-block;margin-top:2px;padding:1px 8px;border-radius:999px;color:#000;font-weight:700;background:'+(r.stock<=0||r.qty>r.stock?'#FEE2E2':'#E7F8EC')+'">Stock: '+r.stock+(r.qty>r.stock&&r.stock>0?' (only '+r.stock+' available)':'')+'</small>':'')+'</td>'
   +'<td><span class="oe-q"><button type="button" data-a="dec">−</button><input type="number" min="1" data-f="qty" value="'+r.qty+'"'+(rm?' disabled':'')+'><button type="button" data-a="inc">+</button></span></td>'
   +'<td>৳<input type="number" step="0.01" min="0" data-f="price" value="'+r.price+'" style="width:84px"'+((PERM.price&&!rm)?'':' readonly title="Changing a price needs permission"')+'>'+(chg?'<br><input data-f="reason" placeholder="Reason for price change" value="'+esc(r.price_reason||'')+'" style="width:150px;margin-top:3px">':'')+(chg&&r.base!=null?'<br><small style="color:#6B7280">was ৳'+fm(r.base)+'</small>':'')+'</td>'
   +'<td data-d="'+i+'" style="color:#6B7280">—</td><td><b>৳'+fm(rm?0:total)+'</b></td><td class="oe-a"><button type="button" data-a="rep">Replace</button>'+(rm?'<button type="button" data-a="undo">Undo</button>':'<button type="button" class="rm" data-a="rm">Remove</button>')+'</td></tr>'});
 b.innerHTML=h;shareDisc();quote()}
var curDisc=0,curSub=0;
function shareDisc(){var sub=0;ROWS.forEach(function(r){if(!r._rm)sub+=r.qty*r.price});ROWS.forEach(function(r,i){var c=document.querySelector('[data-d="'+i+'"]');if(!c)return;var share=(!r._rm&&sub>0)?curDisc*(r.qty*r.price)/sub:0;c.textContent=share>0?'−৳'+fm(share):'—'})}
$('oeBody').addEventListener('click',function(e){var a=e.target.getAttribute('data-a');if(!a)return;var i=+e.target.closest('tr').getAttribute('data-i'),r=ROWS[i];
 if(a==='inc'){r.qty++}else if(a==='dec'){if(r.qty>1)r.qty--}
 else if(a==='rm'){if(!window.confirm('Remove this product from the order?'))return;if(!r.item_id){ROWS.splice(i,1)}else r._rm=true}
 else if(a==='undo'){r._rm=false}
 else if(a==='rep'){repIdx=i;$('oeAdd').placeholder='🔁 Replacing "'+r.name+'" — search the new product';$('oeAdd').focus();$('oeAdd').scrollIntoView({block:'center'});return}
 render()});
$('oeBody').addEventListener('input',function(e){var f=e.target.getAttribute('data-f');if(!f)return;var r=ROWS[+e.target.closest('tr').getAttribute('data-i')];
 if(f==='qty'){r.qty=Math.max(1,parseInt(e.target.value)||1)}else if(f==='price'){r.price=Math.max(0,parseFloat(e.target.value)||0)}else{r.price_reason=e.target.value;return}
 var tr=e.target.closest('tr');var tot=tr.children[5].firstChild;tot.textContent='৳'+fm(r.qty*r.price);if(f==='price'){clearTimeout(window._pr);window._pr=setTimeout(function(){render()},700)}else{shareDisc();quote()}});
/* ---- add / replace product */
var repIdx=-1,st=null;
$('oeAdd').addEventListener('input',function(){var q=this.value.trim();clearTimeout(st);if(!q){$('oeAddRes').style.display='none';return}
 st=setTimeout(function(){fetch('/admin/orders/rev-products?q='+encodeURIComponent(q),{headers:{Accept:'application/json'}}).then(function(r){return r.json()}).then(function(a){var box=$('oeAddRes'),h='';
  a.forEach(function(p,pi){if(p.variants.length){p.variants.forEach(function(v,vi){h+='<div data-p="'+pi+'" data-v="'+vi+'"><b>'+esc(p.name)+'</b> — '+esc(v.label)+' · ৳'+fm(v.price)+' · stock '+v.stock+(v.sku?' · '+esc(v.sku):'')+'</div>'})}else h+='<div data-p="'+pi+'"><b>'+esc(p.name)+'</b> · ৳'+fm(p.price)+' · stock '+p.stock+(p.sku?' · '+esc(p.sku):'')+(p.barcode?' · '+esc(p.barcode):'')+'</div>'});
  box.innerHTML=h||'<div style="color:#6B7280">No product found.</div>';box.style.display='block';box._d=a})},250)});
$('oeAddRes').addEventListener('click',function(e){var d=e.target.closest('[data-p]');if(!d)return;var p=this._d[+d.getAttribute('data-p')],v=d.hasAttribute('data-v')?p.variants[+d.getAttribute('data-v')]:null;
 var nm=p.name+(v?' – '+v.label:''),pr=v?v.price:p.price,stk=v?v.stock:p.stock;
 if(repIdx>=0){var r=ROWS[repIdx];if(!r._old)r._old=r.orig_name;r.product_id=p.id;r.variant_id=v?v.id:null;r.name=nm;r.price=pr;r.base=pr;r.sku=(v&&v.sku)||p.sku;r.image=p.image;r.stock=stk;r._rep=true;repIdx=-1;$('oeAdd').placeholder='➕ Add product — name, SKU, product code or barcode'}
 else{var ex=ROWS.filter(function(x){return x.product_id==p.id&&(x.variant_id||null)==(v?v.id:null)&&!x._rm})[0];if(ex&&!ex.item_id)ex.qty++;else ROWS.push({item_id:null,product_id:p.id,variant_id:v?v.id:null,name:nm,sku:(v&&v.sku)||p.sku,qty:1,price:pr,base:pr,image:p.image,stock:stk})}
 this.style.display='none';$('oeAdd').value='';render()});
document.addEventListener('click',function(e){if(!e.target.closest('#oeAdd,#oeAddRes'))$('oeAddRes').style.display='none'});
/* ---- server quote: discount rules + delivery charge */
function payload(){var items=ROWS.filter(function(r){return !r._rm}).map(function(r){return {item_id:r.item_id,product_id:r.product_id,variant_id:r.variant_id,name:r.name,qty:r.qty,price:r.price,price_reason:r.price_reason||''}});
 return {items:items,coupon:$('oeCp').value,discount_mode:$('oeDm').value,discount_manual:$('oeDman').value,ship_mode:$('oeSm').value,ship_override:$('oeSo').value,ship_reason:($('oeSr').value||$('oeRs').value)}}
function quote(){clearTimeout(qt);qt=setTimeout(function(){fetch('/admin/orders/'+OID+'/quote',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':T,Accept:'application/json'},body:JSON.stringify(payload())}).then(function(r){return r.json()}).then(show).catch(function(){})},250)}
function show(c){last=c;curDisc=c.disc;curSub=c.sub;shareDisc();
 $('sO').textContent=fm(c.old_total);$('sSub').textContent=fm(c.sub);$('sDis').textContent=fm(c.disc);$('sShp').textContent=fm(c.ship);$('sTot').textContent=fm(c.total);
 $('sDo').textContent=Math.abs(c.disc-c.old_disc)>0.009?'(was ৳'+fm(c.old_disc)+')':'';$('sSo').textContent=Math.abs(c.ship-c.old_ship)>0.009?'(was ৳'+fm(c.old_ship)+')':'';
 var d=$('sDif');d.textContent=(c.diff>0?'+':(c.diff<0?'−':'±'))+'৳'+fm(Math.abs(c.diff));d.className=c.diff>0?'oe-pos':(c.diff<0?'oe-neg':'');
 $('oeDnote').textContent='Original Discount ৳'+fm(c.old_disc)+' → Updated ৳'+fm(c.disc)+(c.dmode==='auto'&&c.disc_lines.length?' · '+c.disc_lines.filter(function(l){return l.applied}).map(function(l){return l.label}).join(' + '):'');
 $('oeSnote').textContent='Previous Delivery Charge ৳'+fm(c.old_ship)+' → Updated ৳'+fm(c.ship)+(c.smode==='auto'?' (system ৳'+fm(c.auto_ship)+')':'');
 var w='';(c.warnings||[]).forEach(function(x){w+='<div class="oe-w">⚠ '+esc(x)+'</div>'});(c.errors||[]).forEach(function(x){w+='<div class="oe-b">'+esc(x)+'</div>'});$('oeWarn').innerHTML=w}
$('oeRs').addEventListener('change',quote);['oeDm','oeSm'].forEach(function(i){$(i).addEventListener('change',function(){$('oeDman').style.display=$('oeDm').value==='manual'?'':'none';$('oeSov').style.display=$('oeSm').value==='override'?'':'none';quote()})});
['oeDman','oeSo','oeCp'].forEach(function(i){$(i).addEventListener('input',quote)});
/* ---- save */
document.querySelectorAll('[data-m]').forEach(function(b){b.addEventListener('click',function(){var m=b.getAttribute('data-m');$('oeErr').textContent='';
 if(!$('oeRs').value){$('oeErr').textContent='Select the reason for this order change.';return}
 if(m!=='pending'&&!$('oeCc').checked){$('oeErr').textContent='Tick "Customer confirmed this order change" — or use "Save as Pending Revision".';return}
 if(last&&last.errors&&last.errors.length){$('oeErr').textContent=last.errors[0];return}
 var box=$('oeItems');box.innerHTML='';var n=0;ROWS.forEach(function(r){if(r._rm)return;[['item_id',r.item_id||''],['product_id',r.product_id||''],['variant_id',r.variant_id||''],['name',r.name],['qty',r.qty],['price',r.price],['price_reason',r.price_reason||'']].forEach(function(p){var i=document.createElement('input');i.type='hidden';i.name='items['+n+']['+p[0]+']';i.value=p[1];box.appendChild(i)});n++});
 $('oeMode').value=m;F.submit()})});
render();
})();
</script>
@endverbatim
@endif
@endsection
