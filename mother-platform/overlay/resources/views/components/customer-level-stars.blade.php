@php
  $level = $level ?? null; $orders = $orders ?? null;
  $__s = (int)(is_array($level) ? ($level['stars'] ?? 0) : 0);
@endphp
@once
<style>
@keyframes clsGlow{0%{opacity:.55;text-shadow:0 0 1px #8A6508;filter:brightness(.9)}50%{opacity:1;text-shadow:0 0 6px #FFC93C,0 0 12px #F5B000;filter:brightness(1.25)}100%{opacity:.55;text-shadow:0 0 1px #8A6508;filter:brightness(.9)}}
.cls .cls-s{display:inline-block;animation:clsGlow 1.8s ease-in-out infinite}
@media (prefers-reduced-motion:reduce){.cls .cls-s{animation:none}}
</style>
@endonce
@if($__s > 0)<span class="cls" title="Customer Level: {{ is_array($level) ? $level['name'] : '' }}{{ $orders !== null ? ' · '.$orders.' successful orders' : '' }}" style="display:inline-flex;align-items:center;white-space:nowrap;line-height:1;cursor:help"><span class="cls-s" style="color:#B8860B;font-size:17px;letter-spacing:1px">{{ str_repeat('★', $__s) }}</span></span>@endif
