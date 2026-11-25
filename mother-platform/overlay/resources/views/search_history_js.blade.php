{{-- Admin search memory: remembers each staff member's past searches (server side, per person) and offers them back when they type again. Nothing shows on an empty box. --}}
<style>#dfSH{position:absolute;z-index:9995;background:#fff;border:1px solid #E5E7EB;border-radius:10px;box-shadow:0 8px 24px rgba(15,23,42,.14);overflow:hidden;font:13px/1.4 system-ui,sans-serif;display:none}#dfSH div{padding:7px 12px;cursor:pointer;display:flex;gap:8px;align-items:baseline;color:#0C161F;border-bottom:1px solid #F1F3F5}#dfSH div:last-child{border:0}#dfSH div.on,#dfSH div:hover{background:#FFF4EC}#dfSH small{color:#6B7280}#dfSH b{font-weight:600}</style>
<script>(function(){var tok=(document.querySelector('meta[name=csrf-token]')||{}).content,box,cur,idx=-1,t,seq=0;
function ok(i){return i&&i.tagName==='INPUT'&&i.name==='q'&&i.id!=='sbq'&&(!i.type||i.type==='text'||i.type==='search')&&!i.readOnly&&i.form&&i.form.method.toLowerCase()==='get'}
function hide(){if(box)box.style.display='none';idx=-1}
function show(i,list){if(!box){box=document.createElement('div');box.id='dfSH';document.body.appendChild(box)}
 if(!list.length){hide();return}
 box.innerHTML='';list.forEach(function(x){var d=document.createElement('div'),b=document.createElement('b');b.textContent=x.q;d.appendChild(b);if(x.n){var s=document.createElement('small');s.textContent=x.n;d.appendChild(s)}
  d.addEventListener('mousedown',function(e){e.preventDefault();i.value=x.q;hide();if(i.form.requestSubmit)i.form.requestSubmit();else i.form.submit()});box.appendChild(d)});
 var r=i.getBoundingClientRect();box.style.left=(r.left+scrollX)+'px';box.style.top=(r.bottom+scrollY+2)+'px';box.style.minWidth=Math.max(r.width,200)+'px';box.style.display='block';idx=-1}
document.addEventListener('input',function(e){var i=e.target;if(!ok(i))return;cur=i;clearTimeout(t);var v=i.value.trim();if(v.length<2){hide();return}
 t=setTimeout(function(){var n=++seq;fetch('/admin/search-history?q='+encodeURIComponent(v),{headers:{Accept:'application/json'},credentials:'same-origin'}).then(function(r){return r.ok?r.json():[]}).then(function(l){if(n===seq&&cur===i&&i.value.trim()===v)show(i,l)}).catch(function(){})},180)});
document.addEventListener('keydown',function(e){if(!box||box.style.display==='none'||e.target!==cur)return;var k=box.children;
 if(e.key==='ArrowDown'||e.key==='ArrowUp'){e.preventDefault();if(idx>=0)k[idx].classList.remove('on');idx=(idx+(e.key==='ArrowDown'?1:-1)+k.length)%k.length;k[idx].classList.add('on')}
 else if(e.key==='Enter'&&idx>=0){e.preventDefault();k[idx].dispatchEvent(new MouseEvent('mousedown',{bubbles:true}))}else if(e.key==='Escape')hide()});
document.addEventListener('focusout',function(e){if(ok(e.target))setTimeout(hide,120)});
document.addEventListener('submit',function(e){var f=e.target,i=f&&f.querySelector&&f.querySelector('input[name=q]');if(!ok(i))return;var v=i.value.trim();if(v.length<3)return;
 try{fetch('/admin/search-history',{method:'POST',keepalive:true,credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':tok||'',Accept:'application/json'},body:JSON.stringify({q:v})})}catch(x){}},true);
})();</script>
