@php
  $oq = $oq ?? request()->only('q','per','from','to','ft','tt');
  $activeStatus = $activeStatus ?? request('status');
  $isInc = $isInc ?? false;
  $counts = $counts ?? collect();
  $incOpen = $incOpen ?? 0;
@endphp
@once
<style>
/* Shared Order Management container + status tab bar (used by All Orders and Late Delivery) */
.ocb{position:static!important;margin:0 0 8px!important;background:#fff;border:1px solid #EDF0F3;border-radius:12px;box-shadow:0 4px 14px rgba(0,0,0,.06);padding:10px 12px}
.ocb .r2{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:6px}.ocb>.r2:first-child{margin-top:0}
.ocb .strip{display:flex;flex-wrap:nowrap;align-items:center;gap:6px;flex:1 1 100%;min-width:0;overflow-x:auto;overflow-y:hidden;white-space:nowrap;padding-bottom:3px;scrollbar-width:thin}
.ocb .chip{flex:0 0 auto;height:30px;line-height:28px;padding:0 10px;border-radius:14px;font-size:12px;white-space:nowrap;box-sizing:border-box;border:1px solid transparent}.ocb .chip.on{font-weight:700;border-color:currentColor;box-shadow:0 0 0 1px rgba(0,0,0,.06)}
</style>
@endonce
<div class="r2"><div class="strip" id="ostrip"><a class="chip {{ $activeStatus ? '' : 'on' }}" href="/admin/orders?{{ http_build_query($oq) }}">All <b>{{ collect($counts)->sum() }}</b></a>
@foreach(\App\Models\Mother::STAGES as $s)@if($s==='Pre Order') @continue @endif @if($s==='Incomplete')<a class="chip s-incomplete {{ $isInc ? 'on' : '' }}" href="/admin/orders?{{ http_build_query(['status'=>'Incomplete']) }}">Incomplete <b>{{ $incOpen }}</b></a>@continue @endif<a class="chip s-{{ \Illuminate\Support\Str::slug($s) }} {{ $activeStatus==$s?'on':'' }}" href="{{ $s==='Late Delivery' ? '/admin/late-delivery' : '/admin/orders?'.http_build_query(array_merge($oq,['status'=>$s])) }}">{{ $s }} <b>{{ $counts[$s] ?? 0 }}</b></a>@endforeach</div></div>
