@extends('layout')@section('title','Categories & Brands')
@section('body')
<style>
.tx{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(320px,.85fr);gap:14px;align-items:start}@media(max-width:980px){.tx{grid-template-columns:1fr}}
.tx h1{margin:0 0 4px}.txt{display:flex;gap:6px;margin:10px 0 12px}.txt button{background:#fff;color:#0B1220;border:1px solid var(--bd)}.txt button.on{background:#0B1220;color:#fff;border-color:#0B1F3A}
.tb{display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin-bottom:10px}.tb input,.tb select{border:1px solid var(--bd);border-radius:9px;padding:7px 10px;font-size:13px;background:#fff}.tb input{flex:1;min-width:140px}
.tn ul{list-style:none;margin:0;padding:0}.tn ul ul{margin-left:22px;border-left:1.5px dashed #F1D9C8;padding-left:8px}
.tn li{margin:3px 0}.tn .rw{display:flex;align-items:center;gap:6px;padding:6px 8px;border:1px solid #E5E7EB;border-radius:10px;background:#fff}.tn .rw:hover{border-color:#FFC9A3;background:#FFFAF6}
.tn li.dg>.rw{opacity:.45}.tn li.ov>.rw{border-color:#FF5A00;box-shadow:0 0 0 2px #FFE0CC}
.tn .hd{cursor:grab;color:#9CA3AF;font-size:14px;user-select:none}.tn .cr{width:18px;text-align:center;cursor:pointer;color:#6B7280;user-select:none;font-size:11px}
.tn .nm{flex:1;min-width:0;font-weight:600;font-size:13.5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.tn .l1>.rw .nm{font-size:14.5px}
.tn .ps{font-size:11px;font-weight:700;color:#FF5A00;background:#FFF1E8;border-radius:6px;padding:1px 6px}.tn .mv{width:22px;height:22px;font-size:9px}.tn .ct{font-size:11.5px;color:#6B7280;white-space:nowrap}.tn .ib2{border:1px solid var(--bd);background:#fff;color:#0B1220;border-radius:8px;width:28px;height:28px;padding:0;font-size:14px;line-height:1;cursor:pointer}.tn .ib2:hover{border-color:#FF5A00;color:#FF5A00}.tn .ib2.d:hover{border-color:#DC2626;color:#B91C1C}
.tn .st{font-size:10.5px;font-weight:700;border-radius:999px;padding:2px 8px;cursor:pointer;border:0}.st.a{background:#DCFCE7;color:#166534}.st.i{background:#F3F4F6;color:#6B7280}
.tn .thm{width:26px;height:26px;border-radius:7px;object-fit:cover;background:#F3F4F6;flex:none}
.fm label{display:block;font-size:12px;font-weight:700;margin:10px 0 4px;color:#374151}.fm input[type=text],.fm input[type=number],.fm select,.fm textarea{width:100%;border:1px solid var(--bd);border-radius:9px;padding:8px 10px;font-size:13px;background:#fff;font-family:inherit}.fm textarea{min-height:64px}
.ftabs{display:flex;gap:6px;margin:10px 0 0}.ftabs button{flex:1;background:#fff;color:#0B1220;border:1px solid var(--bd);padding:8px 4px;font-size:12px}.ftabs button.on{background:#FF5A00;color:#fff;border-color:#FF5A00}.fm .row2{display:grid;grid-template-columns:1fr 1fr;gap:10px}.fm .ck{display:flex;gap:14px;flex-wrap:wrap;margin-top:6px}.fm .ck label{display:flex;gap:6px;align-items:center;font-weight:600;margin:0}
.fm .pv{display:flex;gap:10px;align-items:center;margin-top:4px}.fm .pv img{width:54px;height:54px;border-radius:10px;object-fit:cover;background:#F3F4F6;border:1px solid var(--bd)}
.fm .hint{font-size:11.5px;color:#6B7280;margin-top:3px}.fm .url{font-family:monospace;font-size:12px;background:#F9FAFB;border:1px dashed var(--bd);border-radius:8px;padding:6px 8px;margin-top:4px;word-break:break-all}
.fm .acts{display:flex;gap:8px;margin-top:14px}.fm .acts .g{background:#fff;color:#0B1220;border:1px solid var(--bd)}
#txm{position:fixed;left:50%;top:18px;transform:translateX(-50%);z-index:99999;border-radius:10px;padding:10px 16px;font-size:13px;font-weight:700;display:none;box-shadow:0 8px 24px rgba(0,0,0,.2);max-width:90vw}#txm.ok{background:#166534;color:#fff;display:block}#txm.er{background:#B91C1C;color:#fff;display:block}
.bt2 table{width:100%;border-collapse:collapse;font-size:13px}.bt2 th{text-align:left;font-size:11.5px;color:#6B7280;padding:6px 6px}.bt2 td{padding:6px;border-top:1px solid #E5E7EB;vertical-align:middle}.bt2 .lg{width:34px;height:34px;border-radius:8px;object-fit:contain;background:#F3F4F6;border:1px solid var(--bd)}
</style>
<div id="txm"></div>
<h1>Categories &amp; Brands</h1>
<p style="color:#6B7280;margin:0">Main Category → Sub Category → Child Category. Brands are managed separately and are not part of the tree.</p>
<div class="txt"><button type="button" id="tC" class="on">Categories</button><button type="button" id="tB">Brands</button></div>

<div id="pC" class="tx">
 <div class="card tn"><div class="tb"><input id="q" placeholder="Search category"><select id="fs"><option value="">All status</option><option value="a">Active</option><option value="i">Inactive</option></select><select id="fm"><option value="">All main categories</option></select><button type="button" class="g" id="ea" style="background:#fff;color:#0B1220;border:1px solid var(--bd)">Expand all</button><button type="button" class="g" id="ca" style="background:#fff;color:#0B1220;border:1px solid var(--bd)">Collapse all</button><button type="button" id="am">+ Add Main Category</button><button type="button" id="as" style="background:#0B1220">+ Add Sub Category</button><button type="button" id="ac" style="background:#0F766E">+ Add Child Category</button></div>
  <div id="tree"></div><p class="hint" style="font-size:12px;color:#6B7280">Drag ⠿ to change the order inside the same level. The storefront menu follows this order.</p></div>
 <div class="card fm" id="form"></div>
</div>

<div id="pB" class="tx" style="display:none">
 <div class="card bt2"><div class="tb"><input id="bq" placeholder="Search brand"><button type="button" id="ab">+ Add Brand</button></div><div id="bl"></div></div>
 <div class="card fm" id="bform"></div>
</div>
<script type="application/json" id="txd">@json($data)</script>
<script>window.TX_CSRF=@json(csrf_token());</script>
@verbatim
<script>
(function(){
var D=JSON.parse(document.getElementById('txd').textContent),$=function(i){return document.getElementById(i)},T=window.TX_CSRF,open={},cur=null,bcur=null;
function E(s){var d=document.createElement('div');d.textContent=s==null?'':s;return d.innerHTML}
function msg(t,ok){var m=$('txm');m.className=ok?'ok':'er';m.textContent=t;clearTimeout(msg.t);msg.t=setTimeout(function(){m.className=''},ok?1800:5000)}
function post(u,b){return fetch(u,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':T},body:JSON.stringify(b)}).then(function(r){return r.json().then(function(j){if(!r.ok&&!j.message&&j.errors){j.message=Object.values(j.errors)[0][0]}j.ok=r.ok&&j.ok!==false;return j})})}
function reload(){return fetch('/admin/taxonomy/data',{headers:{Accept:'application/json'}}).then(function(r){return r.json()}).then(function(j){D=j;draw();bdraw();fillMains()})}
function fillMains(){var s=$('fm'),v=s.value;s.innerHTML='<option value="">All main categories</option>'+D.tree.map(function(m){return '<option value="'+m.id+'">'+E(m.name)+'</option>'}).join('');s.value=v}
/* ---------- tree ---------- */
function match(n,q){return !q||n.name.toLowerCase().indexOf(q)>=0}
function vis(n,q,fs){var ok=match(n,q)&&(!fs||(fs==='a')===n.active);var k=(n.children||[]).map(function(c){return vis(c,q,fs)});return ok||k.some(Boolean)?{ok:ok,k:k}:null}
function row(n,lvl,q,fs){var kids=n.children||[],o=open[n.type+n.id]||(q&&true),h='<li class="l'+lvl+'" draggable="true" data-t="'+n.type+'" data-id="'+n.id+'" data-p="'+(n.parent_id||0)+'">';
 h+='<div class="rw"><span class="hd" title="Drag to reorder">⠿</span><span class="ps" title="Position">'+(n.position<10?'0':'')+n.position+'</span><button class="ib2 mv" title="Move up" data-mv="'+n.type+':'+n.id+':-1">▲</button><button class="ib2 mv" title="Move down" data-mv="'+n.type+':'+n.id+':1">▼</button><span class="cr" data-tg="'+n.type+n.id+'">'+(kids.length?(o?'▼':'▶'):'')+'</span>'+(n.image?'<img class="thm" src="'+E(n.image)+'" alt="">':'')+'<span class="nm" title="'+E(n.url)+'">'+E(n.name)+'</span><span class="ct">'+n.count+' Products</span><button class="st '+(n.active?'a':'i')+'" data-tog="'+n.type+':'+n.id+'">'+(n.active?'Active':'Inactive')+'</button>'
 +(n.type!=='subsub'?'<button class="ib2" title="'+(n.type==='main'?'Add Sub Category':'Add Child Category')+'" data-add="'+n.type+':'+n.id+'">+</button>':'')+'<button class="ib2" title="Edit" data-ed="'+n.type+':'+n.id+'">✎</button><button class="ib2 d" title="Delete" data-del="'+n.type+':'+n.id+'">🗑</button></div>';
 if(kids.length&&o){h+='<ul>';kids.forEach(function(c){if(vis(c,q,fs)||(!q&&!fs))h+=row(c,lvl+1,q,fs)});h+='</ul>'}return h+'</li>'}
function draw(){var q=$('q').value.trim().toLowerCase(),fs=$('fs').value,fm=$('fm').value,h='<ul>';
 D.tree.forEach(function(m){if(fm&&String(m.id)!==fm)return;if(q||fs){if(!vis(m,q,fs))return}h+=row(m,1,q,fs)});h+='</ul>';$('tree').innerHTML=D.tree.length?h:'<p>No categories yet. Click “+ Main Category”.</p>'}
function findSub(id){var r=null;D.tree.forEach(function(m){(m.children||[]).forEach(function(x){if(x.id==id)r=x})});return r}
function find(t,id){var r=null;(function w(a){a.forEach(function(n){if(n.type===t&&n.id==id)r=n;if(n.children)w(n.children)})})(D.tree);return r}
$('tree').addEventListener('click',function(e){var b=e.target.closest('[data-tg],[data-tog],[data-add],[data-ed],[data-del],[data-mv]');if(!b)return;
 if(b.dataset.mv){var m=b.dataset.mv.split(':'),n0=find(m[0],m[1]),sib=m[0]==='main'?D.tree:find(m[0]==='sub'?'main':'sub',n0.parent_id).children,ix=sib.indexOf(n0),jx=ix+ +m[2];if(jx<0||jx>=sib.length)return;var ids=sib.map(function(x){return x.id});ids.splice(ix,1);ids.splice(jx,0,n0.id);post('/admin/taxonomy/reorder',{kind:m[0]==='main'?'main':'node',ids:ids}).then(function(j){if(j.ok){msg('Order saved — menu updated',true);reload()}else msg('Could not save order')});return}
 if(b.dataset.tg){open[b.dataset.tg]=!open[b.dataset.tg];draw();return}
 if(b.dataset.tog){var p=b.dataset.tog.split(':');post('/admin/taxonomy/toggle',{type:p[0],id:+p[1]}).then(function(){reload()});return}
 if(b.dataset.add){var a=b.dataset.add.split(':');form(a[0]==='main'?'sub':'subsub',null,+a[1]);open[a[0]+a[1]]=true;draw();return}
 if(b.dataset.ed){var p2=b.dataset.ed.split(':');form(p2[0],find(p2[0],p2[1]));return}
 if(b.dataset.del){var p3=b.dataset.del.split(':'),n=find(p3[0],p3[1]);if(!confirm('Delete “'+n.name+'”?'))return;post('/admin/taxonomy/delete',{type:p3[0],id:+p3[1]}).then(function(j){if(j.ok){msg('Deleted',true);if(cur&&cur.n&&cur.n.id==n.id)form('main',null);reload()}else msg(j.message||'Could not delete')})}});
['q','fs','fm'].forEach(function(i){$(i).addEventListener('input',draw)});
$('ea').onclick=function(){(function w(a){a.forEach(function(n){if(n.children&&n.children.length){open[n.type+n.id]=true;w(n.children)}})})(D.tree);draw()};
$('ca').onclick=function(){open={};draw()};$('am').onclick=function(){form('main',null)};$('as').onclick=function(){form('sub',null)};$('ac').onclick=function(){form('subsub',null)};
/* ---------- drag & drop (same parent only) ---------- */
var drag=null;
$('tree').addEventListener('dragstart',function(e){var li=e.target.closest('li[draggable]');if(!li)return;drag=li;li.classList.add('dg');e.dataTransfer.effectAllowed='move';try{e.dataTransfer.setData('text/plain','x')}catch(x){}});
$('tree').addEventListener('dragover',function(e){if(!drag)return;var li=e.target.closest('li[draggable]');if(!li||li===drag||li.parentNode!==drag.parentNode)return;e.preventDefault();var r=li.getBoundingClientRect(),after=e.clientY>r.top+r.height/2;li.parentNode.insertBefore(drag,after?li.nextSibling:li)});
$('tree').addEventListener('dragend',function(){if(!drag)return;drag.classList.remove('dg');var ul=drag.parentNode,items=[].slice.call(ul.children).filter(function(x){return x.dataset&&x.dataset.id}),kind=drag.dataset.t==='main'?'main':'node';drag=null;
 post('/admin/taxonomy/reorder',{kind:kind,ids:items.map(function(x){return +x.dataset.id})}).then(function(j){if(j.ok)msg('Order saved — menu updated',true);else msg('Could not save order');reload()})});
/* ---------- form ---------- */
function up(inp,hid,pv){inp.onchange=function(){var f=inp.files[0];if(!f)return;var fd=new FormData();fd.append('file',f);fd.append('kind','category');fetch('/admin/upload-image',{method:'POST',headers:{'X-CSRF-TOKEN':T,Accept:'application/json'},body:fd}).then(function(r){return r.json()}).then(function(j){if(j.url){hid.value=j.url;pv.src=j.url;pv.style.display=''}else msg(j.message||'Upload failed')}).catch(function(){msg('Upload failed')})}}
function form(type,n,parent){cur={type:type,n:n};var L={main:'Main Category',sub:'Sub Category',subsub:'Child Category'}[type],h='<h3 style="margin:0">'+(n?'Edit ':'Add ')+L+'</h3>';
 if(!n)h+='<div class="ftabs">'+[['main','Main Category'],['sub','Sub Category'],['subsub','Child Category']].map(function(t){return '<button type="button" class="'+(t[0]===type?'on':'')+'" data-ft="'+t[0]+'">'+t[1]+'</button>'}).join('')+'</div>';
 var mains=D.tree,subs=[];D.tree.forEach(function(m){(m.children||[]).forEach(function(s){subs.push({id:s.id,name:m.name+' → '+s.name})})});
 var pv=n?n.parent_id:parent;
 if(type==='sub')h+='<label>Parent Main Category *</label><select id="f-p"><option value="">— Select main category —</option>'+mains.map(function(m){return '<option value="'+m.id+'"'+(m.id==pv?' selected':'')+'>'+E(m.name)+'</option>'}).join('')+'</select>'+(mains.length?'':'<div class="hint">Create a Main Category first.</div>');
 var pm=null;if(type==='subsub'){var ps=pv?findSub(pv):null;pm=ps?ps.main_id:null;
  h+='<label>Main Category *</label><select id="f-m"><option value="">— Select main category —</option>'+mains.map(function(m){return '<option value="'+m.id+'"'+(m.id==pm?' selected':'')+'>'+E(m.name)+'</option>'}).join('')+'</select><label>Parent Sub Category *</label><select id="f-p"><option value="">— Select sub category —</option></select><div class="hint" id="f-ph"></div>'}
 h+='<label>'+L+' Name *</label><input type="text" id="f-n" value="'+E(n?n.name:'')+'" placeholder="e.g. Cigarette and Vape">';
 if(type==='main')h+='<label>Slug *</label><input type="text" id="f-s" value="'+E(n?n.slug:'')+'" placeholder="cigarette-and-vape"><div class="hint">Changing the slug changes the URL of the category and its products.</div>';
 else h+='<div class="hint">URL slug is generated from the name.</div><div class="url" id="f-u">'+E(n?n.url:'')+'</div>';
 h+='<label>Category Image / Icon <small style="font-weight:400">(JPG, PNG, WebP — square works best)</small></label><div class="pv"><img id="f-pv" src="'+E(n&&n.image||'')+'" alt="" style="'+(n&&n.image?'':'display:none')+'"><input type="file" id="f-f" accept="image/jpeg,image/png,image/webp"></div><input type="hidden" id="f-i" value="'+E(n&&n.image||'')+'">';
 h+='<label>Description</label><textarea id="f-d">'+E(n&&n.description||'')+'</textarea>';
 h+='<div class="row2"><div><label>Position / Sort Order</label><input type="number" id="f-o" value="'+(n?n.position:'')+'" placeholder="auto (next)"></div><div><label>Status</label><select id="f-a"><option value="1"'+(!n||n.active?' selected':'')+'>Active</option><option value="0"'+(n&&!n.active?' selected':'')+'>Inactive</option></select></div></div>';
 h+='<div class="ck"><label><input type="checkbox" id="f-h"'+(n?(n.show_home?' checked':''):(type==='main'?' checked':''))+'> Show on Homepage</label>'+(type!=='main'?'<label><input type="checkbox" id="f-ft"'+(n&&n.featured?' checked':'')+'> Featured</label>':'')+'</div>';
 h+='<div class="acts"><button type="button" id="f-save">Save '+L+'</button>'+(n?'<button type="button" class="g" id="f-new">New</button>':'')+'</div>';
 $('form').innerHTML=h;up($('f-f'),$('f-i'),$('f-pv'));
 [].forEach.call($('form').querySelectorAll('[data-ft]'),function(b){b.onclick=function(){form(b.dataset.ft,null)}});
 if(type==='subsub'){var fm=$('f-m'),fp=$('f-p');var fill=function(){var m=find('main',fm.value),k=m?m.children:[];fp.innerHTML='<option value="">— Select sub category —</option>'+k.map(function(x){return '<option value="'+x.id+'"'+(x.id==pv?' selected':'')+'>'+E(x.name)+'</option>'}).join('');$('f-ph').textContent=m&&!k.length?'This main category has no Sub Category yet — add a Sub Category first.':''};fm.onchange=function(){pv=null;fill()};fill()}
 var nm=$('f-n');if(type!=='main'&&!n)nm.addEventListener('input',function(){var s=nm.value.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'');$('f-u').textContent=s?'…/'+s:''});
 if(type==='main'&&!n){nm.addEventListener('input',function(){if(!$('f-s').dataset.m)$('f-s').value=nm.value.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'')});$('f-s').addEventListener('input',function(){this.dataset.m=1})}
 $('f-save').onclick=function(){var b={type:type,id:n?n.id:0,name:nm.value,image:$('f-i').value,description:$('f-d').value,position:$('f-o').value===''?null:+$('f-o').value,active:$('f-a').value==='1',show_home:$('f-h').checked};
  if(type==='main')b.slug=$('f-s').value;else{b.parent_id=+$('f-p').value;b.featured=$('f-ft').checked}
  if(!b.name.trim()){msg('Name is required');return}if(type!=='main'&&!b.parent_id){msg(type==='sub'?'Select a parent Main Category':'Select a parent Sub Category');return}
  post('/admin/taxonomy/save',b).then(function(j){if(j.ok){msg('Saved',true);if(b.parent_id)open[(type==='sub'?'main':'sub')+b.parent_id]=true;reload().then(function(){form(type,find(type,j.id))})}else msg(j.message||'Could not save')})};
 var nw=$('f-new');if(nw)nw.onclick=function(){form(type,null,pv)}}
/* ---------- brands ---------- */
function bdraw(){var q=$('bq').value.trim().toLowerCase(),a=D.brands.filter(function(b){return !q||b.name.toLowerCase().indexOf(q)>=0});
 $('bl').innerHTML='<table><thead><tr><th></th><th>Brand</th><th>Linked products</th><th>Status</th><th></th></tr></thead><tbody>'+(a.length?a.map(function(b){return '<tr><td>'+(b.logo?'<img class="lg" src="'+E(b.logo)+'" alt="">':'')+'</td><td><b>'+E(b.name)+'</b><div style="color:#6B7280;font-size:11.5px">'+E(b.slug)+'</div></td><td>'+b.count+'</td><td><span class="pill '+(b.active?'ok':'')+'">'+(b.active?'Active':'Inactive')+'</span></td><td style="white-space:nowrap"><button class="g" style="background:#fff;color:#0B1220;border:1px solid var(--bd);padding:4px 9px" data-be="'+b.id+'">Edit</button> <button class="g" style="background:#fff;color:#B91C1C;border:1px solid var(--bd);padding:4px 9px" data-bd="'+b.id+'">Delete</button></td></tr>'}).join(''):'<tr><td colspan="5">No brands.</td></tr>')+'</tbody></table>'}
function bfind(id){return D.brands.filter(function(b){return b.id==id})[0]}
function bform(b){bcur=b;var h='<h3 style="margin:0">'+(b?'Edit Brand':'Add Brand')+'</h3><label>Brand Name *</label><input type="text" id="b-n" value="'+E(b?b.name:'')+'"><label>Slug *</label><input type="text" id="b-s" value="'+E(b?b.slug:'')+'"><label>Logo</label><div class="pv"><img id="b-pv" src="'+E(b&&b.logo||'')+'" alt="" style="'+(b&&b.logo?'':'display:none')+'"><input type="file" id="b-f" accept="image/jpeg,image/png,image/webp"></div><input type="hidden" id="b-i" value="'+E(b&&b.logo||'')+'"><label>Description</label><textarea id="b-d">'+E(b&&b.description||'')+'</textarea><div class="row2"><div><label>Sort Order</label><input type="number" id="b-o" value="'+(b?b.position:'')+'" placeholder="auto"></div><div><label>Status</label><select id="b-a"><option value="1"'+(!b||b.active?' selected':'')+'>Active</option><option value="0"'+(b&&!b.active?' selected':'')+'>Inactive</option></select></div></div><div class="hint">A brand can be linked to products from any category. Linked products: '+(b?b.count:0)+'</div><div class="acts"><button type="button" id="b-save">Save Brand</button>'+(b?'<button type="button" class="g" id="b-new">New</button>':'')+'</div>';
 $('bform').innerHTML=h;up($('b-f'),$('b-i'),$('b-pv'));
 if(!b)$('b-n').addEventListener('input',function(){if(!$('b-s').dataset.m)$('b-s').value=this.value.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'')});$('b-s').addEventListener('input',function(){this.dataset.m=1});
 $('b-save').onclick=function(){var x={id:b?b.id:0,name:$('b-n').value,slug:$('b-s').value,logo:$('b-i').value,description:$('b-d').value,position:$('b-o').value===''?null:+$('b-o').value,active:$('b-a').value==='1'};if(!x.name.trim()){msg('Brand name is required');return}
  post('/admin/taxonomy/brand',x).then(function(j){if(j.ok){msg('Saved',true);reload().then(function(){bform(bfind(j.id))})}else msg(j.message||'Could not save')})};
 var nw=$('b-new');if(nw)nw.onclick=function(){bform(null)}}
$('bl').addEventListener('click',function(e){var b=e.target.closest('[data-be],[data-bd]');if(!b)return;if(b.dataset.be){bform(bfind(b.dataset.be));return}
 var x=bfind(b.dataset.bd);if(!confirm('Delete brand “'+x.name+'”?'))return;post('/admin/taxonomy/brand/delete',{id:x.id}).then(function(j){if(j.ok){msg('Deleted',true);bform(null);reload()}else msg(j.message||'Could not delete')})});
$('bq').addEventListener('input',bdraw);$('ab').onclick=function(){bform(null)};
$('tC').onclick=function(){$('pC').style.display='';$('pB').style.display='none';$('tC').className='on';$('tB').className=''};
$('tB').onclick=function(){$('pB').style.display='';$('pC').style.display='none';$('tB').className='on';$('tC').className=''};
fillMains();draw();bdraw();form('main',null);bform(null);if(location.hash==='#brands'||location.pathname==='/admin/brands')$('tB').click();
})();
</script>
@endverbatim
@endsection
