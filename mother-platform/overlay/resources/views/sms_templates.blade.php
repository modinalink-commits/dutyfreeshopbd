@extends('layout')@section('title','এসএমএস টেমপ্লেট')
@section('body')
<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:12px"><div style="flex:1;min-width:240px"><h1 style="margin:0">এসএমএস টেমপ্লেট</h1><span style="font-size:13px;color:#6B7280">সিস্টেম থেকে স্বয়ংক্রিয়ভাবে যাওয়া সব এসএমএস। প্রিভিউতে নমুনা তথ্য বসিয়ে অক্ষর, খণ্ড ও খরচ গোনা হয়েছে।</span></div>
<a href="/admin/sms" style="font-size:13px;font-weight:600;color:#F95505">← SMS Setup</a></div>
<div class="card" style="font-size:13px;line-height:1.7;color:#374151">
<b>গণনার নিয়ম:</b> বাংলা লেখা থাকলে এক খণ্ডে ৭০ অক্ষর, তার বেশি হলে প্রতি খণ্ডে ৬৭ অক্ষর। শুধু ইংরেজি হলে ১৬০ / ১৫৩ অক্ষর। গ্রাহকের নাম, টাকার অঙ্ক বা লিংক লম্বা হলে খণ্ড বাড়তে পারে।<br>
<b>প্রতি খণ্ডের রেট:</b> ৳{{ rtrim(rtrim(number_format($unit, 2), '0'), '.') }} <a href="/admin/sms-marketing/settings" style="color:#F95505">(বদলান)</a> · <b>লেখার নিয়ম:</b> বাক্য বাংলায়; DutyFreeBD, অর্ডার নম্বর, ওটিপি, ফোন নম্বর ও লিংক যেমন আছে তেমন থাকবে। ইংরেজি অক্ষরে বাংলা লিখবেন না।
</div>
<div class="card" style="overflow-x:auto;padding:0"><table style="font-size:13px"><thead><tr><th>ধরন</th><th>টেমপ্লেট</th><th style="min-width:320px">প্রিভিউ (নমুনা তথ্যসহ)</th><th style="text-align:right">অক্ষর</th><th style="text-align:right">খণ্ড</th><th style="text-align:right">আনুমানিক খরচ</th><th>কখন যায়</th></tr></thead><tbody>
@foreach($rows as $r)<tr style="vertical-align:top"><td><span class="pill">{{ $r['group'] }}</span></td>
<td><b>{{ $r['name'] }}</b><div style="font-size:12px;color:#6B7280;max-width:240px">{{ $r['desc'] }}</div>@if($r['edit'])<a href="{{ $r['edit'] }}" style="font-size:12px;color:#F95505">সেটিংস খুলুন</a>@endif</td>
<td><div style="white-space:pre-wrap;background:#F9FAFB;border:1px solid #E5E7EB;border-radius:8px;padding:8px 10px;line-height:1.55">{{ $r['text'] }}</div></td>
<td style="text-align:right">{{ $r['chars'] }}</td>
<td style="text-align:right"><span class="pill {{ $r['parts'] <= 1 ? 'ok' : ($r['parts'] >= 3 ? 'red' : '') }}">{{ $r['parts'] }}</span></td>
<td style="text-align:right">৳{{ number_format($r['cost'], 2) }}</td><td>{{ $r['when'] }}</td></tr>@endforeach
</tbody></table></div>
<div class="card" style="max-width:720px"><b>নিজে লিখে গুনে দেখুন</b><p style="font-size:12px;color:#6B7280;margin:4px 0 8px">নতুন লেখা এখানে বসালে অক্ষর, খণ্ড ও খরচ সাথে সাথে দেখাবে। এখান থেকে কিছু পাঠানো বা সেভ হয় না।</p>
<textarea id="stx" rows="3" placeholder="এসএমএসের লেখা এখানে লিখুন"></textarea>
<div id="sto" style="font-size:13px;margin-top:6px;color:#374151">অক্ষর 0 · খণ্ড 0 · খরচ ৳0.00</div></div>
<div class="card" style="font-size:13px;line-height:1.7;color:#374151"><b>যেগুলো স্টাফ নিজে লেখেন (এখানে নেই):</b> পেমেন্ট চাওয়ার এসএমএস, SMS Marketing ক্যাম্পেইন, সাপ্লায়ারকে হাতে লেখা এসএমএস। এগুলোও বাংলায় লিখতে হবে; লেখার পর্দায় অক্ষর গোনা দেখে নিন।</div>
<script>(function(){var u={{ json_encode($unit) }},t=document.getElementById('stx'),o=document.getElementById('sto');function c(){var s=t.value,uni=/[^\x00-\x7F]/.test(s),n=s.length,p=n===0?0:(n<=(uni?70:160)?1:Math.ceil(n/(uni?67:153)));o.textContent='অক্ষর '+n+' · খণ্ড '+p+' · খরচ ৳'+(p*u).toFixed(2)+(n?(uni?' · বাংলা গণনা':' · ইংরেজি গণনা'):'');}t.addEventListener('input',c);})();</script>
@endsection
