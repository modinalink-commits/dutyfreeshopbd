@extends('layout')
@section('title', 'Retail sale')
@section('content')
<header style="display:flex;flex-wrap:wrap;align-items:center;gap:12px">
  <div style="flex-grow:1"><h1>Retail sale</h1><p class="sub">Counter and home delivery · next memo {{ $nextNo }}</p></div>
  <a class="btn" href="{{ route('sales.index') }}">Today's sales ({{ $todayCount }})</a>
</header>
<form method="post" action="{{ route('pos.store') }}" id="pos" style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-start">
  @csrf
  <section style="flex:999 1 460px;min-width:0;display:flex;flex-direction:column;gap:12px">
    <div class="tabs" id="tabs">
      <a href="#" data-cat="" class="on">All</a>
      @foreach(\App\Models\Product::CATEGORIES as $k => $l)<a href="#" data-cat="{{ $k }}">{{ $l }}</a>@endforeach
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px" id="grid">
      @forelse($items as $p)
      <button type="button" class="card prod" data-id="{{ $p['id'] }}" data-cat="{{ $p['cat'] }}" style="text-align:left;padding:14px;display:flex;flex-direction:column;gap:8px;cursor:pointer;font-family:inherit;color:var(--ink);min-height:150px" @disabled($p['price'] === null)>
        <div style="display:flex;align-items:center;gap:8px;width:100%"><span class="pill" style="background:{{ $p['cat'] === 'refill' ? '#e6eeff;color:#1d4ed8' : ($p['cat'] === 'new_package' ? '#e3f6ec;color:#0f6b3c' : '#efe7fb;color:#5b2fa8') }}">{{ $p['size'] }}</span><span style="flex-grow:1"></span>
          @if($p['stock'] !== null)<span style="font-size:11px;font-weight:600;color:{{ $p['stock'] < 10 ? '#b3261e' : 'var(--muted)' }}">{{ $p['stock'] }} in stock</span>@endif</div>
        <span style="font-size:14px;font-weight:700">{{ $p['name'] }}</span>
        <span style="font-size:11px;color:var(--muted)">{{ $p['note'] }}</span>
        <span style="margin-top:auto;font-size:16px;font-weight:700">@if($p['price'] !== null)৳{{ number_format($p['price']) }}@else<span style="color:#b3261e;font-size:12px">No price set</span>@endif</span>
      </button>
      @empty
      <p class="sub">No products yet. Add them in Products &amp; prices.</p>
      @endforelse
    </div>
  </section>
  <aside class="card" style="flex:1 1 340px;min-width:300px;padding:20px;display:flex;flex-direction:column;gap:14px">
    <h2 style="margin:0;font-size:15px">Memo</h2>
    <div class="grid2" style="grid-template-columns:repeat(2,minmax(0,1fr))">
      <label class="f">Customer phone<input type="tel" id="phone" name="walkin_phone" value="{{ old('walkin_phone') }}" placeholder="01XXXXXXXXX" autocomplete="off"></label>
      <label class="f">Name<input type="text" id="cname" name="walkin_name" value="{{ old('walkin_name') }}"></label>
    </div>
    <input type="hidden" name="customer_id" id="cid" value="{{ old('customer_id') }}">
    <span class="sub" id="cinfo">Walk-in customer. Type a saved phone to use a customer account.</span>
    <label class="f">Delivery charge (৳)<input type="number" name="delivery_charge" id="dc" min="0" step="1" value="{{ old('delivery_charge', 0) }}"></label>
    <div id="lines" style="border-top:1px solid var(--line);padding-top:12px;display:flex;flex-direction:column;gap:10px"><span class="sub" style="text-align:center;padding:16px 0" id="emptyMsg">Tap a product to add it.</span></div>
    <div id="hidden"></div>
    <div class="grid2" style="grid-template-columns:repeat(2,minmax(0,1fr))">
      <label class="f">Empties received<input type="number" name="empties" id="emp" min="0" value="{{ old('empties', 0) }}"></label>
      <label class="f">Empty brand<select name="empty_brand_id">@foreach($brands as $b)<option value="{{ $b->id }}" @selected(old('empty_brand_id', $ownBrand) == $b->id)>{{ $b->name }}</option>@endforeach</select></label>
    </div>
    @if(in_array(auth()->user()->role, ['super_admin', 'admin', 'manager']))<label class="f">Discount (৳)<input type="number" name="discount" id="disc" min="0" step="1" value="{{ old('discount', 0) }}"></label>@endif
    <div style="background:#eef2f9;border-radius:12px;padding:12px;display:flex;flex-direction:column;gap:6px;font-size:13px;font-variant-numeric:tabular-nums">
      <div style="display:flex"><span style="flex-grow:1;color:var(--muted)">Subtotal</span><span id="sub">৳0</span></div>
      <div style="display:flex"><span style="flex-grow:1;color:var(--muted)">Refills (empties expected)</span><span id="expEmp">0</span></div>
      <div style="display:flex;font-size:18px;font-weight:700;padding-top:6px;border-top:1px solid var(--line)"><span style="flex-grow:1">Total</span><span id="tot">৳0</span></div>
    </div>
    <div class="f">Payment
      <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:6px" id="pays">
        @foreach(['cash' => 'Cash', 'bkash' => 'bKash', 'nagad' => 'Nagad', 'due' => 'Due'] as $k => $l)
        <label style="display:flex"><input type="radio" name="method" value="{{ $k }}" @checked(old('method', 'cash') === $k) style="position:absolute;opacity:0;width:1px"><span class="paybtn">{{ $l }}</span></label>
        @endforeach
      </div>
    </div>
    <label class="f" id="paidBox" style="display:none">Paid now (rest goes on due)<input type="number" name="paid" min="0" step="1" value="{{ old('paid', 0) }}"></label>
    <button class="btn pri" type="submit" style="min-height:48px;font-size:15px" id="save" disabled>Save &amp; print memo</button>
  </aside>
</form>
<style>
.prod:disabled{opacity:.55;cursor:not-allowed}.prod:hover:not(:disabled){border-color:#123a86}
.paybtn{flex:1;display:flex;align-items:center;justify-content:center;min-height:44px;border-radius:8px;border:1px solid #7b8aa8;background:#fff;font-weight:600;cursor:pointer;color:var(--ink)}
#pays input:checked+.paybtn{border-color:#1d4ed8;background:#dce8ff;color:#1d4ed8}
#pays input:focus-visible+.paybtn{outline:2px solid #1d4ed8;outline-offset:2px}
.qty{width:32px;height:32px;border-radius:999px;border:1px solid #7b8aa8;background:#fff;font:700 15px sans-serif;cursor:pointer}
</style>
<script>
(function(){
  var items=@json($items), cart={}, byId={};
  items.forEach(function(p){byId[p.id]=p;});
  var tk=function(n){return '৳'+Math.round(n).toLocaleString('en-IN');};
  document.querySelectorAll('.prod').forEach(function(b){b.addEventListener('click',function(){var id=b.dataset.id;var p=byId[id];var q=(cart[id]||0)+1;if(p.stock!==null&&q>p.stock){alert('Only '+p.stock+' in stock.');return;}cart[id]=q;draw();});});
  document.querySelectorAll('#tabs a').forEach(function(a){a.addEventListener('click',function(e){e.preventDefault();document.querySelectorAll('#tabs a').forEach(function(x){x.classList.toggle('on',x===a);});var c=a.dataset.cat;document.querySelectorAll('.prod').forEach(function(b){b.style.display=!c||b.dataset.cat===c?'':'none';});});});
  function draw(){
    var box=document.getElementById('lines'),h=document.getElementById('hidden');box.innerHTML='';h.innerHTML='';var sub=0,refill=0;
    Object.keys(cart).forEach(function(id){var p=byId[id],q=cart[id];sub+=p.price*q;if(p.cat==='refill')refill+=q;
      var row=document.createElement('div');row.style.cssText='display:flex;align-items:center;gap:10px';
      row.innerHTML='<div style="flex-grow:1;display:flex;flex-direction:column"><span style="font-size:13px;font-weight:600"></span><span style="font-size:11px;color:#51607a">'+tk(p.price)+' each</span></div><button class="qty" type="button" aria-label="One less">−</button><span style="width:22px;text-align:center;font-weight:700">'+q+'</span><button class="qty" type="button" aria-label="One more">+</button><span style="width:78px;text-align:right;font-weight:700">'+tk(p.price*q)+'</span>';
      row.querySelector('span').textContent=p.name;
      var bs=row.querySelectorAll('button');bs[0].onclick=function(){cart[id]=q-1;if(cart[id]<=0)delete cart[id];draw();};bs[1].onclick=function(){if(p.stock!==null&&q+1>p.stock){alert('Only '+p.stock+' in stock.');return;}cart[id]=q+1;draw();};
      box.appendChild(row);
      var i=document.createElement('input');i.type='hidden';i.name='qty['+id+']';i.value=q;h.appendChild(i);});
    if(!Object.keys(cart).length){box.innerHTML='<span class="sub" style="text-align:center;padding:16px 0">Tap a product to add it.</span>';}
    var dc=parseFloat(document.getElementById('dc').value||0),ds=document.getElementById('disc')?parseFloat(document.getElementById('disc').value||0):0;
    document.getElementById('sub').textContent=tk(sub);document.getElementById('tot').textContent=tk(sub+dc-ds);document.getElementById('expEmp').textContent=refill;
    var emp=document.getElementById('emp');if(!emp.dataset.touched)emp.value=refill;
    document.getElementById('save').disabled=!Object.keys(cart).length;
  }
  document.getElementById('emp').addEventListener('input',function(){this.dataset.touched=1;});
  ['dc','disc'].forEach(function(id){var e=document.getElementById(id);if(e)e.addEventListener('input',draw);});
  document.querySelectorAll('input[name=method]').forEach(function(r){r.addEventListener('change',function(){document.getElementById('paidBox').style.display=r.value==='due'&&r.checked?'':'none';});});
  var t=null;document.getElementById('phone').addEventListener('input',function(){var v=this.value;clearTimeout(t);t=setTimeout(function(){
    fetch('{{ route('pos.lookup') }}?phone='+encodeURIComponent(v),{headers:{Accept:'application/json'}}).then(function(r){return r.json();}).then(function(j){
      var info=document.getElementById('cinfo');
      if(j.found){document.getElementById('cid').value=j.id;document.getElementById('cname').value=j.name;info.textContent=j.name+' · '+j.type+' · due '+tk(j.due)+(j.credit>0?' · credit limit '+tk(j.credit):' · credit off');}
      else{document.getElementById('cid').value='';info.textContent='Walk-in customer. Type a saved phone to use a customer account.';}
    });},350);});
  document.getElementById('pos').addEventListener('submit',function(){document.getElementById('save').disabled=true;document.getElementById('save').textContent='Saving…';});
  draw();
})();
</script>
@endsection
