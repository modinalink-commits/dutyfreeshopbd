@extends('layout')@section('title','Add damage')
@section('body')
<h1 style="margin:0 0 10px">Add Damage</h1>@include('proc.nav')
<form class="card" method="post" action="/admin/purchase/damages" enctype="multipart/form-data">@csrf<input type="hidden" name="product_id" id="pid">
<div class="fg"><div><label>Supplier</label><select name="supplier_id" id="sup"><option value="">— none (company stock) —</option>@foreach($sup as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></div>
<div><label>Purchase invoice</label><select name="purchase_id" id="inv"><option value="">—</option></select></div>
<div><label>Product * <span class="mut" id="pn"></span></label><select id="isel" style="display:none"></select><input id="pq" placeholder="Search product / SKU…" autocomplete="off"><div id="pl" style="position:relative"></div></div>
<div><label>Batch</label><input name="batch" id="batch"></div><div><label>Quantity *</label><input type="number" min="1" name="qty" id="qty" required></div>
<div><label>Purchase price</label><input type="number" step="0.01" name="unit_cost" id="uc" value="0"></div><div><label>Damage value</label><input id="val" disabled value="0.00"></div>
<div><label>Damage reason</label><select name="reason_type">@foreach($reasons as $r)<option>{{ $r }}</option>@endforeach</select></div>
<div><label>Damage date *</label><input type="date" name="dmg_date" required value="{{ now('Asia/Dhaka')->toDateString() }}"></div>
<div><label>Store</label><select name="store_id"><option value="">—</option>@foreach($stores as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></div>
<div><label>Responsibility</label><select name="responsibility"><option value="company">Company loss</option><option value="supplier">Supplier claim (reduces supplier due)</option></select></div>
<div><label>Photo</label><input type="file" name="photo" accept="image/*"></div></div>
<label>Note</label><textarea name="note" rows="2"></textarea><p class="mut">Damaged quantity leaves saleable stock and is counted in damage stock. A supplier claim needs a supplier selected.</p>
<button>Save damage</button> <a class="btn g" href="/admin/purchase/damages">Cancel</a></form>
<script>(function(){var $=function(i){return document.getElementById(i)},sup=$('sup'),inv=$('inv'),is=$('isel'),H=function(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})},it=[];
function val(){$('val').value=((+$('qty').value||0)*(+$('uc').value||0)).toFixed(2)}$('qty').oninput=val;$('uc').oninput=val;
sup.onchange=function(){inv.innerHTML='<option value="">—</option>';is.style.display='none';if(!sup.value)return;fetch('/admin/purchase/api/supplier/'+sup.value).then(function(r){return r.json()}).then(function(d){d.invoices.forEach(function(x){inv.insertAdjacentHTML('beforeend','<option value="'+x.id+'">'+H(x.invoice)+' · '+x.date+'</option>')})})};
inv.onchange=function(){is.style.display='none';if(!inv.value)return;fetch('/admin/purchase/api/items/'+inv.value).then(function(r){return r.json()}).then(function(a){it=a;is.innerHTML='<option value="">Pick from this invoice…</option>'+a.map(function(x,i){return '<option value="'+i+'">'+H(x.name)+' ('+x.qty+' @ '+x.cost+')</option>'}).join('');is.style.display='block'})};
is.onchange=function(){var x=it[is.value];if(!x)return;$('pid').value=x.product_id||'';$('pn').textContent=x.name;$('uc').value=x.cost;$('batch').value=x.batch||'';val()};
var tm;$('pq').oninput=function(){clearTimeout(tm);var v=this.value.trim();if(!v){$('pl').innerHTML='';return}tm=setTimeout(function(){fetch('/admin/purchase/api/products?q='+encodeURIComponent(v)).then(function(r){return r.json()}).then(function(a){$('pl').innerHTML='<div style="position:absolute;z-index:9;left:0;right:0;background:#fff;border:1px solid var(--bd);border-radius:10px;max-height:220px;overflow:auto">'+a.map(function(p){return '<div class="pp" data-i="'+p.id+'" data-n="'+H(p.name)+'" data-c="'+p.cost+'" style="padding:7px 10px;cursor:pointer">'+H(p.name)+' <span class="mut">'+H(p.sku||'')+'</span></div>'}).join('')+'</div>';$('pl').querySelectorAll('.pp').forEach(function(e){e.onclick=function(){$('pid').value=e.dataset.i;$('pn').textContent=e.dataset.n;if(!+$('uc').value)$('uc').value=e.dataset.c;$('pl').innerHTML='';$('pq').value='';val()}})})},200)};
document.querySelector('form').onsubmit=function(){if(!$('pid').value){alert('Choose a product');return false}}})();</script>
@endsection
