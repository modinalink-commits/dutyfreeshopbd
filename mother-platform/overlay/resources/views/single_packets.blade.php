@extends('layout')@section('title','Single Packet')
@section('body')
<h1>Single Packet পণ্য</h1>
<p style="font-size:13px;color:#6B7280;margin:0 0 12px">প্রতিটি পণ্যের একটা "Single Packet …" যমজ থাকবে (Cigarette &amp; Vape → Single Packet)। ছবি একই, দাম ও খরচ কার্টুনের ১০ ভাগের ১ ভাগ, ডেলিভারি চার্জ সবসময় লাগবে। যমজ প্রথমে নিষ্ক্রিয় (আনপাবলিশড) থাকে; খুচরা প্যাকেট রিটার্ন রিসিভ হয়ে স্টকে ঢুকলে নিজে থেকে পাবলিশ হয়।</p>
@if($done)<div style="border:1px solid #16A34A;background:#F0FDF4;border-radius:8px;padding:10px 12px;margin-bottom:12px;font-size:13px">সম্পন্ন: পুরনো সরানো {{ $done['retired'] }}, নতুন যমজ {{ $done['created'] }}, আপডেট {{ $done['updated'] }}@if(!empty($done['errors'])), সমস্যা {{ count($done['errors']) }}@endif</div>@endif
@if(!$pv['ready'])<div style="border:1px solid #DC2626;border-radius:8px;padding:10px 12px;font-size:13px">ডাটাবেস ধাপ (migration) এখনও হয়নি।</div>@else
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:8px;margin-bottom:12px">
@foreach([['যমজ পাবে এমন পণ্য',$pv['eligible']],['যমজ আছে',$pv['have']],['নতুন তৈরি হবে',$pv['to_create']],['পুরনো Single Packet (সরবে)',$pv['old'].' (পাবলিশড '.$pv['old_published'].', অর্ডারের ইতিহাস আছে '.($pv['old_orders'] ?? 0).')'],['যমজ হবে যে ক্যাটাগরির',implode(', ', $pv['allowed_names'])],['নতুন পণ্যে অটো',$pv['auto']?'চালু':'বন্ধ']] as $c)
<div style="border:1px solid #E5E7EB;border-radius:8px;padding:10px 12px"><div style="font-size:11px;color:#6B7280">{{ $c[0] }}</div><div style="font-size:18px;font-weight:700;color:#0B1B3A">{{ $c[1] }}</div></div>
@endforeach</div>
<p style="font-size:12px;color:#6B7280">ঠিকানা: <b>{{ $pv['target_name'] ?? '—' }} → Single Packet</b></p>
<table class="no-export" data-no-export data-nogrid data-nofit data-nopg style="width:100%;border-collapse:collapse;font-size:13px;margin:8px 0 14px"><thead><tr style="text-align:left;color:#6B7280"><th>মূল পণ্য</th><th>যমজ নাম</th><th>SKU</th><th>দাম</th><th>খরচ</th></tr></thead><tbody>
@foreach($pv['sample'] as $s)<tr style="border-top:1px solid #E5E7EB"><td>{{ $s['main'] }}</td><td>{{ $s['twin'] }}</td><td>{{ $s['sku'] }}</td><td>{{ $s['price'] }}</td><td>{{ $s['cost'] }}</td></tr>@endforeach
</tbody></table>
@if($isAdmin && !$pv['auto'])
<form method="post" action="/admin/single-packets/run" style="border:1px solid #E5E7EB;border-radius:8px;padding:12px;max-width:520px">@csrf
<div style="font-size:13px;margin-bottom:8px">চালালে: পুরনো {{ $pv['old'] }}টা Single Packet পণ্য সাইট থেকে সরবে (অর্ডারের ইতিহাস থাকবে), {{ $pv['to_create'] }}টা নতুন যমজ নিষ্ক্রিয় অবস্থায় তৈরি হবে, এরপর নতুন পণ্যে যমজ অটো হবে।</div>
<input name="confirm" placeholder="YES লিখুন" autocomplete="off" style="border:1px solid #D1D5DB;border-radius:6px;padding:7px 9px;width:140px"> <button class="btn" type="submit">চালু করুন</button>
@error('confirm')<div style="color:#DC2626;font-size:12px;margin-top:6px">{{ $message }}</div>@enderror</form>
@endif
@endif
@endsection
