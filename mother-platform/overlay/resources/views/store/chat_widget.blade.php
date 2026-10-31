@php $__cw = \Illuminate\Support\Facades\Cache::remember('chat.web_on', 60, fn() => (\DB::table('settings')->where('key', 'chat_web_on')->value('value') ?? '1') === '1' && \Illuminate\Support\Facades\Schema::hasTable('chat_threads')); @endphp
@if($__cw)
<style>.lc0{position:fixed;left:14px;bottom:{{ \App\Support\AiChat::on() ? '66px' : '18px' }};z-index:55;background:#FF5A00;color:#fff;border:0;border-radius:24px;padding:11px 16px;font-size:13px;font-weight:700;box-shadow:0 4px 14px rgba(255,90,0,.35);cursor:pointer;display:flex;gap:6px;align-items:center}.lc0 i{font-style:normal}.lc0 em{display:none;background:#E11D48;color:#fff;border-radius:20px;font-size:11px;font-style:normal;padding:0 6px}
.lcp{position:fixed;left:14px;bottom:{{ \App\Support\AiChat::on() ? '112px' : '64px' }};z-index:56;width:320px;max-width:calc(100vw - 28px);height:430px;max-height:calc(100vh - 140px);background:#fff;border:1.5px solid #FDC2A5;border-radius:16px;box-shadow:0 14px 34px rgba(11,18,32,.25);display:none;flex-direction:column;overflow:hidden;font-size:13px;color:#0B1220}.lcp.op{display:flex}
.lcp h4{margin:0;background:#0B1220;color:#fff;padding:11px 14px;font-size:14px;display:flex;justify-content:space-between;align-items:center}.lcp h4 button{background:none;border:0;color:#fff;font-size:20px;cursor:pointer;line-height:1}
.lcp .lm{flex:1;overflow:auto;padding:12px;background:#FFF8F3;display:flex;flex-direction:column;gap:7px}.lcp .lm div{max-width:84%;padding:8px 11px;border-radius:13px;line-height:1.45;word-break:break-word;white-space:pre-wrap}.lcp .lm .me{align-self:flex-end;background:#FF5A00;color:#fff;border-bottom-right-radius:3px}.lcp .lm .th{align-self:flex-start;background:#fff;border:1px solid #F1E3D8;border-bottom-left-radius:3px}.lcp .lm img{max-width:100%;border-radius:8px;display:block}.lcp .lm a{color:inherit;text-decoration:underline}
.lcp form{display:flex;gap:6px;padding:8px;border-top:1px solid #FFE7D6;background:#fff}.lcp input{flex:1;border:1px solid #FDC2A5;border-radius:9px;padding:9px 10px;font:inherit;min-width:0}.lcp form button{background:#FF5A00;color:#fff;border:0;border-radius:9px;padding:0 14px;font-weight:700;cursor:pointer}
.lcp .st{padding:16px;display:flex;flex-direction:column;gap:9px;flex:1;background:#FFF8F3}.lcp .st p{margin:0 0 4px;line-height:1.5}.lcp .st button{background:#FF5A00;color:#fff;border:0;border-radius:9px;padding:10px;font-weight:700;cursor:pointer}</style>
<button type="button" class="lc0" id="lc0" aria-label="Live chat"><i>💬</i> Live chat<em id="lcn"></em></button>
<div class="lcp" id="lcp" role="dialog" aria-label="Live chat"><h4><span>DutyFree Shop BD — Live Chat</span><button type="button" id="lcx" aria-label="Close">×</button></h4>
<div class="st" id="lcs"><p>আসসালামু আলাইকুম! আমাদের টিমের সাথে সরাসরি কথা বলতে নাম ও মোবাইল নম্বর দিন।</p><input id="lcna" placeholder="আপনার নাম" maxlength="60" autocomplete="name"><input id="lcph" placeholder="মোবাইল নম্বর (ঐচ্ছিক)" inputmode="tel" maxlength="20" autocomplete="tel"><button type="button" id="lcgo">চ্যাট শুরু করুন</button></div>
<div class="lm" id="lcm" style="display:none"></div><form id="lcf" style="display:none"><input id="lcq" placeholder="মেসেজ লিখুন…" maxlength="1000" autocomplete="off"><button>পাঠান</button></form></div>
@verbatim
<script>(function(){
var o=document.getElementById('lc0'),p=document.getElementById('lcp'),st=document.getElementById('lcs'),m=document.getElementById('lcm'),f=document.getElementById('lcf'),q=document.getElementById('lcq'),dot=document.getElementById('lcn');
var K='dfchat',S={},last=0,tm=null,seen=0;try{S=JSON.parse(localStorage.getItem(K)||'{}')}catch(e){}
function save(){try{localStorage.setItem(K,JSON.stringify(S))}catch(e){}}
function J(u,b){var o={headers:{'Accept':'application/json'}};if(b){o.method='POST';o.headers['Content-Type']='application/json';o.body=JSON.stringify(b)}return fetch(u,o).then(function(r){if(!r.ok)throw r.status;return r.json()})}
function add(c,t,img){var d=document.createElement('div');d.className=c;if(img){var a=document.createElement('a');a.href=img;a.target='_blank';var i=document.createElement('img');i.src=img;i.alt='';a.appendChild(i);d.appendChild(a)}
 var re=/(https?:\/\/[^\s]+)/g,i=0,x;t=t||'';while((x=re.exec(t))){d.appendChild(document.createTextNode(t.slice(i,x.index)));var l=document.createElement('a');l.href=x[0];l.target='_blank';l.rel='noopener';l.textContent=x[0];d.appendChild(l);i=x.index+x[0].length}d.appendChild(document.createTextNode(t.slice(i)));m.appendChild(d);m.scrollTop=m.scrollHeight}
function ui(){var on=!!S.t;st.style.display=on?'none':'flex';m.style.display=on?'flex':'none';f.style.display=on?'flex':'none'}
function poll(){if(!S.t)return;J('/chat/poll?token='+S.t+'&after='+last).then(function(j){var n=0,first=!m._init;(j.messages||[]).forEach(function(x){if(x.id>last)last=x.id;if(x.dir==='out'){add('th',x.body,x.att_type==='image'?x.att:null);if(!first)n++}else if(first)add('me',x.body)});m._init=1;
 if(n&&!p.classList.contains('op')){seen+=n;dot.textContent=seen;dot.style.display='inline'}}).catch(function(e){if(e===404){S={};save();m.innerHTML='';m._init=0;last=0;ui()}})}
function start(){var n=document.getElementById('lcna').value.trim();if(!n){document.getElementById('lcna').focus();return}
 J('/chat/start',{name:n,phone:document.getElementById('lcph').value.trim()}).then(function(j){S={t:j.token,n:n};save();m.innerHTML='';last=0;m._init=1;add('th','আসসালামু আলাইকুম '+n+'! কীভাবে সাহায্য করতে পারি?');ui();q.focus()}).catch(function(){alert('দুঃখিত, এখন চ্যাট শুরু করা যাচ্ছে না। অনুগ্রহ করে কল করুন: 09613828081')})}
document.getElementById('lcgo').onclick=start;
o.onclick=function(){p.classList.toggle('op');if(p.classList.contains('op')){seen=0;dot.style.display='none';ui();if(S.t){if(!m._init){poll()}q.focus()}}};
document.getElementById('lcx').onclick=function(){p.classList.remove('op')};
f.onsubmit=function(e){e.preventDefault();var t=q.value.trim();if(!t||!S.t)return;q.value='';add('me',t);J('/chat/send',{token:S.t,body:t}).catch(function(){add('th','মেসেজ পাঠানো যায়নি। আবার চেষ্টা করুন।')})};
var tk=0;setInterval(function(){if(!S.t||document.hidden)return;if(p.classList.contains('op')||(tk++%4)===0)poll()},4000);if(S.t)poll();
ui();
})();</script>
@endverbatim
@endif
