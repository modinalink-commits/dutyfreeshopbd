@extends('layout')@section('title', $c ? 'Edit coupon' : 'New coupon')
@section('body')
@php
$v = fn($k, $d = '') => old($k, $c->$k ?? $d);
$R = \App\Support\CouponRules::class;
$sv = old('starts_at', $c && !empty($c->starts_at) ? $R::local($c->starts_at, 'Y-m-d\TH:i') : ($c && !empty($c->valid_from) ? substr((string)$c->valid_from, 0, 10).'T00:00' : ''));
$ev = old('ends_at', $c && !empty($c->ends_at) ? $R::local($c->ends_at, 'Y-m-d\TH:i') : ($c && !empty($c->valid_to) ? substr((string)$c->valid_to, 0, 10).'T23:59' : ''));
$selCat = array_map('strval', old('category_ids', $c ? $R::ids($c->category_ids ?? '') : []));
$selBr = array_map('strval', old('brand_ids', $c ? $R::ids($c->brand_ids ?? '') : []));
$aud = $v('audience', 'all'); $act = old('active', $c ? (int)$c->active : 1); $pub = old('is_public', $c ? (int)$c->is_public : 0);
@endphp
<style>
.cf .sec{border:1px solid #E5E7EB;border-radius:12px;padding:14px;margin-bottom:12px;background:#fff}.cf .sec h3{margin:0 0 4px;font-size:15px}.cf .sec>p{margin:0 0 8px;color:#6B7280;font-size:12px}
.cf .row{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px}.cf .opt{display:flex;gap:8px;align-items:flex-start;padding:8px 10px;border:1px solid #E5E7EB;border-radius:10px;margin:4px 0;cursor:pointer;color:#0B1220}.cf .opt input{width:auto;margin-top:3px}.cf .opt.on{border:2px solid #000;background:#FFF1E8}
.cf .q{display:inline-block;margin:4px 4px 0 0;padding:4px 10px;border-radius:999px;border:1px solid #D1D5DB;background:#F3F4F6;color:#0B1220;font-size:12px;font-weight:700;cursor:pointer}
.cf .chip{display:inline-flex;gap:6px;align-items:center;padding:3px 8px;margin:3px 4px 0 0;border-radius:999px;background:#E6EFF9;border:1px solid #E6EFF9;color:#0B1220;font-size:12px;font-weight:600}.cf .chip button{padding:0 4px;background:none;border:0;color:#0B1220;font-size:14px;cursor:pointer}
.cf #psr{border:1px solid #E5E7EB;border-radius:8px;max-height:180px;overflow:auto;display:none;margin-top:4px}.cf #psr div{padding:6px 10px;cursor:pointer;font-size:13px;color:#0B1220}.cf #psr div:hover{background:#FFF1E8}
.cf .hint{font-size:11.5px;color:#6B7280;margin-top:3px}.cf select[multiple]{min-height:130px}
</style>
<div class="cf">
<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:12px"><h1 style="margin:0;flex:1">{{ $c ? 'Edit coupon '.$c->code : 'New coupon' }}</h1><a class="btn g" href="/admin/coupons">Back to coupons</a></div>
@if($errors->any())<div class="card" style="background:#FEE2E2;border-color:#FECACA;color:#0B1220">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
<form method="post" action="{{ $c ? '/admin/coupons/'.$c->id : '/admin/coupons' }}" id="cfm">@csrf

<div class="sec"><h3>1. Coupon</h3><p>The code the customer types at checkout.</p>
<div class="row"><div><label>Code *</label><div style="display:flex;gap:6px"><input name="code" id="ccode" value="{{ $v('code') }}" maxlength="40" required style="text-transform:uppercase"><button type="button" class="g" id="cgen" style="white-space:nowrap">Make code</button></div></div>
<div><label>Name (for staff only)</label><input name="name" value="{{ $v('name') }}" maxlength="120" placeholder="e.g. Eid offer 2026"></div></div>
<label>Note (for staff only)</label><textarea name="description" rows="2" maxlength="1000">{{ $v('description') }}</textarea>
<div style="display:flex;gap:18px;flex-wrap:wrap;margin-top:8px"><label style="display:flex;gap:6px;align-items:center;color:#0B1220;font-size:13px"><input type="checkbox" name="active" value="1" {{ $act ? 'checked' : '' }} style="width:auto"> Active (customers can use it)</label>
<label style="display:flex;gap:6px;align-items:center;color:#0B1220;font-size:13px"><input type="checkbox" name="is_public" value="1" {{ $pub ? 'checked' : '' }} style="width:auto"> Show in the customer's My Coupons page (only to customers it is made for)</label></div></div>

<div class="sec"><h3>2. How much discount</h3><p>Percent, fixed taka, or free delivery.</p>
<div class="row"><div><label>Discount type *</label><select name="type" id="ctype">@foreach($R::TYPES as $k=>$l)<option value="{{ $k }}" {{ $v('type','percent')===$k?'selected':'' }}>{{ $l }}</option>@endforeach</select></div>
<div id="cvalw"><label id="cvall">Value *</label><input name="value" id="cval" type="number" step="0.01" min="0" value="{{ $v('value','') }}"></div>
<div id="cmaxw"><label>Maximum discount (৳) <small>percent only</small></label><input name="max_discount" type="number" step="0.01" min="0" value="{{ $v('max_discount') }}" placeholder="No limit"></div></div>
<div class="row"><div><label>Minimum order amount (৳)</label><input name="min_order" type="number" step="0.01" min="0" value="{{ $v('min_order', 0) }}"></div>
<div><label>Minimum items in the order</label><input name="min_qty" type="number" min="1" value="{{ $v('min_qty') }}" placeholder="No minimum"></div></div>
<div class="hint">Free delivery works on the website checkout only. For percent and fixed coupons, if you pick products below, the discount is worked out on those products only.</div></div>

<div class="sec"><h3>3. Which customers</h3><p>Who is allowed to use this coupon.</p>
@foreach($R::AUDIENCES as $k=>$l)<label class="opt {{ $aud===$k?'on':'' }}"><input type="radio" name="audience" value="{{ $k }}" {{ $aud===$k?'checked':'' }}><span>{{ $l }}</span></label>@endforeach
<div id="cphw" style="display:none"><label>Mobile numbers (one per line or separated by comma) *</label><textarea name="audience_phones" rows="4" placeholder="01XXXXXXXXX">{{ $v('audience_phones') }}</textarea></div>
<div class="row" style="margin-top:6px"><div><label>At least this many earlier orders</label><input name="min_past_orders" type="number" min="0" value="{{ $v('min_past_orders') }}" placeholder="Any"></div><div><label>At most this many earlier orders</label><input name="max_past_orders" type="number" min="0" value="{{ $v('max_past_orders') }}" placeholder="Any"></div></div></div>

<div class="sec"><h3>4. How long</h3><p>Exact start and end, Bangladesh time. Leave empty for no limit. The coupon stops working by itself at the end time.</p>
<div class="row"><div><label>Starts</label><input type="datetime-local" name="starts_at" id="cst" value="{{ $sv }}"></div><div><label>Ends</label><input type="datetime-local" name="ends_at" id="cen" value="{{ $ev }}"></div></div>
<div><span class="q" data-d="1">Today only</span><span class="q" data-d="3">3 days</span><span class="q" data-d="7">7 days</span><span class="q" data-d="15">15 days</span><span class="q" data-d="30">30 days</span><span class="q" data-d="0">No limit</span></div></div>

<div class="sec"><h3>5. Usage limits</h3><div class="row"><div><label>Total times the coupon can be used</label><input name="usage_limit" type="number" min="1" value="{{ $v('usage_limit') }}" placeholder="Unlimited"></div>
<div><label>Times one customer can use it</label><input name="per_customer" type="number" min="0" value="{{ $v('per_customer', 1) }}"><div class="hint">0 means no limit per customer.</div></div></div></div>

<div class="sec"><h3>6. Where it works</h3><div class="row"><div><label>Channel</label><select name="channel">@foreach($R::CHANNELS as $k=>$l)<option value="{{ $k }}" {{ $v('channel','any')===$k?'selected':'' }}>{{ $l }}</option>@endforeach</select></div></div>
<p style="margin-top:10px">Limit to certain products (optional). Leave all empty to work on the whole cart.</p>
<label>Products</label><input id="ps" placeholder="Search product name or SKU" autocomplete="off"><div id="psr"></div><div id="pch">@foreach($picked as $p)<span class="chip" data-id="{{ $p->id }}">{{ $p->name }}<button type="button">×</button></span>@endforeach</div><input type="hidden" name="product_ids" id="pids" value="{{ old('product_ids', $c->product_ids ?? '') }}">
<div class="row"><div><label>Categories (hold Ctrl / Cmd to pick many)</label><select name="category_ids[]" multiple>@foreach($cats as $x)<option value="{{ $x->id }}" {{ in_array((string)$x->id, $selCat) ? 'selected' : '' }}>{{ $x->parent_id ? '— ' : '' }}{{ $x->name }}</option>@endforeach</select></div>
<div><label>Brands</label><select name="brand_ids[]" multiple>@foreach($brands as $x)<option value="{{ $x->id }}" {{ in_array((string)$x->id, $selBr) ? 'selected' : '' }}>{{ $x->name }}</option>@endforeach</select></div></div></div>

<div style="display:flex;gap:8px;margin-bottom:16px"><button>{{ $c ? 'Save changes' : 'Create coupon' }}</button><a class="btn g" href="/admin/coupons">Cancel</a></div>
</form>

@if($c)
<div class="sec"><h3>Test this coupon</h3><p>Check if it works for a customer before you send it.</p>
<div class="row"><div><label>Customer mobile</label><input id="tph" placeholder="01XXXXXXXXX"></div><div><label>Order amount (৳)</label><input id="tsub" type="number" min="0" value="1000"></div><div style="align-self:end"><button type="button" id="tgo">Check</button></div></div><div id="tres" style="margin-top:8px;font-weight:700;color:#0B1220"></div></div>
<div class="sec"><h3>Make many codes like this one</h3><p>Creates new codes with the same rules and downloads them as a CSV file.</p>
<form method="post" action="/admin/coupons/{{ $c->id }}/bulk">@csrf<div class="row"><div><label>Code prefix</label><input name="prefix" maxlength="12" value="{{ substr($c->code, 0, 8) }}" required></div><div><label>How many (max 500)</label><input name="count" type="number" min="1" max="500" value="50" required></div><div style="align-self:end"><label style="display:flex;gap:6px;align-items:center;color:#0B1220"><input type="checkbox" name="single_use" value="1" checked style="width:auto"> One use per code</label></div></div><div style="margin-top:8px"><button class="g">Generate and download</button></div></form></div>
@endif
</div>
<script>
(function(){
var $=function(i){return document.getElementById(i)};
function t(){var ty=$('ctype').value;$('cvalw').style.display=ty==='free_delivery'?'none':'';$('cmaxw').style.display=ty==='percent'?'':'none';$('cvall').textContent=ty==='percent'?'Discount percent (%) *':'Discount amount (৳) *';var a=document.querySelector('input[name=audience]:checked');$('cphw').style.display=a&&a.value==='phones'?'block':'none';document.querySelectorAll('.cf .opt').forEach(function(o){o.classList.toggle('on',o.querySelector('input').checked)})}
document.addEventListener('change',t);t();
$('cgen').onclick=function(){var s='ABCDEFGHJKMNPQRSTUVWXYZ23456789',r='';for(var i=0;i<8;i++)r+=s[Math.floor(Math.random()*s.length)];$('ccode').value=r};
function pad(n){return n<10?'0'+n:n}function fmt(d){return d.getFullYear()+'-'+pad(d.getMonth()+1)+'-'+pad(d.getDate())+'T'+pad(d.getHours())+':'+pad(d.getMinutes())}
document.querySelectorAll('.cf .q').forEach(function(b){b.onclick=function(){var n=parseInt(b.dataset.d,10);if(!n){$('cst').value='';$('cen').value='';return}var s=new Date();$('cst').value=fmt(s);var e=new Date(s.getTime());e.setDate(e.getDate()+n-1);e.setHours(23,59,0,0);$('cen').value=fmt(e)}});
var ids=($('pids').value||'').split(',').filter(Boolean);
function sync(){$('pids').value=ids.join(',')}
$('pch').addEventListener('click',function(e){if(e.target.tagName==='BUTTON'){var c=e.target.parentNode;ids=ids.filter(function(x){return x!==c.dataset.id});c.remove();sync()}});
var tm;$('ps').addEventListener('input',function(){clearTimeout(tm);var s=this.value.trim(),R=$('psr');if(s.length<2){R.style.display='none';return}tm=setTimeout(function(){fetch('/admin/orders/rev-products?q='+encodeURIComponent(s),{credentials:'same-origin',headers:{Accept:'application/json'}}).then(function(r){return r.json()}).then(function(L){R.textContent='';L.forEach(function(p){var d=document.createElement('div');d.textContent=p.name+(p.sku?' · '+p.sku:'');d.onclick=function(){var id=String(p.id);if(ids.indexOf(id)<0){ids.push(id);var c=document.createElement('span');c.className='chip';c.dataset.id=id;c.textContent=p.name;var b=document.createElement('button');b.type='button';b.textContent='×';c.appendChild(b);$('pch').appendChild(c);sync()}R.style.display='none';$('ps').value=''};R.appendChild(d)});if(!L.length){R.textContent='No product found'}R.style.display='block'}).catch(function(){R.textContent='Product search is not available for your role. Ask an admin.';R.style.display='block'})},250)});
var g=$('tgo');if(g)g.onclick=function(){var fd=new FormData();fd.append('_token',document.querySelector('input[name=_token]').value);fd.append('phone',$('tph').value);fd.append('sub',$('tsub').value);fetch('/admin/coupons/{{ $c->id ?? 0 }}/test',{method:'POST',body:fd,credentials:'same-origin',headers:{Accept:'application/json'}}).then(function(r){return r.json()}).then(function(d){var x=$('tres');x.textContent=(d.ok?'✅ ':'❌ ')+d.message;x.style.color='#000'}).catch(function(){$('tres').textContent='Could not check'})}
})();
</script>
@endsection
