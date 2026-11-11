@extends('layout')@section('title', $title)
@section('body')
<style>
.cl{display:grid;grid-template-columns:minmax(0,1.3fr) minmax(320px,.7fr);gap:14px;align-items:start}@media(max-width:980px){.cl{grid-template-columns:1fr}}
.cl h1{margin:0}.cl .sub0{color:#6B7280;margin:2px 0 12px}.cl .nav2{display:flex;gap:6px;flex-wrap:wrap;margin:10px 0 14px}.cl .nav2 a{border:1px solid var(--bd);background:#fff;color:#0B1220;border-radius:9px;padding:7px 12px;font-size:13px;font-weight:700}.cl .nav2 a.on{background:#FF5A00;border-color:#FF5A00;color:#fff}
.cl table{width:100%;border-collapse:collapse;font-size:13px}.cl th{font-size:11.5px;color:#6B7280;text-align:left;padding:6px}.cl td{padding:6px;border-top:1px solid #E5E7EB;vertical-align:middle}
.cl .gh td{background:#FFF1E8;font-weight:800;color:#0B1220;border-top:1px solid #FFF1E8;font-size:12.5px}
.cl tr.dg{opacity:.4}.cl .hd{cursor:grab;color:#9CA3AF;user-select:none}.cl .ps{font-weight:800;color:#FF5A00}.cl .thm{width:30px;height:30px;border-radius:8px;object-fit:cover;background:#F3F4F6}
.cl .ib2{border:1px solid var(--bd);background:#fff;color:#0B1220;border-radius:8px;width:28px;height:28px;padding:0;cursor:pointer;font-size:13px}.cl .ib2:hover{border-color:#FF5A00;color:#FF5A00}.cl .ib2.d:hover{border-color:#DC2626;color:#B91C1C}
.cl .st{font-size:10.5px;font-weight:700;border-radius:999px;padding:2px 8px;border:0;cursor:pointer}.cl .st.a{background:#DCFCE7;color:#166534}.cl .st.i{background:#F3F4F6;color:#6B7280}
.cl .tb{display:flex;gap:6px;margin-bottom:10px}.cl .tb input,.cl .tb select{border:1px solid var(--bd);border-radius:9px;padding:7px 10px;font-size:13px;background:#fff}.cl .tb input{flex:1}
.fm label{display:block;font-size:12px;font-weight:700;margin:10px 0 4px;color:#374151}.fm input[type=text],.fm input[type=number],.fm select,.fm textarea{width:100%;border:1px solid var(--bd);border-radius:9px;padding:8px 10px;font-size:13px;background:#fff;font-family:inherit}.fm textarea{min-height:60px}
.fm .row2{display:grid;grid-template-columns:1fr 1fr;gap:10px}.fm .ck{display:flex;gap:14px;flex-wrap:wrap;margin-top:8px}.fm .ck label{display:flex;gap:6px;align-items:center;font-weight:600;margin:0}
.fm .pv{display:flex;gap:10px;align-items:center}.fm .pv img{width:54px;height:54px;border-radius:10px;object-fit:cover;background:#F3F4F6;border:1px solid var(--bd)}
.fm .hint{font-size:11.5px;color:#6B7280;margin-top:3px}.fm .url{font-family:Inter,'Bornomala',sans-serif;font-size:12px;background:#F8FAFC;border:1px dashed var(--bd);border-radius:8px;padding:6px 8px;margin-top:4px;word-break:break-all}
.fm .acts{display:flex;gap:8px;margin-top:14px}.fm .acts .g{background:#fff;color:#0B1220;border:1px solid var(--bd)}
#txm{position:fixed;left:50%;top:18px;transform:translateX(-50%);z-index:99999;border-radius:10px;padding:10px 16px;font-size:13px;font-weight:700;display:none;box-shadow:0 8px 24px rgba(0,0,0,.2);max-width:90vw}#txm.ok{background:#166534;color:#fff;display:block}#txm.er{background:#B91C1C;color:#fff;display:block}
</style>
<div id="txm"></div>
<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:12px"><div style="flex:1;min-width:240px"><h1 style="margin:0">{{ $title }}</h1><span style="font-size:13px;color:#6B7280">{{ $intro }}</span></div></div>
<div class="chips" style="margin-bottom:14px"><a class="chip {{ $level==='main'?'on':'' }}" href="/admin/categories/main">1 · Main Categories</a><a class="chip {{ $level==='sub'?'on':'' }}" href="/admin/categories/sub">2 · Sub Categories</a><a class="chip {{ $level==='subsub'?'on':'' }}" href="/admin/categories/child">3 · Child Categories</a><a class="chip" href="/admin/brands">Brands</a></div>
<div class="cl"><div class="card"><div class="tb"><input id="q" placeholder="Search"><select id="fp"></select></div><div id="list"></div><p style="font-size:12px;color:#6B7280">Drag ⠿ or use ▲▼ to change the position inside the same group. The store menu follows this order.</p></div>
<div class="card fm" id="form"></div></div>
<script type="application/json" id="txd">@json($data)</script>
<script>window.TX_CSRF=@json(csrf_token());window.TX_LEVEL=@json($level);</script>
@verbatim
<script>
(function(){
var D=JSON.parse(document.getElementById('txd').textContent),LV=window.TX_LEVEL,T=window.TX_CSRF,$=function(i){return document.getElementById(i)};
var NAME={main:'Main Category',sub:'Sub Category',subsub:'Child Category'}[LV];
function E(s){var d=document.createElement('div');d.textContent=s==null?'':s;return d.innerHTML}
function msg(t,ok){var m=$('txm');m.className=ok?'ok':'er';m.textContent=t;clearTimeout(msg.t);msg.t=setTimeout(function(){m.className=''},ok?1800:5000)}
function post(u,b){return fetch(u,{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':T},body:JSON.stringify(b)}).then(function(r){return r.json().then(function(j){if(!r.ok&&!j.message&&j.errors)j.message=Object.values(j.errors)[0][0];j.ok=r.ok&&j.ok!==false;return j})})}
function reload(){return fetch('/admin/taxonomy/data',{headers:{Accept:'application/json'}}).then(function(r){return r.json()}).then(function(j){D=j;fillFilter();draw()})}
function groups(){var g=[];if(LV==='main')g.push({key:'0',label:null,items:D.tree});else D.tree.forEach(function(m){if(LV==='sub')g.push({key:'m'+m.id,label:m.name,items:m.children||[]});else(m.children||[]).forEach(function(s){g.push({key:'s'+s.id,label:m.name+' → '+s.name,items:s.children||[]})})});return g}
function fillFilter(){var s=$('fp'),v=s.value;if(LV==='main'){s.style.display='none';return}s.innerHTML='<option value="">All '+(LV==='sub'?'main categories':'sub categories')+'</option>'+groups().map(function(g){return '<option value="'+g.key+'">'+E(g.label)+'</option>'}).join('');s.value=v}
function find(id){var r=null;(function w(a){a.forEach(function(n){if(n.type===LV&&n.id==id)r=n;if(n.children)w(n.children)})})(D.tree);return r}
function row(n){return '<tr draggable="true" data-id="'+n.id+'"><td class="hd" title="Drag">⠿</td><td class="ps">'+(n.position<10?'0':'')+n.position+'</td><td>'+(n.image?'<img class="thm" src="'+E(n.image)+'" alt="">':'')+'</td><td><b>'+E(n.name)+'</b><div style="color:#6B7280;font-size:11px">'+E(n.url)+'</div></td><td>'+n.count+'</td><td><button class="st '+(n.active?'a':'i')+'" data-tog="'+n.id+'">'+(n.active?'Active':'Inactive')+'</button></td><td style="white-space:nowrap"><button class="ib2" data-mv="'+n.id+':-1" title="Up">▲</button> <button class="ib2" data-mv="'+n.id+':1" title="Down">▼</button> <button class="ib2" data-ed="'+n.id+'" title="Edit">✎</button> <button class="ib2 d" data-del="'+n.id+'" title="Delete">🗑</button></td></tr>'}
function draw(){var q=$('q').value.trim().toLowerCase(),fp=$('fp').value,h='<table><thead><tr><th></th><th>Pos</th><th></th><th>'+NAME+'</th><th>Products</th><th>Status</th><th></th></tr></thead>',any=false;
 groups().forEach(function(g){if(fp&&g.key!==fp)return;var it=g.items.filter(function(n){return !q||n.name.toLowerCase().indexOf(q)>=0});if(!it.length)return;any=true;h+='<tbody data-g="'+g.key+'">'+(g.label?'<tr class="gh"><td colspan="7">'+E(g.label)+' <span style="font-weight:400;color:#6B7280">('+g.items.length+')</span></td></tr>':'')+(it.length?it.map(row).join(''):'<tr><td colspan="7" style="color:#6B7280">No '+NAME.toLowerCase()+' here yet.</td></tr>')+'</tbody>'});
 $('list').innerHTML=any?h+'</table>':'<p><b style="font-size:22px">0</b> '+NAME.toLowerCase()+' yet. Use the form to add the first one.</p>'}
['q','fp'].forEach(function(i){$(i).addEventListener('input',draw)});
$('list').addEventListener('click',function(e){var b=e.target.closest('[data-tog],[data-mv],[data-ed],[data-del]');if(!b)return;
 if(b.dataset.tog)return post('/admin/taxonomy/toggle',{type:LV,id:+b.dataset.tog}).then(reload);
 if(b.dataset.ed)return form(find(b.dataset.ed));
 if(b.dataset.del){var n=find(b.dataset.del);if(!confirm('Delete “'+n.name+'”?'))return;return post('/admin/taxonomy/delete',{type:LV,id:n.id}).then(function(j){if(j.ok){msg('Deleted',true);form(null);reload()}else msg(j.message||'Could not delete')})}
 if(b.dataset.mv){var p=b.dataset.mv.split(':'),tb=b.closest('tbody'),ids=[].slice.call(tb.querySelectorAll('tr[data-id]')).map(function(r){return +r.dataset.id}),i=ids.indexOf(+p[0]),j=i+ +p[1];if(j<0||j>=ids.length)return;ids.splice(i,1);ids.splice(j,0,+p[0]);saveOrder(ids)}});
function saveOrder(ids){post('/admin/taxonomy/reorder',{kind:LV==='main'?'main':'node',ids:ids}).then(function(j){if(j.ok){msg('Position saved — menu updated',true);reload()}else msg('Could not save position')})}
var drag=null;$('list').addEventListener('dragstart',function(e){var r=e.target.closest('tr[data-id]');if(!r)return;drag=r;r.classList.add('dg');try{e.dataTransfer.setData('text/plain','x')}catch(x){}});
$('list').addEventListener('dragover',function(e){if(!drag)return;var r=e.target.closest('tr[data-id]');if(!r||r===drag||r.parentNode!==drag.parentNode)return;e.preventDefault();var b=r.getBoundingClientRect();r.parentNode.insertBefore(drag,e.clientY>b.top+b.height/2?r.nextSibling:r)});
$('list').addEventListener('dragend',function(){if(!drag)return;drag.classList.remove('dg');var ids=[].slice.call(drag.parentNode.querySelectorAll('tr[data-id]')).map(function(r){return +r.dataset.id});drag=null;saveOrder(ids)});
function up(inp,hid,pv){inp.onchange=function(){var f=inp.files[0];if(!f)return;var fd=new FormData();fd.append('file',f);fd.append('kind','category');fetch('/admin/upload-image',{method:'POST',headers:{'X-CSRF-TOKEN':T,Accept:'application/json'},body:fd}).then(function(r){return r.json()}).then(function(j){if(j.url){hid.value=j.url;pv.src=j.url;pv.style.display=''}else msg(j.message||'Upload failed')}).catch(function(){msg('Upload failed')})}}
function form(n){var h='<h3 style="margin:0">'+(n?'Edit ':'Add ')+NAME+'</h3>',mains=D.tree,pm=null,ps=n?n.parent_id:null;
 if(LV==='sub')h+='<label>Link to Main Category *</label><select id="f-p"><option value="">— Select main category —</option>'+mains.map(function(m){return '<option value="'+m.id+'"'+(n&&m.id==n.parent_id?' selected':'')+'>'+E(m.name)+'</option>'}).join('')+'</select>'+(mains.length?'':'<div class="hint">Add a Main Category first.</div>');
 if(LV==='subsub'){if(n)mains.forEach(function(m){(m.children||[]).forEach(function(s){if(s.id==n.parent_id)pm=m.id})});h+='<label>Main Category *</label><select id="f-m"><option value="">— Select main category —</option>'+mains.map(function(m){return '<option value="'+m.id+'"'+(m.id==pm?' selected':'')+'>'+E(m.name)+'</option>'}).join('')+'</select><label>Link to Sub Category *</label><select id="f-p"><option value="">— Select sub category —</option></select><div class="hint" id="f-ph"></div>'}
 h+='<label>'+NAME+' Name *</label><input type="text" id="f-n" value="'+E(n?n.name:'')+'">';
 if(LV==='main')h+='<label>Slug *</label><input type="text" id="f-s" value="'+E(n?n.slug:'')+'"><div class="hint">Changing the slug changes the URL of the category and its products.</div>';else h+='<div class="hint">URL slug is generated from the name.</div><div class="url" id="f-u">'+E(n?n.url:'')+'</div>';
 h+='<label>Image / Icon</label><div class="pv"><img id="f-pv" src="'+E(n&&n.image||'')+'" alt="" style="'+(n&&n.image?'':'display:none')+'"><input type="file" id="f-f" accept="image/jpeg,image/png,image/webp"></div><input type="hidden" id="f-i" value="'+E(n&&n.image||'')+'"><label>Description</label><textarea id="f-d">'+E(n&&n.description||'')+'</textarea>';
 h+='<div class="row2"><div><label>Position / Sort Order</label><input type="number" id="f-o" min="1" value="'+(n?n.position:'')+'" placeholder="auto (last)"><div class="hint">1 = first. Smaller number shows first.</div></div><div><label>Status</label><select id="f-a"><option value="1"'+(!n||n.active?' selected':'')+'>Active</option><option value="0"'+(n&&!n.active?' selected':'')+'>Inactive</option></select></div></div>';
 h+='<div class="ck"><label><input type="checkbox" id="f-h"'+(n?(n.show_home?' checked':''):(LV==='main'?' checked':''))+'> Show on Homepage</label>'+(LV!=='main'?'<label><input type="checkbox" id="f-ft"'+(n&&n.featured?' checked':'')+'> Featured</label>':'')+'</div>';
 h+='<div class="acts"><button type="button" id="f-save">Save '+NAME+'</button>'+(n?'<button type="button" class="g" id="f-new">+ Add New</button>':'')+'</div>';
 $('form').innerHTML=h;up($('f-f'),$('f-i'),$('f-pv'));var nm=$('f-n');
 if(LV==='subsub'){var fm=$('f-m'),fp=$('f-p');var fill=function(){var m=D.tree.filter(function(x){return x.id==fm.value})[0],k=m?m.children||[]:[];fp.innerHTML='<option value="">— Select sub category —</option>'+k.map(function(x){return '<option value="'+x.id+'"'+(ps&&x.id==ps?' selected':'')+'>'+E(x.name)+'</option>'}).join('');$('f-ph').textContent=m&&!k.length?'This main category has no Sub Category yet — add one on the Sub Categories page first.':''};fm.onchange=function(){ps=null;fill()};fill()}
 if(!n){nm.addEventListener('input',function(){var s=nm.value.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'');if(LV==='main'){if(!$('f-s').dataset.m)$('f-s').value=s}else $('f-u').textContent=s?'…/'+s:''});if(LV==='main')$('f-s').addEventListener('input',function(){this.dataset.m=1})}
 $('f-save').onclick=function(){var b={type:LV,id:n?n.id:0,name:nm.value,image:$('f-i').value,description:$('f-d').value,position:$('f-o').value===''?null:+$('f-o').value,active:$('f-a').value==='1',show_home:$('f-h').checked};
  if(LV==='main')b.slug=$('f-s').value;else{b.parent_id=+$('f-p').value;b.featured=$('f-ft').checked;if(!b.parent_id){msg(LV==='sub'?'Select the Main Category to link with':'Select the Sub Category to link with');return}}
  if(!b.name.trim()){msg('Name is required');return}
  post('/admin/taxonomy/save',b).then(function(j){if(j.ok){msg('Saved',true);reload().then(function(){form(null)})}else msg(j.message||'Could not save')})};
 var nw=$('f-new');if(nw)nw.onclick=function(){form(null)}}
fillFilter();draw();form(null);
})();
</script>
@endverbatim
@endsection
