@extends('layout')@section('title','Product')
@section('body')
@php
  $v = fn($k, $d = '') => old($k, $p->{$k} ?? $d);
  $regular = old('regular_price', $p ? ($p->compare_price && $p->compare_price > $p->price ? $p->compare_price : $p->price) : '');
  $sale = old('sale_price', $p && $p->compare_price && $p->compare_price > $p->price ? $p->price : '');
  $isPub = old('status', $p ? ($p->published ? 'published' : 'draft') : 'draft') === 'published';
  $imgList = old('images', $imgs);
  $varList = old('v', $vars);
  $hasVar = old('has_variants', count($varList) ? 1 : 0);
  $descRaw = (string)old('description', $p->description ?? '');
  $descHtml = \App\Support\Html::isHtml($descRaw) ? \App\Support\Html::clean($descRaw) : nl2br(e($descRaw));
@endphp
<style>
.pf{display:grid;grid-template-columns:minmax(0,1fr) 300px;gap:18px;align-items:start;max-width:1180px}
.pf .sec{background:#fff;border:1px solid var(--bd);border-radius:16px;padding:20px 22px;margin-bottom:16px}
.pf .sec>h2{font-size:15px;margin:0 0 4px;display:flex;align-items:center;gap:10px}.pf .sec>h2 i{font-style:normal;width:24px;height:24px;border-radius:50%;background:var(--n);color:#fff;font-size:12px;display:inline-flex;align-items:center;justify-content:center}
.pf .sec>p.sub{margin:0 0 6px;font-size:12px;color:#6B7280}
.pf .g2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 16px}.pf .g3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:0 16px}
.pf label small{color:#9CA3AF;font-weight:400}
.pf input:focus,.pf select:focus,.pf textarea:focus{outline:2px solid #FFD2B8;border-color:var(--o)}
.pf .side{position:sticky;top:16px}.pf .side .sec{padding:18px}
.pf .side .btn,.pf .side button{width:100%;text-align:center;margin-top:8px;padding:11px 14px}
.pf .side .dk{background:var(--n)}
.pf .disc{display:inline-block;margin-top:8px;font-size:13px;font-weight:700;color:#15803D;background:#DCFCE7;border-radius:8px;padding:5px 10px}
.pf .imgs{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:10px}
.pf .im{position:relative;aspect-ratio:1;border:1.25px solid var(--bd);border-radius:12px;overflow:hidden;background:#FFF7F1;cursor:grab}
.pf .im img{width:100%;height:100%;object-fit:cover;display:block}
.pf .im.drag{opacity:.4}.pf .im .cv{position:absolute;left:6px;top:6px;background:var(--o);color:#fff;font-size:10px;font-weight:700;border-radius:6px;padding:2px 7px}
.pf .im .ac{position:absolute;right:6px;bottom:6px;display:flex;gap:4px}.pf .im .ac button{width:auto;margin:0;padding:3px 7px;font-size:11px;background:rgba(11,18,32,.82)}
.pf .add{aspect-ratio:1;border:1.5px dashed var(--bd);border-radius:12px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;color:#6B7280;font-size:12px;cursor:pointer;background:#fff}
.pf .add b{font-size:22px;color:var(--o);line-height:1}
.pf .hint{font-size:12px;color:#6B7280;margin-top:8px}.pf .hint.bad{color:#B91C1C}
.pf .tb{display:flex;flex-wrap:wrap;gap:4px;border:1px solid var(--bd);border-bottom:0;border-radius:10px 10px 0 0;padding:6px;background:#FFF7F1}
.pf .tb button{width:auto;margin:0;background:#fff;color:var(--n);border:1px solid var(--bd);padding:5px 9px;font-size:12px;min-width:32px}
.pf .tb button:hover{background:var(--n);color:#fff}
.pf .ed{border:1px solid var(--bd);border-radius:0 0 10px 10px;min-height:240px;padding:12px 14px;background:#fff;font-size:14px;line-height:1.6;overflow:auto}
.pf .ed:focus{outline:2px solid #FFD2B8}
.pf .ed table{border-collapse:collapse;width:100%}.pf .ed td,.pf .ed th{border:1px solid #E5E7EB;padding:6px 8px}.pf .ed img{max-width:100%;height:auto}
.pf .vt{width:100%}.pf .vt td{padding:6px 6px 6px 0;border:0}.pf .vt th{font-size:11px}.pf .vt input,.pf .vt select{padding:7px 8px}
.pf .url{font-size:12px;color:#166534;background:#F0FDF4;border:1px solid #BBF7D0;border-radius:8px;padding:8px 10px;margin-top:8px;word-break:break-all}
.pf .shr{display:flex;gap:8px;flex-wrap:wrap;margin-top:6px}.pf .shr a,.pf .shr button{width:auto;margin:0;padding:8px 12px;font-size:12px}
.pf .okb{background:#DCFCE7;color:#166534;border-radius:10px;padding:9px 14px;margin-bottom:12px;font-size:13px;font-weight:600;max-width:1180px}
.pf .tg{display:flex;align-items:center;gap:8px;margin-top:10px;font-size:13px;color:var(--n)}.pf .tg input{width:auto}
@media(max-width:980px){.pf{grid-template-columns:1fr}.pf .side{position:static}.pf .g3{grid-template-columns:1fr 1fr}}
@media(max-width:600px){.pf .g2,.pf .g3{grid-template-columns:1fr}.pf .imgs{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style>
<h1>{{ $p ? 'Edit product' : 'Add product' }}</h1>
@if(request('saved'))<div class="okb">Saved. {{ $p && $p->published ? 'This product is live on the store.' : 'It is a draft, not visible to customers.' }}</div>@endif
@if($errors->any())<div class="card err" style="max-width:1180px">{{ $errors->first() }}</div>@endif
<form id="pf" class="pf" method="post" action="{{ $p ? '/admin/products/'.$p->id : '/admin/products' }}">@csrf
<input type="hidden" name="act" id="act" value="publish">
<div>

<div class="sec"><h2><i>1</i>Basic information</h2><p class="sub">Name, brand and where the product lives on the store.</p>
<label>Product name *</label><input name="name" id="pname" value="{{ $v('name') }}" required maxlength="200">
<div class="g2"><div><label>Brand</label><select name="brand_id"><option value="">No brand</option>@foreach($brands as $b)<option value="{{ $b->id }}" @selected($v('brand_id')==$b->id)>{{ $b->name }}</option>@endforeach</select></div>
<div><label>SKU</label><input name="sku" value="{{ $v('sku') }}" maxlength="60"></div></div>
<div class="g3"><div><label>Main category *</label><select name="category_id" id="cMain" required><option value="">Select</option>@foreach($mains as $m)<option value="{{ $m->id }}" @selected($v('category_id')==$m->id)>{{ $m->name }}</option>@endforeach</select></div>
<div><label>Sub category</label><input name="sub" id="cSub" list="dlSub" value="{{ $v('sub') }}" autocomplete="off" disabled placeholder="Select main first"><datalist id="dlSub"></datalist></div>
<div><label>Sub-sub category</label><input name="sub_sub" id="cSs" list="dlSs" value="{{ $v('sub_sub') }}" autocomplete="off" disabled placeholder="Select sub first"><datalist id="dlSs"></datalist></div></div>
<p class="hint">Pick from the list or type a new sub category. Manage main categories and brands in <a href="/admin/m/categories">Categories</a> and <a href="/admin/m/brands">Brands</a>.</p>
<div class="g2"><div><label>Video URL <small>(optional)</small></label><input name="video_url" type="url" value="{{ $v('video_url') }}" placeholder="https://youtube.com/..."></div>
<div><label>Affiliate commission % <small>(optional)</small></label><input name="affiliate_commission" type="number" step="0.01" min="0" max="100" value="{{ $v('affiliate_commission') }}"></div></div>
</div>

<div class="sec"><h2><i>2</i>Pricing</h2><p class="sub">Regular price is the full price. Add a sale price and the discount is calculated for you.</p>
<div class="g2"><div><label>Regular price (TK) *</label><input name="regular_price" id="pReg" type="number" step="0.01" min="0" value="{{ $regular }}" required></div>
<div><label>Sale price (TK)</label><input name="sale_price" id="pSale" type="number" step="0.01" min="0" value="{{ $sale }}"></div></div>
<span class="disc" id="pDisc" style="display:none"></span>
<label style="margin-top:14px">Quantity prices <small>(total price for the pack, optional)</small></label>
<div class="g3" style="grid-template-columns:repeat(5,minmax(0,1fr))">@foreach([1=>'Single',2=>'Buy 2',3=>'Buy 3',4=>'Buy 4',5=>'Buy 5'] as $n=>$lab)<div><label style="margin-top:2px">{{ $lab }}</label><input name="buy{{ $n }}_price" type="number" step="0.01" min="0" value="{{ $v('buy'.$n.'_price') }}"></div>@endforeach</div>
</div>

<div class="sec"><h2><i>3</i>Inventory</h2>
<div class="g3"><div><label>Stock quantity *</label><input name="stock" type="number" min="0" value="{{ $v('stock', 0) }}" required></div>
<div><label>Stock status</label><select name="stock_status">@foreach(['in_stock'=>'In stock','out_of_stock'=>'Out of stock','pre_order'=>'Pre order'] as $k=>$l)<option value="{{ $k }}" @selected($v('stock_status','in_stock')===$k)>{{ $l }}</option>@endforeach</select></div>
<div><label>Low stock alert at</label><input name="low_stock_alert" type="number" min="0" value="{{ $v('low_stock_alert') }}"></div></div>
</div>

<div class="sec"><h2><i>4</i>Variants</h2><p class="sub">Only for products that come in sizes, colours or weights.</p>
<label class="tg"><input type="checkbox" name="has_variants" value="1" id="hasVar" @checked($hasVar)> This product has variants</label>
<div id="varBox" style="display:none;margin-top:10px">
<table class="vt"><thead><tr><th>Attribute</th><th>Value</th><th>SKU</th><th>Price</th><th>Stock</th><th></th></tr></thead><tbody id="varRows"></tbody></table>
<button type="button" class="g" id="addVar" style="margin-top:8px">+ Add variant</button></div>
</div>

<div class="sec"><h2><i>5</i>Images</h2><p class="sub">Up to 6 images. The first one is the cover. Drag to reorder.</p>
<div class="imgs" id="imgs"></div>
<input type="file" id="imgFile" accept="image/jpeg,image/png,image/webp" multiple style="display:none">
<p class="hint" id="imgHint">Recommended 600 × 600 px, square (1:1). JPG, PNG or WebP. Images are resized and converted to WebP automatically.</p>
<div id="imgVals"></div>
</div>

<div class="sec"><h2><i>6</i>Product details</h2>
<div class="tb" id="tb">
<button type="button" data-c="formatBlock:h2" title="Heading">H2</button><button type="button" data-c="formatBlock:h3" title="Subheading">H3</button><button type="button" data-c="formatBlock:p" title="Paragraph">¶</button>
<button type="button" data-c="bold" title="Bold"><b>B</b></button><button type="button" data-c="italic" title="Italic"><i>I</i></button>
<button type="button" data-c="insertUnorderedList" title="Bullet list">• List</button><button type="button" data-c="insertOrderedList" title="Numbered list">1. List</button>
<button type="button" data-c="justifyLeft" title="Align left">⇤</button><button type="button" data-c="justifyCenter" title="Center">↔</button><button type="button" data-c="justifyRight" title="Align right">⇥</button>
<button type="button" id="edTable">Table</button><button type="button" id="edLink">Link</button><button type="button" id="edImg">Image</button><button type="button" data-c="removeFormat" title="Clear formatting">Clear</button>
</div>
<div class="ed" id="ed" contenteditable="true">{!! $descHtml !!}</div>
<textarea name="description" id="descTa" style="display:none"></textarea>
<input type="file" id="edImgFile" accept="image/*" style="display:none">
</div>

<div class="sec"><h2><i>7</i>SEO</h2>
<label>Meta title</label><input name="meta_title" value="{{ $v('meta_title') }}" maxlength="200">
<label>Meta description</label><textarea name="meta_description" rows="2" maxlength="500">{{ $v('meta_description') }}</textarea>
<label>Meta keywords <small>(comma separated)</small></label><input name="meta_keywords" value="{{ $v('meta_keywords') }}" maxlength="500">
<label>URL slug</label><input name="slug" id="pSlug" value="{{ old('slug', $p->path_slug ?? ($p->slug ?? '')) }}" placeholder="auto from product name">
<div class="url" id="urlPrev"></div>
</div>

<div class="sec"><h2><i>8</i>Share</h2>
@if($canon)
<p class="sub">Customers see this canonical link everywhere you share it.</p>
<div class="shr"><a class="btn dk" target="_blank" rel="noopener" href="https://www.facebook.com/sharer/sharer.php?u={{ rawurlencode($canon) }}">Facebook</a><a class="btn dk" target="_blank" rel="noopener" href="https://wa.me/?text={{ rawurlencode($p->name."\n".$canon) }}">WhatsApp</a><button type="button" class="g" id="cpy" data-u="{{ $canon }}">Copy link</button></div>
@else
<p class="sub">Share buttons appear here once the product is published.</p>
@endif
</div>

</div>
<aside class="side"><div class="sec">
<label style="margin-top:0">Product status</label>
<select name="status" id="pStatus"><option value="draft" @selected(!$isPub)>Draft</option><option value="published" @selected($isPub)>Published</option></select>
<button type="submit" data-a="preview" class="g">Preview</button>
<button type="submit" data-a="draft" class="g">Save draft</button>
<button type="submit" data-a="publish">{{ $p ? 'Update product' : 'Publish product' }}</button>
<a class="btn g" style="width:100%;text-align:center;margin-top:8px;padding:11px 14px" href="/admin/products">Back to products</a>
</div></aside>
</form>
<script>
(function(){
var MAP=@json($map), IMGS=@json(array_values($imgList)), VARS=@json(array_values($varList)), HOST=@json($host), MAXI=6;
var $=function(i){return document.getElementById(i)};
function slugify(s){return (s||'').toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'')}
/* categories */
var cm=$('cMain'),cs=$('cSub'),ss=$('cSs');
function fill(dl,arr){dl.innerHTML='';arr.forEach(function(x){var o=document.createElement('option');o.value=x;dl.appendChild(o)})}
function onMain(keep){var m=MAP[cm.value];cs.disabled=!m;if(!keep){cs.value='';ss.value=''}cs.placeholder=m?'Choose or type':'Select main first';fill($('dlSub'),m?Object.keys(m.subs):[]);onSub(keep)}
function onSub(keep){var m=MAP[cm.value];var ok=!!m&&cs.value.trim()!=='';ss.disabled=!ok;if(!ok&&!keep)ss.value='';ss.placeholder=ok?'Choose or type':'Select sub first';fill($('dlSs'),ok&&m.subs[cs.value]?m.subs[cs.value]:[]);urlPrev()}
cm.addEventListener('change',function(){onMain(false)});cs.addEventListener('input',function(){onSub(false)});ss.addEventListener('input',urlPrev);
/* price */
function disc(){var r=parseFloat($('pReg').value),s=parseFloat($('pSale').value),b=$('pDisc');if(r>0&&s>0&&s<r){var p=Math.round((r-s)/r*100);b.textContent='Discount: '+p+'% (save TK '+Math.round(r-s).toLocaleString()+')';b.style.display='inline-block'}else b.style.display='none'}
$('pReg').addEventListener('input',disc);$('pSale').addEventListener('input',disc);
/* url preview */
var slugTouched={{ $p ? 'true' : 'false' }};
$('pSlug').addEventListener('input',function(){slugTouched=true;urlPrev()});
$('pname').addEventListener('input',function(){if(!slugTouched){$('pSlug').value=slugify($('pname').value)}urlPrev()});
function urlPrev(){var m=MAP[cm.value];var sl=slugify($('pSlug').value)||slugify($('pname').value)||'product-name';var parts=[(m&&m.slug)||'main-category',slugify(cs.value)||'other',sl];$('urlPrev').textContent='https://'+HOST+'/'+parts.join('/')}
/* variants */
function varRow(d){d=d||{};var tr=document.createElement('tr');var attrs=['Size','Color','Weight','Custom'];var known=attrs.indexOf(d.attr)>-1;
tr.innerHTML='<td><select data-k="attr">'+attrs.map(function(a){return '<option'+((known?d.attr:(d.attr?'Custom':'Size'))===a?' selected':'')+'>'+a+'</option>'}).join('')+'</select><input data-k="custom" placeholder="Attribute name" style="margin-top:4px;'+(known||!d.attr?'display:none':'')+'" value="'+(!known&&d.attr?String(d.attr).replace(/"/g,'&quot;'):'')+'"></td><td><input data-k="value" placeholder="e.g. M" value="'+(d.value||'').toString().replace(/"/g,'&quot;')+'"></td><td><input data-k="sku" value="'+(d.sku||'')+'"></td><td><input data-k="price" type="number" step="0.01" min="0" value="'+(d.price==null?'':d.price)+'"></td><td><input data-k="stock" type="number" min="0" value="'+(d.stock||0)+'"></td><td><button type="button" class="g" style="width:auto;margin:0;padding:6px 10px">✕</button></td>';
tr.querySelector('select').onchange=function(){tr.querySelector('[data-k=custom]').style.display=this.value==='Custom'?'':'none'};
tr.querySelector('button').onclick=function(){tr.remove()};$('varRows').appendChild(tr)}
VARS.forEach(varRow);if(!VARS.length)varRow();
$('addVar').onclick=function(){varRow()};
function togVar(){$('varBox').style.display=$('hasVar').checked?'block':'none'}$('hasVar').onchange=togVar;togVar();
/* images */
function imgHint(t,bad){var h=$('imgHint');h.textContent=t;h.className='hint'+(bad?' bad':'')}
function renderImgs(){var w=$('imgs');w.innerHTML='';IMGS.forEach(function(u,i){var d=document.createElement('div');d.className='im';d.draggable=true;d.dataset.i=i;
d.innerHTML='<img src="'+u+'" alt="">'+(i===0?'<span class="cv">Cover</span>':'')+'<span class="ac">'+(i>0?'<button type="button" data-m="cover">Make cover</button>':'')+'<button type="button" data-m="del">Remove</button></span>';
d.addEventListener('dragstart',function(e){d.classList.add('drag');e.dataTransfer.setData('text/plain',i)});d.addEventListener('dragend',function(){d.classList.remove('drag')});
d.addEventListener('dragover',function(e){e.preventDefault()});d.addEventListener('drop',function(e){e.preventDefault();var f=+e.dataTransfer.getData('text/plain');if(f===i||isNaN(f))return;var x=IMGS.splice(f,1)[0];IMGS.splice(i,0,x);renderImgs()});
d.querySelectorAll('button').forEach(function(b){b.onclick=function(){if(b.dataset.m==='del')IMGS.splice(i,1);else{var x=IMGS.splice(i,1)[0];IMGS.unshift(x)}renderImgs()}});w.appendChild(d)});
if(IMGS.length<MAXI){var a=document.createElement('label');a.className='add';a.style.margin=0;a.innerHTML='<b>+</b><span>Add images</span>';a.onclick=function(e){e.preventDefault();$('imgFile').click()};w.appendChild(a)}
var hv=$('imgVals');hv.innerHTML='';IMGS.forEach(function(u){var h=document.createElement('input');h.type='hidden';h.name='images[]';h.value=u;hv.appendChild(h)})}
function toWebp(file,max){return new Promise(function(res){if(!/^image\/(jpeg|png|webp)$/.test(file.type)||!window.createImageBitmap||!document.createElement('canvas').toBlob)return res(file);
createImageBitmap(file).then(function(b){var side=Math.max(b.width,b.height),sc=Math.min(1,max/side),W=Math.round(b.width*sc),H=Math.round(b.height*sc),S=Math.max(W,H);var c=document.createElement('canvas');c.width=S;c.height=S;var x=c.getContext('2d');x.fillStyle='#fff';x.fillRect(0,0,S,S);x.drawImage(b,(S-W)/2,(S-H)/2,W,H);
c.toBlob(function(bl){res(bl?new File([bl],(file.name.replace(/\.[^.]+$/,'')||'image')+'.webp',{type:'image/webp'}):file)},'image/webp',.86)}).catch(function(){res(file)})})}
function up(file,max){return toWebp(file,max).then(function(f){var d=new FormData();d.append('file',f);d.append('slug','products');d.append('field','image');d.append('_token',document.querySelector('input[name=_token]').value);return fetch('/admin/upload-image',{method:'POST',body:d,headers:{'Accept':'application/json'}}).then(function(r){return r.json()}).then(function(j){if(!j.url)throw 0;return j.url})})}
$('imgFile').onchange=function(){var fs=Array.prototype.slice.call(this.files).slice(0,MAXI-IMGS.length);this.value='';if(!fs.length)return;imgHint('Uploading '+fs.length+' image(s)...');
fs.reduce(function(p,f){return p.then(function(){return up(f,1000).then(function(u){IMGS.push(u);renderImgs()})})},Promise.resolve()).then(function(){imgHint('Recommended 600 × 600 px, square (1:1). JPG, PNG or WebP. Images are resized and converted to WebP automatically.')}).catch(function(){imgHint('Upload failed. Use JPG, PNG or WebP under 8 MB.',true)})};
renderImgs();
/* editor */
var ed=$('ed');
$('tb').addEventListener('mousedown',function(e){if(e.target.closest('button'))e.preventDefault()});
document.querySelectorAll('[data-c]').forEach(function(b){b.onclick=function(){var c=b.dataset.c.split(':');ed.focus();document.execCommand(c[0],false,c[1]?'<'+c[1]+'>':null)}});
$('edTable').onclick=function(){ed.focus();document.execCommand('insertHTML',false,'<table><tbody><tr><th>Feature</th><th>Detail</th></tr><tr><td>&nbsp;</td><td>&nbsp;</td></tr><tr><td>&nbsp;</td><td>&nbsp;</td></tr></tbody></table><p><br></p>')};
$('edLink').onclick=function(){var u=prompt('Link URL (https://...)');if(u&&/^(https?:\/\/|\/|mailto:|tel:)/i.test(u)){ed.focus();document.execCommand('createLink',false,u)}};
$('edImg').onclick=function(){$('edImgFile').click()};
$('edImgFile').onchange=function(){var f=this.files[0];this.value='';if(!f)return;up(f,1200).then(function(u){ed.focus();document.execCommand('insertImage',false,u)}).catch(function(){alert('Image upload failed')})};
ed.addEventListener('paste',function(e){e.preventDefault();var t=(e.clipboardData||window.clipboardData).getData('text/plain');document.execCommand('insertText',false,t)});
/* share + submit */
if($('cpy'))$('cpy').onclick=function(){var b=this;navigator.clipboard.writeText(b.dataset.u).then(function(){b.textContent='Copied'})};
var f=$('pf');
f.querySelectorAll('[data-a]').forEach(function(b){b.addEventListener('click',function(){$('act').value=b.dataset.a})});
f.addEventListener('submit',function(){var h=ed.innerHTML.trim();$('descTa').value=(ed.textContent.trim()===''&&!ed.querySelector('img,table'))?'':h;cs.disabled=false;ss.disabled=false});
onMain(true);disc();urlPrev();
})();
</script>
@endsection
