@extends('layout')
@section('title', 'Hero slider')
@section('content')
@php($can = in_array(auth()->user()->role, ['super_admin', 'admin'], true))
@php($img = fn ($p) => $p && str_starts_with($p, '/img/') ? 'https://nklpgbd.com'.$p : $p)
<style>.fld{display:flex;flex-direction:column;gap:4px;font-size:11px;font-weight:600;color:#51607a}</style>
<header style="display:flex;flex-wrap:wrap;align-items:center;gap:12px">
  <div style="flex-grow:1;display:flex;flex-direction:column"><h1>Hero slider</h1><span class="sub">Frontend → homepage · changes go live after Publish, no code needed{{ $publishedAt ? ' · last published '.$publishedAt : '' }}</span></div>
  <a class="btn" href="https://nklpgbd.com/" target="_blank">View live slider</a>
  @if($can)<a class="btn" href="{{ route('hero.index', ['new' => 1]) }}">＋ Add slide</a>
  <form method="post" action="{{ route('hero.publish') }}">@csrf<button class="btn pri" type="submit">{{ $dirty ? 'Publish' : 'Published ✓' }}</button></form>@endif
</header>
<div style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-start">
  <section class="card" style="flex:1 1 300px;min-width:280px;padding:16px;display:flex;flex-direction:column;gap:8px">
    <h2 style="margin:0 0 4px;font-size:15px">Slides · use ↑ ↓ to reorder</h2>
    @forelse($slides as $i => $s)
      @php($on = $cur && $cur->id === $s->id)
      <div style="display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:12px;border:1px solid {{ $on ? '#123a86' : '#dbe3f0' }};background:{{ $on ? '#e6eeff' : '#fff' }}">
        @if($can)<span style="display:flex;flex-direction:column">
          <form method="post" action="{{ route('hero.move', $s) }}">@csrf<input type="hidden" name="dir" value="up"><button type="submit" aria-label="Move up" style="border:0;background:none;cursor:pointer;font-size:11px;padding:0;color:#7b8aa8" @disabled($i === 0)>▲</button></form>
          <form method="post" action="{{ route('hero.move', $s) }}">@csrf<input type="hidden" name="dir" value="down"><button type="submit" aria-label="Move down" style="border:0;background:none;cursor:pointer;font-size:11px;padding:0;color:#7b8aa8" @disabled($loop->last)>▼</button></form></span>@endif
        <div style="width:40px;height:48px;border-radius:8px;background:{{ $s->background }};display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden">@if($s->image)<img src="{{ $img($s->image) }}" alt="" style="height:44px;width:auto">@endif</div>
        <a href="{{ route('hero.index', ['s' => $s->id]) }}" style="flex-grow:1;text-decoration:none;display:flex;flex-direction:column"><span style="font-size:13px;font-weight:700;color:#14213d">{{ $i + 1 }}. {{ $s->heading }}</span><span style="font-size:11px;color:#51607a">{{ $s->brand }} · {{ $s->size }} · {{ $s->duration }} s</span></a>
        <span style="font-size:11px;font-weight:700;padding:3px 9px;border-radius:999px;{{ $s->enabled ? 'background:#e3f6ec;color:#0f6b3c' : 'background:#eef2f9;color:#51607a' }}">{{ $s->enabled ? 'On' : 'Off' }}</span>
      </div>
    @empty
      <p class="sub" style="margin:0">No slides yet.</p>
    @endforelse
  </section>

  <div style="flex:999 1 560px;min-width:0;display:flex;flex-direction:column;gap:16px">
    <form class="card" method="post" action="{{ route('hero.save') }}" enctype="multipart/form-data" id="slideForm" style="padding:20px;display:flex;flex-direction:column;gap:12px">@csrf
      @if($cur)<input type="hidden" name="id" value="{{ $cur->id }}">@endif
      <div style="display:flex;align-items:center"><h2 style="margin:0;font-size:15px;flex-grow:1">{{ $cur ? 'Edit slide '.($slides->search(fn ($x) => $x->id === $cur->id) + 1) : 'New slide' }}</h2>
        @if($cur && $can)<button class="btn sm" type="submit" form="delForm" onclick="return confirm('Delete this slide?')">Delete</button>@endif</div>
      <fieldset @disabled(! $can) style="border:0;padding:0;margin:0;display:flex;flex-direction:column;gap:12px;min-width:0">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px">
        <label class="fld">LPG brand (Brand Master)<select name="brand">@foreach($brands->whenEmpty(fn ($c) => collect(['Aygaz United'])) as $b)<option @selected(($cur?->brand ?? 'Aygaz United') === $b)>{{ $b }}</option>@endforeach</select></label>
        <label class="fld">Cylinder size<select name="size" data-p="size">@foreach(['12 KG', '35 KG', '45 KG'] as $z)<option @selected(($cur?->size ?? '12 KG') === $z)>{{ $z }}</option>@endforeach</select></label>
        <label class="fld">Status<select name="enabled"><option value="1" @selected($cur?->enabled ?? true)>Enabled</option><option value="0" @selected($cur && ! $cur->enabled)>Disabled</option></select></label>
      </div>
      <label class="fld">Heading<input name="heading" data-p="heading" required maxlength="120" value="{{ old('heading', $cur?->heading) }}"></label>
      <label class="fld">Description<textarea name="description" data-p="desc" rows="2" maxlength="300">{{ old('description', $cur?->description) }}</textarea></label>
      <label class="fld">Description (Bangla)<textarea name="description_bn" rows="2" maxlength="300" style="font-family:'Hind Siliguri',sans-serif">{{ old('description_bn', $cur?->description_bn) }}</textarea></label>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px">
        <label class="fld">Button text<input name="button_text" data-p="btn" maxlength="40" value="{{ old('button_text', $cur?->button_text) }}"></label>
        <label class="fld">Button goes to<select name="button_link">@foreach(\App\Models\HeroSlide::LINKS as $k => $l)<option value="{{ $k }}" @selected(($cur?->button_link ?? 'order') === $k)>{{ $l }}</option>@endforeach</select></label>
        <label class="fld">Cylinder image<input type="file" name="image" accept="image/png,image/webp,image/jpeg" style="padding:8px"><span style="font-weight:500">[upload PNG/WebP · auto WebP{{ function_exists('imageavif') ? ' + AVIF' : '' }}]</span></label>
        <label class="fld">Animation<select name="animation">@foreach(\App\Models\HeroSlide::ANIMS as $k => $l)<option value="{{ $k }}" @selected(($cur?->animation ?? 'slide') === $k)>{{ $l }}</option>@endforeach</select></label>
        <label class="fld">Show for<select name="duration">@foreach([6, 4, 8] as $d)<option value="{{ $d }}" @selected(($cur?->duration ?? 6) == $d)>{{ $d }} seconds</option>@endforeach</select></label>
      </div>
      <div class="fld">Background
        <div style="display:flex;gap:8px">@foreach(\App\Models\HeroSlide::BGS as $c => $l)<label title="{{ $l }}" style="cursor:pointer"><input type="radio" name="background" value="{{ $c }}" @checked(($cur?->background ?? '#f7f4ec') === $c) style="display:none" onchange="document.getElementById('pv').style.background='{{ $c }}';this.form.querySelectorAll('.bg').forEach(b=>b.style.outline='none');this.nextElementSibling.style.outline='2px solid #123a86'"><span class="bg" style="display:block;width:34px;height:34px;border-radius:999px;border:1px solid #c9d2e3;background:{{ $c }};outline:{{ ($cur?->background ?? '#f7f4ec') === $c ? '2px solid #123a86' : 'none' }};outline-offset:2px"></span></label>@endforeach</div></div>
      @if($can)<button class="btn pri" type="submit" style="align-self:flex-start">Save slide (draft)</button>@endif
      </fieldset>
    </form>
    @if($cur)<form id="delForm" method="post" action="{{ route('hero.delete', $cur) }}">@csrf</form>@endif

    <section class="card" style="padding:20px;display:flex;flex-direction:column;gap:12px">
      <div style="display:flex;align-items:center"><h2 style="margin:0;font-size:15px;flex-grow:1">Preview</h2>
        <div style="display:flex;border:1px solid #7b8aa8;border-radius:999px;overflow:hidden"><button type="button" id="toDesk" style="min-height:34px;padding:0 14px;border:0;cursor:pointer;font:600 12px 'Plus Jakarta Sans',sans-serif;background:#123a86;color:#fff">Desktop</button><button type="button" id="toMob" style="min-height:34px;padding:0 14px;border:0;cursor:pointer;font:600 12px 'Plus Jakarta Sans',sans-serif;background:#fff;color:#14213d">Mobile</button></div></div>
      <div style="display:flex;justify-content:center;background:#eef2f9;border-radius:12px;padding:16px">
        <div id="pv" style="background:{{ $cur?->background ?? '#f7f4ec' }};border-radius:10px;box-shadow:0 2px 8px rgba(20,33,61,.15);overflow:hidden;width:100%;max-width:640px">
          <div id="pvIn" style="display:flex;gap:14px;padding:22px;align-items:center;flex-direction:row">
            <div style="flex:1;display:flex;flex-direction:column;gap:8px">
              <span style="align-self:flex-start;font-size:9px;font-weight:800;letter-spacing:.14em;color:#6e5214;background:#efe5cc;padding:4px 8px;border-radius:999px">AYGAZ UNITED · LPG</span>
              <span id="pvH" style="font-weight:800;color:#0f1b2d;font-size:28px;line-height:1.1">{{ $cur?->heading }}</span>
              <span id="pvD" style="font-size:12px;color:#3e4756">{{ $cur?->description }}</span>
              <span id="pvB" style="align-self:flex-start;background:#0f1b2d;color:#fff;font-size:11px;font-weight:700;padding:8px 14px;border-radius:999px">{{ $cur?->button_text }}</span>
            </div>
            @if($cur?->image)<img src="{{ $img($cur->image) }}" alt="" style="height:150px;width:auto">@endif
          </div>
        </div>
      </div>
    </section>
  </div>
</div>
<p class="sub" style="margin:0;font-size:12px">Only the first slide's image loads with the page; the rest load later. Autoplay pauses when the slider is off-screen or the tab is hidden, and animation turns off for visitors who ask for reduced motion.</p>
<script>
(function(){
  const f=document.getElementById('slideForm'), map={heading:'pvH',desc:'pvD',btn:'pvB'};
  f.addEventListener('input',e=>{ const k=e.target.dataset.p; if(map[k]) document.getElementById(map[k]).textContent=e.target.value; });
  const set=m=>{ document.getElementById('pv').style.maxWidth=m?'240px':'640px'; const i=document.getElementById('pvIn'); i.style.flexDirection=m?'column-reverse':'row'; document.getElementById('pvH').style.fontSize=m?'20px':'28px';
    document.getElementById('toDesk').style.cssText+=';background:'+(m?'#fff':'#123a86')+';color:'+(m?'#14213d':'#fff'); document.getElementById('toMob').style.cssText+=';background:'+(m?'#123a86':'#fff')+';color:'+(m?'#fff':'#14213d'); };
  document.getElementById('toDesk').onclick=()=>set(false); document.getElementById('toMob').onclick=()=>set(true);
})();
</script>
@endsection
