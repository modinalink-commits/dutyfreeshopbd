{{-- Packaging Scan popup (owner rule 14 Nov 2026). Included on the Orders page and on /admin/packing-scan. The scanner types the label number and presses Enter. --}}
<style>
#psOv{position:fixed;inset:0;background:rgba(11,27,51,.55);z-index:9990;display:none;align-items:flex-start;justify-content:center;overflow:auto;padding:14px 12px}
#psOv.on{display:flex}
#psOv .ps{width:min(1040px,100%);background:#fff;border:1px solid #CBD5E1;border-radius:12px;overflow:hidden;color:#0B1B33;font-size:14px}
#psOv .ps-h{display:flex;flex-wrap:wrap;gap:10px;align-items:center;padding:10px 14px;background:#EEF1F5;border-bottom:1px solid #D9DEE6}
#psOv .ps-h b{font-size:16px}
#psOv .ps-k{margin-left:auto;display:flex;gap:8px;flex-wrap:wrap}
#psOv .ps-k span{border:1px solid #D9DEE6;background:#fff;border-radius:8px;padding:4px 10px;font-size:12px;color:#5B6676;font-variant-numeric:tabular-nums}
#psOv .ps-k span b{font-size:15px;color:#0B1B33;margin-left:4px}
#psOv #psX{width:36px;height:36px;border-radius:9px;border:1.5px solid #0B1B33;background:#DC2626;color:#fff;font-size:20px;font-weight:800;line-height:1;cursor:pointer;padding:0}
#psOv #psX:hover{background:#991B1B}
#psOv .ps-b{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(0,1fr);gap:14px;padding:14px}
@media(max-width:820px){#psOv .ps-b{grid-template-columns:minmax(0,1fr)}}
#psOv .ps-f{display:flex;gap:8px}
#psOv #psIn{flex:1;min-width:0;height:50px;border:2px solid #0B1B33;border-radius:10px;padding:0 14px;font:700 20px Inter,sans-serif;letter-spacing:.5px;color:#0B1B33;background:#fff;width:auto;margin:0}
#psOv #psIn:focus{outline:3px solid rgba(255,90,0,.35);border-color:#FF5A00}
#psOv #psGo{height:50px;padding:0 18px;border-radius:10px;border:1.5px solid #0B1B33;background:#FF5A00;color:#fff;font-weight:800;cursor:pointer;width:auto;margin:0}
#psOv .ps-hint{font-size:12px;color:#5B6676;margin:6px 2px 10px}
#psOv #psRes{border-radius:12px;padding:16px;min-height:220px;display:flex;flex-direction:column;gap:8px;border:2px dashed #D9DEE6;background:#FAFBFC}
#psOv #psRes.ok{border:2px solid #15803D;background:#DCFCE7}
#psOv #psRes.bad{border:2px solid #B91C1C;background:#FEE2E2}
#psOv #psRes.warn{border:2px solid #B45309;background:#FEF3C7}
#psOv .ps-big{font-size:28px;font-weight:800;line-height:1.15;color:#5B6676}
#psOv .ok .ps-big{color:#15803D}#psOv .bad .ps-big{color:#B91C1C}#psOv .warn .ps-big{color:#B45309}
#psOv .ps-sub{font-size:14.5px;font-weight:600}
#psOv .ps-o{margin-top:6px;background:#fff;border:1px solid #D9DEE6;border-radius:10px;padding:10px 12px;display:grid;grid-template-columns:auto minmax(0,1fr);gap:4px 12px;font-size:13px}
#psOv .ps-o span{color:#5B6676}#psOv .ps-o b{font-weight:600;overflow-wrap:anywhere}
#psOv .ps-t{font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:#5B6676;font-weight:700;margin:0 0 6px}
#psOv .ps-w{overflow:auto;max-height:420px;border:1px solid #D9DEE6;border-radius:10px}
#psOv table{border-collapse:collapse;width:100%;font-size:12.5px;font-variant-numeric:tabular-nums;margin:0}
#psOv th,#psOv td{text-align:left;padding:6px 9px;border-bottom:1px solid #E5E7EB;white-space:nowrap}
#psOv th{background:#EEF1F5;font-weight:700;position:sticky;top:0}
#psOv .pp{display:inline-block;border-radius:999px;padding:1px 9px;font-weight:700;font-size:11.5px}
#psOv .pp.ok{background:#DCFCE7;color:#15803D}#psOv .pp.bad{background:#FEE2E2;color:#B91C1C}#psOv .pp.warn{background:#FEF3C7;color:#B45309}
html body button.ps-open,html body .toolbar-control.ps-open{background:#0B1B33!important;background-image:none!important;color:#fff!important;border:1.5px solid #FF5A00!important;font-weight:700!important}
html body button.ps-open:hover,html body .toolbar-control.ps-open:hover{background:#FF5A00!important;border-color:#0B1B33!important}
</style>
<div id="psOv" role="dialog" aria-modal="true" aria-label="Packaging Scan" data-keep><div class="ps">
<div class="ps-h"><b>Packaging Scan</b><span style="font-size:12.5px;color:#5B6676">Scan each parcel label, then put the parcel in the courier bag.</span>
<div class="ps-k"><span>Handed over today<b id="psOk">0</b></span><span>Stopped<b id="psBad" style="color:#B91C1C">0</b></span><span>Still in Packaging<b id="psW">0</b></span></div>
<button type="button" id="psX" aria-label="Close" title="Close">×</button></div>
<div class="ps-b"><div>
<form class="ps-f" id="psF" autocomplete="off"><input id="psIn" inputmode="numeric" autocomplete="off" placeholder="Scan the parcel label" aria-label="Parcel label barcode"><button type="submit" id="psGo">Check</button></form>
<div class="ps-hint">The scanner types the number and presses Enter. You can also type the invoice number by hand.</div>
<div id="psRes"><div class="ps-big">Ready to scan</div><div class="ps-sub" style="color:#5B6676">Green: give the parcel to the courier. Red: keep the parcel.</div></div>
</div><div>
<div class="ps-t">Today's scans</div>
<div class="ps-w"><table><thead><tr><th>Time</th><th>Invoice</th><th>Result</th><th>Staff</th></tr></thead><tbody id="psLog"><tr><td colspan="4" style="color:#5B6676;padding:12px">Nothing scanned yet.</td></tr></tbody></table></div>
</div></div></div></div>
<script>
(function(){
 var ov=document.getElementById('psOv'),inp=document.getElementById('psIn'),res=document.getElementById('psRes'),busy=false,changed=false,ac=null;
 function tk(){var e=document.querySelector('meta[name=csrf-token]');return e?e.content:''}
 function esc(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
 function beep(good){try{ac=ac||new (window.AudioContext||window.webkitAudioContext)();var o=ac.createOscillator(),g=ac.createGain();o.connect(g);g.connect(ac.destination);o.type=good?'sine':'square';o.frequency.value=good?1250:240;g.gain.value=good?.12:.22;o.start();o.stop(ac.currentTime+(good?.12:.7))}catch(e){}}
 function paint(j){
  if(j.rows){var b=document.getElementById('psLog');b.innerHTML=j.rows.length?j.rows.map(function(r){var L={ok:'Handed over',bad:'Stopped',warn:'Not sent'}[r.res]||r.res;return '<tr title="'+esc(r.msg)+'"><td>'+esc(r.t)+'</td><td><b>'+esc(r.inv)+'</b></td><td><span class="pp '+esc(r.res)+'">'+L+'</span></td><td>'+esc(r.by)+'</td></tr>'}).join(''):'<tr><td colspan="4" style="color:#5B6676;padding:12px">Nothing scanned yet.</td></tr>';
   document.getElementById('psOk').textContent=j.ok||0;document.getElementById('psBad').textContent=j.stopped||0;document.getElementById('psW').textContent=j.waiting||0}
  if(!j.result)return;
  var o=j.order,card=o?'<div class="ps-o"><span>Invoice</span><b>'+esc(o.invoice)+'</b><span>Customer</span><b>'+esc(o.name)+(o.phone?' · '+esc(o.phone):'')+'</b><span>Items</span><b>'+esc(o.items)+'</b><span>Total</span><b>৳'+esc(o.total)+'</b><span>Status</span><b>'+esc(o.status)+(o.tracking?' · '+esc(o.courier)+' '+esc(o.tracking):'')+'</b></div>':'';
  res.className=j.result;res.innerHTML='<div class="ps-big">'+(j.result==='ok'?'✓ ':j.result==='bad'?'✖ ':'⚠ ')+esc(j.title)+'</div><div class="ps-sub">'+esc(j.message)+'</div>'+card;
  beep(j.result==='ok');if(j.result==='ok')changed=true}
 function send(code){if(busy||!code)return;busy=true;res.className='';res.innerHTML='<div class="ps-big">Checking…</div>';
  fetch('/admin/packing-scan/scan',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':tk()},body:JSON.stringify({code:code})})
   .then(function(r){return r.json()}).then(function(j){busy=false;paint(j);inp.focus()})
   .catch(function(){busy=false;paint({result:'bad',title:'NO CONNECTION',message:'The scan was not saved. Check the internet and scan this parcel again. Keep the parcel until it shows green.'});inp.focus()})}
 document.getElementById('psF').addEventListener('submit',function(e){e.preventDefault();var v=inp.value.trim();inp.value='';send(v)});
 function open(){ov.classList.add('on');res.className='';res.innerHTML='<div class="ps-big">Ready to scan</div><div class="ps-sub" style="color:#5B6676">Green: give the parcel to the courier. Red: keep the parcel.</div>';
  fetch('/admin/packing-scan/today',{credentials:'same-origin',headers:{'Accept':'application/json'}}).then(function(r){return r.json()}).then(paint).catch(function(){});setTimeout(function(){inp.focus()},60)}
 function close(){ov.classList.remove('on');if(window.__psPage){location.href='/admin/orders?status=Shipment';return}if(changed){changed=false;location.reload()}}
 document.getElementById('psX').addEventListener('click',close);
 document.addEventListener('click',function(e){var b=e.target.closest&&e.target.closest('.ps-open');if(b){e.preventDefault();open()}});
 // keep the scan box ready: any click inside the window puts the cursor back
 ov.addEventListener('click',function(e){if(e.target!==inp&&!(e.target.closest&&e.target.closest('button')))inp.focus()});
 window.dfPackScan=open;
})();
</script>
