@php
  $n = fn($v) => number_format((float)$v);
  $sub = 0; foreach ($items as $i) $sub += $i->price * $i->qty;
  $ship = (float)($o->shipping ?? 0); $disc = (float)($o->discount ?? 0); $total = (float)($o->total ?? ($sub + $ship - $disc));
  $steps = ['received' => 1, 'payment' => 2, 'confirmed' => 2, 'shipped' => 3, 'delivered' => 4]; $at = $steps[$kind] ?? 0;
  $labels = [1 => 'অর্ডার গৃহীত', 2 => 'কনফার্ম', 3 => 'কুরিয়ারে', 4 => 'ডেলিভার্ড'];
@endphp
<!doctype html><html lang="bn"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light"><title>{{ $subject }}</title></head>
<body style="margin:0;padding:0;background:#F3F4F6;font-family:'Hind Siliguri','Noto Sans Bengali',Arial,Helvetica,sans-serif;color:#0B1B33">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F3F4F6"><tr><td align="center" style="padding:24px 12px">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#FFFFFF;border:1px solid #E5E7EB;border-radius:14px;overflow:hidden">
<tr><td style="padding:22px 28px 18px;border-bottom:3px solid #F95505"><a href="{{ $base }}/" style="text-decoration:none"><img src="{{ $logo }}" alt="DutyFree BD" height="36" style="height:36px;border:0;display:block;font-size:22px;font-weight:700;color:#0B1B33"></a></td></tr>
<tr><td style="padding:28px 28px 6px">
<p style="margin:0 0 6px;font-size:13px;color:#6B7280;letter-spacing:.02em">ইনভয়েস {{ $o->invoice }}</p>
<h1 style="margin:0 0 10px;font-size:24px;line-height:1.3;color:#0B1B33;font-weight:700">{{ $title }}</h1>
<p style="margin:0 0 4px;font-size:15.5px;line-height:1.7;color:#374151">{{ trim((string)($o->customer_name ?? '')) !== '' ? 'প্রিয় '.trim($o->customer_name).',' : 'প্রিয় গ্রাহক,' }}</p>
<p style="margin:0 0 18px;font-size:15.5px;line-height:1.7;color:#374151">{{ $intro }}</p>
</td></tr>
@if($at)<tr><td style="padding:0 28px 20px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
@foreach($labels as $k => $lb)<td width="25%" align="center" style="padding:0 3px"><div style="height:6px;border-radius:99px;background:{{ $k <= $at ? '#F95505' : '#E5E7EB' }}"></div><div style="font-size:11.5px;margin-top:6px;color:{{ $k <= $at ? '#0B1B33' : '#9CA3AF' }};font-weight:{{ $k === $at ? '700' : '400' }}">{{ $lb }}</div></td>@endforeach
</tr></table></td></tr>@endif
<tr><td align="center" style="padding:0 28px 24px"><a href="{{ $url }}" style="display:inline-block;background:#F95505;color:#FFFFFF;text-decoration:none;font-size:16px;font-weight:700;padding:13px 34px;border-radius:10px;border:1px solid #0B1B33">{{ $cta }}</a></td></tr>
<tr><td style="padding:0 28px 8px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #E5E7EB;border-radius:10px">
<tr><td colspan="3" style="padding:11px 14px;background:#F9FAFB;font-size:13px;font-weight:700;color:#0B1B33;border-bottom:1px solid #E5E7EB">অর্ডারের বিবরণ</td></tr>
@foreach($items as $i)<tr>
<td width="56" style="padding:10px 0 10px 14px;border-bottom:1px solid #F0F1F3">@if($i->image)<img src="{{ $i->image }}" alt="" width="46" height="46" style="width:46px;height:46px;object-fit:contain;border:1px solid #EEF0F2;border-radius:8px;display:block">@endif</td>
<td style="padding:10px 10px;border-bottom:1px solid #F0F1F3;font-size:14px;line-height:1.45;color:#0B1B33">{{ $i->name }}<br><span style="font-size:12.5px;color:#6B7280">{{ $i->qty }} × ৳{{ $n($i->price) }}</span></td>
<td align="right" style="padding:10px 14px 10px 0;border-bottom:1px solid #F0F1F3;font-size:14px;font-weight:700;white-space:nowrap">৳{{ $n($i->price * $i->qty) }}</td></tr>@endforeach
<tr><td colspan="2" style="padding:9px 14px 2px;font-size:13.5px;color:#4B5563">পণ্যের মূল্য</td><td align="right" style="padding:9px 14px 2px;font-size:13.5px;color:#4B5563;white-space:nowrap">৳{{ $n($sub) }}</td></tr>
<tr><td colspan="2" style="padding:2px 14px;font-size:13.5px;color:#4B5563">ডেলিভারি চার্জ</td><td align="right" style="padding:2px 14px;font-size:13.5px;color:#4B5563;white-space:nowrap">{{ $ship > 0 ? '৳'.$n($ship) : 'ফ্রি' }}</td></tr>
@if($disc > 0)<tr><td colspan="2" style="padding:2px 14px;font-size:13.5px;color:#166534">ছাড়</td><td align="right" style="padding:2px 14px;font-size:13.5px;color:#166534;white-space:nowrap">− ৳{{ $n($disc) }}</td></tr>@endif
<tr><td colspan="2" style="padding:9px 14px 12px;font-size:16px;font-weight:700;color:#0B1B33;border-top:1px solid #E5E7EB">সর্বমোট</td><td align="right" style="padding:9px 14px 12px;font-size:16px;font-weight:700;color:#C2410C;border-top:1px solid #E5E7EB;white-space:nowrap">৳{{ $n($total) }}</td></tr>
</table></td></tr>
<tr><td style="padding:14px 28px 24px;font-size:13.5px;line-height:1.7;color:#4B5563">যেকোনো প্রয়োজনে আমরা আছি।
@if($hotline) হটলাইন: <a href="tel:{{ preg_replace('/[^0-9+]/', '', $hotline) }}" style="color:#C2410C;text-decoration:none;font-weight:700">{{ $hotline }}</a> @endif
@if($wa) · <a href="{{ $wa }}" style="color:#C2410C;text-decoration:none;font-weight:700">WhatsApp</a> @endif
@if($hours) <span style="color:#6B7280">({{ $hours }})</span> @endif
</td></tr>
<tr><td style="padding:16px 28px;background:#0B1B33;color:#C7CEDA;font-size:12px;line-height:1.7" align="center">এই ইমেলটি আপনার অর্ডারের তথ্য জানাতে পাঠানো হয়েছে।<br><a href="{{ $base }}/" style="color:#FFFFFF;text-decoration:none;font-weight:700">dutyfreebd.com</a></td></tr>
</table></td></tr></table></body></html>
