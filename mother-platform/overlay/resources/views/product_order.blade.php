@extends('layout')@section('title','Product Order')
@section('body')
<div style="margin-bottom:12px"><h1 style="margin:0">Product Order</h1><span style="font-size:13px;color:#6B7280">Set the position of products inside a Main, Sub or Child category. The store category page follows this order (position 1 comes first).</span></div>
<div class="card" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end">
<div><label>Main category</label><select id="m"><option value="">— choose —</option>@foreach($mains as $x)<option value="{{ $x->id }}">{{ $x->name }}</option>@endforeach</select></div>
<div><label>Sub category</label><select id="s"><option value="">All (whole main category)</option></select></div>
<div><label>Child category</label><select id="c"><option value="">All (whole sub category)</option></select></div>
<div><label>Search</label><input id="q" placeholder="product name"></div>
<button type="button" class="g" id="rs">Reset order</button><span id="st" style="font-size:13px;font-weight:700"></span></div>
<div class="card" style="overflow-x:auto"><div id="ls" style="color:#6B7280">Choose a category to see its products.</div></div>
<style>#ls table{width:100%;border-collapse:collapse;font-size:13px}#ls td,#ls th{padding:6px;border-top:1px solid #E5E7EB;text-align:left}#ls img{width:38px;height:38px;object-fit:cover;border-radius:8px;background:#F3F4F6}#ls .hd{cursor:grab;color:#9CA3AF}#ls tr.dg{opacity:.4}#ls .ib2{border:1px solid var(--bd);background:#fff;border-radius:8px;width:28px;height:28px;padding:0;cursor:pointer;color:#0B1220}#ls input.pn{width:64px;padding:5px 6px;font-weight:800;color:#FF5A00}</style>
<script type="application/json" id="nd">@json($nodes)</script>
<script>(function(){var N=JSON.parse(document.getElementById('nd').textContent),$=function(i){return document.getElementById(i)},M=$('m'),S=$('s'),C=$('c'),L=$('ls'),st=$('st'),tok=document.querySelector('meta[name=csrf-token]').content,items=[],scope='',dragI=null;
function opts(sel,rows,first){sel.innerHTML='<option value="">'+first+'</option>'+rows.map(function(n){return '<option value="'+n.id+'">'+n.name.replace(/</g,'&lt;')+'</option>'}).join('')}
function cur(){return C.value?'n:'+C.value:S.value?'n:'+S.value:M.value?'m:'+M.value:''}
function say(t,ok){st.style.color=ok?'#166534':'#B91C1C';st.textContent=t;clearTimeout(say.t);say.t=setTimeout(function(){st.textContent=''},2500)}
function load(){scope=cur();if(!scope){L.textContent='Choose a category to see its products.';return}L.textContent='Loading…';
fetch('/admin/product-order/data?scope='+scope,{headers:{Accept:'application/json'}}).then(function(r){return r.json()}).then(function(j){items=j.items||[];draw()})}
M.onchange=function(){opts(S,N.filter(function(n){return n.level==2&&n.category_id==M.value}),'All (whole main category)');opts(C,[],'All (whole sub category)');load()};
S.onchange=function(){opts(C,N.filter(function(n){return n.level==3&&n.parent_id==S.value}),'All (whole sub category)');load()};C.onchange=load;$('q').oninput=draw;
function draw(){var q=$('q').value.trim().toLowerCase();if(!items.length){L.textContent='0 products in this category.';return}
var h='<table><thead><tr><th></th><th>Position</th><th></th><th>Product</th><th>Price</th><th></th></tr></thead><tbody>';
items.forEach(function(p,i){if(q&&p.name.toLowerCase().indexOf(q)<0)return;h+='<tr draggable="true" data-i="'+i+'"><td class="hd">⠿</td><td><input class="pn" type="number" min="1" value="'+(i+1)+'" data-i="'+i+'"></td><td>'+(p.image?'<img loading="lazy" src="'+p.image+'">':'')+'</td><td>'+p.name.replace(/</g,'&lt;')+'</td><td>৳'+Number(p.price).toLocaleString('en-US')+'</td><td style="white-space:nowrap"><button type="button" class="ib2" data-u="'+i+'">▲</button> <button type="button" class="ib2" data-d="'+i+'">▼</button> <button type="button" class="ib2" data-t="'+i+'" title="Move to top">⤒</button></td></tr>'});
L.innerHTML=h+'</tbody></table>'}
function move(a,b){b=Math.max(0,Math.min(items.length-1,b));if(a===b)return;var x=items.splice(a,1)[0];items.splice(b,0,x);draw();save()}
function save(){fetch('/admin/product-order/save',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':tok,Accept:'application/json'},body:JSON.stringify({scope:scope,ids:items.map(function(p){return p.id})})}).then(function(r){return r.json()}).then(function(j){say(j.ok?'Saved ✓':(j.message||'Could not save'),j.ok)}).catch(function(){say('Could not save',false)})}
L.addEventListener('click',function(e){var b=e.target.closest('button');if(!b)return;if(b.dataset.u!==undefined)move(+b.dataset.u,+b.dataset.u-1);else if(b.dataset.d!==undefined)move(+b.dataset.d,+b.dataset.d+1);else if(b.dataset.t!==undefined)move(+b.dataset.t,0)});
L.addEventListener('change',function(e){var t=e.target;if(t.classList.contains('pn'))move(+t.dataset.i,(parseInt(t.value,10)||1)-1)});
L.addEventListener('dragstart',function(e){var tr=e.target.closest('tr[data-i]');if(!tr)return;dragI=+tr.dataset.i;tr.classList.add('dg');e.dataTransfer.effectAllowed='move';try{e.dataTransfer.setData('text/plain','x')}catch(z){}});
L.addEventListener('dragover',function(e){if(dragI!==null)e.preventDefault()});
L.addEventListener('drop',function(e){var tr=e.target.closest('tr[data-i]');if(dragI===null||!tr)return;e.preventDefault();var to=+tr.dataset.i,f=dragI;dragI=null;move(f,to)});
L.addEventListener('dragend',function(){dragI=null;draw()});
$('rs').onclick=function(){if(!scope||!confirm('Remove custom order for this category? Products go back to newest-first.'))return;fetch('/admin/product-order/reset',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':tok,Accept:'application/json'},body:JSON.stringify({scope:scope})}).then(function(){say('Order reset',true);load()})};})();</script>
@endsection
