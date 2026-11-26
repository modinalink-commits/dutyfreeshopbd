@extends('layout')@section('title','Marine Supply')
@section('body')
<div style="margin-bottom:10px"><h1 style="margin:0">Marine Supply</h1><div style="font-size:12px;color:#6B7280">marinedutyfreebd.com: জাহাজ ও নাবিকদের জন্য B2B সরবরাহ। এই গ্রুপে মেরিনের সব কাজ থাকবে।</div></div>
@if(!$ready)<div class="card" style="background:#FEF3C7;border-color:#FDE68A;color:#713F12">মেরিনের টেবিল এখনো তৈরি হয়নি। ডেপ্লয় শেষ হলে পাতাটি আবার খুলুন।</div>
@else
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:10px;margin-bottom:12px">
<a class="card" href="/admin/marine/requests" style="margin:0;text-decoration:none;color:inherit"><div style="font-size:11px;font-weight:600;color:#6B7280;text-transform:uppercase">Pending access requests</div><div style="font-size:24px;font-weight:700;color:{{ ($n['pending'] ?? 0) ? '#C2410C' : '#0F172A' }}">{{ $n['pending'] ?? 0 }}</div><div style="font-size:12px;color:#6B7280">Approved {{ $n['approved'] ?? 0 }} · Rejected {{ $n['rejected'] ?? 0 }}</div></a>
<a class="card" href="/admin/marine/catalog?show=on" style="margin:0;text-decoration:none;color:inherit"><div style="font-size:11px;font-weight:600;color:#6B7280;text-transform:uppercase">Products on marine channel</div><div style="font-size:24px;font-weight:700">{{ $p['on'] }}</div><div style="font-size:12px;color:#6B7280">Public {{ $p['public'] }} · After login {{ $p['on'] - $p['public'] }}</div></a>
<a class="card" href="/admin/marine/catalog?show=on" style="margin:0;text-decoration:none;color:inherit"><div style="font-size:11px;font-weight:600;color:#6B7280;text-transform:uppercase">Without USD price</div><div style="font-size:24px;font-weight:700;color:{{ $p['noprice'] ? '#B45309' : '#0F172A' }}">{{ $p['noprice'] }}</div><div style="font-size:12px;color:#6B7280">চালু পণ্য, কিন্তু ডলার দাম নেই</div></a>
<a class="card" href="/admin/marine/settings" style="margin:0;text-decoration:none;color:inherit"><div style="font-size:11px;font-weight:600;color:#6B7280;text-transform:uppercase">Marine desk contact</div><div style="font-size:14px;font-weight:600;margin-top:6px">{{ $conf['marine_desk_email'] ?: 'ইমেইল দেওয়া হয়নি' }}</div><div style="font-size:12px;color:#6B7280">{{ $conf['marine_desk_phone'] ?: 'ফোন দেওয়া হয়নি' }}</div></a>
</div>
<div class="card"><b>Latest access requests</b>
<table style="width:100%;font-size:13px;border-collapse:collapse;margin-top:8px">
@forelse($last as $x)<tr style="border-top:1px solid #EEE"><td style="padding:7px 4px"><b>{{ $x->company ?: $x->name ?: '-' }}</b> <span style="font-size:11.5px;color:#6B7280">{{ ucfirst($x->kind) }}</span></td><td>{{ $x->vessel }} {{ $x->imo ? '· IMO '.$x->imo : '' }}</td><td>{{ $x->email }}</td><td>{{ ucfirst($x->status) }}</td><td style="text-align:right;color:#6B7280">{{ \Carbon\Carbon::parse($x->created_at)->timezone('Asia/Dhaka')->format('d M, h:i A') }}</td></tr>
@empty<tr><td style="padding:10px 4px;color:#6B7280">এখনো কোনো আবেদন আসেনি।</td></tr>@endforelse
</table></div>
<div class="card"><b>Marine site</b><div style="font-size:13px;line-height:1.8;margin-top:6px"><a href="{{ $site }}" target="_blank" rel="noopener">{{ $site }}</a> (পাবলিক সাইট) · <a href="https://search.google.com/search-console?resource_id=sc-domain:marinedutyfreebd.com" target="_blank" rel="noopener">Google Search Console</a></div>
<div style="font-size:12px;color:#6B7280;margin-top:6px">এখনো তৈরি হয়নি: জাহাজ ও পোর্ট কল, কোটেশন, অর্ডার, নাবিকের শপ, ক্রেডিট ও লাভের রিপোর্ট। তৈরি হলে এই গ্রুপেই যোগ হবে।</div></div>
@endif
@endsection
