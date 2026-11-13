@extends('layout')@section('title','Clearance')
@section('body')
<style>.clr .card{padding:12px 14px}.clr h2{font-size:14px;margin:0 0 8px}.clr table{width:100%;border-collapse:collapse;font-size:12.5px}.clr th{font-size:11px;color:#6B7280;text-align:left;padding:6px 8px;border-bottom:1px solid #E5E7EB;white-space:nowrap}.clr td{padding:6px 8px;border-bottom:1px solid #EEF1F4;vertical-align:top}.clr td.n,.clr th.n{text-align:right;font-variant-numeric:tabular-nums}.clr .pl{display:inline-block;padding:1px 8px;border-radius:99px;font-size:11px;font-weight:700;border:1px solid}.clr .f{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:8px;align-items:end}.clr label{display:block;font-size:11px;color:#6B7280;margin-bottom:2px}.clr input,.clr select{width:100%}.clr .wr{background:#FFF8E8;border:1px solid #F4D79B;color:#7A4A06;border-radius:8px;padding:8px 10px;font-size:12.5px;margin-top:8px}.clr .er{background:#FEF3F2;border-color:#F6C4C0;color:#B42318}.clr .hit{padding:5px 8px;cursor:pointer;border-bottom:1px solid #EEF1F4;font-size:12.5px}.clr .hit:hover{background:#F6F7F9}</style>
<div class="clr">
<h1 style="margin:0">Clearance</h1>
<p style="color:#6B7280;font-size:13px;margin:2px 0 10px">যে প্রোডাক্ট নষ্ট হওয়ার পথে (মেয়াদ শেষের পথে, ড্যামেজ, পুরনো স্টক) সেটা এখানে <b>কেনা দাম পর্যন্ত</b> কমিয়ে বিক্রি করা যায়। কেনা দামের নিচে দেওয়া যাবে না। শুধু Super Admin চালু/অনুমোদন করতে পারেন। স্টক শেষ হলে, নতুন স্টক এলে বা শেষ তারিখ পার হলে স্বাভাবিক দাম নিজে ফিরে আসে।</p>
@if(session('ok'))<div class="card" style="border-color:#BFE3CC;background:#F0FAF3;color:#14683A;font-weight:600">{{ session('ok') }}</div>@endif
@if(session('err'))<div class="card" style="border-color:#F6C4C0;background:#FEF3F2;color:#B42318;font-weight:600">{{ session('err') }}</div>@endif
@if($errors->any())<div class="card" style="border-color:#F6C4C0;background:#FEF3F2;color:#B42318;font-weight:600">{{ $errors->first() }}</div>@endif

<div class="card"><h2>{{ $super ? 'নতুন Clearance চালু করুন' : 'Clearance-এর অনুরোধ পাঠান' }}</h2>
<form method="post" action="/admin/clearance" id="clF">@csrf
<input type="hidden" name="product_id" id="clP" value="{{ $pre->id ?? old('product_id') }}">
<div class="f">
<div style="grid-column:span 2;position:relative"><label>প্রোডাক্ট (নাম / SKU / ID লিখে বাছাই করুন)</label><input type="text" id="clQ" autocomplete="off" placeholder="যেমন: Davidoff বা DF-1194" value="{{ $pre->name ?? '' }}"><div id="clH" style="position:absolute;left:0;right:0;top:100%;background:#fff;border:1px solid #E5E7EB;border-radius:8px;z-index:5;display:none;max-height:260px;overflow:auto"></div></div>
<div><label>কারণ</label><select name="reason">@foreach(\App\Support\Clearance::REASONS as $k=>$t)<option value="{{ $k }}">{{ $t }}</option>@endforeach</select></div>
<div><label>Clearance দাম (৳)</label><input type="number" step="1" min="1" name="price" id="clPr" required value="{{ old('price') }}"></div>
<div><label>শেষ তারিখ (ঐচ্ছিক)</label><input type="date" name="ends_on" min="{{ now('Asia/Dhaka')->toDateString() }}"></div>
<div style="grid-column:span 2"><label>নোট (ঐচ্ছিক)</label><input type="text" name="note" maxlength="300" placeholder="যেমন: মেয়াদ ডিসেম্বর ২০২৬"></div>
</div>
<div id="clI" class="wr" style="display:none"></div>
<label style="display:flex;gap:8px;align-items:flex-start;margin-top:8px;font-size:12.5px;color:#0B1220"><input type="checkbox" name="ack" value="1" style="width:auto;margin-top:2px"> <span>আমি বুঝেছি: কেনা দামে বা তার কাছাকাছি বিক্রি করলে প্রতি অর্ডারে <b>ডেলিভারি চার্জ ও প্যাকেজিং খরচ (প্রায় ৳{{ number_format($pack) }}) কোম্পানির লস</b> হবে।</span></label>
<div style="margin-top:10px"><button class="btn" id="clB">{{ $super ? 'Clearance চালু করুন' : 'অনুরোধ পাঠান' }}</button></div>
</form></div>

<div class="card" style="overflow-x:auto"><h2>Clearance তালিকা</h2>
<table><thead><tr><th>প্রোডাক্ট</th><th>কারণ</th><th class="n">কেনা দাম</th><th class="n">স্বাভাবিক দাম</th><th class="n">Clearance দাম</th><th class="n">স্টক</th><th class="n">বিক্রি (পিস)</th><th class="n">বিক্রয়মূল্য</th><th class="n">লস (কেনা দামের তুলনায়)</th><th>শেষ তারিখ</th><th>অবস্থা</th><th></th></tr></thead><tbody>
@forelse($rows as $x)
@php($col = ['pending'=>['#9A5B0B','#FFF8E8','#F4D79B'],'active'=>['#166534','#F0FBF4','#BFE6CE'],'ended'=>['#4B5563','#F3F4F6','#D5D9DE'],'rejected'=>['#B42318','#FEF3F2','#F6C4C0']][$x->status] ?? ['#4B5563','#F3F4F6','#D5D9DE'])
<tr><td><b>{{ $x->name ?? ('#'.$x->product_id) }}</b><br><span style="color:#6B7280">{{ $x->sku }} · {{ $x->requested_by }}{{ $x->note ? ' · '.$x->note : '' }}</span></td>
<td>{{ \App\Support\Clearance::REASONS[$x->reason] ?? $x->reason }}</td>
<td class="n">{{ (float)$x->cost_at_start > 0 ? number_format($x->cost_at_start, 2) : '—' }}</td><td class="n">{{ number_format($x->normal_price, 2) }}</td><td class="n"><b>{{ number_format($x->price, 2) }}</b></td>
<td class="n">{{ $x->stock ?? '—' }}</td><td class="n">{{ $x->st['qty'] }}</td><td class="n">{{ number_format($x->st['rev']) }}</td><td class="n" style="color:{{ $x->st['loss'] > 0 ? '#B91C1C' : 'inherit' }}">{{ number_format($x->st['loss']) }}</td>
<td>{{ $x->ends_on ? \Carbon\Carbon::parse($x->ends_on)->format('d M Y') : '—' }}</td>
<td><span class="pl" style="color:{{ $col[0] }};background:{{ $col[1] }};border-color:{{ $col[2] }}">{{ ['pending'=>'অনুমোদনের অপেক্ষায়','active'=>'চালু','ended'=>'শেষ','rejected'=>'বাতিল'][$x->status] ?? $x->status }}</span>@if($x->ended_reason)<br><span style="color:#6B7280;font-size:11px">{{ $x->ended_reason }}</span>@endif</td>
<td style="white-space:nowrap">@if($super && $x->status==='pending')<form method="post" action="/admin/clearance/{{ $x->id }}" style="display:inline">@csrf<button class="btn" name="action" value="approve">Approve</button> <button class="btn g" name="action" value="reject">Reject</button></form>@elseif($super && $x->status==='active')<form method="post" action="/admin/clearance/{{ $x->id }}" style="display:inline">@csrf<button class="btn g" name="action" value="end">শেষ করুন</button></form>@endif</td></tr>
@empty<tr><td colspan="12" style="color:#6B7280">এখনো কোনো Clearance নেই।</td></tr>@endforelse
</tbody></table></div>
</div>
<script>
(function(){var Q=document.getElementById('clQ'),H=document.getElementById('clH'),P=document.getElementById('clP'),PR=document.getElementById('clPr'),I=document.getElementById('clI'),B=document.getElementById('clB'),cur={!! json_encode($pre ? ['id'=>$pre->id,'name'=>$pre->name,'price'=>(float)$pre->price,'cost'=>round((float)$pre->cost,2),'stock'=>$pre->stock] : null, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!},t=0,PACK={{ (float)$pack }};
function tk(n){return '৳'+Number(n).toLocaleString('en-US',{maximumFractionDigits:2})}
function info(){if(!cur){I.style.display='none';return}var v=parseFloat(PR.value),bad=false,m='<b>'+cur.name.replace(/</g,'&lt;')+'</b> · এখনকার দাম '+tk(cur.price)+' · কেনা দাম '+(cur.cost>0?tk(cur.cost):'সিস্টেমে নেই')+' · স্টক '+(cur.stock==null?'—':cur.stock);
if(cur.cost<=0){bad=true;m+='<br>⚠ কেনা দাম সিস্টেমে নেই, তাই Clearance দেওয়া যাবে না।'}
else if(!isNaN(v)){if(v<cur.cost){bad=true;m+='<br>⚠ এই দাম কেনা দামের নিচে। সর্বনিম্ন '+tk(Math.ceil(cur.cost))+' দেওয়া যাবে।'}else if(v>=cur.price){bad=true;m+='<br>⚠ Clearance দাম এখনকার দামের চেয়ে কম হতে হবে।'}else{var g=v-cur.cost;m+='<br>প্রতি পিসে প্রোডাক্টের লাভ '+tk(g)+(g<PACK?' → <b>আপনি কি লসে বিক্রি করতে চাচ্ছেন?</b> ডেলিভারি চার্জ ও প্যাকেজিং খরচ ধরলে প্রতি অর্ডারে লস হবে।':'')}}
I.className='wr'+(bad?' er':'');I.innerHTML=m;I.style.display='block';B.disabled=bad}
function pick(r){cur=r;P.value=r.id;Q.value=r.name;H.style.display='none';if(!PR.value&&r.cost>0)PR.value=Math.ceil(r.cost);info()}
Q.addEventListener('input',function(){clearTimeout(t);P.value='';cur=null;info();var q=Q.value.trim();if(q.length<2){H.style.display='none';return}t=setTimeout(function(){fetch('/admin/clearance/find?q='+encodeURIComponent(q),{headers:{Accept:'application/json'},credentials:'same-origin'}).then(function(r){return r.json()}).then(function(d){H.innerHTML='';(d.rows||[]).forEach(function(r){var e=document.createElement('div');e.className='hit';e.textContent=r.name+' · '+(r.sku||r.id)+' · দাম '+r.price+' · কেনা '+(r.cost||'—')+(r.on?' · (Clearance চালু)':'');e.onclick=function(){pick(r)};H.appendChild(e)});H.style.display=(d.rows||[]).length?'block':'none'}).catch(function(){})},250)});
PR.addEventListener('input',info);document.getElementById('clF').addEventListener('submit',function(e){if(!P.value){e.preventDefault();Q.focus();I.className='wr er';I.textContent='আগে তালিকা থেকে একটা প্রোডাক্ট বাছাই করুন।';I.style.display='block'}});
if(cur){if(!PR.value&&cur.cost>0)PR.value=Math.ceil(cur.cost);info()}})();
</script>
@endsection
