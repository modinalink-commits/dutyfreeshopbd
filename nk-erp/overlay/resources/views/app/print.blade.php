@extends('app.layout', ['tab' => 'sale'])
@section('title', 'POS প্রিন্ট')
@section('bar')@include('app._back', ['href' => route('app.invoice', $sale), 'title' => 'POS প্রিন্ট'])@endsection
@section('content')
<div class="card" style="display:flex;align-items:center;gap:10px"><div style="flex-grow:1;display:flex;flex-direction:column"><b style="font-size:var(--fs-14)">থার্মাল প্রিন্টার · Bluetooth</b><span style="font-size:var(--fs-12);color:#51607a">ফোনের প্রিন্ট সার্ভিস দিয়ে প্রিন্ট হবে (Android-এ RawBT / প্রিন্টারের অ্যাপ)</span></div></div>
<div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px"><button type="button" class="chip pp on" data-mm="80">৮০ মিমি কাগজ</button><button type="button" class="chip pp" data-mm="58">৫৮ মিমি কাগজ</button></div>
<div style="background:#eef2f9;border-radius:14px;padding:12px;display:flex;justify-content:center"><iframe id="rc" title="রসিদ" src="{{ route('app.receipt', ['sale' => $sale, 'size' => '80']) }}" style="width:300px;height:560px;border:0;background:#fff;border-radius:6px"></iframe></div>
<div class="card" style="display:flex;flex-direction:column;gap:4px"><b style="font-size:var(--fs-13)">প্রিন্ট ইতিহাস</b>
  @forelse($hist as $h)<div style="display:flex;font-size:var(--fs-12)"><span style="flex-grow:1">{{ $h->details }}</span><span class="n" style="color:#51607a">{{ $h->created_at->timezone('Asia/Dhaka')->format('H:i') }}</span></div>@empty<span style="font-size:var(--fs-12);color:#51607a">এখনো প্রিন্ট হয়নি।</span>@endforelse
</div>
<button class="big" type="button" id="go" style="background:#123a86">এক চাপে প্রিন্ট</button>
@endsection
@push('js')
<script>
(function(){
  const base=@json(route('app.receipt', $sale)), logUrl=@json(route('app.print.log', $sale)), rc=document.getElementById('rc'); let mm='80';
  document.querySelectorAll('.pp').forEach(b=>b.onclick=()=>{mm=b.dataset.mm;document.querySelectorAll('.pp').forEach(x=>x.classList.toggle('on',x===b));rc.src=base+'?size='+mm;});
  document.getElementById('go').onclick=()=>{ fetch(logUrl,{method:'POST',headers:{'X-CSRF-TOKEN':NK.tok,'Accept':'application/json'}}).catch(()=>{}); try{rc.contentWindow.print();}catch(e){window.open(base+'?size='+mm+'&auto=1');} };
})();
</script>
@endpush
