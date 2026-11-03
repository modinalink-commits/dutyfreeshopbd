@php
  $level = $level ?? null; $orders = $orders ?? null;
  $__s = (int)(is_array($level) ? ($level['stars'] ?? 0) : 0);
@endphp
@if($__s > 0)<span class="cls" title="Customer Level: {{ is_array($level) ? $level['name'] : '' }}{{ $orders !== null ? ' · '.$orders.' successful orders' : '' }}" style="display:inline-flex;align-items:center;white-space:nowrap;line-height:1;cursor:help"><span style="color:#B8860B;font-size:13px;letter-spacing:.5px;text-shadow:0 0 1px #8A6508">{{ str_repeat('★', $__s) }}</span></span>@endif
