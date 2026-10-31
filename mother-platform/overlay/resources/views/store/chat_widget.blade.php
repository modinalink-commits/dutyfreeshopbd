@php $__cw = \Illuminate\Support\Facades\Cache::remember('chat.web_on', 60, fn() => (\DB::table('settings')->where('key', 'chat_web_on')->value('value') ?? '1') === '1' && \Illuminate\Support\Facades\Schema::hasTable('chat_threads')); $__bn = ($s['mobile_bottom_nav'] ?? '') === '1'; @endphp
@if($__cw)
<style>
.dfc-l{position:fixed;right:18px;bottom:22px;z-index:58;display:flex;align-items:center;gap:10px;font-family:inherit}
.dfc-lab{background:#fff;color:#0B1220;border:1.25px solid #FDC2A5;border-radius:22px;padding:9px 14px;font-size:12.5px;font-weight:700;box-shadow:0 6px 18px rgba(11,18,32,.14);white-space:nowrap;opacity:0;transform:translateX(8px);transition:.25s;pointer-events:none}
.dfc-l:hover .dfc-lab,.dfc-l.hint .dfc-lab{opacity:1;transform:none}
.dfc-b{position:relative;width:58px;height:58px;border-radius:50%;border:0;background:linear-gradient(145deg,#FF7A2E,#FF5A00);color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 8px 24px rgba(255,90,0,.45),0 0 0 6px rgba(255,90,0,.12);transition:transform .2s;animation:dfcg 2.8s ease-in-out infinite;padding:0}
.dfc-b:hover{transform:translateY(-3px) scale(1.06)}.dfc-b:hover svg{animation:dfcw .6s}.dfc-b:active{transform:scale(.96)}
@keyframes dfcg{0%,100%{box-shadow:0 8px 24px rgba(255,90,0,.45),0 0 0 6px rgba(255,90,0,.12)}50%{box-shadow:0 8px 28px rgba(255,90,0,.55),0 0 0 12px rgba(255,90,0,0)}}
@keyframes dfcw{0%,100%{transform:rotate(0)}30%{transform:rotate(-12deg)}60%{transform:rotate(10deg)}}
.dfc-b svg{width:27px;height:27px}.dfc-n{position:absolute;top:-3px;right:-3px;min-width:20px;height:20px;border-radius:10px;background:#E11D48;color:#fff;font-size:11px;font-weight:800;display:none;align-items:center;justify-content:center;padding:0 5px;border:2px solid #fff}
.dfc-w{position:fixed;right:18px;bottom:96px;z-index:59;width:372px;max-width:calc(100vw - 24px);height:min(590px,calc(100vh - 120px));background:#fff;border-radius:20px;box-shadow:0 24px 60px rgba(11,18,32,.32);display:flex;flex-direction:column;overflow:hidden;font:14px/1.45 system-ui,-apple-system,'Segoe UI',Roboto,Arial,sans-serif;color:#0B1220;opacity:0;transform:translateY(16px) scale(.96);transform-origin:bottom right;pointer-events:none;visibility:hidden;transition:opacity .22s,transform .22s,visibility .22s}
.dfc-w.op{opacity:1;transform:none;pointer-events:auto;visibility:visible}
.dfc-h{background:#0B1220;color:#fff;padding:13px 14px;display:flex;align-items:center;gap:11px}
.dfc-av{width:40px;height:40px;border-radius:50%;background:#FF5A00;color:#fff;font-weight:800;font-size:15px;display:flex;align-items:center;justify-content:center;flex-shrink:0;position:relative}.dfc-av i{position:absolute;right:0;bottom:0;width:11px;height:11px;border-radius:50%;background:#22C55E;border:2px solid #0B1220}
.dfc-h b{display:block;font-size:15px;line-height:1.2}.dfc-h small{display:block;font-size:12px;color:#B8C2D6;margin-top:2px}.dfc-h .g{color:#4ADE80}
.dfc-h button{background:rgba(255,255,255,.08);border:0;color:#fff;width:30px;height:30px;border-radius:9px;cursor:pointer;font-size:16px;line-height:1;margin-left:2px;padding:0}.dfc-h button:hover{background:rgba(255,255,255,.18)}
.dfc-m{flex:1;overflow:auto;padding:14px;background:#FFF8F3;display:flex;flex-direction:column;gap:8px;scroll-behavior:smooth}
.dfc-m .r{max-width:86%;padding:9px 12px;border-radius:15px;word-break:break-word;white-space:pre-wrap;font-size:13.5px;position:relative}
.dfc-m .t{align-self:flex-start;background:#fff;border:1px solid #F1E3D8;border-bottom-left-radius:4px}.dfc-m .me{align-self:flex-end;background:#FF5A00;color:#fff;border-bottom-right-radius:4px}
.dfc-m .r small{display:flex;gap:6px;justify-content:flex-end;font-size:10.5px;opacity:.75;margin-top:3px}.dfc-m .me small b{font-weight:600}.dfc-m .me small b.seen{color:#BFE3FF;opacity:1}
.dfc-m .r a{color:inherit;text-decoration:underline;word-break:break-all}.dfc-m .r img{display:block;max-width:100%;border-radius:10px;margin-bottom:3px}
.dfc-m .pc{display:block;background:#FFF1E8;border:1px solid #FDC2A5;color:#0B1220!important;border-radius:10px;padding:7px 10px;margin:4px 0;text-decoration:none!important;font-weight:600;font-size:12.5px}
.dfc-m .card{align-self:stretch;background:#fff;border:1.25px solid #FDC2A5;border-radius:13px;padding:11px 12px;font-size:13px}.dfc-m .card b{display:block;margin-bottom:6px}
.dfc-m .card input{width:100%;border:1px solid #FDC2A5;border-radius:9px;padding:9px 10px;font:inherit;margin-bottom:7px;box-sizing:border-box}.dfc-m .card button{width:100%;background:#FF5A00;color:#fff;border:0;border-radius:9px;padding:10px;font-weight:700;cursor:pointer;font-size:13px}.dfc-m .card .e{color:#B91C1C;font-size:12px;min-height:0;margin:-2px 0 5px}
.dfc-q{display:flex;flex-wrap:wrap;gap:6px;margin:2px 0}.dfc-q button{background:#fff;border:1.25px solid #FDC2A5;color:#0B1220;border-radius:18px;padding:7px 12px;font-size:12.5px;font-weight:600;cursor:pointer;transition:.15s}.dfc-q button:hover{background:#FF5A00;border-color:#FF5A00;color:#fff}
.dfc-qb{display:none;gap:6px;overflow-x:auto;padding:7px 10px 0;background:#fff;scrollbar-width:none}.dfc-qb::-webkit-scrollbar{display:none}.dfc-qb button{flex-shrink:0;background:#FFF1E8;border:0;color:#0B1220;border-radius:16px;padding:6px 11px;font-size:12px;font-weight:600;cursor:pointer}
.dfc-o{display:none;padding:7px 12px;background:#FFF1E8;border-top:1px solid #FFE0CC;font-size:12px;align-items:center;gap:8px}.dfc-o a{color:#C2410C;font-weight:700;margin-left:auto;white-space:nowrap}
.dfc-f{display:flex;align-items:flex-end;gap:6px;padding:9px 10px;background:#fff;border-top:1px solid #FFE7D6;position:relative}
.dfc-f textarea{flex:1;border:1.25px solid #FDC2A5;border-radius:14px;padding:9px 12px;font:inherit;font-size:13.5px;resize:none;max-height:92px;min-height:40px;outline:0;box-sizing:border-box}.dfc-f textarea:focus{border-color:#FF5A00}
.dfc-f .ic{width:36px;height:40px;border:0;background:none;font-size:19px;cursor:pointer;border-radius:10px;padding:0;flex-shrink:0}.dfc-f .ic:hover{background:#FFF1E8}
.dfc-f .sd{width:42px;height:40px;border:0;background:#FF5A00;color:#fff;border-radius:13px;cursor:pointer;flex-shrink:0;display:flex;align-items:center;justify-content:center;padding:0}.dfc-f .sd:disabled{opacity:.5}
.dfc-em{position:absolute;left:10px;bottom:58px;background:#fff;border:1.25px solid #FDC2A5;border-radius:14px;padding:8px;display:none;grid-template-columns:repeat(8,1fr);gap:2px;box-shadow:0 10px 26px rgba(11,18,32,.18);z-index:2}.dfc-em.op{display:grid}.dfc-em button{border:0;background:none;font-size:20px;cursor:pointer;border-radius:8px;padding:3px}.dfc-em button:hover{background:#FFF1E8}
.dfc-pw{text-align:center;font-size:10.5px;color:#9CA3AF;padding:0 0 6px;background:#fff}
.dfc-p{position:fixed;right:18px;bottom:96px;z-index:57;width:290px;max-width:calc(100vw - 100px);background:#fff;border:1.25px solid #FDC2A5;border-radius:16px 16px 4px 16px;box-shadow:0 14px 34px rgba(11,18,32,.22);padding:13px 15px 12px;font:13.5px/1.5 system-ui,-apple-system,'Segoe UI',Roboto,Arial,sans-serif;color:#0B1220;cursor:pointer;opacity:0;transform:translateY(10px);pointer-events:none;visibility:hidden;transition:.3s}
.dfc-p.op{opacity:1;transform:none;pointer-events:auto;visibility:visible}.dfc-p b{display:block;font-size:14.5px;margin-bottom:3px}.dfc-p span{display:block;color:#4B5563;font-size:12.5px}.dfc-p .x{position:absolute;top:5px;right:8px;border:0;background:none;color:#9CA3AF;font-size:17px;cursor:pointer;line-height:1;padding:2px 4px}
.dfc-p:after{content:'';position:absolute;right:20px;bottom:-7px;width:12px;height:12px;background:#fff;border-right:1.25px solid #FDC2A5;border-bottom:1.25px solid #FDC2A5;transform:rotate(45deg)}
.fb{bottom:96px!important}
@media(max-width:760px){.dfc-l{right:12px;bottom:{{ $__bn ? '70px' : '18px' }}}.dfc-lab{display:none}.dfc-b{width:54px;height:54px}.fb{bottom:{{ $__bn ? '136px' : '84px' }}!important}
.dfc-w{right:0;left:0;bottom:0;top:0;width:auto;max-width:none;height:auto;border-radius:0;transform:translateY(24px)}.dfc-w.op~.dfc-l,.dfc-l.hid{display:none}
.dfc-p{right:12px;bottom:{{ $__bn ? '138px' : '86px' }};width:250px}}
@media(prefers-reduced-motion:reduce){.dfc-b{animation:none}.dfc-w,.dfc-p{transition:none}}
</style>
<script>window.DFC={wa:@json($wa ?? ''),bn:@json($__bn)};</script>
<div class="dfc-p" id="dfcp" role="status"><button class="x" type="button" id="dfcpx" aria-label="Dismiss">×</button><b>আসসালামু আলাইকুম 👋</b><span>আমি আপনার জন্য প্রস্তুত আছি। কীভাবে সাহায্য করতে পারি?</span><span style="margin-top:5px;font-size:12px">অর্ডার, প্রোডাক্ট, ডেলিভারি বা পেমেন্ট বিষয়ে জানতে মেসেজ দিন।</span></div>
<div class="dfc-w" id="dfcw" role="dialog" aria-label="DutyFreeBD Support chat" aria-hidden="true">
<div class="dfc-h"><div class="dfc-av">DF<i></i></div><div style="flex:1;min-width:0"><b>DutyFreeBD Support</b><small><span class="g">🟢</span> Online এখনই</small></div><button type="button" id="dfcmin" aria-label="Minimize" title="Minimize">–</button><button type="button" id="dfcclose" aria-label="Close" title="Close">×</button></div>
<div class="dfc-m" id="dfcm" aria-live="polite"></div>
<div class="dfc-o" id="dfco"></div>
<div class="dfc-qb" id="dfcqb"></div>
<form class="dfc-f" id="dfcf" autocomplete="off"><div class="dfc-em" id="dfcem"></div><button type="button" class="ic" id="dfcemb" aria-label="Emoji">😊</button><button type="button" class="ic" id="dfcat" aria-label="Attach image">📎</button><input type="file" id="dfcfi" accept="image/jpeg,image/png,image/webp,image/gif" hidden>
<textarea id="dfcq" rows="1" maxlength="1000" placeholder="এখানে আপনার মেসেজ লিখুন..." aria-label="Message"></textarea><button class="sd" id="dfcsd" type="submit" aria-label="Send"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2 11 13M22 2l-7 20-4-9-9-4z"/></svg></button></form>
<div class="dfc-pw">DutyFreeBD · আমরা সাধারণত কয়েক মিনিটের মধ্যে উত্তর দিই</div></div>
<div class="dfc-l" id="dfcl"><span class="dfc-lab">Need Help? Chat with us</span><button type="button" class="dfc-b" id="dfcb" aria-label="Open live chat"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a8.5 8.5 0 0 1-12.2 7.6L3 21l1.5-5.4A8.5 8.5 0 1 1 21 12z"/><path d="M8.5 11.5h.01M12 11.5h.01M15.5 11.5h.01" stroke-width="2.6"/></svg><span class="dfc-n" id="dfcn"></span></button></div>
@verbatim
<script>(function(){
var D=window.DFC||{},K='dfchat',$=function(i){return document.getElementById(i)};
var W=$('dfcw'),M=$('dfcm'),Q=$('dfcq'),F=$('dfcf'),L=$('dfcl'),B=$('dfcb'),N=$('dfcn'),P=$('dfcp'),QB=$('dfcqb'),OB=$('dfco');
var S={},last=0,isOpen=false,unread=0,pend=null,started=false,shown={},mine={},seenUp=0,firstPoll=true,busy=false,order=null;
try{S=JSON.parse(localStorage.getItem(K)||'{}')||{}}catch(e){S={}}
function save(){try{localStorage.setItem(K,JSON.stringify(S))}catch(e){}}
function ck(){try{return JSON.parse(localStorage.getItem('df_cust')||'null')||{}}catch(e){return {}}}
function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
function tm(iso){try{return new Date(iso).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'})}catch(e){return ''}}
function J(u,b,fd){var o={headers:{'Accept':'application/json'}};if(fd){o.method='POST';o.body=fd}else if(b){o.method='POST';o.headers['Content-Type']='application/json';o.body=JSON.stringify(b)}return fetch(u,o).then(function(r){return r.json().catch(function(){return {}}).then(function(j){if(!r.ok){j._err=r.status;throw j}return j})})}
/* text -> safe html: links, product links as cards, order numbers as track links */
function fmt(t){var h=esc(t),host=location.host;
 h=h.replace(/(https?:\/\/[^\s<]+)/g,function(u){var path=u.replace(/^https?:\/\/[^\/]+/,'');var mine=u.indexOf('//'+host)>-1;
  if(mine&&/^\/(p\/|[a-z0-9\-]+\/[a-z0-9\-]+\/[a-z0-9\-]+)/i.test(path))return '<a class="pc" href="'+u+'" target="_blank" rel="noopener">🛍 প্রোডাক্ট দেখুন →</a>';
  return '<a href="'+u+'" target="_blank" rel="noopener">'+u+'</a>'});
 return h.replace(/\b(INV-\d{4,8})\b(?![^<]*<\/a>)/gi,function(m){return '<a href="/track?invoice='+m.toUpperCase()+'" target="_blank">'+m.toUpperCase()+'</a>'})}
function scroll(){M.scrollTop=M.scrollHeight}
function bubble(cls,html,at,id,status,img){var d=document.createElement('div');d.className='r '+cls;d.innerHTML=(img?'<a href="'+esc(img)+'" target="_blank"><img src="'+esc(img)+'" alt="" loading="lazy"></a>':'')+html+(at||status?'<small><span>'+(at?tm(at):'')+'</span>'+(cls==='me'?'<b>'+(status||'✓ Sent')+'</b>':'')+'</small>':'');M.appendChild(d);scroll();return d}
function setStatus(el,seen){var b=el&&el.querySelector('small b');if(!b)return;b.textContent=seen?'✓✓ Seen':'✓✓ Delivered';b.className=seen?'seen':''}
/* quick actions */
var QA=[['🛒 Order করতে চাই','msg','আমি অর্ডার করতে চাই। কীভাবে অর্ডার করব?'],['📦 Track Order','track'],['ℹ️ Product Info','msg','আমি একটি প্রোডাক্ট সম্পর্কে জানতে চাই।'],['💳 Payment Help','msg','পেমেন্ট সংক্রান্ত সাহায্য দরকার।'],['🚚 Delivery Info','msg','ডেলিভারি চার্জ ও সময় সম্পর্কে জানতে চাই।'],['💬 WhatsApp Chat','wa']];
function qaClick(a){if(a[1]==='wa'){window.open('https://wa.me/'+(D.wa||''),'_blank','noopener');return}
 if(a[1]==='track'){botSay('আপনার অর্ডার নম্বরটি লিখুন (যেমন INV-000123)। আমি স্ট্যাটাস দেখে দিচ্ছি।');Q.placeholder='অর্ডার নম্বর, যেমন INV-000123';Q.focus();S.track=1;return}
 send(a[2])}
function qaHtml(el){QA.forEach(function(a){var b=document.createElement('button');b.type='button';b.textContent=a[0];b.onclick=function(){qaClick(a)};el.appendChild(b)})}
function botSay(t){return bubble('t',fmt(t),new Date().toISOString())}
function welcome(){var n=S.n||ck().n;botSay('আসসালামু আলাইকুম'+(n?' '+n.split(' ')[0]:'')+'! 👋\nআমি আপনার জন্য প্রস্তুত আছি। কীভাবে সাহায্য করতে পারি?\n\nঅর্ডার, প্রোডাক্ট, ডেলিভারি বা পেমেন্ট বিষয়ে জানতে মেসেজ দিন।');
 var q=document.createElement('div');q.className='dfc-q';q.id='dfcqi';qaHtml(q);M.appendChild(q);scroll()}
/* identity: a returning browser is recognised without asking again */
function identity(){var c=ck();return {phone:S.p||c.p||'',name:S.n||c.n||''}}
function askPhone(){var i=identity();var c=document.createElement('div');c.className='card';c.id='dfcid';
 c.innerHTML='<b>শুরু করতে আপনার মোবাইল নম্বর দিন</b><input id="dfcph" inputmode="tel" maxlength="14" placeholder="01XXXXXXXXX" value="'+esc(i.phone)+'" autocomplete="tel"><input id="dfcnm" maxlength="60" placeholder="আপনার নাম (ঐচ্ছিক)" value="'+esc(i.name)+'" autocomplete="name"><div class="e" id="dfce"></div><button type="button" id="dfcgo">চ্যাট শুরু করুন</button>';
 M.appendChild(c);scroll();var go=function(){startChat($('dfcph').value,$('dfcnm').value)};$('dfcgo').onclick=go;c.addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();go()}})}
function startChat(p,n){var d=String(p).replace(/\D/g,'');d=d.slice(-11);if(!/^01[3-9]\d{8}$/.test(d)){$('dfce').textContent='সঠিক মোবাইল নম্বর দিন (১১ ডিজিট)';return}
 $('dfcgo').disabled=true;J('/chat/start',{phone:d,name:(n||'').trim()}).then(function(j){S.t=j.token;S.p=d;if(n&&n.trim())S.n=n.trim();save();var c=$('dfcid');if(c)c.remove();started=true;botSay('ধন্যবাদ! আপনার মেসেজ লিখুন, আমাদের টিম শীঘ্রই উত্তর দেবে।');ping();if(pend){var t=pend;pend=null;send(t)}}).catch(function(e){var el=$('dfce');if(el){el.textContent=(e&&e._err===422)?'সঠিক মোবাইল নম্বর দিন':'এখন চ্যাট শুরু করা যাচ্ছে না। কল করুন: 09613828081';$('dfcgo').disabled=false}})}
function ensure(cb){if(S.t){cb();return}var i=identity();if(/^01[3-9]\d{8}$/.test(String(i.phone).replace(/\D/g,'').slice(-11))){J('/chat/start',{phone:String(i.phone).replace(/\D/g,'').slice(-11),name:i.name}).then(function(j){S.t=j.token;S.p=i.phone;save();started=true;ping();cb()}).catch(function(){askPhone()})}else askPhone()}
/* sending */
function send(text){text=(text||'').trim();if(!text)return;var q=$('dfcqi');if(q)q.remove();showBar();
 if(!S.t){pend=text;ensure(function(){});return}
 var el=bubble('me',fmt(text),new Date().toISOString(),null,'⏳ Sending');
 J('/chat/send',{token:S.t,body:text}).then(function(j){if(j.id){mine[j.id]=el;shown[j.id]=1;if(j.id>last)last=j.id}el.querySelector('small b').textContent='✓ Sent';poll(true)}).catch(function(e){if(e&&e._err===404){S={};save();pend=text;el.remove();askPhone();return}el.querySelector('small b').textContent='⚠ Not sent';el.querySelector('small b').style.cursor='pointer';el.querySelector('small b').onclick=function(){el.remove();send(text)}});
 var m=text.match(/\b(INV-?\d{4,8}|\d{6})\b/i);if(S.track&&m){S.track=0;Q.placeholder='এখানে আপনার মেসেজ লিখুন...';lookup(m[1])}}
function lookup(inv){var p=identity().phone;if(!p){return}J('/chat/order?invoice='+encodeURIComponent(inv.replace(/^INV-?/i,'INV-'))+'&phone='+encodeURIComponent(p)).then(function(j){if(j.found)botSay('অর্ডার '+j.invoice+'\nস্ট্যাটাস: '+j.status+(j.courier_status?' ('+j.courier_status+')':'')+'\nট্র্যাক করুন: '+j.url);else botSay('এই নম্বরে অর্ডারটি খুঁজে পাইনি। নম্বরটি আবার দেখুন, অথবা আমাদের টিম আপনাকে সাহায্য করবে।')}).catch(function(){})}
F.onsubmit=function(e){e.preventDefault();var t=Q.value;Q.value='';Q.style.height='';send(t)};
Q.addEventListener('keydown',function(e){if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();F.onsubmit(e)}});
Q.addEventListener('input',function(){Q.style.height='auto';Q.style.height=Math.min(92,Q.scrollHeight)+'px'});
/* emoji + attachment */
var EM='😊 😀 😍 🙏 👍 ❤️ 😢 😮 🎁 📦 🚚 💳 ✅ ❓ 🙂 😅'.split(' '),emb=$('dfcem');EM.forEach(function(x){var b=document.createElement('button');b.type='button';b.textContent=x;b.onclick=function(){var s=Q.selectionStart||Q.value.length;Q.value=Q.value.slice(0,s)+x+Q.value.slice(s);Q.focus();emb.classList.remove('op')};emb.appendChild(b)});
$('dfcemb').onclick=function(e){e.stopPropagation();emb.classList.toggle('op')};document.addEventListener('click',function(e){if(!emb.contains(e.target))emb.classList.remove('op')});
$('dfcat').onclick=function(){if(!S.t){ensure(function(){$('dfcfi').click()});return}$('dfcfi').click()};
$('dfcfi').onchange=function(){var f=this.files[0];this.value='';if(!f)return;if(f.size>5*1024*1024){botSay('ছবির সাইজ ৫ MB এর বেশি হতে পারবে না।');return}
 var url=URL.createObjectURL(f),el=bubble('me','',new Date().toISOString(),null,'⏳ Sending',url),fd=new FormData();fd.append('token',S.t);fd.append('file',f);
 J('/chat/upload',null,fd).then(function(j){if(j.id){mine[j.id]=el;shown[j.id]=1;if(j.id>last)last=j.id}el.querySelector('small b').textContent='✓ Sent';showBar()}).catch(function(){el.querySelector('small b').textContent='⚠ Not sent'})};
/* polling: support replies, delivered / seen ticks */
function poll(force){if(!S.t||busy)return;busy=true;J('/chat/poll?token='+S.t+'&after='+last).then(function(j){busy=false;var n=0,lastBody='';
 (j.messages||[]).forEach(function(x){if(x.id>last)last=x.id;if(shown[x.id])return;shown[x.id]=1;
  if(x.dir==='out'){bubble('t',fmt(x.body||''),x.at,null,null,x.att_type==='image'?x.att:null);if(!firstPoll){n++;lastBody=x.body||'[ছবি]'}}
  else{mine[x.id]=bubble('me',fmt(x.body||''),x.at,null,'✓✓ Delivered',x.att_type==='image'?x.att:null)}});
 var changed=false;Object.keys(mine).forEach(function(id){if(+id<=j.seen_upto)setStatus(mine[id],true);else if(mine[id]&&/Sent|Sending/.test((mine[id].querySelector('small b')||{}).textContent||''))setStatus(mine[id],false)});
 if(firstPoll){firstPoll=false;if(M.children.length<=1&&!j.messages.length){}if(j.messages&&j.messages.length){started=true;showBar()}}
 if(n){if(!isOpen){unread+=n;badge();toast(lastBody)}}
 if(j.messages&&j.messages.length&&isOpen)scroll()}).catch(function(e){busy=false;if(e&&e._err===404){S={};save();started=false}})}
function badge(){N.textContent=unread>9?'9+':unread;N.style.display=unread?'flex':'none'}
function showBar(){if(QB.childElementCount===0){QA.forEach(function(a){var b=document.createElement('button');b.type='button';b.textContent=a[0];b.onclick=function(){qaClick(a)};QB.appendChild(b)})}QB.style.display='flex'}
function toast(t){P.querySelector('b').textContent='DutyFreeBD Support';var s=P.querySelectorAll('span');s[0].textContent=(t||'').slice(0,110);s[1].style.display='none';P.classList.add('op');clearTimeout(toast.t);toast.t=setTimeout(function(){P.classList.remove('op')},8000)}
/* current order of this device (invoice + phone must match) */
function showOrder(){var inv='';try{inv=localStorage.getItem('df_last')||''}catch(e){}var p=identity().phone;if(!inv||!p)return;J('/chat/order?invoice='+encodeURIComponent(inv)+'&phone='+encodeURIComponent(p)).then(function(j){if(j.found){OB.style.display='flex';OB.innerHTML='<span>📦 আপনার অর্ডার <b>'+esc(j.invoice)+'</b> · '+esc(j.status)+'</span><a href="'+esc(j.url)+'" target="_blank">Track →</a>'}}).catch(function(){})}
/* open / close */
function openChat(){if(isOpen)return;isOpen=true;W.classList.add('op');W.setAttribute('aria-hidden','false');L.classList.add('hid');P.classList.remove('op');unread=0;badge();try{sessionStorage.setItem('dfw','1')}catch(e){}
 if(!started&&!M.children.length){if(S.t){started=true}else welcome()}
 if(S.t){poll();showBar();showOrder()}setTimeout(function(){Q.focus({preventScroll:true});scroll()},250)}
function closeChat(){isOpen=false;W.classList.remove('op');W.setAttribute('aria-hidden','true');L.classList.remove('hid');emb.classList.remove('op')}
B.onclick=function(){isOpen?closeChat():openChat()};$('dfcmin').onclick=closeChat;$('dfcclose').onclick=function(){closeChat();try{sessionStorage.setItem('dfw','1')}catch(e){}};
document.addEventListener('keydown',function(e){if(e.key==='Escape'&&isOpen)closeChat()});
P.onclick=function(e){if(e.target.id==='dfcpx'){P.classList.remove('op');try{sessionStorage.setItem('dfw','1')}catch(x){}return}openChat()};
/* automatic welcome popup: once per visit, after a short delay, never over an open chat */
var seenW=false;try{seenW=sessionStorage.getItem('dfw')==='1'}catch(e){}
if(!seenW&&!S.t){setTimeout(function(){if(!isOpen&&!P.classList.contains('op')){P.classList.add('op');L.classList.add('hint');try{sessionStorage.setItem('dfw','1')}catch(e){}setTimeout(function(){P.classList.remove('op');L.classList.remove('hint')},14000)}},4500)}
else{setTimeout(function(){L.classList.add('hint');setTimeout(function(){L.classList.remove('hint')},3500)},1500)}
function ping(){poll()}
setInterval(function(){if(!S.t||document.hidden)return;poll()},4000);
if(S.t){setTimeout(poll,1200)}
document.addEventListener('visibilitychange',function(){if(!document.hidden&&S.t)poll()});
})();</script>
@endverbatim
@endif
