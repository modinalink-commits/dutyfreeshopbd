@php
  $mk = in_array(($key ?? ''), ['silver','gold','platinum','diamond'], true) ? $key : 'silver';
  $ms = max(1, min(4, (int)($stars ?? 1)));
  $mn = trim((string)($name ?? ''));
  $mi = (string)($id ?? '');
  $starPts = '12,2 15.1,8.6 22.4,9.5 17,14.5 18.5,21.7 12,18.2 5.5,21.7 7,14.5 1.6,9.5 8.9,8.6';
@endphp
@once
<style>
.dfmcw{container-type:inline-size;width:100%;max-width:500px}
.dfmc{position:relative;width:100%;aspect-ratio:500/315;border-radius:4.4cqw;overflow:hidden;font-family:'Inter','Hind Siliguri','Noto Sans Bengali',system-ui,-apple-system,'Segoe UI',Arial,sans-serif;box-shadow:0 14px 34px rgba(11,18,32,.3);isolation:isolate}
.dfmc>i{position:absolute;inset:0;display:block}
.dfmc .in{position:absolute;inset:0;padding:4.8cqw 5.6cqw;display:flex;flex-direction:column;justify-content:space-between}
.dfmc .top{display:flex;justify-content:space-between;align-items:flex-start;gap:3cqw}
.dfmc .plate{background:#fff;border-radius:2.2cqw;padding:1.4cqw 2.4cqw;display:flex;align-items:center;box-shadow:0 2px 8px rgba(0,0,0,.18)}.dfmc .plate img{height:6cqw;width:auto;display:block}
.dfmc .lab{font-size:max(8px,2cqw);font-weight:600;letter-spacing:.22em;text-transform:uppercase;text-align:right}
.dfmc .tier{font-family:'Playfair Display',Georgia,serif;font-weight:700;font-size:max(18px,6cqw);line-height:1.05;text-align:right;margin-top:.6cqw;display:flex;align-items:center;justify-content:flex-end;gap:1.6cqw}.dfmc .tier svg{width:5.2cqw;height:5.2cqw}
.dfmc .stars{display:flex;gap:1.6cqw}.dfmc .stars svg{width:6.8cqw;height:6.8cqw;display:block}
.dfmc .bot{display:flex;justify-content:space-between;align-items:flex-end;gap:3cqw}
.dfmc .sm{font-size:max(8px,2cqw);font-weight:600;letter-spacing:.2em;text-transform:uppercase;margin-bottom:1cqw}
.dfmc .nm{font-size:max(13px,4.2cqw);font-weight:700;letter-spacing:.07em;text-transform:uppercase;line-height:1.15;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;word-break:break-word}
.dfmc .id{font-size:max(10px,2.8cqw);font-weight:600;letter-spacing:.1em;text-align:right;white-space:nowrap}.dfmc .web{font-size:max(8px,2cqw);letter-spacing:.14em;margin-top:1cqw;text-align:right}
.dfmc .bar{position:absolute;left:0;right:0;bottom:0;height:1.1cqw;background:#FF5A00}
.dfmc.silver{color:#0B1220}.dfmc.silver .s2{color:#3B4658}
.dfmc.gold{color:#2B1D00}.dfmc.gold .s2{color:#5A3E00}
.dfmc.platinum{color:#F1F5F9}.dfmc.platinum .s2{color:#D1D5DB}
.dfmc.diamond{color:#fff}.dfmc.diamond .s2{color:#9FDCFF}
.dfmc.silver .bg{background:linear-gradient(135deg,#F4F6F9 0%,#C9D0DA 38%,#9AA4B2 70%,#DDE2E9 100%)}
.dfmc.gold .bg{background:linear-gradient(135deg,#FBEBB5 0%,#E3B84E 34%,#B98512 68%,#F0D07A 100%)}
.dfmc.platinum .bg{background:linear-gradient(135deg,#59667A 0%,#2E3848 40%,#1A2230 72%,#3C4859 100%)}
.dfmc.diamond .bg{background:linear-gradient(135deg,#1F3A66 0%,#0B1220 36%,#070D1A 70%,#17325C 100%)}
.dfmc .ring{background:repeating-radial-gradient(circle at 86% 13%,rgba(255,255,255,.09) 0 1px,transparent 1px 30px)}.dfmc.silver .ring,.dfmc.gold .ring{background:repeating-radial-gradient(circle at 86% 13%,rgba(11,18,32,.07) 0 1px,transparent 1px 30px)}
.dfmc .shine{background:linear-gradient(115deg,transparent 38%,rgba(255,255,255,.4) 50%,transparent 62%)}.dfmc.platinum .shine{background:linear-gradient(115deg,transparent 38%,rgba(255,255,255,.2) 50%,transparent 62%)}.dfmc.diamond .shine{background:linear-gradient(115deg,transparent 36%,rgba(160,225,255,.22) 50%,transparent 64%)}
.dfmc .edge{border-radius:4.4cqw;box-shadow:inset 0 0 0 1.5px rgba(255,255,255,.55)}.dfmc.platinum .edge{box-shadow:inset 0 0 0 1.5px rgba(255,255,255,.28)}.dfmc.diamond .edge{box-shadow:inset 0 0 0 1.5px rgba(143,216,255,.45)}
</style>
@endonce
<div class="dfmcw"><div class="dfmc {{ $mk }}" role="img" aria-label="DutyFree BD membership card, {{ ucfirst($mk) }}, {{ $ms }} of 4 stars, {{ $mn }}">
<i class="bg"></i><i class="ring"></i><i class="shine"></i><i class="edge"></i>
<div class="in">
<div class="top"><div class="plate"><img src="/dutyfree-bd-logo.png" alt="DutyFree BD"></div><div><div class="lab s2">{{ $mk === 'diamond' ? 'Premium Membership' : 'Membership' }}</div><div class="tier">@if($mk === 'diamond')<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h12l4 6-10 12L2 9z" fill="#8FD8FF"/><path d="M2 9h20M9 3L7 9l5 12M15 3l2 6-5 12" fill="none" stroke="#0F1F3D" stroke-width="1" stroke-opacity=".6"/></svg>@endif{{ ucfirst($mk) }}</div></div></div>
<div class="stars" aria-hidden="true">@for($i = 1; $i <= 4; $i++)@php $on = $i <= $ms; @endphp<svg viewBox="0 0 24 24"><polygon points="{{ $starPts }}" fill="{{ $on ? 'currentColor' : 'none' }}" @if(!$on) stroke="currentColor" stroke-opacity=".4" stroke-width="1.4" @endif/></svg>@endfor</div>
<div class="bot"><div style="min-width:0"><div class="sm s2">Member</div><div class="nm">{{ $mn }}</div></div><div><div class="id">{{ $mi }}</div><div class="web s2">dutyfreebd.com</div></div></div>
</div>
<div class="bar"></div>
</div></div>
