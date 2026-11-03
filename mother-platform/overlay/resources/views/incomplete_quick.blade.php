<style>
.inc-ab{font-size:10px;color:#92400E;background:#FEF3C7;border-radius:8px;padding:0 6px;margin-left:4px}
.incomplete-actions .oar{display:flex;gap:6px;justify-content:flex-end;align-items:center}
.iqm{position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:10001;display:none;align-items:center;justify-content:center;padding:16px}.iqm.on{display:flex}
.iqm .iqb{position:relative;background:#fff;border-radius:14px;width:420px;max-width:100%;padding:18px;box-shadow:0 20px 50px rgba(0,0,0,.25)}
.iqm h3{margin:0 0 10px;font-size:16px}.iqm label{display:block;font-size:12px;font-weight:600;color:#374151;margin:8px 0 3px}.iqm input,.iqm textarea{width:100%;box-sizing:border-box}
.iqm .iqx{position:absolute;top:10px;right:12px;border:0;background:#FEE2E2;color:#B91C1C;width:26px;height:26px;border-radius:50%;font-size:15px;line-height:1;cursor:pointer;padding:0}
.iqm .iql{display:flex;gap:8px;margin:0 0 4px}.iqm .iql a{font-size:12px;font-weight:600;text-decoration:none;border:1px solid #E5E7EB;border-radius:8px;padding:4px 10px;color:#111827}
.iqm .iqm-msg{font-size:12px;margin-top:8px;min-height:16px}
</style>
<div class="iqm" id="iqm" role="dialog" aria-modal="true"><div class="iqb"><button type="button" class="iqx" id="iqx" aria-label="Close">×</button>
<h3>Quick edit - incomplete checkout</h3><div class="iql"><a id="iqc" href="#">📞 Call</a><a id="iqw" href="#" target="_blank" rel="noopener">💬 WhatsApp</a><a id="iqe" href="#">✏️ Full edit</a></div>
<label>Customer name</label><input id="iqn" maxlength="120"><label>Mobile</label><input id="iqp"><label>Address</label><textarea id="iqa" rows="2" maxlength="500"></textarea><label>Note</label><input id="iqt" maxlength="500">
<div style="margin-top:12px;display:flex;gap:8px"><button type="button" class="btn" id="iqs">Save</button><button type="button" class="btn g" id="iqk">Cancel</button></div><div class="iqm-msg" id="iqg"></div></div></div>
<script>(function(){var m=document.getElementById('iqm');if(!m||window.__iqk)return;window.__iqk=1;var cur=null,btn=null;function $(i){return document.getElementById(i)}
function tk(){var e=document.querySelector('meta[name=csrf-token]');return e?e.content:''}
function close(){m.classList.remove('on');cur=null}
document.addEventListener('click',function(e){var b=e.target.closest('.iqk-btn');if(!b)return;e.preventDefault();e.stopPropagation();btn=b;cur=b.dataset.id;
$('iqn').value=b.dataset.name||'';$('iqp').value=b.dataset.phone||'';$('iqa').value=b.dataset.address||'';$('iqt').value=b.dataset.note||'';$('iqg').textContent='';
var d=(b.dataset.phone||'').replace(/\D/g,'');$('iqc').href='tel:'+(b.dataset.phone||'');$('iqw').href='https://wa.me/'+(d.charAt(0)==='0'?'88'+d:d);$('iqe').href='/admin/incomplete/'+cur+'/edit';m.classList.add('on');$('iqn').focus()},true);
$('iqx').onclick=close;$('iqk').onclick=close;m.addEventListener('click',function(e){if(e.target===m)close()});document.addEventListener('keydown',function(e){if(e.key==='Escape'&&cur)close()});
$('iqs').onclick=function(){if(!cur)return;var s=$('iqs'),g=$('iqg');s.disabled=true;g.style.color='#6B7280';g.textContent='Saving...';
var fd=new FormData();fd.append('_token',tk());fd.append('name',$('iqn').value);fd.append('phone',$('iqp').value);fd.append('address',$('iqa').value);fd.append('note',$('iqt').value);
fetch('/admin/incomplete/'+cur+'/quick',{method:'POST',body:fd,credentials:'same-origin',headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}}).then(function(r){return r.json().then(function(j){return{s:r.status,j:j}})}).then(function(x){s.disabled=false;
if(x.s===200&&x.j.ok){g.style.color='#166534';g.textContent='Saved';setTimeout(function(){close();(window.dfSoft?dfSoft():location.reload())},500)}else{g.style.color='#B91C1C';g.textContent=(x.j&&(x.j.message||(x.j.errors&&Object.values(x.j.errors)[0][0])))||'Could not save'}}).catch(function(){s.disabled=false;g.style.color='#B91C1C';g.textContent='Network error'})}})();</script>
