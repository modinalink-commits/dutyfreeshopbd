@extends('layout')@section('title','Create product from image')
@section('body')
<style>
.aif{max-width:980px}.aif .box{background:#fff;border:1px solid var(--bd);border-radius:16px;padding:20px 22px;margin-bottom:16px}
.aif .drop{border:2px dashed #CBD5E1;border-radius:14px;padding:34px 16px;text-align:center;cursor:pointer;background:#F8FAFC;transition:.15s}.aif .drop.on{border-color:#FF5A00;background:#FFF7F0}
.aif .drop b{display:block;font-size:16px;margin-bottom:4px}.aif .drop small{color:#6B7280}
.aif .prev{display:flex;gap:18px;flex-wrap:wrap;align-items:flex-start}.aif .prev img{width:200px;height:200px;object-fit:contain;border:1px solid var(--bd);border-radius:12px;background:#fff}
.aif table{width:100%;border-collapse:collapse;font-size:14px}.aif td{padding:7px 8px;border-bottom:1px solid #EEF0F3;vertical-align:top}.aif td.l{width:170px;color:#6B7280}
.aif .cf{font-size:11px;font-weight:700;border-radius:8px;padding:2px 7px;white-space:nowrap}.aif .hi{background:#DCFCE7;color:#166534}.aif .lo{background:#FEF3C7;color:#92400E}
.aif input.e,.aif textarea.e{width:100%;margin:0}.aif .steps{font-size:14px;color:#475569}.aif .steps div{padding:3px 0}.aif .steps .d{color:#16A34A}
.aif .mt{display:flex;gap:12px;align-items:center;border:1px solid #FCD34D;background:#FFFBEB;border-radius:12px;padding:10px 12px;margin:8px 0}.aif .mt img{width:56px;height:56px;object-fit:contain;background:#fff;border-radius:8px;border:1px solid var(--bd)}
.aif .err{background:#FEF2F2;border:1px solid #FCA5A5;color:#991B1B;border-radius:12px;padding:12px 14px}.aif .wn{background:#FFFBEB;border:1px solid #FCD34D;color:#92400E;border-radius:12px;padding:10px 14px;margin:8px 0}
.aif .acts{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}
</style>
<div class="aif">
<h1 style="margin:0 0 4px">Create product from image</h1>
<p style="margin:0 0 14px;color:#6B7280;font-size:13px">Upload one product photo. The AI reads the pack and fills the name, brand, category, description and SEO. It never fills a price – you add the price, then save.</p>
@if(session('ok'))<div class="card" style="color:#166534">{{ session('ok') }}</div>@endif
@if(!$ready)<div class="err">The AI key is not set. Add the Claude key in <a href="/admin/ai-chat">AI Chat settings</a> first.</div>@endif

<div class="box" id="up">
  <div class="drop" id="drop"><b>Drag &amp; drop a product image here</b><small>or click to choose · JPG, PNG, WebP · up to 8 MB</small></div>
  <input type="file" id="file" accept="image/jpeg,image/png,image/webp" style="display:none">
  <p style="margin:10px 0 0;font-size:12px;color:#6B7280">Best result: clear front-facing photo, good light, pack text visible, no heavy blur, one product, clean background.</p>
  <div class="acts"><button type="button" id="scanBtn" disabled>Scan Product</button></div>
</div>

<div class="box" id="busy" style="display:none"><b>Analyzing Product...</b><div class="steps" id="steps"></div></div>
<div id="errBox" style="display:none" class="err"></div>

<div class="box" id="res" style="display:none">
  <div class="prev"><img id="pimg" alt=""><div style="flex:1;min-width:260px"><h2 style="margin:0 0 6px;font-size:17px">AI Product Detection</h2><div id="mode" style="font-size:13px;margin-bottom:6px"></div><div id="notes"></div></div></div>
  <div id="multi" style="display:none" class="wn"><b>Multiple products detected.</b> Choose the one to create:<div id="cands" class="acts"></div></div>
  <div id="dups" style="display:none"></div>
  <div id="tblWrap"><table id="tbl"></table></div>
  <div class="wn" id="priceNote">Price is not filled by the AI. On the next screen enter the <b>Regular / Sale price</b>. Purchase price comes from Purchase (average-price and 15% margin rules stay unchanged).</div>
  <div class="acts" id="btns"><button type="button" id="accept">Accept &amp; Continue</button><button type="button" class="g" id="editBtn">Edit Details</button><button type="button" class="g" id="again">Scan Again</button><button type="button" class="g" id="cancel">Cancel</button></div>
</div>

@if($recent->count())<div class="box"><b>Recent scans</b><table style="margin-top:6px">@foreach($recent as $s)@php($d=json_decode($s->detected ?? '',true) ?: [])<tr><td style="width:48px"><img src="{{ $s->image }}" style="width:40px;height:40px;object-fit:contain"></td><td>{{ $d['fields']['name']['v'] ?? '—' }}</td><td>{{ $s->user_name }}</td><td>{{ ucfirst($s->status) }}</td><td style="white-space:nowrap">{{ \Illuminate\Support\Carbon::parse($s->created_at)->format('d M, h:i A') }}</td><td>@if($s->product_id)<a href="/admin/products/{{ $s->product_id }}/edit">Open product</a>@endif</td></tr>@endforeach</table></div>@endif

@if($admin)
<form method="post" action="/admin/products/from-image/settings" class="box">@csrf<b>Settings</b> <span style="font-size:12px;color:#6B7280">(Admin only) · model {{ $model }}</span>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:8px;margin:10px 0">@foreach($set as $k=>$s)<label style="display:flex;gap:8px;align-items:center;margin:0"><input type="checkbox" name="s_{{ $k }}" value="1" style="width:auto;margin:0" @checked($s['on'])> {{ $s['label'] }}</label>@endforeach</div><button>Save settings</button></form>
@endif
</div>
<script>
(function(){
var $=function(i){return document.getElementById(i)},tok=document.querySelector('meta[name="csrf-token"]').content,cur=null,file=null,timer=null;
var STEPS=['Reading package','Detecting brand','Matching catalog','Generating product information','Preparing preview'];
function esc(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
function pick(f){if(!f)return;if(!/^image\/(jpeg|png|webp)$/.test(f.type)){showErr('Use a JPG, PNG or WebP image.');return}if(f.size>8*1048576){showErr('The image is larger than 8 MB.');return}file=f;$('drop').innerHTML='<b>'+esc(f.name)+'</b><small>Scanning...</small>';$('scanBtn').disabled=false;run({file:f})}
function showErr(m){$('errBox').style.display='block';$('errBox').textContent=m;$('busy').style.display='none'}
function busy(on){$('busy').style.display=on?'block':'none';clearInterval(timer);if(on){$('errBox').style.display='none';$('res').style.display='none';var i=0;$('steps').innerHTML='';function t(){if(i<STEPS.length){var p=$('steps').lastChild;if(p)p.className='d';$('steps').insertAdjacentHTML('beforeend','<div>'+(i?'✔ ':'• ')+STEPS[i]+'</div>');i++}}t();timer=setInterval(t,2600)}}
function run(o){
  var fd=new FormData();if(o.file)fd.append('file',o.file);if(o.scan_id)fd.append('scan_id',o.scan_id);if(o.focus)fd.append('focus',o.focus);
  busy(true);
  fetch('/admin/products/from-image/scan',{method:'POST',headers:{'X-CSRF-TOKEN':tok,'Accept':'application/json'},body:fd}).then(function(r){return r.json().catch(function(){return{ok:false,error:'Unexpected server reply ('+r.status+')'}})}).then(function(j){busy(false);if(!j.ok){if(j.scan_id)cur={scan_id:j.scan_id};showErr(j.error||j.message||'Scan failed');return}show(j)}).catch(function(){busy(false);showErr('Network problem. Check your connection and try again.')});
}
function show(j){
  cur=j;$('res').style.display='block';$('pimg').src=j.image;$('errBox').style.display='none';
  var ok=j.identified,m=j.mode;
  $('mode').innerHTML=m?('Inventory rule: <b>'+(m==='STOCK_CONTROLLED'?'STOCK_CONTROLLED</b> – stock 0 = unpublished, stock-in publishes it':'SOURCING</b> – can be sold at zero stock, stock must be added before shipment')):'';
  var n=(j.quality==='poor'?'Image quality is poor – a clearer photo will give better results. ':'')+(j.notes||'');$('notes').innerHTML=n?'<div class="wn">'+esc(n)+'</div>':'';
  $('multi').style.display=j.multiple&&j.candidates.length?'block':'none';
  if(j.multiple){$('cands').innerHTML=j.candidates.map(function(c,i){return'<button type="button" class="g" data-c="'+i+'">'+esc(c)+'</button>'}).join('');[].forEach.call($('cands').children,function(b){b.onclick=function(){run({scan_id:cur.scan_id,focus:j.candidates[+b.dataset.c]})}})}
  var d='';if(j.matches&&j.matches.length){d='<div class="wn"><b>Possible Existing Product Found</b></div>'+j.matches.map(function(x){return'<div class="mt">'+(x.image?'<img src="'+esc(x.image)+'">':'')+'<div style="flex:1"><b>'+esc(x.name)+'</b><br><span style="font-size:12px;color:#6B7280">'+esc(x.sku||'no SKU')+' · '+esc(x.category)+' · '+esc(x.status)+' · '+esc(x.why)+' ('+x.score+'%)</span></div><a class="btn g" href="/admin/products/'+x.id+'/edit">Use Existing Product</a></div>'}).join('')+'<div style="font-size:12px;color:#6B7280">If none is the same product, press Accept &amp; Continue to create a new one.</div>'}
  $('dups').style.display=d?'block':'none';$('dups').innerHTML=d;
  if(!ok){$('tblWrap').style.display='none';$('btns').style.display='flex';$('accept').style.display='none';$('editBtn').style.display='none';$('priceNote').style.display='none';
    if(!j.multiple){$('notes').innerHTML='<div class="err"><b>Product could not be identified confidently.</b><br>Please upload a clearer image, or <a href="/admin/products/new">enter product details manually</a>.</div>'}return}
  $('tblWrap').style.display='block';$('accept').style.display='';$('editBtn').style.display='';$('priceNote').style.display='';
  $('tbl').innerHTML=j.rows.map(function(r){var lo=r.conf<j.low;var v=r.value==null?'<i style="color:#9CA3AF">not found</i>':esc(r.value);
    return'<tr data-k="'+r.key+'" data-e="'+(r.edit?1:0)+'"><td class="l">'+esc(r.label)+'</td><td class="v" data-raw="'+esc(r.value==null?'':r.value)+'">'+v+'</td><td style="width:110px;text-align:right"><span class="cf '+(lo?'lo':'hi')+'">'+(r.key==='sku'?'Suggestion':(lo?'⚠ Please Review · ':'')+r.conf+'%')+'</span></td></tr>'}).join('');
  editing=false;$('editBtn').textContent='Edit Details';
}
var editing=false;
$('editBtn').onclick=function(){editing=!editing;$('editBtn').textContent=editing?'Done editing':'Edit Details';[].forEach.call($('tbl').rows,function(tr){if(tr.dataset.e!=='1')return;var td=tr.querySelector('.v');if(editing){var long=/description/.test(tr.dataset.k);td.innerHTML=long?'<textarea class="e" rows="3">'+esc(td.dataset.raw)+'</textarea>':'<input class="e" value="'+esc(td.dataset.raw)+'">'}else{var el=td.firstElementChild;if(el&&el.classList.contains('e')){td.dataset.raw=el.value;td.innerHTML=el.value?esc(el.value):'<i style="color:#9CA3AF">not found</i>'}}})};
$('accept').onclick=function(){
  if(editing)$('editBtn').click();var edit={};[].forEach.call($('tbl').rows,function(tr){if(tr.dataset.e==='1')edit[tr.dataset.k]=tr.querySelector('.v').dataset.raw});
  if(!edit.name){alert('Product name is empty – use Edit Details.');return}
  $('accept').disabled=true;fetch('/admin/products/from-image/accept',{method:'POST',headers:{'X-CSRF-TOKEN':tok,'Accept':'application/json','Content-Type':'application/json'},body:JSON.stringify({scan_id:cur.scan_id,edit:edit})}).then(function(r){return r.json()}).then(function(j){if(j.ok)location.href=j.url;else{$('accept').disabled=false;showErr(j.message||'Could not continue')}}).catch(function(){$('accept').disabled=false;showErr('Network problem')});
};
function reset(){$('res').style.display='none';$('errBox').style.display='none';file=null;$('file').value='';$('scanBtn').disabled=true;$('drop').innerHTML='<b>Drag &amp; drop a product image here</b><small>or click to choose · JPG, PNG, WebP · up to 8 MB</small>'}
$('again').onclick=function(){reset();$('file').click()};
$('cancel').onclick=function(){if(cur&&cur.scan_id)fetch('/admin/products/from-image/cancel',{method:'POST',headers:{'X-CSRF-TOKEN':tok,'Accept':'application/json','Content-Type':'application/json'},body:JSON.stringify({scan_id:cur.scan_id})});cur=null;reset()};
$('drop').onclick=function(){$('file').click()};$('file').onchange=function(){pick(this.files[0])};
['dragover','dragenter'].forEach(function(e){$('drop').addEventListener(e,function(ev){ev.preventDefault();$('drop').classList.add('on')})});
['dragleave','drop'].forEach(function(e){$('drop').addEventListener(e,function(ev){ev.preventDefault();$('drop').classList.remove('on')})});
$('drop').addEventListener('drop',function(ev){pick(ev.dataTransfer.files[0])});
$('scanBtn').onclick=function(){if(file)run({file:file})};
})();
</script>
@endsection
