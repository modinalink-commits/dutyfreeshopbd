@extends('store.layout')
@section('main')
<style>.bsp{max-width:1440px;margin:0 auto;padding:0 12px}.bsp .hx{display:grid;gap:10px}.bsp .ph{border-radius:14px;background:linear-gradient(120deg,#0B1220,#1F2A44);color:#9CA3AF;display:grid;place-items:center;min-height:400px;font:600 14px Inter,sans-serif}@media (max-width:1023px){.bsp .ph{min-height:180px}}</style>
<div class="bsp"><div style="font-size:12px;color:#6B7280;margin:10px 0">Preview only (not on the front page yet). The dark box stands for the big slider; the Best Sellers card under Flash Sale is new and shows today's live prices.</div>
<div class="w hx"><div class="ph">Home slider</div>@include('store.hero_side', ['bsOn' => true])</div></div>
@endsection
