@php
  $oq = $oq ?? request()->only('q','per','from','to','ft','tt');
  $activeStatus = $activeStatus ?? request('status');
  $isInc = $isInc ?? false;
  $counts = $counts ?? collect();
  $incOpen = $incOpen ?? 0;
  $vals = $vals ?? collect();
  $mv = function ($n) { $n = (float)$n; if ($n <= 0) return '৳0'; if ($n >= 10000000) return '৳'.rtrim(rtrim(number_format($n / 10000000, 2), '0'), '.').' Cr'; if ($n >= 1000000) return '৳'.rtrim(rtrim(number_format($n / 100000, 2), '0'), '.').' L'; return '৳'.number_format($n); };
@endphp
@once
<style>
/* Shared Order Management container + status tab bar (used by All Orders and Late Delivery) */
.ocb{position:static!important;margin:0 0 8px!important;background:#fff;border:1px solid #EDF0F3;border-radius:12px;box-shadow:0 4px 14px rgba(0,0,0,.06);padding:10px 12px}
.ocb .r2{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:6px}.ocb>.r2:first-child{margin-top:0}
.ocb .strip{display:flex;flex-wrap:nowrap;align-items:center;gap:6px;flex:1 1 100%;min-width:0;overflow-x:auto;overflow-y:hidden;white-space:nowrap;padding-bottom:3px;scrollbar-width:thin}
.ocb .strip{gap:4px;height:26px;padding-bottom:0;box-sizing:border-box;scrollbar-width:none;-ms-overflow-style:none}.ocb .strip::-webkit-scrollbar{display:none;height:0}
.ocb .chip{flex:0 0 auto;height:24px;line-height:21px;padding:0 8px;border-radius:12px;font-size:11px;white-space:nowrap;box-sizing:border-box;border:1.5px solid color-mix(in srgb,var(--c,#64748B) 55%,#fff);transition:border-color .15s ease,background-color .15s ease}
.ocb .chip b{font-weight:700}
.ocb .chip:hover{border-color:var(--c,#4B5563)}
.ocb .chip.on{font-weight:700;border-color:var(--c,#0B1220);box-shadow:0 0 0 1px color-mix(in srgb,var(--c,#0B1220) 25%,transparent)}
/* v1.375 very light per-status fill, always black text; active chip = bolder ring */
.ocb .chip,.ocb .chip b{color:#0B1220!important}
.ocb .chip[class*=' s-']{background:color-mix(in srgb,var(--b,#F3F4F6) 70%,#fff)!important}
.ocb .chip:not([class*=' s-']){background:#F3F4F6!important}
.ocb .chip.on,.ocb .chip.on[class*=' s-']{background:var(--b,#E5E7EB)!important;color:#0B1220!important;border-color:var(--c,#0B1220)!important;border-width:2px;box-shadow:0 0 0 2px color-mix(in srgb,var(--c,#0B1220) 28%,transparent)}
.ocb .chip.on:not([class*=' s-']){background:#E5E7EB!important;border-color:#0B1220!important}
/* v1.388 count big, parcel value small underneath */
.ocb .strip{height:46px!important;align-items:flex-start!important}
.ocb .chip{height:42px!important;line-height:1.15!important;padding:3px 10px!important;border-radius:12px!important;display:inline-flex!important;flex-direction:column!important;align-items:center!important;justify-content:center!important;gap:1px}
.ocb .chip .cl{font-size:11px;font-weight:600;color:#0B1220!important}.ocb .chip .cl b{font-size:16px;font-weight:800;margin-left:3px}
.ocb .chip .cv{font-size:9.5px;font-weight:600;color:#0B1220!important;opacity:.85;white-space:nowrap}
</style>
@endonce
<div class="r2"><div class="strip" id="ostrip"><a class="chip status-card status-card-sm {{ $activeStatus ? '' : 'on' }}" href="/admin/orders?{{ http_build_query($oq) }}"><span class="cl"><span class="sl">All</span> <b>{{ collect($counts)->sum() }}</b></span><small class="cv" title="Total parcel value">{{ $mv(collect($vals)->sum()) }}</small></a>
@foreach(\App\Models\Mother::STAGES as $s)@if($s==='Pre Order') @continue @endif @if($s==='AI Order')@continue @endif @if($s==='Incomplete')<a class="chip status-card status-card-md s-incomplete {{ $isInc ? 'on' : '' }}" href="/admin/orders?{{ http_build_query(['status'=>'Incomplete']) }}"><span class="cl"><span class="sl">Incomplete</span> <b>{{ $incOpen }}</b></span><small class="cv" title="Total value of open checkouts">{{ $mv($vals['Incomplete'] ?? 0) }}</small></a>@continue @endif<a class="chip status-card {{ mb_strlen($s) <= 9 ? 'status-card-sm' : 'status-card-md' }} s-{{ \Illuminate\Support\Str::slug($s) }} {{ $activeStatus==$s?'on':'' }}" href="{{ '/admin/orders?'.http_build_query(array_merge($oq,['status'=>$s])) }}"><span class="cl"><span class="sl">{{ $s }}</span> <b>{{ max(0, ($counts[$s] ?? 0) - ($s==='New' ? ($aiAp ?? [0,0])[0] : 0)) }}</b></span><small class="cv" title="Total parcel value">{{ $mv(max(0, ($vals[$s] ?? 0) - ($s==='New' ? ($aiAp ?? [0,0])[1] : 0))) }}</small></a>@endforeach</div></div>
@include('admin.orders.partials.header-system')

@if(!empty($apprC) && $apprC['total'] > 0)<div class="apc" style="margin:2px 0 8px;font-size:12px;font-weight:600;color:#4B5563" title="Orders approved in the selected period (approval source is saved at the moment of approval)">Approved: <b>{{ number_format($apprC['total']) }}</b> · 🤖 AI <b>{{ number_format($apprC['AI']) }}</b> · 👤 Manual <b>{{ number_format($apprC['MANUAL']) }}</b>@if($apprC['other'] > 0) · System/API <b>{{ number_format($apprC['other']) }}</b>@endif · AI rate <b>{{ $apprC['rate'] }}%</b></div>@endif
