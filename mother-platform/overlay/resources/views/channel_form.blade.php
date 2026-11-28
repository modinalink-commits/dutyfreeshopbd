@extends('layout')@section('title', $c ? 'Edit Channel' : 'Add Channel')
@section('body')
@php $v = fn($k, $d = '') => old($k, $d); $b = $s['brand']; $h = $s['hero']; $sel = old('categories', $s['categories']); $st = $c->status ?? 'draft'; @endphp
<style>
.cf{max-width:1120px}.cf .grid{display:grid;grid-template-columns:minmax(0,1.3fr) minmax(0,1fr);gap:14px;align-items:start}@media(max-width:900px){.cf .grid{grid-template-columns:minmax(0,1fr)}}
.cf .tc{background:#fff;border:1px solid #E3E6EC;border-radius:10px;padding:12px 14px;margin-bottom:12px;box-shadow:0 1px 2px rgba(15,27,51,.04)}
.cf h2{font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:#6B7280;margin:0 0 10px}
.cf .r2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}@media(max-width:520px){.cf .r2{grid-template-columns:minmax(0,1fr)}}
.cf label{display:block;font-weight:600;font-size:12px;margin:0 0 3px}.cf .hint{color:#6B7280;font-size:11.5px;margin-top:4px}
.cf .chips{display:flex;flex-wrap:wrap;gap:6px}.cf .chips label{margin:0;border:1px solid #E3E6EC;border-radius:999px;padding:4px 10px;cursor:pointer;background:#fff;font-weight:500}.cf .chips label:has(input:checked){background:#FFF1E8;border-color:#F95505;color:#F95505}
.cf .lock{background:#F7F8FA;border:1px dashed #D1D5DB;border-radius:8px;padding:8px 10px;font-size:12px;color:#4B5563;margin-top:8px}
.cf .sticky{position:sticky;top:8px}
.cf .pv{border:1px solid #E3E6EC;border-radius:12px;overflow:hidden;background:#fff}.cf .pvh{display:flex;align-items:center;gap:8px;padding:8px 10px;border-bottom:1px solid #E3E6EC}.cf .pvl{width:28px;height:28px;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;overflow:hidden}.cf .pvl img{width:100%;height:100%;object-fit:contain;background:#fff}
.cf .pvn{display:flex;gap:6px;padding:6px 10px;color:#fff;font-size:11px;flex-wrap:wrap}.cf .pvn span{opacity:.92}
.cf .hero{padding:22px 14px;background-size:cover;background-position:center;color:#fff;min-height:120px}.cf .hero b{font-size:17px;display:block}.cf .hero i{font-style:normal;font-size:12px;opacity:.9;display:block;margin:4px 0 10px}.cf .hero u{text-decoration:none;display:inline-block;border-radius:6px;padding:5px 12px;font-size:12px;font-weight:700;color:#fff}
.cf .pvg{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;padding:10px}.cf .pvg div{border:1px solid #E3E6EC;border-radius:8px;height:62px;background:#F7F8FA}
.cf .hist{font-size:12px;color:#4B5563;max-height:150px;overflow:auto}.cf .hist div{padding:3px 0;border-bottom:1px solid #F0F1F4}
</style>
<div class="cf">
<h1 style="font-size:20px;margin:0 0 4px">{{ $c ? 'Edit Channel — '.$c->name : 'Add Channel' }}</h1>
<div style="font-size:12.5px;color:#6B7280;margin-bottom:12px"><a href="/admin/channels">← Sales Channels</a> · only the storefront design is set here. Phone, SMS, WhatsApp, payments and everything else come from the existing DutyFreeBD setup.</div>
@if(session('ok'))<div class="card" style="color:#166534">{{ session('ok') }}</div>@endif
@if($errors->any())<div class="card err">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ $c ? '/admin/channels/'.$c->id.'/update' : '/admin/channels' }}">@csrf
<div class="grid"><div>
 <div class="tc"><h2>1 · Channel</h2><div class="r2"><div><label>Channel name</label><input name="name" id="f_name" value="{{ $v('name', $c->name ?? '') }}" required maxlength="120" placeholder="Duty Free Dubai"></div><div><label>Domain</label><input name="domain" value="{{ $v('domain', $c->domain ?? '') }}" required maxlength="200" placeholder="dutyfreedubai.net"></div></div>
  @if($c)<div style="margin-top:10px"><label>Status</label><select name="status" style="max-width:260px">@foreach(['draft'=>'Draft — not visible to anyone','preview'=>'Preview — staff only, private','paused'=>'Paused'] as $k=>$t)<option value="{{ $k }}" {{ $st===$k?'selected':'' }}>{{ $t }}</option>@endforeach @if($st==='live')<option value="live" selected>Live</option>@endif</select>
  <div class="hint">"Live" is not switchable here. Going live needs a separate approval from the owner.</div></div>@endif
  <div class="lock">🔒 Orders and payments are <b>OFF</b> for this channel. SMS, WhatsApp, phone numbers, couriers and payment gateways use the DutyFreeBD setup. The preview is hidden from search engines.</div></div>
 <div class="tc"><h2>2 · Logo, colours &amp; theme</h2>
  <div class="r2"><div><label>Logo image address</label><input name="logo_url" id="f_logo" value="{{ $v('logo_url', $b['logo_url']) }}" maxlength="400" placeholder="https://… or /uploads/…"></div><div><label>Favicon image address</label><input name="favicon_url" value="{{ $v('favicon_url', $b['favicon_url']) }}" maxlength="400"></div></div>
  <div class="r2" style="margin-top:10px"><div><label>Main colour (buttons, header)</label><input type="color" name="primary" id="f_pri" value="{{ $v('primary', $b['primary']) }}" style="height:36px;padding:2px"></div><div><label>Accent colour (text, footer)</label><input type="color" name="accent" id="f_acc" value="{{ $v('accent', $b['accent']) }}" style="height:36px;padding:2px"></div></div>
  <div style="margin-top:10px"><label>Theme</label><select name="theme" id="f_theme" style="max-width:280px">@foreach($themes as $k=>$t)<option value="{{ $k }}" {{ $v('theme',$b['theme'])===$k?'selected':'' }}>{{ $t }}</option>@endforeach</select></div></div>
 <div class="tc"><h2>3 · Hero banner</h2>
  <div><label>Banner image address <small style="font-weight:400;color:#6B7280">(wide, about 1600×600)</small></label><input name="hero_image_url" id="f_himg" value="{{ $v('hero_image_url', $h['image_url']) }}" maxlength="400"></div>
  <div class="r2" style="margin-top:10px"><div><label>Headline</label><input name="hero_title" id="f_ht" value="{{ $v('hero_title', $h['title']) }}" maxlength="120"></div><div><label>Sub-line</label><input name="hero_subtitle" id="f_hs" value="{{ $v('hero_subtitle', $h['subtitle']) }}" maxlength="200"></div></div>
  <div class="r2" style="margin-top:10px"><div><label>Button text</label><input name="hero_cta_text" id="f_hc" value="{{ $v('hero_cta_text', $h['cta_text']) }}" maxlength="40"></div><div><label>Button link</label><input name="hero_cta_url" value="{{ $v('hero_cta_url', $h['cta_url']) }}" maxlength="300" placeholder="/category/cigarettes"></div></div></div>
 <div class="tc"><h2>4 · Categories shown <small style="text-transform:none;letter-spacing:0">(from the main category list)</small></h2>
  <div class="chips">@foreach($cats as $cn)<label><input type="checkbox" name="categories[]" value="{{ $cn }}" class="f_cat" {{ in_array($cn, (array)$sel, true) ? 'checked' : '' }}>{{ $cn }}</label>@endforeach</div>
  <div class="hint">Only ticked categories appear on this storefront. Products, prices, stock and discounts are the same central ones.</div></div>
 <div style="display:flex;gap:8px;margin-bottom:14px"><button class="btn" type="submit">{{ $c ? 'Save Settings' : 'Add as Draft' }}</button><a class="btn g" href="/admin/channels">Cancel</a></div>
</div><div class="sticky">
 <div class="tc"><h2>Storefront preview <small style="text-transform:none;letter-spacing:0">(updates as you type)</small></h2>
  <div class="pv" id="pv"><div class="pvh" id="pvh"><span class="pvl" id="pvl"></span><b id="pvn"></b></div><div class="pvn" id="pvnav"></div><div class="hero" id="hero"><b id="hb"></b><i id="hsb"></i><u id="hcta"></u></div><div class="pvg"><div></div><div></div><div></div><div></div><div></div><div></div></div></div>
  <div class="hint">Mobile and desktop use the same design; this is a quick look. The full page is opened from the Preview link once the storefront is built.</div></div>
 @if($c)<div class="tc"><h2>Change history</h2><div class="hist">@foreach(array_slice($s['history'] ?? [],0,12) as $x)<div>{{ $x['at'] }} · <b>{{ $x['by'] }}</b> — {{ $x['what'] }}</div>@endforeach</div></div>@endif
</div></div></form></div>
@endsection
@section('js')
<script>
(function(){var $=function(i){return document.getElementById(i)};
function up(){var p=$('f_pri').value,a=$('f_acc').value,t=$('f_theme').value,n=$('f_name').value||'Channel',lg=$('f_logo').value.trim();
 var pv=$('pv');pv.style.background=t==='dark'?'#111827':'#fff';pv.style.color=t==='dark'?'#F9FAFB':a;
 $('pvh').style.background=t==='premium'?a:(t==='dark'?'#111827':'#fff');$('pvh').style.color=t==='premium'||t==='dark'?'#fff':a;
 var l=$('pvl');l.style.background=p;l.textContent='';if(lg){var im=document.createElement('img');im.src=lg;im.alt='';l.appendChild(im)}else l.textContent=n.charAt(0).toUpperCase();
 $('pvn').textContent=n;
 var cats=[].slice.call(document.querySelectorAll('.f_cat:checked')).map(function(x){return x.value});var nv=$('pvnav');nv.style.background=p;nv.textContent='';(cats.length?cats:['All categories']).forEach(function(c){var s=document.createElement('span');s.textContent=c;nv.appendChild(s)});
 var h=$('hero'),im2=$('f_himg').value.trim();h.style.backgroundColor=a;h.style.backgroundImage=im2?'url("'+im2.replace(/"/g,'')+'")':'none';
 $('hb').textContent=$('f_ht').value||n;$('hsb').textContent=$('f_hs').value;var c=$('hcta');c.textContent=$('f_hc').value;c.style.background=p;c.style.display=$('f_hc').value?'inline-block':'none'}
document.querySelectorAll('.cf input,.cf select').forEach(function(e){e.addEventListener('input',up);e.addEventListener('change',up)});up()})();
</script>
@endsection
