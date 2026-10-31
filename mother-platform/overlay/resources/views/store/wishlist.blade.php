@extends('store.layout')
@section('main')
<div class="w" style="padding-top:34px;padding-bottom:48px">
<div id="wle" style="text-align:center;font-size:12px;display:none"><span style="width:34px;height:34px;border-radius:50%;background:#DC2626;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-weight:700">×</span><div style="margin-top:6px">YOUR Wish List IS EMPTY</div>
<div style="width:196px;height:100px;background:#F3EEF8;margin:12px auto;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#7C3AED"><svg viewBox="0 0 64 48" width="72" height="54" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round"><path d="M6 16h52l-6 26H12z"/><path d="M20 16 28 4M44 16 36 4"/><path d="M24 24v10M32 24v10M40 24v10"/></svg></div>
<a href="/" style="background:#0B1220;color:#fff;font-size:11px;padding:9px 14px;border-radius:3px;display:inline-block">Continue Shopping</a></div>
<div id="wlf" style="display:none"><div style="font-size:13px;font-weight:600;margin-bottom:12px">Your Wish List</div><div class="gr" id="wlg"></div></div>
</div>
@endsection
@section('js')
<script>
(function(){var w=DF.wish();if(!w.length){document.getElementById('wle').style.display='block';return}
var E=function(s){var d=document.createElement('div');d.textContent=s;return d.innerHTML};
fetch('/cart-items?ids='+encodeURIComponent(w.map(function(i){return i+':1'}).join(','))).then(function(r){return r.json()}).then(function(q){if(!q.items.length){document.getElementById('wle').style.display='block';return}
var h='';q.items.forEach(function(it){var off=it.compare>it.price?Math.round((it.compare-it.price)/it.compare*100):0;h+='<div class="pc">'+(off?'<span class="bo">'+off+'%</span>':'')+'<a class="ph" href="'+E(it.url)+'"><img src="'+E(it.image||'/icon.png')+'" alt="'+E(it.name)+'" loading="lazy"></a><a class="nm" href="'+E(it.url)+'">'+E(it.name)+'</a><div class="pr"><b>'+Math.round(it.price).toLocaleString('en-US')+' TK</b> <br>'+(off?'<s>'+Math.round(it.compare).toLocaleString('en-US')+' TK</s>':'')+'</div><a class="on" href="/checkout?add='+it.id+'&p='+it.price+'">ORDER NOW</a><button type="button" data-x="'+it.id+'" style="border:0;background:none;color:#DC2626;font-size:11px;padding:2px">Remove</button></div>'});
var g=document.getElementById('wlg');g.innerHTML=h;document.getElementById('wlf').style.display='block';g.onclick=function(e){var k=e.target.getAttribute('data-x');if(k){DF.tw(k);e.target.parentNode.remove();if(!DF.wish().length){document.getElementById('wlf').style.display='none';document.getElementById('wle').style.display='block'}}}})})();
</script>
@endsection
