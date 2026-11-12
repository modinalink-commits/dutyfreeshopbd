@extends('layout')@section('title','Edit Store')
@section('body')
@php
  $v = fn($k, $d = '') => old($k, $s->{$k} ?? $d);
  $selCats = array_map('trim', explode(',', (string)old('source_cat_str', $s->source_category ?? '')));
  $gset = fn($k, $d) => (string)(\Illuminate\Support\Facades\DB::table('settings')->where('key', $k)->value('value') ?? $d);
  $defSale = $gset('source_markup_pct', '15'); $defReg = $gset('source_regular_pct', '25');
  $pay = $v('pay_mode', 'prepaid');
@endphp
<style>
.te{max-width:1080px}.te .grid{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(0,1fr);gap:14px;align-items:start}@media(max-width:860px){.te .grid{grid-template-columns:minmax(0,1fr)}}
.te .tc{background:#fff;border:1px solid #E3E6EC;border-radius:10px;padding:12px 14px;margin-bottom:12px;box-shadow:0 1px 2px rgba(15,27,51,.04)}
.te h2{font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:#6B7280;margin:0 0 10px;display:flex;justify-content:space-between;align-items:center}
.te .r2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}@media(max-width:520px){.te .r2{grid-template-columns:minmax(0,1fr)}}
.te label{display:block;font-weight:600;font-size:12px;margin:0 0 3px}.te label small{font-weight:400;color:#6B7280}
.te .hint{color:#6B7280;font-size:11.5px;margin-top:4px}
.te .seg{display:flex;border:1px solid #E3E6EC;border-radius:8px;overflow:hidden}.te .seg label{flex:1;margin:0;text-align:center;padding:8px;cursor:pointer;border-right:1px solid #E3E6EC;font-weight:600;background:#fff}.te .seg label:last-child{border-right:0}.te .seg input{display:none}.te .seg input:checked+span{color:#fff}.te .seg label:has(input:checked){background:#FF5A00;color:#fff}
.te .chips{display:flex;flex-wrap:wrap;gap:6px}.te .chips label{margin:0;border:1px solid #E3E6EC;border-radius:999px;padding:4px 10px;cursor:pointer;background:#fff;font-weight:500}.te .chips label:has(input:checked){background:#FFF1E8;border-color:#FF5A00;color:#FF5A00;font-weight:700}.te .chips input{display:none}
.te .staff{border:1px dashed #FF5A00}
.te .pill{display:inline-block;border-radius:999px;padding:2px 9px;font-size:11px;font-weight:700;background:#FFF1E8;color:#FF5A00}
.te .pv{display:flex;gap:10px;align-items:center;border:1px solid #E3E6EC;border-radius:10px;padding:10px}.te .img{width:60px;height:60px;border-radius:8px;background:repeating-linear-gradient(45deg,#E3E6EC,#E3E6EC 6px,transparent 6px,transparent 12px);flex:none}
.te .price{font-size:18px;font-weight:800}.te .old{color:#6B7280;text-decoration:line-through;margin-left:6px;font-weight:400;font-size:13px}
.te table{width:100%;border-collapse:collapse;font-size:12.5px}.te td{padding:5px 0;border-bottom:1px solid #E3E6EC}.te td:last-child{text-align:right;font-weight:700}
.te .al{border-radius:8px;padding:8px 10px;background:#FEF3C7;color:#B45309;font-weight:600;margin-top:8px;display:none}
.te .sticky{position:sticky;top:8px}.te .tpl{display:flex;gap:6px;flex-wrap:wrap}
</style>
<h1>Edit Store — Terms &amp; Conditions</h1>
@if($errors->any())<div class="card err">{{ $errors->first() }}</div>@endif
<form class="te" method="post" action="/admin/stores/{{ $s->id }}/update">@csrf
<div class="grid"><div>
 <div class="tc"><h2>Quick template <span class="pill">starts from</span></h2><div class="tpl"><button type="button" class="btn g" data-t="sourcing">Sourcing standard</button><button type="button" class="btn g" data-t="premium">Premium partner</button><button type="button" class="btn g" data-t="local">Local stock</button></div>
  <div class="hint">One click fills the pricing, payment and delivery fields. You can still change each one. "Sourcing standard" = +15% sale, +25% regular, Prepaid only, 3–5 days.</div></div>

 <div class="tc"><h2>1 · Store</h2><div class="r2"><div><label>Store name</label><input name="name" value="{{ $v('name') }}" required maxlength="120"></div><div><label>Domain</label><input name="domain" value="{{ $v('domain') }}" required maxlength="200" placeholder="example.com"></div></div>
  <div style="display:flex;gap:18px;flex-wrap:wrap;margin-top:10px"><label style="display:flex;gap:6px;align-items:center;margin:0"><input type="checkbox" name="active" value="1" style="width:auto" {{ $s->active ? 'checked' : '' }}> Active</label>
  <label style="display:flex;gap:6px;align-items:center;margin:0;color:#B91C1C"><input type="checkbox" name="paused" value="1" style="width:auto" {{ $v('paused') ? 'checked' : '' }}> Pause store <small>(hides all its products at once; untick to bring them back)</small></label></div></div>

 <div class="tc"><h2>2 · Category <small style="text-transform:none;letter-spacing:0">(from the main list)</small></h2>
  <div class="chips">@foreach($catNames as $__cn)<label><input type="checkbox" name="source_categories[]" value="{{ $__cn }}" {{ in_array($__cn, $selCats, true) ? 'checked' : '' }}>{{ $__cn }}</label>@endforeach</div>
  <div class="hint">The first tick is where "Import" puts the products. A category may have 2–3 domains. Create a new category from the Stores page.</div></div>

 <div class="tc"><h2>3 · Pricing</h2><div class="r2">
  <div><label>Sale price commission <small>(source + %) · empty = {{ $defSale }}</small></label><input id="sale" name="sale_pct" type="number" step="0.01" min="0" value="{{ $v('sale_pct') }}" placeholder="{{ $defSale }}"></div>
  <div><label>Regular (crossed-out) price <small>(source + %) · empty = {{ $defReg }}</small></label><input id="reg" name="regular_pct" type="number" step="0.01" min="0" value="{{ $v('regular_pct') }}" placeholder="{{ $defReg }}"></div></div>
  <div class="r2" style="margin-top:10px"><div><label>Only import products priced <small>(BDT, optional)</small></label><div style="display:flex;gap:6px"><input name="price_min" type="number" min="0" value="{{ $v('price_min') }}" placeholder="min"><input name="price_max" type="number" min="0" value="{{ $v('price_max') }}" placeholder="max"></div></div>
  <div><label>Price jump guard <small>(hide a product if the vendor raises its price by more than %)</small></label><input name="price_jump_pct" type="number" min="0" value="{{ $v('price_jump_pct', 20) }}" placeholder="20"></div></div>
  <div class="hint">Protected margin 10% stays on. Changing the % applies to new imports at once; for existing products press "Apply prices now" below (it also accepts held price jumps).</div></div>

 <div class="tc"><h2>4 · Payment</h2><div class="seg">@foreach(['prepaid'=>'Prepaid only','cod'=>'Cash on Delivery allowed','part'=>'Partial advance + COD'] as $k=>$l)<label><input type="radio" name="pay_mode" value="{{ $k }}" {{ $pay===$k ? 'checked' : '' }}><span>{{ $l }}</span></label>@endforeach</div>
  <div class="hint">Prepaid makes the store's categories block COD at checkout (discounts and bundles stay off). COD / partial advance is saved as the store's term; opening COD on a category stays a separate approval.</div></div>

 <div class="tc"><h2>5 · Delivery time &amp; fulfilment</h2><div class="r2">
  <div><label>Delivery within <small>(days)</small></label><div style="display:flex;gap:6px;align-items:center"><input id="dmin" name="delivery_min" type="number" min="0" max="60" value="{{ $v('delivery_min', 3) }}"> to <input id="dmax" name="delivery_max" type="number" min="0" max="60" value="{{ $v('delivery_max', 5) }}"></div></div>
  <div><label>Shown to the customer as</label><select id="dstyle" name="delivery_style"><option value="range" {{ $v('delivery_style','range')==='range'?'selected':'' }}>Delivery in 3–5 days</option><option value="max" {{ $v('delivery_style')==='max'?'selected':'' }}>Delivered within 5 days</option></select></div></div>
  <div class="r2" style="margin-top:10px"><div><label>Fulfilment</label><select name="fulfillment"><option value="dropship" {{ $v('fulfillment','dropship')==='dropship'?'selected':'' }}>Vendor ships directly to the customer</option><option value="warehouse" {{ $v('fulfillment')==='warehouse'?'selected':'' }}>Vendor sends to us, then we ship</option></select></div>
  <div><label>Order cut-off time <small>(Dhaka, optional)</small></label><input name="cutoff_time" type="time" value="{{ $v('cutoff_time') }}"></div></div>
  <div class="hint">Our own stock stays 24–48 hours. For a mixed cart the longest time is shown.</div></div>

 <div class="tc"><h2>6 · Returns, shipping &amp; limits</h2><div class="r2">
  <div><label>Return window <small>(days · 0 = no returns · empty = not shown)</small></label><input name="return_days" type="number" min="0" max="90" value="{{ $v('return_days') }}"></div>
  <div><label>Return shipping paid by</label><select name="return_cost_by"><option value="">—</option><option value="customer" {{ $v('return_cost_by')==='customer'?'selected':'' }}>Customer</option><option value="us" {{ $v('return_cost_by')==='us'?'selected':'' }}>DutyFreeBD</option></select></div></div>
  <div style="margin-top:10px"><label>Return note <small>(shown to the customer)</small></label><input name="return_note" maxlength="255" value="{{ $v('return_note') }}" placeholder="e.g. Unused, in original packaging"></div>
  <div class="r2" style="margin-top:10px"><div><label>Shipping charge</label><select name="ship_rule"><option value="default" {{ $v('ship_rule','default')==='default'?'selected':'' }}>Our standard charge</option><option value="flat" {{ $v('ship_rule')==='flat'?'selected':'' }}>Flat charge for this store</option><option value="free_over" {{ $v('ship_rule')==='free_over'?'selected':'' }}>Free over an amount</option></select></div>
  <div><label>Flat charge / free-over amount <small>(BDT)</small></label><div style="display:flex;gap:6px"><input name="ship_flat" type="number" min="0" value="{{ $v('ship_flat') }}" placeholder="flat ৳"><input name="ship_free_over" type="number" min="0" value="{{ $v('ship_free_over') }}" placeholder="free over ৳"></div></div></div>
  <div class="r2" style="margin-top:10px"><div><label>Min order value <small>(BDT)</small></label><input name="min_order_value" type="number" min="0" value="{{ $v('min_order_value') }}"></div><div><label>Max quantity / max order value <small>(optional)</small></label><div style="display:flex;gap:6px"><input name="max_qty" type="number" min="0" value="{{ $v('max_qty') }}" placeholder="qty"><input name="max_order_value" type="number" min="0" value="{{ $v('max_order_value') }}" placeholder="৳"></div></div></div>
  <div style="margin-top:10px"><label>Authenticity / warranty line <small>(shown to the customer)</small></label><input name="auth_note" maxlength="160" value="{{ $v('auth_note') }}" placeholder="e.g. 100% original, brand warranty"></div>
  <div class="hint">Return and authenticity lines and delivery time show on the product page now. Shipping charge and order limits are saved as the store's terms; enforcement at checkout is the next step.</div></div>

 <div class="tc staff"><h2>7 · Vendor details <span class="pill">staff only · hidden from customers</span></h2>  <div class="r2"><div><label>Company name</label><input name="company_name" maxlength="160" value="{{ $v('company_name') }}" placeholder="Registered / trading name"></div><div><label>Website</label><input name="website" maxlength="200" value="{{ $v('website') ?: ($s->domain ?? '') }}" placeholder="https://"></div></div>
  <div class="r2" style="margin-top:10px"><div><label>Mobile number</label><input name="company_phone" maxlength="40" value="{{ $v('company_phone') }}" placeholder="01XXXXXXXXX"></div><div><label>Support number <small>(hotline / WhatsApp)</small></label><input name="support_phone" maxlength="40" value="{{ $v('support_phone') }}" placeholder="01XXXXXXXXX"></div></div>
  <div class="r2" style="margin-top:10px"><div><label>Email</label><input name="company_email" maxlength="120" value="{{ $v('company_email') }}" placeholder="info@..."></div><div><label>Address</label><input name="company_address" maxlength="300" value="{{ $v('company_address') }}" placeholder="Shop / office address"></div></div>
  <div style="height:10px"></div><div class="r2">
  <div><label>Vendor contact / WhatsApp</label><input name="vendor_contact" maxlength="60" value="{{ $v('vendor_contact') }}" placeholder="01XXXXXXXXX"></div>
  <div><label>How we order</label><select name="order_channel"><option value="manual" {{ $v('order_channel','manual')==='manual'?'selected':'' }}>Manual (Vendor Orders page)</option><option value="whatsapp" {{ $v('order_channel')==='whatsapp'?'selected':'' }}>WhatsApp message</option></select></div></div>
  <div class="r2" style="margin-top:10px"><div><label>How we pay the vendor</label><input name="vendor_pay_terms" maxlength="160" value="{{ $v('vendor_pay_terms') }}" placeholder="e.g. bKash, advance"></div><div><label>Vendor lead time <small>(days to dispatch)</small></label><input name="vendor_lead_days" type="number" min="0" max="60" value="{{ $v('vendor_lead_days') }}"></div></div>
  <div style="margin-top:10px"><label>Internal note</label><textarea name="internal_note" rows="2" maxlength="2000">{{ $v('internal_note') }}</textarea></div></div>

 <div style="display:flex;gap:8px;flex-wrap:wrap"><button>Save store</button><a class="btn g" href="/admin/stores">Cancel</a></div>
</div>
<div class="sticky">
 <div class="tc"><h2>Customer sees</h2><div class="pv"><div class="img"></div><div><div style="font-weight:700">Sample product</div><div><span class="price" id="pp">৳1,150</span><span class="old" id="po">৳1,250</span></div><div style="margin-top:4px"><span class="pill" id="pb">Prepaid only</span> <span class="pill" style="background:#E7F6EC;color:#0E7A3F" id="pd">Delivery in 3–5 days</span></div></div></div><div class="hint">Example with a source price of ৳1,000.</div></div>
 <div class="tc"><h2>Staff sees (order line)</h2><table><tr><td>Vendor</td><td>{{ $s->name }}</td></tr><tr><td>Vendor price</td><td>৳1,000</td></tr><tr><td>Our sale price</td><td id="sp">৳1,150</td></tr><tr><td>Gross margin</td><td id="gm">13.0%</td></tr></table><div class="al" id="al">Margin is below the protected 10%. Raise the sale commission.</div></div>
 <div class="tc"><h2>Prices</h2><div class="hint" style="margin:0 0 8px">Re-prices every product of this store with the saved sale % / regular %, and accepts any held price jumps. Save first.</div></div>
</div></div></form>
<form method="post" action="/admin/stores/{{ $s->id }}/apply" class="te" style="margin-top:-6px" onsubmit="return confirm('Re-apply prices for all products of this store now?')">@csrf<button class="btn g">Apply prices now</button> <span class="hint">Last terms saved: {{ ($s->terms_updated_at ?? null) ? \Carbon\Carbon::parse($s->terms_updated_at)->timezone('Asia/Dhaka')->format('d M Y, H:i') : 'never' }}</span></form>
<script>
(function(){var $=function(i){return document.getElementById(i)},f=function(n){return '৳'+Math.round(n).toLocaleString('en-US')};
function calc(){var s=parseFloat($('sale').value),r=parseFloat($('reg').value);if(isNaN(s))s={{ (float)$defSale }};if(isNaN(r))r={{ (float)$defReg }};var a=+$('dmin').value||0,b=+$('dmax').value||0,sp=1000*(1+s/100);
$('pp').textContent=f(sp);$('po').textContent=f(1000*(1+r/100));$('sp').textContent=f(sp);var m=(sp-1000)/sp*100;$('gm').textContent=m.toFixed(1)+'%';$('al').style.display=m<10?'block':'none';
var ds=$('dstyle');ds.options[0].text='Delivery in '+a+'–'+b+' days';ds.options[1].text='Delivered within '+b+' days';$('pd').textContent=b?(ds.value==='range'&&a!==b?'Delivery in '+a+'–'+b+' days':'Delivered within '+b+' days'):'';
var p=document.querySelector('[name=pay_mode]:checked');$('pb').textContent=p&&p.value==='prepaid'?'Prepaid only':p&&p.value==='cod'?'COD available':'Advance + COD'}
document.querySelectorAll('#sale,#reg,#dmin,#dmax,#dstyle,[name=pay_mode]').forEach(function(e){e.addEventListener('input',calc);e.addEventListener('change',calc)});
var T={sourcing:[15,25,3,5,'prepaid'],premium:[12,20,2,4,'prepaid'],local:[10,15,1,2,'cod']};
document.querySelectorAll('[data-t]').forEach(function(b){b.onclick=function(){var t=T[b.dataset.t];$('sale').value=t[0];$('reg').value=t[1];$('dmin').value=t[2];$('dmax').value=t[3];document.querySelector('[name=pay_mode][value='+t[4]+']').checked=true;calc()}});calc()})();
</script>
@endsection
