@extends('layout')@section('title','Image ALT audit')
@section('body')
<div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:10px"><div style="flex:1"><h1 style="margin:0">Image ALT audit</h1><div style="font-size:12px;color:#6B7280">ALT লেখা ছবি Google Images-এ র‍্যাংক করতে সাহায্য করে। ফাঁকা থাকলে সাইট পণ্যের নাম দেখায়। <a href="/admin/seo/items?type=product">← Product SEO</a></div></div>
<form method="post" action="/admin/seo/alt/fill" onsubmit="return confirm('যাদের ALT নেই শুধু তাদের জন্য ALT তৈরি হবে; আপনার লেখা ALT বদলাবে না। চালাবো?')">@csrf<button>Generate all missing ALT</button></form></div>
@if(session('ok'))<div class="card" style="color:#166534">{{ session('ok') }}</div>@endif
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;margin-bottom:12px">@foreach([['Products with image',$tot],['Custom ALT missing',$miss],['Products without image',$noimg],['Gallery images',$gal],['Gallery ALT missing',$galMiss]] as [$l,$v])<div class="card" style="margin:0;padding:10px 12px"><span style="font-size:11.5px;color:#6B7280;display:block">{{ $l }}</span><b style="font-size:20px">{{ $v }}</b></div>@endforeach</div>
<div class="card"><form method="get" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap"><div><label>Search</label><input name="q" value="{{ $qs }}"></div><div><label>Show</label><select name="f"><option value="missing" @selected($f==='missing')>ALT missing</option><option value="all" @selected($f==='all')>All</option></select></div><button>Apply</button></form></div>
<div class="card" style="padding:0"><table><thead><tr><th></th><th>Product</th><th>ALT text</th><th></th></tr></thead><tbody>@forelse($rows as $p)
<tr><td><img src="{{ $p->image }}" alt="" width="40" height="40" loading="lazy" style="object-fit:cover;border-radius:6px"></td><td>{{ $p->name }}</td>
<td colspan="2"><form method="post" action="/admin/seo/alt/{{ $p->id }}" style="display:flex;gap:6px">@csrf<input name="alt" value="{{ $p->image_alt }}" placeholder="{{ $p->name }}" maxlength="300" style="flex:1;min-width:200px"><button class="g">Save</button></form></td></tr>
@empty<tr><td colspan="4" style="padding:16px;color:#6B7280">সব ঠিক আছে ✓</td></tr>@endforelse</tbody></table></div>
<div style="margin-top:10px">{{ $rows->links() }}</div>
@endsection
