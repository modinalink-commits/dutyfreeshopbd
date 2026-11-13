@php($__siu = auth()->user())
@if($__siu && !in_array($__siu->role, ['super','admin'], true) && \App\Support\StaffIdle::on() && \App\Support\StaffIdle::applies($__siu))
<div id="siBar" hidden style="position:fixed;left:50%;transform:translateX(-50%);top:10px;z-index:9995;background:#FFF7ED;border:1px solid #FDBA74;color:#9A3412;border-radius:10px;padding:8px 14px;font:13px/1.45 system-ui,sans-serif;box-shadow:0 4px 14px rgba(15,23,42,.12);max-width:calc(100vw - 24px)"></div>
<div id="siBrk" style="position:fixed;left:12px;bottom:12px;z-index:9989;font:12.5px/1.4 system-ui,sans-serif">
<button type="button" id="siBt" style="border:1px solid #E5E7EB;background:#fff;border-radius:10px;padding:6px 10px;cursor:pointer;box-shadow:0 1px 3px rgba(15,23,42,.08)">বিরতি</button>
<div id="siMn" hidden style="position:absolute;bottom:36px;left:0;background:#fff;border:1px solid #E5E7EB;border-radius:10px;padding:6px;width:210px;box-shadow:0 8px 24px rgba(15,23,42,.14)">
@foreach(\App\Support\StaffIdle::BREAKS as $bk => $bl)<button type="button" data-k="{{ $bk }}" data-m="{{ in_array($bk, ['meal','packing'], true) ? 30 : 15 }}" style="display:block;width:100%;text-align:left;border:0;background:none;padding:7px 8px;border-radius:7px;cursor:pointer;font:inherit">{{ $bl }} · {{ in_array($bk, ['meal','packing'], true) ? 30 : 15 }} মিনিট</button>@endforeach
</div></div>
<script>(function(){
var bar=document.getElementById('siBar'),bt=document.getElementById('siBt'),mn=document.getElementById('siMn'),tk=document.querySelector('meta[name=csrf-token]');
function post(o){return fetch('/admin/staff-idle/break',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':tk?tk.content:''},body:JSON.stringify(o)}).then(ping)}
function ping(){fetch('/admin/staff-idle/ping',{credentials:'same-origin',headers:{Accept:'application/json'}}).then(function(r){return r.ok?r.json():null}).then(function(j){if(!j)return;
 if(j.lock){location.href='/admin/staff-idle/notice';return}
 if(j.break){bt.textContent='বিরতিতে ('+j.break+', '+j.break_left+' মি.) · শেষ করুন';bt.dataset.on='1';bar.hidden=true;return}
 bt.textContent='বিরতি';bt.dataset.on='';
 if(j.warn){bar.hidden=false;bar.textContent='সতর্কতা: আপনি '+j.idle+' মিনিট কোনো কাজ করেননি। আর '+j.left+' মিনিট পর আপনার নামে নোটিশ আসবে।'}else bar.hidden=true}).catch(function(){})}
bt.addEventListener('click',function(){if(bt.dataset.on){post({end:1})}else mn.hidden=!mn.hidden});
mn.addEventListener('click',function(e){var b=e.target.closest('button');if(!b)return;mn.hidden=true;post({kind:b.dataset.k,min:+b.dataset.m})});
setTimeout(ping,2500);setInterval(ping,60000);
})();</script>
@endif
