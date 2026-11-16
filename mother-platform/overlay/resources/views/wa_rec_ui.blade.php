{{-- WhatsApp call recordings: one list renderer + player, used by the call window, the Call Recordings page and the order page. WaRecUI.render(host, rows, admin, opts) --}}
@once
<style>
.wr-i{border-top:1px solid #F2F4F7;padding:9px 14px;font-size:12.5px;color:#0B1B33}.wr-i:first-child{border-top:0}
.wr-h{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.wr-h b{font-size:13px}.wr-h small{color:#475467}
.wr-p{display:inline-block;font-size:11px;font-weight:700;border-radius:6px;padding:2px 7px;background:#F2F4F7;color:#344054}.wr-p.ok{background:#ECFDF3;color:#067647}.wr-p.bad{background:#FEF3F2;color:#B42318}.wr-p.warn{background:#FFFAEB;color:#B54708}
main button.wr-b,.wr-b{height:30px;padding:0 10px;border-radius:8px;border:1px solid #D0D5DD;background:#fff;color:#0B1B33;font-weight:700;font-size:12px;cursor:pointer;display:inline-flex;align-items:center;text-decoration:none}
main button.wr-b.pri{background:#F95505;border-color:#F95505;color:#fff}main button.wr-b[disabled]{opacity:.55;cursor:default}
.wr-pl{display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin-top:7px}.wr-pl input[type=range]{flex:1;min-width:120px;accent-color:#F95505}.wr-pl select{height:30px;border:1px solid #D0D5DD;border-radius:8px;font-size:12px}.wr-pl span{font-variant-numeric:tabular-nums;color:#475467;min-width:86px;text-align:right}
.wr-m{display:flex;gap:6px;flex-wrap:wrap;margin-top:7px}.wr-m input{height:30px;border:1px solid #D0D5DD;border-radius:8px;padding:0 8px;font-size:12.5px}.wr-m input.v{flex:1;min-width:170px}.wr-m input.o{width:120px}
.wr-e{color:#B42318;font-size:12px;margin-top:4px}.wr-e.ok{color:#067647}
</style>
<script>
window.WaRecUI=(function(){
  var cur=null;
  function esc(s){var d=document.createElement('div');d.textContent=s==null?'':s;return d.innerHTML}
  function tk(){var e=document.querySelector('meta[name=csrf-token]');return e?e.content:''}
  function post(u,b){return fetch(u,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':tk()},body:JSON.stringify(b||{})}).then(function(r){return r.json().catch(function(){return{ok:false,message:r.status===403?'You are not allowed to do this.':'Server error '+r.status}})})}
  function mmss(s){s=Math.max(0,Math.floor(s||0));return ('0'+Math.floor(s/60)).slice(-2)+':'+('0'+s%60).slice(-2)}
  function size(b){return b>1048576?(b/1048576).toFixed(1)+' MB':Math.max(1,Math.round(b/1024))+' KB'}
  var ST={complete:['Recorded','ok'],incomplete:['Incomplete','warn'],failed:['Recording failed','bad'],recording:['Recording…','warn'],deleted:['Deleted','bad'],expired:['Expired','']};
  function player(row,box,btn){
    if(box.dataset.on){var a0=box._a;if(a0.paused)a0.play();else a0.pause();return}
    btn.disabled=true;btn.textContent='Loading…';var er=box.parentNode.querySelector('.wr-e');er.textContent='';
    fetch('/admin/call-center/whatsapp/rec/'+row.id+'/audio',{credentials:'same-origin'}).then(function(r){if(!r.ok)throw new Error(r.status===403?'You are not allowed to listen to this recording.':'The recording could not be opened ('+r.status+').');var cid=r.headers.get('X-Call-Id');if(row.call_id&&cid&&String(cid)!==String(row.call_id))throw new Error('This recording belongs to another call. Not played.');return r.blob()}).then(function(b){
      var a=new Audio();box._a=a;box.dataset.on='1';a.preload='auto';a.src=URL.createObjectURL(b);var D=row.duration||0;
      box.innerHTML='<button type="button" class="wr-b" data-k="b">−10s</button><input type="range" min="0" max="1000" value="0"><button type="button" class="wr-b" data-k="f">+10s</button><select><option value="1">1×</option><option value="1.25">1.25×</option><option value="1.5">1.5×</option><option value="2">2×</option><option value="0.75">0.75×</option></select><span>00:00 / '+mmss(D)+'</span>';
      var rg=box.querySelector('input'),sp=box.querySelector('span'),drag=false;
      function dur(){return isFinite(a.duration)&&a.duration>0?a.duration:D}
      // a browser recording has no length written in the file: jumping to the end once makes the browser measure it, so seeking works
      a.addEventListener('loadedmetadata',function(){if(!isFinite(a.duration)){var f=function(){a.removeEventListener('timeupdate',f);a.currentTime=0;go()};a.addEventListener('timeupdate',f);a.currentTime=1e7}else go()});
      var started=false;function go(){if(started)return;started=true;if(cur&&cur!==a)cur.pause();cur=a;a.play().catch(function(){});btn.disabled=false}
      a.addEventListener('timeupdate',function(){if(!started)return;var d=dur();if(!drag&&d)rg.value=Math.round(a.currentTime/d*1000);sp.textContent=mmss(a.currentTime)+' / '+mmss(d)});
      function ev(on){try{box.dispatchEvent(new CustomEvent('wrstate',{detail:on,bubbles:true}))}catch(e){}}
      a.addEventListener('play',function(){if(cur&&cur!==a)cur.pause();cur=a;btn.textContent='Pause';ev(true)});a.addEventListener('pause',function(){btn.textContent='Play';ev(false)});a.addEventListener('ended',function(){btn.textContent='Play';ev(false)});
      rg.addEventListener('input',function(){drag=true});rg.addEventListener('change',function(){var d=dur();if(d)a.currentTime=rg.value/1000*d;drag=false});
      box.querySelector('[data-k=b]').onclick=function(){a.currentTime=Math.max(0,a.currentTime-10)};box.querySelector('[data-k=f]').onclick=function(){a.currentTime=Math.min(dur()||1e9,a.currentTime+10)};
      box.querySelector('select').onchange=function(){a.playbackRate=+this.value};
    }).catch(function(e){btn.disabled=false;btn.textContent='Play';er.textContent=e.message||'Could not load'})}
  function render(host,rows,admin,opts){opts=opts||{};
    if(!rows||!rows.length){host.innerHTML='<div class="wr-i" style="color:#475467">'+esc(opts.empty||'No call recording yet.')+'</div>';return}
    host.innerHTML='';rows.forEach(function(r){var s=ST[r.status]||[r.status,''],d=document.createElement('div');d.className='wr-i';
      var h='<div class="wr-h"><b>'+esc(r.at)+'</b><span class="wr-p '+s[1]+'">'+s[0]+'</span>'+(opts.current===r.call_id?'<span class="wr-p">This call</span>':'')+'<small>'+(r.duration!=null?mmss(r.duration)+' · ':'')+esc(r.by||'—')+(r.bytes?' · '+size(r.bytes):'')+'</small>';
      if(r.can_listen)h+='<button type="button" class="wr-b pri" style="margin-left:auto" data-k="play">Play</button>'+(r.can_download?'<button type="button" class="wr-b" data-k="dl">Download</button>':'')+(admin?'<button type="button" class="wr-b" data-k="del">Delete</button>':'');
      h+='</div>';
      if(opts.full)h+='<div style="margin-top:4px;color:#475467">'+esc(r.customer||'WhatsApp caller')+' · '+esc(r.wa||'number hidden')+' → '+esc(r.business||'')+' · Call #'+r.call_id+(r.thread_id?' · <a href="/admin/chat?open='+r.thread_id+'">Conversation</a>':'')+(r.order_id?' · <a href="/admin/orders/'+r.order_id+'">Order '+esc(r.order)+'</a>':'')+(r.expires&&r.can_listen?' · kept until '+esc(r.expires):'')+'</div>';
      else if(r.order_id)h+='<div style="margin-top:4px;color:#475467">Order <a target="_blank" href="/admin/orders/'+r.order_id+'">'+esc(r.order)+'</a></div>';
      if(r.why&&r.status!=='complete')h+='<div style="margin-top:4px;color:#B54708">'+esc(r.why)+'</div>';
      if(r.removed_by)h+='<div style="margin-top:4px;color:#475467">Removed by '+esc(r.removed_by)+'</div>';
      if(r.status==='recording'&&!r.notice)h+='';
      h+='<div class="wr-pl"></div>';
      if(r.can_listen)h+='<div class="wr-m"><input class="v" maxlength="300" placeholder="Verification result (what was checked, outcome)" value="'+esc(r.verify)+'"><input class="o" maxlength="40" placeholder="Order no." value="'+esc(r.order||'')+'"><button type="button" class="wr-b" data-k="save">Save</button></div>'+(r.verify_by?'<div style="font-size:11.5px;color:#475467;margin-top:3px">Checked by '+esc(r.verify_by)+'</div>':'');
      else if(r.verify)h+='<div style="margin-top:4px">Verification: '+esc(r.verify)+(r.verify_by?' <small style="color:#475467">('+esc(r.verify_by)+')</small>':'')+'</div>';
      h+='<div class="wr-e"></div>';d.innerHTML=h;host.appendChild(d);
      var er=d.querySelector('.wr-e'),pb=d.querySelector('[data-k=play]'),sv=d.querySelector('[data-k=save]'),dl=d.querySelector('[data-k=del]');
      if(pb)pb.onclick=function(){player(r,d.querySelector('.wr-pl'),pb)};
      var db=d.querySelector('[data-k=dl]');if(db)db.onclick=function(){if(window.RecDL)RecDL.get('/admin/call-center/whatsapp/rec/'+r.id+'/audio?dl=1',r.call_id,db)};
      if(sv)sv.onclick=function(){sv.disabled=true;er.className='wr-e';er.textContent='';post('/admin/call-center/whatsapp/rec/'+r.id+'/meta',{verify:d.querySelector('.v').value.trim(),order:d.querySelector('.o').value.trim()}).then(function(k){sv.disabled=false;er.className='wr-e'+(k.ok?' ok':'');er.textContent=k.message||(k.ok?'Saved.':'Not saved.')})};
      if(dl)dl.onclick=function(){if(dl.dataset.sure!=='1'){dl.dataset.sure='1';dl.textContent='Delete for good?';setTimeout(function(){dl.dataset.sure='';dl.textContent='Delete'},4000);return}dl.disabled=true;post('/admin/call-center/whatsapp/rec/'+r.id+'/delete').then(function(k){if(k.ok){if(opts.reload)location.reload();else{d.style.opacity=.5;er.textContent='Deleted.'}}else{dl.disabled=false;er.textContent='Could not delete.'}})};
    })}
  function load(host,query,opts){fetch('/admin/call-center/whatsapp/rec/list?'+query,{credentials:'same-origin',headers:{'Accept':'application/json'}}).then(function(r){return r.json()}).then(function(j){render(host,j.rows||[],!!j.admin,opts)}).catch(function(){host.innerHTML='<div class="wr-i" style="color:#475467">Recordings could not be loaded.</div>'})}
  function pauseAll(){if(cur)cur.pause()}
  return{render:render,load:load,mmss:mmss,player:player,pauseAll:pauseAll};
})();
</script>
@endonce
