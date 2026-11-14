@extends('layout')@section('title','Daily Purchase List')
@section('body')
<style>.dpl .card{padding:12px 14px;margin-bottom:10px}.dpl h2{font-size:14px;margin:0 0 8px}.dpl table{width:100%;border-collapse:collapse;font-size:12.5px}.dpl th{font-size:11px;color:#6B7280;text-align:left;padding:6px 8px;border-bottom:1px solid #E5E7EB;white-space:nowrap}.dpl td{padding:6px 8px;border-bottom:1px solid #EEF1F4}.dpl .n{text-align:right;font-variant-numeric:tabular-nums}.dpl .kp{display:flex;gap:10px;flex-wrap:wrap}.dpl .kp div{border:1px solid #E5E7EB;border-radius:10px;padding:8px 12px;min-width:120px;background:#fff}.dpl .kp b{display:block;font-size:18px}.dpl .kp small{color:#6B7280}.dpl .f{display:flex;gap:10px;flex-wrap:wrap;align-items:end}.dpl label{font-size:12px;color:#374151}</style>
<div class="dpl">
<div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:8px"><div><h1 style="font-size:18px;margin:0">Daily Purchase List</h1><small style="color:#6B7280">কোন পণ্য কিনতে হবে আর কেমন বিক্রি হচ্ছে। প্রতিদিন {{ $s('send_at','12:00') }}-এ WhatsApp-এ PDF লিঙ্কসহ যায়।</small></div>
<a class="btn" href="/admin/purchase/daily-list/pdf" target="_blank" rel="noopener">আজকের PDF খুলুন</a></div>
@if(session('ok'))<div class="card" style="background:#F0FAF3;border-color:#BFE3CC;color:#14683A;font-weight:600">{{ session('ok') }}</div>@endif
@if(session('err'))<div class="card" style="background:#FEF3F2;border-color:#F6C4C0;color:#B42318;font-weight:600">{{ session('err') }}</div>@endif
<div class="kp" style="margin-bottom:10px"><div><b>{{ $d['urgent_total'] }}</b><small>এখনই কিনতে হবে</small></div><div><b style="color:#B42318">{{ $d['out'] }}</b><small>স্টক একদম শেষ</small></div><div><b>{{ $d['cover'] }} দিন</b><small>এর কম স্টক থাকলে তালিকায়</small></div></div>
@if($admin)
<div class="card"><h2>WhatsApp-এ পাঠানো</h2>
<form method="post" action="/admin/purchase/daily-list/settings">@csrf<div class="f">
<label><input type="checkbox" name="enabled" value="1" @checked($s('enabled','1')==='1')> প্রতিদিন পাঠানো চালু</label>
<label>সময় (২৪ ঘণ্টা)<br><input name="send_at" value="{{ $s('send_at','12:00') }}" style="width:80px"></label>
<label>কত দিনের কম স্টক<br><input type="number" name="cover_days" min="3" max="30" value="{{ $s('cover_days','7') }}" style="width:80px"></label>
<label style="flex:1;min-width:240px">কাদের কাছে যাবে (প্রতি লাইনে: নাম|নম্বর)<br><textarea name="to" rows="3" style="width:100%">{{ $s('to',"Mamun|01824522800\nFaruk|01884808080") }}</textarea></label>
</div><button class="btn" type="submit" style="margin-top:8px">Save</button></form>
<div style="border-top:1px solid #E5E7EB;margin-top:10px;padding-top:8px">Template <code>{{ $s('tpl','daily_purchase_list') }}</code> · status: <b>{{ $tplStatus }}</b>
<pre style="white-space:pre-wrap;font-family:inherit;font-size:12.5px;background:#F8FAFC;border:1px solid #E5E7EB;border-radius:8px;padding:8px;margin:8px 0">{{ $body }}

[ PDF খুলুন ]</pre>
<div style="display:flex;gap:8px;flex-wrap:wrap"><form method="post" action="/admin/purchase/daily-list/submit">@csrf<button class="btn" type="submit">Submit to Meta</button></form>
<form method="post" action="/admin/purchase/daily-list/sync">@csrf<button class="btn g" type="submit">Refresh status</button></form>
<form method="post" action="/admin/purchase/daily-list/send" style="display:flex;gap:8px">@csrf<input name="phone" placeholder="01XXXXXXXXX" style="max-width:170px"><button class="btn g" type="submit">এই নম্বরে টেস্ট</button></form>
<form method="post" action="/admin/purchase/daily-list/send">@csrf<button class="btn g" type="submit">এখনই সবাইকে পাঠান</button></form></div>
<small style="color:#6B7280;display:block;margin-top:6px">Meta টেমপ্লেট APPROVED করার পর থেকে পাঠানো শুরু হয়। PDF-এর লিঙ্ক শুধু বার্তার বাটন দিয়ে খোলে এবং চার দিন পর বন্ধ হয়ে যায়।</small></div></div>
@endif
<div class="card"><h2>এখনই কিনতে হবে</h2>@if(!$d['urgent'])<div style="color:#6B7280">আজ জরুরি কিছু নেই।</div>@else
<div style="overflow-x:auto"><table><tr><th>#</th><th>পণ্য</th><th class="n">স্টক</th><th class="n">৭ দিনে বিক্রি</th><th class="n">৩০ দিনে বিক্রি</th><th class="n">কত দিন চলবে</th><th class="n">কিনতে হবে</th><th class="n">শেষ ক্রয়মূল্য</th><th>সাপ্লায়ার</th></tr>
@foreach($d['urgent'] as $i => $u)<tr @if($u['stock']<=0) style="background:#FEF3F2" @endif><td>{{ $i+1 }}</td><td>{{ $u['name'] }}</td><td class="n">{{ $u['stock'] }}</td><td class="n">{{ $u['q7'] }}</td><td class="n">{{ $u['q30'] }}</td><td class="n">{{ $u['stock']<=0 ? 'শেষ' : number_format($u['left'],1) }}</td><td class="n"><b>{{ $u['buy'] }}</b></td><td class="n">{{ $u['cost']!==null ? number_format($u['cost']) : '—' }}</td><td>{{ $u['sup'] ?: '—' }}</td></tr>@endforeach</table></div>@endif</div>
<div class="card"><h2>গত ৭ দিনের সেরা বিক্রি</h2><div style="overflow-x:auto"><table><tr><th>#</th><th>পণ্য</th><th class="n">৭ দিন</th><th class="n">৩০ দিন</th><th class="n">স্টক</th></tr>
@foreach($d['top'] as $i => $t)<tr><td>{{ $i+1 }}</td><td>{{ $t['name'] }}</td><td class="n">{{ $t['q7'] }}</td><td class="n">{{ $t['q30'] }}</td><td class="n">{{ $t['stock'] }}</td></tr>@endforeach</table></div></div>
<div class="card"><h2>কিনবেন না: স্টকে আছে কিন্তু ৩০ দিনে বিক্রি নেই</h2><div style="overflow-x:auto"><table><tr><th>#</th><th>পণ্য</th><th class="n">স্টক</th></tr>
@foreach($d['dead'] as $i => $t)<tr><td>{{ $i+1 }}</td><td>{{ $t['name'] }}</td><td class="n">{{ $t['stock'] }}</td></tr>@endforeach</table></div></div>
</div>
@endsection
