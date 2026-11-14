@php $n = fn($v) => number_format((float)$v); @endphp
<!doctype html><html lang="bn"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light"><title>{{ $subject }}</title></head>
<body style="margin:0;padding:0;background:#F3F4F6;font-family:'Hind Siliguri','Noto Sans Bengali',Arial,Helvetica,sans-serif;color:#0B1B33">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F3F4F6"><tr><td align="center" style="padding:24px 12px">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#FFFFFF;border:1px solid #E5E7EB;border-radius:14px;overflow:hidden">
<tr><td style="padding:22px 28px 18px;border-bottom:3px solid #F95505"><a href="{{ $base }}/" style="text-decoration:none"><img src="{{ $logo }}" alt="DutyFree BD" width="158" height="36" style="width:158px;height:36px;border:0;display:block;font-size:22px;font-weight:700;color:#0B1B33"></a></td></tr>
<tr><td style="padding:30px 28px 8px;background:#0B1B33">
<p style="margin:0 0 8px;font-size:12.5px;letter-spacing:.12em;color:#F9B08A;font-weight:700">DUTYFREE BD · SPECIAL</p>
<h1 style="margin:0 0 12px;font-size:26px;line-height:1.3;color:#FFFFFF;font-weight:700">{{ $title }}</h1>
<p style="margin:0 0 22px;font-size:15.5px;line-height:1.7;color:#D5DBE6">{{ trim((string)$name) !== '' ? 'প্রিয় '.trim($name).', ' : '' }}{{ $msg ?: 'বাছাই করা পণ্যে আজকের সেরা দাম আপনার জন্য সাজিয়ে রেখেছি। পছন্দের পণ্যটি বেছে নিন, বাকিটা আমরা পৌঁছে দেব।' }}</p>
<table role="presentation" cellpadding="0" cellspacing="0"><tr><td style="padding:0 0 28px"><a href="{{ $cta[1] }}" style="display:inline-block;background:#F95505;color:#FFFFFF;text-decoration:none;font-size:16px;font-weight:700;padding:13px 32px;border-radius:10px">{{ $cta[0] }}</a></td></tr></table>
</td></tr>
@if(count($items))<tr><td style="padding:22px 20px 6px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0">
@foreach(array_chunk($items, 2) as $pair)<tr>@foreach($pair as $p)<td width="50%" valign="top" style="padding:0 8px 16px">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #E5E7EB;border-radius:12px"><tr><td align="center" style="padding:14px 12px 6px"><a href="{{ $p['url'] }}" style="text-decoration:none">@if($p['img'])<img src="{{ $p['img'] }}" alt="{{ $p['name'] }}" width="150" style="width:150px;max-width:100%;height:150px;object-fit:contain;border:0;display:block">@endif</a></td></tr>
<tr><td style="padding:4px 12px 14px">
@if($p['was'] > $p['price'])<span style="display:inline-block;background:#DC2626;color:#FFFFFF;font-size:11px;font-weight:700;border-radius:99px;padding:2px 9px">সেভ ৳{{ $n($p['was'] - $p['price']) }}</span>@endif
@if($p['tag']) <span style="display:inline-block;background:#FFF1E8;color:#C2410C;font-size:11px;font-weight:700;border-radius:99px;padding:2px 9px">{{ $p['tag'] }}</span>@endif
<div style="font-size:13.5px;line-height:1.45;color:#0B1B33;margin:8px 0 6px;min-height:39px">{{ \Illuminate\Support\Str::limit($p['name'], 58) }}</div>
<div style="font-size:18px;font-weight:700;color:#C2410C">৳{{ $n($p['price']) }} @if($p['was'] > $p['price'])<span style="font-size:13px;font-weight:400;color:#9CA3AF;text-decoration:line-through">৳{{ $n($p['was']) }}</span>@endif</div>
<a href="{{ $p['url'] }}" style="display:block;margin-top:10px;text-align:center;background:#FFFFFF;color:#0B1B33;border:1px solid #0B1B33;text-decoration:none;font-size:13.5px;font-weight:700;padding:9px 8px;border-radius:8px">অর্ডার করুন</a>
</td></tr></table></td>@endforeach @if(count($pair) === 1)<td width="50%"></td>@endif</tr>@endforeach
</table></td></tr>@endif
<tr><td style="padding:6px 28px 22px;font-size:13.5px;line-height:1.7;color:#4B5563" align="center">দাম ও স্টক ইমেল পাঠানোর সময়ের; চূড়ান্ত দাম চেকআউটে দেখাবে।
@if($hotline)<br>হটলাইন: <a href="tel:{{ preg_replace('/[^0-9+]/', '', $hotline) }}" style="color:#C2410C;text-decoration:none;font-weight:700">{{ $hotline }}</a> @endif
@if($wa) · <a href="{{ $wa }}" style="color:#C2410C;text-decoration:none;font-weight:700">WhatsApp</a> @endif
</td></tr>
<tr><td style="padding:16px 28px;background:#0B1B33;color:#C7CEDA;font-size:12px;line-height:1.8" align="center"><a href="{{ $base }}/" style="color:#FFFFFF;text-decoration:none;font-weight:700">dutyfreebd.com</a><br>অফারের ইমেল আর পেতে না চাইলে <a href="{{ $unsub }}" style="color:#F9B08A">এখানে ক্লিক করুন</a>।</td></tr>
</table></td></tr></table></body></html>
