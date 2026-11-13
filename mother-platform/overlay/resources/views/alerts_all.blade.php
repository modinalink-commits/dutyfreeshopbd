@extends('layout')@section('title','Alerts')
@section('body')
<style>
.al2{--bd:#CED4DA;--mu:#6B7280;--n:#0B1220;display:flex;flex-direction:column;gap:10px;font-size:12.5px;color:var(--n)}
.al2 h1{margin:0;font-size:clamp(18px,1.5vw,22px)}.al2 h2{margin:0 0 6px;font-size:12.5px;font-weight:700}
.al2 .cd{background:#fff;border:1px solid var(--bd);border-radius:10px;padding:10px 12px;min-width:0}
.al2 .it{padding:9px 0;border-top:1px solid #EEF0F3;display:grid;gap:3px}.al2 .it:first-of-type{border-top:0}
.al2 .tt{display:flex;gap:8px;align-items:center;flex-wrap:wrap;font-weight:700;font-size:13px}
.al2 .md{font-size:10.5px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#fff;background:#0B1220;border-radius:5px;padding:1px 7px}
.al2 .sv{width:8px;height:8px;border-radius:50%;background:#E11D48;flex:none}.al2 .sv.warning{background:#F59E0B}.al2 .sv.info{background:#3B82F6}
.al2 .mu{color:var(--mu);font-size:11.5px}.al2 .ac{color:#334155}
.al2 .st{font-size:10.5px;border:1px solid var(--bd);border-radius:999px;padding:1px 8px;color:var(--mu)}
.al2 button{background:#F97316;color:#fff;border:0;border-radius:7px;padding:4px 12px;font-size:12px;font-weight:600;cursor:pointer;justify-self:start}
.al2 .em{color:var(--mu);padding:8px 0}
</style>
<div class="al2">
  <h1>Alerts</h1>
  <div class="cd"><h2>Active ({{ count($active) }})</h2>
    @forelse($active as $a)
    <div class="it">
      <div class="tt"><span class="sv {{ $a['severity'] }}"></span><span class="md">{{ $a['service'] }}</span>{{ $a['title'] }}<span class="st">{{ $a['status'] === 'open' ? 'New' : 'Seen' }}</span></div>
      <div class="mu">{{ $a['detected'] }}</div>
      <div>{{ $a['description_bn'] ?? $a['description'] }}</div>
      @if(!empty($a['action_bn'] ?? $a['action']))<div class="ac">→ {{ $a['action_bn'] ?? $a['action'] }}</div>@endif
      @if($a['status'] === 'open')<button type="button" data-id="{{ $a['id'] }}">Mark seen</button>@endif
    </div>
    @empty<div class="em">No active alerts</div>@endforelse
  </div>
  <div class="cd"><h2>Resolved history ({{ count($history) }})</h2>
    @forelse($history as $a)
    <div class="it">
      <div class="tt"><span class="md">{{ $a['service'] }}</span>{{ $a['title'] }}</div>
      <div class="mu">{{ $a['detected'] }} → {{ $a['resolved'] }} · {{ $a['by'] }}</div>
    </div>
    @empty<div class="em">Nothing yet</div>@endforelse
  </div>
</div>
<script>
document.querySelectorAll('.al2 button[data-id]').forEach(function(b){b.addEventListener('click',function(){
 var t=document.querySelector('meta[name="csrf-token"]');
 fetch('/admin/deploy/issue/'+b.dataset.id,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':t?t.content:''},body:JSON.stringify({status:'investigating'})}).then(function(){location.reload()})})});
</script>
@endsection
