@php
  $level = $level ?? null; $orders = $orders ?? null;
  $__k = is_array($level) ? ($level['key'] ?? null) : null; $__s = (int)(is_array($level) ? ($level['stars'] ?? 0) : 0);
  $__tone = ['silver'=>['#9CA3AF','#6B7280'], 'gold'=>['#F5B000','#B7791F'], 'platinum'=>['#B6C2D4','#64748B'], 'diamond'=>['#38BDF8','#0369A1']][$__k] ?? ['#9CA3AF','#6B7280'];
@endphp
@if($__s > 0)<span class="cls" title="Customer Level: {{ is_array($level) ? $level['name'] : '' }}{{ $orders !== null ? ' · '.$orders.' successful orders' : '' }}" style="display:inline-flex;align-items:center;gap:3px;white-space:nowrap;font-size:10.5px;font-weight:700;line-height:1.2;color:{{ $__tone[1] }}"><span style="color:{{ $__tone[0] }};font-size:12px;letter-spacing:.5px;text-shadow:0 0 1px {{ $__tone[1] }}">{{ str_repeat('★', $__s) }}</span>{{ $level['name'] }}</span>@endif
