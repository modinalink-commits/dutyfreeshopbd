@extends('layout')@section('title','Staff Idle Check')
@section('body')
<style>.sid .card{padding:12px 14px;margin-bottom:10px}.sid h2{font-size:14px;margin:0 0 8px}.sid table{width:100%;border-collapse:collapse;font-size:12.5px}.sid th{font-size:11px;color:#6B7280;text-align:left;padding:6px 8px;border-bottom:1px solid #E5E7EB;white-space:nowrap}.sid td{padding:6px 8px;border-bottom:1px solid #EEF1F4;vertical-align:top}.sid .pl{display:inline-block;padding:1px 8px;border-radius:99px;font-size:11px;font-weight:700;border:1px solid}.sid .f{display:flex;gap:10px;flex-wrap:wrap;align-items:end}.sid label{font-size:12px;color:#374151}.sid input[type=number]{width:80px}.sid .rv{display:flex;gap:4px;flex-wrap:wrap}.sid .rv input{min-width:120px;flex:1}</style>
<div class="sid">
<h1 style="font-size:18px;margin:0 0 4px">Staff Idle Check</h1>
<p style="color:#6B7280;margin:0 0 10px;font-size:12.5px">কাজ অপেক্ষায় থাকা অবস্থায় কোনো স্টাফ নির্ধারিত সময় কোনো কাজ না করলে তার পর্দায় নোটিশ আসে; কারণ জমা না দিলে ড্যাশবোর্ড খোলে না। কাজ বলতে আসল কাজ (সেভ, উত্তর, স্ট্যাটাস বদল) ধরা হয়, শুধু পাতা খোলা নয়। মালিক ও অ্যাডমিন এর বাইরে।</p>
@if(session('ok'))<div class="card" style="background:#F0FAF3;border-color:#BFE3CC;color:#14683A;font-weight:600">{{ session('ok') }}</div>@endif
@if(!$ready)<div class="card" style="color:#B45309">টেবিল এখনো তৈরি হয়নি (ডিপ্লয়ের মাইগ্রেশন চলার পর আসবে)।</div>@endif
<form class="card" method="post" action="/admin/staff-idle/settings">@csrf<h2>সেটিংস</h2>
<div class="f">
<label><input type="checkbox" name="enabled" value="1" @checked($s('enabled','0')==='1')> চালু</label>
<label>কত মিনিটে নোটিশ<br><input type="number" name="limit" min="10" max="240" value="{{ $s('limit','30') }}"></label>
<label>ডিউটি শুরু (ঘণ্টা, ০–২৩)<br><input type="number" name="from" min="0" max="23" value="{{ $s('from','10') }}"></label>
<label>ডিউটি শেষ (ঘণ্টা, ০–২৩)<br><input type="number" name="to" min="0" max="23" value="{{ $s('to','22') }}"></label>
<label><input type="checkbox" name="need_waiting" value="1" @checked($s('need_waiting','1')==='1')> শুধু কাজ অপেক্ষায় থাকলে নোটিশ</label>
</div>
<div style="margin-top:8px;font-size:12px;color:#374151">কোন রোলের ওপর চলবে (কিছু না টিক দিলে সব স্টাফ): @php($sel = array_filter(explode(',', $s('roles',''))))
@foreach($roles as $rl)<label style="margin-right:10px"><input type="checkbox" name="roles[]" value="{{ $rl }}" @checked(in_array($rl, $sel, true))> {{ $rl }}</label>@endforeach</div>
<small style="color:#6B7280;display:block;margin-top:6px">সতর্কবার্তা আসে নোটিশের ৫ মিনিট আগে। স্টাফ "বিরতি" বোতাম চাপলে (নামাজ, খাবার, প্যাকিং, ফোন) সেই সময় গোনা হয় না, তবে প্রতিটা বিরতি রেকর্ড থাকে।</small>
<button class="btn" type="submit" style="margin-top:8px">Save</button></form>
@if(count($sum))<div class="card"><h2>গত ৩০ দিন</h2><table><tr><th>স্টাফ</th><th>আজ</th><th>মোট নোটিশ</th><th>মোট অলস মিনিট</th></tr>
@foreach($sum as $x)<tr><td><b>{{ $x->user_name }}</b></td><td>{{ $x->today }}</td><td>{{ $x->n }}</td><td>{{ $x->m }}</td></tr>@endforeach</table></div>@endif
<div class="card"><h2>টিকিট</h2>
@if(!count($rows))<div style="color:#6B7280">এখনো কোনো নোটিশ নেই।</div>@else
<div style="overflow-x:auto"><table><tr><th>সময়</th><th>স্টাফ</th><th>অলস</th><th>অপেক্ষায় ছিল</th><th>কারণ</th><th>অবস্থা</th><th></th></tr>
@foreach($rows as $x)<tr>
<td style="white-space:nowrap">{{ \Carbon\Carbon::parse($x->created_at)->timezone('Asia/Dhaka')->format('d M, h:i A') }}</td>
<td><b>{{ $x->user_name }}</b></td><td>{{ $x->idle_minutes }} মি.</td>
<td>মেসেজ {{ $x->waiting_chats }} · অর্ডার {{ $x->waiting_orders }}</td>
<td style="max-width:320px;white-space:pre-wrap">{{ $x->reason ?: '—' }}@if($x->admin_note)<br><small style="color:#6B7280">নোট: {{ $x->admin_note }}</small>@endif</td>
<td>@if($x->status==='open')<span class="pl" style="color:#B42318;border-color:#F6C4C0;background:#FEF3F2">কারণ দেয়নি</span>@elseif($x->status==='submitted')<span class="pl" style="color:#B45309;border-color:#FED7AA;background:#FFF7ED">দেখা বাকি</span>@else<span class="pl" style="color:{{ $x->verdict==='rejected' ? '#B42318' : '#14683A' }};border-color:#E5E7EB;background:#F9FAFB">{{ $x->verdict==='rejected' ? 'অগ্রহণযোগ্য' : 'গ্রহণযোগ্য' }}</span><br><small style="color:#6B7280">{{ $x->reviewed_by }}</small>@endif</td>
<td>@if($x->status==='submitted')<form method="post" action="/admin/staff-idle/{{ $x->id }}/review" class="rv">@csrf<input name="note" placeholder="নোট (ঐচ্ছিক)" maxlength="400"><button class="btn" name="verdict" value="accepted">গ্রহণ</button><button class="btn g" name="verdict" value="rejected">অগ্রহণ</button></form>@endif</td>
</tr>@endforeach</table></div>@endif</div>
</div>
@endsection
