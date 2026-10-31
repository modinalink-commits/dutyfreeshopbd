@php $__cu = auth()->user(); $__P = \App\Http\Middleware\ModulePermission::class; @endphp
@if($__P::can($__cu, 'orders', 'view'))
<script>window.MCSRF=@json(csrf_token());window.MCAN=@json($__P::can($__cu, 'orders', 'edit'));window.MUID=@json($__cu->id);</script>
<style>
.mcp{position:fixed;top:14px;right:14px;z-index:9990;display:flex;flex-direction:column;gap:10px;width:350px;max-width:calc(100vw - 28px)}
.mcc{background:#fff;border:1.5px solid #FDC2A5;border-radius:16px;box-shadow:0 14px 34px rgba(11,18,32,.22);padding:12px;animation:mcin .22s ease-out;font-size:13px;color:#0B1220}
@keyframes mcin{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:none}}
.mcc .h{display:flex;align-items:center;gap:8px;font-weight:700;font-size:12.5px}.mcc .h .x{margin-left:auto;background:none;color:#6B7280;padding:0 4px;font-size:18px;line-height:1}
.mcc .cnt{background:#FF5A00;color:#fff;border-radius:20px;font-size:11px;padding:1px 8px}
.mcc .who{display:flex;gap:10px;align-items:center;margin:10px 0 6px}.mcc .who b{display:block;font-size:14px}.mcc .who small{color:#6B7280}
.mcc .msg{background:#FFF6EF;border-radius:10px;padding:8px 10px;margin:4px 0;font-size:13px;line-height:1.4;word-break:break-word}
.mcc .meta{font-size:12px;color:#374151;margin:6px 0 8px}.mcc .meta .rk-Low{color:#15803D}.mcc .meta .rk-Medium{color:#B45309}.mcc .meta .rk-High{color:#B91C1C}.mcc .meta b{font-weight:700}
.mcc .bt{display:flex;flex-wrap:wrap;gap:6px}.mcc .bt button,.mcc .bt a{padding:6px 10px;font-size:12px;border-radius:8px;border:1px solid #FDC2A5;background:#fff;color:#0B1220;text-decoration:none;font-weight:600;cursor:pointer}
.mcc .bt .pr{background:#FF5A00;border-color:#FF5A00;color:#fff}.mcc .qr{display:none;gap:6px;margin-top:8px}.mcc .qr input{flex:1;padding:7px 9px;font-size:12.5px}
.mcav{width:38px;height:38px;border-radius:50%;background:#FFE7D6;color:#C2410C;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0;overflow:hidden;position:relative}.mcav img{width:100%;height:100%;object-fit:cover}
.mcb{display:inline-flex;width:22px;height:22px;border-radius:7px;color:#fff;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0}.mcb.facebook{background:#1877F2}.mcb.instagram{background:linear-gradient(45deg,#F9A03F,#E1306C,#833AB4)}.mcb.whatsapp{background:#25D366}.mcb.web{background:#FF5A00}
.mcd{position:fixed;top:0;right:0;bottom:0;width:460px;max-width:100vw;background:#fff;z-index:9995;box-shadow:-16px 0 40px rgba(11,18,32,.25);display:flex;flex-direction:column;transform:translateX(105%);transition:transform .2s}.mcd.op{transform:none}
.mcd .cls{position:absolute;top:10px;right:12px;background:none;color:#6B7280;font-size:22px;padding:0 6px;z-index:2}
.mc{display:flex;flex-direction:column;height:100%;min-height:0;font-size:13px;color:#0B1220}
.mc .hd{padding:12px 44px 10px 14px;border-bottom:1px solid #FFE7D6;display:flex;flex-direction:column;gap:8px}
.mc .hd .r1{display:flex;gap:10px;align-items:center}.mc .hd .r1 b{font-size:15px;display:block}.mc .hd .r1 small{color:#6B7280}
.mc .hd .r2{display:flex;gap:6px;flex-wrap:wrap;align-items:center}.mc .hd select{width:auto;padding:5px 8px;font-size:12px}
.mc .lk{padding:5px 10px;font-size:12px;border-radius:8px;border:1px solid #FDC2A5;background:#fff;color:#0B1220;text-decoration:none;font-weight:600;cursor:pointer}
.mc .cb{border-bottom:1px solid #FFE7D6;padding:8px 14px;background:#FFFBF8;max-height:38%;overflow:auto}.mc .cb summary{cursor:pointer;font-weight:700;font-size:12.5px;padding:2px 0}
.mc .cb .st{display:flex;flex-wrap:wrap;gap:6px;margin:8px 0}.mc .cb .st span{background:#fff;border:1px solid #FFE7D6;border-radius:8px;padding:3px 8px;font-size:12px}
.mc .cb table{font-size:12px}.mc .cb td{padding:5px 6px 5px 0}.mc .cb .cur{background:#FFF1E8;border-radius:8px;padding:6px 8px;margin:4px 0;font-size:12px}
.mc .cb .mini{padding:2px 7px;font-size:11px;border-radius:6px;border:1px solid #FDC2A5;background:#fff;color:#0B1220;font-weight:600;cursor:pointer}
.mc .ms{flex:1;overflow:auto;padding:12px 14px;background:#F8F5F2;display:flex;flex-direction:column;gap:8px;min-height:120px}
.mc .b{max-width:82%;padding:8px 11px;border-radius:14px;line-height:1.45;word-break:break-word;white-space:pre-wrap;font-size:13px}
.mc .b.in{align-self:flex-start;background:#fff;border:1px solid #EDE7E1;border-bottom-left-radius:4px}.mc .b.out{align-self:flex-end;background:#FFE3D2;border-bottom-right-radius:4px}
.mc .b.fail{background:#FEE2E2}.mc .b small{display:block;color:#6B7280;font-size:10.5px;margin-top:3px}.mc .b img{max-width:100%;border-radius:8px;display:block;margin-bottom:4px}.mc .b a{color:#C2410C}
.mc .cm{border-top:1px solid #FFE7D6;padding:8px 10px;display:flex;flex-direction:column;gap:6px;background:#fff}
.mc .cm .tb{display:flex;flex-wrap:wrap;gap:5px;align-items:center}.mc .cm .tb select{width:auto;max-width:160px;padding:5px 7px;font-size:12px}
.mc .cm .tb .tg{padding:5px 9px;font-size:12px;border-radius:8px;border:1px solid #FDC2A5;background:#fff;color:#0B1220;font-weight:600;cursor:pointer}
.mc .cm textarea{min-height:44px;max-height:120px;resize:vertical;font-size:13px}.mc .cm .sd{display:flex;gap:8px;align-items:flex-end}.mc .cm .sd textarea{flex:1}
.mc .pk{border:1px solid #FFE7D6;border-radius:10px;padding:6px;max-height:170px;overflow:auto;display:none}.mc .pk div.it{padding:6px 8px;border-radius:8px;cursor:pointer;display:flex;justify-content:space-between;gap:8px}.mc .pk div.it:hover{background:#FFF1E8}
.mc .ro{padding:10px 14px;color:#6B7280;font-size:12px;border-top:1px solid #FFE7D6}
@media(max-width:600px){.mcp{top:8px;right:8px;left:8px;width:auto;max-width:none}}
</style>
<div class="mcp" id="mcp" aria-live="polite"></div>
<div class="mcd" id="mcd" role="dialog" aria-label="Customer chat"><button class="cls" type="button" id="mcx" aria-label="Close chat">×</button><div id="mcdb" style="height:100%"></div></div>
@verbatim
<script>
(function(){
var CSRF=window.MCSRF,CAN=window.MCAN;
var CH={facebook:['f','Facebook'],instagram:['IG','Instagram'],whatsapp:['W','WhatsApp'],web:['💬','Website Chat']};
function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
function lnk(s){return esc(s).replace(/(https?:\/\/[^\s<]+)/g,function(u){return '<a href="'+u+'" target="_blank" rel="noopener">'+u+'</a>'})}
function api(u,o){o=o||{};o.headers=Object.assign({'Accept':'application/json','X-CSRF-TOKEN':CSRF,'X-Requested-With':'XMLHttpRequest'},o.headers||{});o.credentials='same-origin';return fetch(u,o).then(function(r){return r.json().catch(function(){return {}}).then(function(j){if(!r.ok&&!j.error){j.error='Error '+r.status}return j})})}
function post(u,obj){return api(u,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(obj||{})})}
function tm(iso){try{return new Date(iso).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'})}catch(e){return ''}}
function av(c){var l=(c.name||'?').trim().charAt(0).toUpperCase();return '<span class="mcav">'+(c.avatar?'<img src="'+esc(c.avatar)+'" alt="" referrerpolicy="no-referrer" onerror="this.remove()">':'')+esc(l)+'</span>'}
function badge(ch){var c=CH[ch]||['?',ch];return '<span class="mcb '+esc(ch)+'" title="'+esc(c[1])+'">'+c[0]+'</span>'}
var M=window.MChat={current:null,mute:false};try{M.mute=localStorage.getItem('mchat_mute')==='1'}catch(e){}
M.toggleMute=function(){M.mute=!M.mute;try{localStorage.setItem('mchat_mute',M.mute?'1':'0')}catch(e){}return M.mute};
/* ---------- conversation panel (used by drawer and inbox page) ---------- */
M.mount=function(el,id,opt){
  opt=opt||{};if(el._mc)el._mc.stop();
  var S={id:id,last:0,timer:null,d:null,kind:'text'};el._mc=S;
  el.innerHTML='<div class="mc"><div class="hd" id="mh"></div><details class="cb" id="mcu" open></details><div class="ms" id="mm"></div><div class="cm" id="mf"></div></div>';
  var $=function(s){return el.querySelector(s)};
  S.stop=function(){clearInterval(S.timer);if(M.current===id)M.current=null};
  M.current=id;
  function bub(m){var a=m.att?(m.att_type==='image'?'<a href="'+esc(m.att)+'" target="_blank"><img src="'+esc(m.att)+'" alt="" loading="lazy"></a>':'<a href="'+esc(m.att)+'" target="_blank">📎 Attachment</a><br>'):'';
    return '<div class="b '+(m.dir==='in'?'in':'out')+(m.status==='failed'?' fail':'')+'" data-id="'+m.id+'">'+a+lnk(m.body||'')+'<small>'+(m.dir==='out'&&m.by?esc(m.by)+' · ':'')+tm(m.at)+(m.status==='failed'?' · ⚠ not delivered: '+esc(m.error||''):'')+'</small></div>'}
  function addMsgs(list){var box=$('#mm'),near=box.scrollHeight-box.scrollTop-box.clientHeight<120||!S.last;list.forEach(function(m){if(m.id>S.last){box.insertAdjacentHTML('beforeend',bub(m));S.last=m.id}});if(near)box.scrollTop=box.scrollHeight}
  function head(){var d=S.d,t=d.thread,opts=Object.keys(d.statuses).map(function(k){return '<option value="'+k+'"'+(t.status===k?' selected':'')+'>'+d.statuses[k]+'</option>'}).join('');
    var ag='<option value="">Unassigned</option>'+d.agents.map(function(a){return '<option value="'+a.id+'"'+(t.assigned_to==a.id?' selected':'')+'>'+esc(a.name)+'</option>'}).join('');
    var lo=d.orders[0];
    $('#mh').innerHTML='<div class="r1">'+av(t)+'<div style="flex:1;min-width:0"><b>'+esc(t.name)+' '+badge(t.channel)+'</b><small>'+esc(t.phone||t.username||'No number yet')+' · '+esc(t.channel_name)+'</small></div></div>'+
      '<div class="r2"><select id="hs" title="Status"'+(CAN?'':' disabled')+'>'+opts+'</select><select id="ha" title="Assigned to"'+(CAN?'':' disabled')+'>'+ag+'</select>'+
      (d.customer?'<a class="lk" href="/admin/m/customers/'+d.customer.id+'/edit">View Customer</a>':(t.phone?'<a class="lk" href="/admin/m/customers?q='+encodeURIComponent(t.phone)+'">Find Customer</a>':''))+
      (lo?'<a class="lk" href="/admin/orders/'+lo.id+'">View Order</a>':'')+(opt.drawer?'<button class="lk" type="button" id="hm" title="Sound">'+(M.mute?'🔕':'🔔')+'</button>':'')+'</div>';
    $('#hs').onchange=function(){post('/admin/chat/thread/'+id+'/status',{status:this.value}).then(function(j){if(opt.onchange)opt.onchange();M.badge(j.unread)})};
    $('#ha').onchange=function(){post('/admin/chat/thread/'+id+'/assign',{user_id:this.value||null}).then(function(){if(opt.onchange)opt.onchange()})};
    var hm=$('#hm');if(hm)hm.onclick=function(){this.textContent=M.toggleMute()?'🔕':'🔔'}}
  function cust(){var d=S.d,t=d.thread,i=d.intel,o=d.orders,cur=o[0];
    var h='<summary>Customer · '+esc(t.cat)+' · '+t.orders+' orders · '+esc(t.risk)+' risk</summary><div class="st"><span>'+esc(t.cat)+' customer</span><span>Orders: '+t.orders+'</span><span class="rk-'+esc(t.risk)+'">Risk: '+esc(t.risk)+(i?' ('+i.fraud+')':'')+'</span>'+(i&&i.rate!=null?'<span>Delivered: '+i.rate+'%</span>':'')+(i?'<span>Returned: '+i.returned+'</span><span>Spent: ৳'+Math.round(i.spent).toLocaleString()+'</span>':'')+'</div>';
    if(!t.phone&&CAN)h+='<div style="display:flex;gap:6px;margin:6px 0"><input id="hp" placeholder="Customer mobile (01XXXXXXXXX)" style="padding:6px 8px;font-size:12px"><button class="mini" id="hpb" type="button" style="padding:6px 10px">Link</button></div>';
    if(d.customer)h+='<div style="font-size:12px;margin:4px 0">'+esc(d.customer.name)+(d.customer.address?' · '+esc(d.customer.address):'')+'</div>';
    if(cur)h+='<div class="cur"><b>Current order</b> '+esc(cur.invoice)+' · <span class="pill">'+esc(cur.status)+'</span> · ৳'+Math.round(cur.total).toLocaleString()+(cur.courier_status?' · '+esc(cur.courier_status):'')+'</div>';
    if(o.length){h+='<table>'+o.map(function(x){return '<tr><td><a href="/admin/orders/'+x.id+'">'+esc(x.invoice)+'</a><br><small style="color:#6B7280">'+esc(x.date)+'</small></td><td><span class="pill">'+esc(x.status)+'</span></td><td>৳'+Math.round(x.total).toLocaleString()+'</td>'+(CAN?'<td style="white-space:nowrap"><button class="mini" data-o="'+x.id+'" data-k="invoice" type="button">Invoice</button> <button class="mini" data-o="'+x.id+'" data-k="tracking" type="button">Track</button></td>':'')+'</tr>'}).join('')+'</table>'}else h+='<div style="color:#6B7280;font-size:12px;padding:4px 0">No previous orders.</div>';
    var u=$('#mcu');u.innerHTML=h;
    u.querySelectorAll('button[data-o]').forEach(function(b){b.onclick=function(){fillOrder(b.dataset.o,b.dataset.k)}});
    var pb=$('#hpb');if(pb)pb.onclick=function(){post('/admin/chat/thread/'+id+'/phone',{phone:$('#hp').value}).then(function(j){if(j.ok)load();else alert(j.error||'Invalid number')})}}
  function fillOrder(oid,k){api('/admin/chat/order/'+oid+'/text?kind='+k).then(function(j){var ta=$('#mt');ta.value=j.text||'';S.kind=k;ta.focus()})}
  function composer(){var d=S.d;
    if(!CAN){$('#mf').innerHTML='<div class="ro">You have view-only access to chats.</div>';return}
    $('#mf').innerHTML='<div class="pk" id="mpk"><input id="mpq" placeholder="Search product name or SKU…" style="margin-bottom:6px;padding:7px 9px"><div id="mpl"></div></div>'+
    '<div class="tb"><select id="mq" title="Quick reply"><option value="">⚡ Quick reply</option>'+d.quick.map(function(q){return '<option value="'+q.id+'">'+esc(q.title)+'</option>'}).join('')+'</select>'+
    '<button class="tg" type="button" id="mpb">🛍 Product</button><button class="tg" type="button" id="mib">🧾 Invoice</button><button class="tg" type="button" id="mtb">🚚 Tracking</button><label class="tg" style="margin:0;cursor:pointer;color:#0B1220">📎 Attach<input type="file" id="mfi" accept="image/*,.pdf" style="display:none"></label></div>'+
    '<div class="sd"><textarea id="mt" placeholder="Type a reply… (Enter to send, Shift+Enter for a new line)"></textarea><button type="button" id="ms">Send</button></div><div id="mer" class="err"></div>';
    var qm={};d.quick.forEach(function(q){qm[q.id]=q.body});
    $('#mq').onchange=function(){if(this.value){$('#mt').value=qm[this.value];S.kind='quick';$('#mt').focus();this.value=''}};
    function pickOrder(k){var o=S.d.orders;if(!o.length){$('#mer').textContent='This customer has no orders yet.';return}fillOrder(o[0].id,k)}
    $('#mib').onclick=function(){pickOrder('invoice')};$('#mtb').onclick=function(){pickOrder('tracking')};
    var tmr;$('#mpb').onclick=function(){var p=$('#mpk');p.style.display=p.style.display==='block'?'none':'block';if(p.style.display==='block')$('#mpq').focus()};
    $('#mpq').oninput=function(){clearTimeout(tmr);var q=this.value;tmr=setTimeout(function(){api('/admin/chat/products?q='+encodeURIComponent(q)).then(function(r){$('#mpl').innerHTML=(r||[]).map(function(p,i){return '<div class="it" data-i="'+i+'"><span>'+esc(p.name)+'</span><b>৳'+Math.round(p.price).toLocaleString()+'</b></div>'}).join('')||'<div style="color:#6B7280;padding:6px">No product found</div>';$('#mpl').querySelectorAll('.it').forEach(function(n){n.onclick=function(){var p=r[n.dataset.i];S.kind='product';S.img=p.image&&p.image.charAt(0)==='/'?p.image:null;$('#mt').value=p.name+'\n৳'+Math.round(p.price).toLocaleString()+'\n'+p.url;$('#mpk').style.display='none'}})})},250)};
    $('#mfi').onchange=function(){if(this.files[0])send(this.files[0])};
    $('#ms').onclick=function(){send()};
    $('#mt').onkeydown=function(e){if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();send()}};
    function send(file){var ta=$('#mt'),body=ta.value.trim();if(!body&&!file)return;var fd=new FormData();fd.append('body',body);fd.append('kind',S.kind||'text');if(file)fd.append('file',file);else if(S.img)fd.append('image_url',S.img);
      $('#ms').disabled=true;$('#mer').textContent='';
      api('/admin/chat/thread/'+id+'/reply',{method:'POST',body:fd}).then(function(j){$('#ms').disabled=false;if(j.error&&!j.id){$('#mer').textContent=j.error;return}ta.value='';S.kind='text';S.img=null;$('#mfi').value='';if(j.ok===false)$('#mer').textContent='Saved, but not delivered: '+(j.error||'');pull();if(opt.onchange)opt.onchange()}).catch(function(){$('#ms').disabled=false;$('#mer').textContent='Network error — try again.'})}}
  function pull(){api('/admin/chat/thread/'+id+'/messages?after='+S.last).then(function(j){if(!j.messages)return;var inc=j.messages.some(function(m){return m.dir==='in'});addMsgs(j.messages);if(inc&&!document.hidden){post('/admin/chat/thread/'+id+'/read').then(function(r){M.badge(r.unread);if(opt.onchange)opt.onchange()})}if(j.status&&S.d&&j.status!==S.d.thread.status){S.d.thread.status=j.status;var hs=$('#hs');if(hs)hs.value=j.status}})}
  function load(){return api('/admin/chat/thread/'+id).then(function(d){if(!d.thread){el.innerHTML='<div class="ro">Conversation not found.</div>';return}S.d=d;head();cust();composer();var box=$('#mm');box.innerHTML='';S.last=0;addMsgs(d.messages);post('/admin/chat/thread/'+id+'/read').then(function(r){M.badge(r.unread);if(opt.onchange)opt.onchange()})})}
  load().then(function(){S.timer=setInterval(function(){if(!document.hidden&&document.body.contains(el))pull();else if(!document.body.contains(el))S.stop()},3000);if(opt.focus&&CAN){var t=$('#mt');if(t)t.focus()}});
  return S};
/* ---------- right-side drawer ---------- */
var dr=document.getElementById('mcd'),db=document.getElementById('mcdb');
M.open=function(id,focus){dr.classList.add('op');M.mount(db,id,{drawer:true,focus:focus});dismissCard(id,true)};
M.close=function(){dr.classList.remove('op');if(db._mc)db._mc.stop();db.innerHTML=''};
document.getElementById('mcx').onclick=M.close;
document.addEventListener('keydown',function(e){if(e.key==='Escape'&&dr.classList.contains('op')&&!/INPUT|TEXTAREA|SELECT/.test((document.activeElement||{}).tagName||''))M.close()});
/* ---------- popups, grouped per customer ---------- */
var P=document.getElementById('mcp'),cards={},dismissed={},lastBeep=0;
function dismissCard(id,silent){var c=cards[id];if(c){clearTimeout(c.t);c.el.remove();delete cards[id]}if(!silent)dismissed[id]=Date.now()}
function cardHtml(it,n){var risk=it.risk||'Low';return '<div class="h">'+badge(it.channel)+'<span>'+esc(it.channel_name)+' — New Message</span><span class="cnt" style="display:'+(n>1?'inline':'none')+'">'+n+' new</span><button class="x" type="button" aria-label="Dismiss">×</button></div>'+
  '<div class="who">'+av(it)+'<div style="min-width:0"><b>'+esc(it.name)+'</b><small>'+esc(it.phone||it.username||it.channel_name)+' · '+tm(it.at)+'</small></div></div>'+
  '<div class="msg">“'+esc((it.preview||'').slice(0,160))+'”</div>'+
  '<div class="meta"><b>'+esc(it.cat)+' Customer</b> · '+it.orders+' Order'+(it.orders===1?'':'s')+' · <span class="rk-'+esc(risk)+'">'+esc(risk)+' Risk</span> · Unread '+it.unread+'</div>'+
  '<div class="bt"><button class="pr" type="button" data-a="open">Open Chat</button>'+(CAN?'<button type="button" data-a="reply">Reply</button>':'')+
  '<a href="'+(it.customer_id?'/admin/m/customers/'+it.customer_id+'/edit':'/admin/m/customers?q='+encodeURIComponent(it.phone||it.name))+'">View Customer</a>'+
  '<button type="button" data-a="order">View Order</button><button type="button" data-a="dis">Dismiss</button></div>'+
  (CAN?'<div class="qr"><input type="text" placeholder="Quick reply…" maxlength="500"><button type="button" class="pr" data-a="send" style="padding:6px 12px;font-size:12px;border-radius:8px;border:0;background:#FF5A00;color:#fff;font-weight:600">Send</button></div>':'')}
function showCard(it){
  if(M.current===it.id&&dr.classList.contains('op')&&!document.hidden)return;
  var c=cards[it.id];if(c){c.n+=it.new_count}else{if(dismissed[it.id]&&Date.now()-dismissed[it.id]<45000)return;var e=document.createElement('div');e.className='mcc';P.prepend(e);c=cards[it.id]={el:e,n:it.new_count};while(P.children.length>4)P.lastChild.remove()}
  c.it=it;var q=c.el.querySelector('.qr input'),keep=q?q.value:'',open=q&&c.el.querySelector('.qr').style.display==='flex';
  c.el.innerHTML=cardHtml(it,c.n);var q2=c.el.querySelector('.qr input');if(q2){q2.value=keep;if(open)c.el.querySelector('.qr').style.display='flex'}
  c.el.onclick=function(ev){var b=ev.target.closest('[data-a],.x');if(!b)return;var a=b.dataset.a||'dis';
    if(a==='open')M.open(it.id);else if(a==='reply'){var r=c.el.querySelector('.qr');r.style.display='flex';r.querySelector('input').focus()}
    else if(a==='send'){var inp=c.el.querySelector('.qr input'),v=inp.value.trim();if(!v)return;var fd=new FormData();fd.append('body',v);api('/admin/chat/thread/'+it.id+'/reply',{method:'POST',body:fd}).then(function(j){if(j.id){inp.value='';dismissCard(it.id)}else inp.placeholder=j.error||'Failed'})}
    else if(a==='order'){api('/admin/chat/thread/'+it.id).then(function(d){location.href=d.orders&&d.orders[0]?'/admin/orders/'+d.orders[0].id:'/admin/orders?q='+encodeURIComponent(it.phone||'')})}
    else dismissCard(it.id)};
  c.el.onkeydown=function(ev){if(ev.key==='Enter'&&ev.target.tagName==='INPUT')c.el.querySelector('[data-a=send]').click()};
  clearTimeout(c.t);c.t=setTimeout(function(){if(!c.el.matches(':hover')&&!c.el.querySelector('.qr input:focus')&&!(c.el.querySelector('.qr input')||{}).value)dismissCard(it.id);},25000);
}
function beep(){if(M.mute||Date.now()-lastBeep<8000)return;lastBeep=Date.now();try{var A=window.AudioContext||window.webkitAudioContext,a=M._a||(M._a=new A()),n=a.currentTime;[[880,0],[1175,.16]].forEach(function(f){var o=a.createOscillator(),g=a.createGain();o.frequency.value=f[0];o.type='sine';g.gain.setValueAtTime(.0001,n+f[1]);g.gain.exponentialRampToValueAtTime(.25,n+f[1]+.02);g.gain.exponentialRampToValueAtTime(.0001,n+f[1]+.2);o.connect(g);g.connect(a.destination);o.start(n+f[1]);o.stop(n+f[1]+.22)})}catch(e){}}
/* ---------- unread badges: sidebar, tab title, favicon ---------- */
var baseTitle=document.title.replace(/^\(\d+\)\s*/,''),icon=null;
M.badge=function(n){n=+n||0;M.unread=n;var b=document.getElementById('chatBadge');if(b){b.textContent=n>99?'99+':n;b.style.display=n?'':'none'}
  document.title=(n?'('+n+') ':'')+baseTitle;
  try{var c=document.createElement('canvas');c.width=c.height=64;var x=c.getContext('2d');x.fillStyle='#0B1220';x.beginPath();x.roundRect?x.roundRect(4,4,56,56,14):x.rect(4,4,56,56);x.fill();x.fillStyle='#FF5A00';x.font='bold 36px system-ui';x.textAlign='center';x.fillText('M',32,46);
    if(n){x.fillStyle='#E11D48';x.beginPath();x.arc(46,18,17,0,7);x.fill();x.fillStyle='#fff';x.font='bold 22px system-ui';x.fillText(n>9?'9+':String(n),46,26)}
    if(!icon){icon=document.querySelector('link[rel~=icon]');if(!icon){icon=document.createElement('link');icon.rel='icon';document.head.appendChild(icon)}}icon.type='image/png';icon.href=c.toDataURL('image/png')}catch(e){}};
document.addEventListener('click',function(){try{if(!M._a){var A=window.AudioContext||window.webkitAudioContext;M._a=new A()}if(M._a.state==='suspended')M._a.resume()}catch(e){}},{once:false,passive:true});
/* ---------- real-time poll ---------- */
var since=null,busy=false;
function tick(){if(busy)return;busy=true;api('/admin/chat/poll'+(since===null?'':'?since='+since)).then(function(j){busy=false;if(j.last_id===undefined)return;
  var first=since===null;since=j.last_id;M.badge(j.unread);
  if(!first&&j.items&&j.items.length){var any=false;j.items.forEach(function(it){if(M.current===it.id&&!document.hidden){return}any=true;showCard(it)});if(any)beep();if(M.onNew)M.onNew(j.items)}
  }).catch(function(){busy=false})}
tick();setInterval(function(){if(!document.hidden||Math.random()<.25)tick()},5000);
document.addEventListener('visibilitychange',function(){if(!document.hidden)tick()});
})();
</script>
@endverbatim
@endif
