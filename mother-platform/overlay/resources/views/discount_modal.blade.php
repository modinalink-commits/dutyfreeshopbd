@once
<style>
.ddp{cursor:pointer}.ddp:hover{filter:brightness(.95);box-shadow:0 0 0 2px rgba(15,23,42,.12)}
#ddov{position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:100000;display:none;align-items:center;justify-content:center;padding:16px}
#ddov.on{display:flex}
#ddbx{background:#fff;border-radius:14px;box-shadow:0 20px 60px rgba(0,0,0,.35);width:min(420px,100%);overflow:hidden;font-family:inherit}
#ddbx .dh{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px 16px;border-bottom:1px solid #E5E7EB}
#ddbx .dh b{font-size:15px}#ddbx .dh button{border:0;background:#F3F4F6;border-radius:8px;width:28px;height:28px;font-size:16px;cursor:pointer}
#ddbx .dr{display:flex;gap:10px;padding:9px 16px;border-bottom:1px solid #F1F5F9;font-size:13px;line-height:1.35}
#ddbx .dr span{flex:0 0 120px;color:#6B7280;font-weight:600}#ddbx .dr div{flex:1;color:#0B1220;font-weight:600;word-break:break-word}
#ddbx .dn{padding:8px 16px 12px;font-size:11px;color:#6B7280}
</style>
<div id="ddov" role="dialog" aria-modal="true"><div id="ddbx"></div></div>
<script>
(function(){
var ov=document.getElementById('ddov'),bx=document.getElementById('ddbx');if(!ov||!bx)return;
function row(k,v){if(v===null||v===undefined||v==='')return null;var r=document.createElement('div');r.className='dr';var a=document.createElement('span');a.textContent=k;var b=document.createElement('div');b.textContent=v;r.appendChild(a);r.appendChild(b);return r}
function fmt(n){return '৳'+Number(n).toLocaleString('en-US',{maximumFractionDigits:2})}
function open(d,el){
bx.textContent='';
var h=document.createElement('div');h.className='dh';var t=document.createElement('b');t.textContent='Discount details';var c=document.createElement('button');c.type='button';c.textContent='×';c.setAttribute('aria-label','Close');c.onclick=close;h.appendChild(t);h.appendChild(c);bx.appendChild(h);
var cs=el?getComputedStyle(el):null;
var nm=row('Discount',d.name);if(nm&&cs){var v=nm.lastChild;v.style.color=cs.color}
var rows=[nm,
row('Type',d.kind==='Percentage'&&d.pct!==null&&d.pct!==undefined?'Percentage ('+d.pct+'%)':(d.kind||null)),
row('Amount',d.amount!==null&&d.amount!==undefined?'-'+fmt(d.amount):'Not recorded (older order)'),
row('Coming from (area)',d.area),
row('Given for',d.why||'Rule not recorded'),
row('Code',d.code),
row('Given by',d.by),
row('When',d.at?new Date(d.at).toLocaleString('en-GB',{day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'}):null)];
rows.forEach(function(r){if(r)bx.appendChild(r)});
if(d.legacy){var n=document.createElement('div');n.className='dn';n.textContent='This order was placed before per-discount details were saved, so only the names and the saved source are shown.';bx.appendChild(n)}
ov.className='on';}
function close(){ov.className=''}
document.addEventListener('click',function(e){
var el=e.target.closest?e.target.closest('.ddp'):null;
if(el){e.preventDefault();e.stopPropagation();var d;try{d=JSON.parse(el.getAttribute('data-d')||'{}')}catch(x){d=null}if(d)open(d,el);return}
if(e.target===ov)close();
},true);
document.addEventListener('keydown',function(e){if(e.key==='Escape')close()});
})();
</script>
@endonce
