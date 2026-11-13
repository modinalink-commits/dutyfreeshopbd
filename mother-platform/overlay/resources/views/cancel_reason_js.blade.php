<script>(function(){
var L={"customer_request": "Customer asked to cancel", "unreachable": "Customer not reachable by phone", "wrong_address": "Wrong address / number", "out_of_stock": "Out of stock", "price_issue": "Price / offer not accepted", "cannot_deliver": "Cannot deliver to this area", "suspicious": "Suspicious / fake order", "duplicate": "Duplicate order (no message sent)", "other": "Other (write the reason, customer will see it)"},F=window.fetch,ov=null;
function ask(cb){if(ov)return;ov=document.createElement('div');ov.style.cssText='position:fixed;inset:0;background:rgba(11,18,32,.45);z-index:99999;display:flex;align-items:center;justify-content:center;padding:16px';
var h='<div style="background:#fff;border:1px solid #E5E7EB;border-radius:10px;max-width:380px;width:100%;padding:16px;box-shadow:0 8px 28px rgba(0,0,0,.18);font-size:13px;color:#0B1220"><div style="font-weight:700;font-size:15px;margin-bottom:4px">Cancel reason <span style="color:#DC2626">*</span></div><div style="color:#6B7280;margin-bottom:10px">ক্যান্সেলের কারণ বাছাই করুন। কাস্টমারকে SMS/WhatsApp-এ জানানো হবে।</div>';
Object.keys(L).forEach(function(k){h+='<label style="display:flex;flex-direction:row;gap:10px;align-items:center;justify-content:flex-start;text-align:left;width:100%;margin:0;padding:6px 0;cursor:pointer;font-weight:500;color:#0B1220"><input type="radio" name="dfcr" value="'+k+'" style="all:revert;flex:none;width:16px;height:16px;margin:0;accent-color:#FF5A00"><span style="flex:1;text-align:left;color:#0B1220">'+L[k]+'</span></label>'});
h+='<textarea id="dfcn" maxlength="120" rows="2" placeholder="Reason for the customer (short)" style="display:none;width:100%;margin-top:6px;border:1px solid #D1D5DB;border-radius:6px;padding:6px;box-sizing:border-box"></textarea><div id="dfce" style="color:#DC2626;min-height:16px;margin-top:6px"></div><div style="display:flex;gap:8px;justify-content:flex-end;margin-top:6px"><button type="button" id="dfcx" style="border:1px solid #D1D5DB;background:#fff;color:#0B1220;font-size:13px;min-width:64px;border-radius:6px;padding:7px 12px;cursor:pointer">Back</button><button type="button" id="dfco" style="border:1.5px solid #0B1220;background:#FF5A00;font-size:13px;color:#fff;border-radius:6px;padding:7px 14px;font-weight:700;cursor:pointer">Cancel order</button></div></div>';
ov.innerHTML=h;document.body.appendChild(ov);
function close(){ov.remove();ov=null}
ov.addEventListener('change',function(e){if(e.target.name==='dfcr')document.getElementById('dfcn').style.display=e.target.value==='other'?'block':'none'});
document.getElementById('dfcx').onclick=close;
document.getElementById('dfco').onclick=function(){var r=ov.querySelector('input[name=dfcr]:checked'),n=document.getElementById('dfcn').value.trim(),er=document.getElementById('dfce');
if(!r){er.textContent='কারণ বাছাই করুন।';return}if(r.value==='other'&&n.length<3){er.textContent='কারণটি লিখুন।';return}close();cb(r.value,n)}}
window.fetch=function(u,o){try{var url=typeof u==='string'?u:(u&&u.url)||'';
if(o&&/\/admin\/orders\/(\d+\/quick-status|bulk)$/.test(url.split('?')[0])){
var b=o.body,isForm=(typeof FormData!=='undefined'&&b instanceof FormData),st=null;
if(isForm)st=b.get('status');else if(typeof b==='string'){try{st=JSON.parse(b).status}catch(e){}}
if(st==='Cancel'&&!(isForm?b.get('cancel_reason'):(function(){try{return JSON.parse(b).cancel_reason}catch(e){return 1}})())){
return new Promise(function(res){ask(function(k,n){if(isForm){b.set('cancel_reason',k);b.set('cancel_note',n)}else{var j=JSON.parse(b);j.cancel_reason=k;j.cancel_note=n;o=Object.assign({},o,{body:JSON.stringify(j)})}res(F(u,o))})})}}
}catch(e){}return F.apply(this,arguments)};
document.addEventListener('submit',function(e){var f=e.target;if(!f||!f.action||!/\/admin\/orders\/\d+\/status$/.test(f.action.split('?')[0]))return;var s=f.querySelector('[name=status]');if(!s||s.value!=='Cancel'||f.querySelector('[name=cancel_reason]'))return;e.preventDefault();var sb=e.submitter;
ask(function(k,n){['cancel_reason','cancel_note'].forEach(function(nm,i){var h=document.createElement('input');h.type='hidden';h.name=nm;h.value=i?n:k;f.appendChild(h)});if(f.requestSubmit)f.requestSubmit(sb||undefined);else f.submit()})},true);
})();</script>
