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
<div class="card"><div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap"><h2 style="margin:0">মাসিক রেকর্ড: কে কতবার কারণ দর্শিয়েছেন</h2>
<form method="get" action="/admin/staff-idle" style="margin:0"><select name="m" onchange="this.form.submit()">@foreach($months as $k => $lbl)<option value="{{ $k }}" @selected($k===$m)>{{ $lbl }}</option>@endforeach</select></form></div>
<p style="color:#6B7280;font-size:12px;margin:6px 0 8px">এই মাসে মোট {{ $total }} বার। এগুলোর জন্য কোনো অ্যালার্ট বা নোটিফিকেশন যায় না। বিস্তারিত দেখতে নামের সারিতে চাপুন।</p>
@if(!count($by))<div style="color:#6B7280">এই মাসে কোনো রেকর্ড নেই।</div>@else
@foreach($by as $x)<details style="border:1px solid #E5E7EB;border-radius:8px;margin-bottom:6px">
<summary style="cursor:pointer;padding:8px 10px;display:flex;gap:14px;flex-wrap:wrap;align-items:center;font-size:13px"><b style="min-width:120px">{{ $x->name ?: '—' }}</b><span>কারণ দর্শানো: <b>{{ $x->n }}</b> বার</span><span style="color:#6B7280">মোট অলস {{ $x->min }} মিনিট</span>@if($x->open)<span class="pl" style="color:#B42318;border-color:#F6C4C0;background:#FEF3F2">{{ $x->open }}টির কারণ এখনো দেয়নি</span>@endif<span style="margin-left:auto;color:#F95505;font-size:12px;font-weight:600">বিস্তারিত</span></summary>
<div style="overflow-x:auto;padding:0 10px 8px"><table><tr><th>সময়</th><th>অলস</th><th>অপেক্ষায় ছিল</th><th>কারণ</th></tr>
@foreach($x->rows as $n)<tr><td style="white-space:nowrap">{{ \Carbon\Carbon::parse($n->created_at)->timezone('Asia/Dhaka')->format('d M, h:i A') }}</td><td>{{ $n->idle_minutes }} মি.</td><td>মেসেজ {{ $n->waiting_chats }} · অর্ডার {{ $n->waiting_orders }}</td><td style="max-width:420px;white-space:pre-wrap">{{ $n->reason ?: ($n->status==='open' ? 'কারণ দেয়নি' : '—') }}</td></tr>@endforeach</table></div></details>@endforeach @endif</div>
</div>
@endsection
