{{-- Help "?" with a short meaning, an example and what to do if it is wrong. Params: t (title), b (body), e (example), f (fix). --}}
@once
<style>
.hlp{position:relative;display:inline-flex;vertical-align:middle;margin-left:4px}
.hlp>button{width:20px;height:20px;min-height:0;padding:0;border-radius:999px;border:1px solid #7b8aa8;background:#fff;color:#123a86;font:700 11px 'Plus Jakarta Sans',sans-serif;cursor:pointer;display:inline-flex;align-items:center;justify-content:center}
.hlp>button[aria-expanded=true]{background:#123a86;color:#fff;border-color:#123a86}
.hlpb{display:none;position:absolute;z-index:40;top:26px;left:-8px;width:280px;max-width:80vw;background:#fff;border:1px solid #dbe3f0;border-radius:12px;box-shadow:0 8px 24px rgba(20,33,61,.14);padding:12px 14px;flex-direction:column;gap:6px;font:400 13px/20px 'Hind Siliguri','Plus Jakarta Sans',sans-serif;color:#14213d;text-transform:none;letter-spacing:0}
.hlp>button[aria-expanded=true]+.hlpb{display:flex}
.hlpb b{font-size:var(--fs-14)}.hlpb .ex{background:#eef2f9;border-radius:8px;padding:6px 8px;font-size:var(--fs-12)}.hlpb .fx{color:#51607a;font-size:var(--fs-12)}
</style>
<script>
document.addEventListener('click',function(e){var b=e.target.closest('.hlp>button');document.querySelectorAll('.hlp>button[aria-expanded=true]').forEach(function(x){if(x!==b)x.setAttribute('aria-expanded','false');});if(!b)return;e.preventDefault();b.setAttribute('aria-expanded',b.getAttribute('aria-expanded')==='true'?'false':'true');});
document.addEventListener('keydown',function(e){if(e.key==='Escape')document.querySelectorAll('.hlp>button[aria-expanded=true]').forEach(function(x){x.setAttribute('aria-expanded','false');});});
</script>
@endonce
<span class="hlp"><button type="button" aria-expanded="false" aria-label="{{ $t }}">?</button><span class="hlpb" role="tooltip"><b>{{ $t }}</b><span>{{ $b }}</span>@if(! empty($e))<span class="ex">{{ $e }}</span>@endif @if(! empty($f))<span class="fx">{{ $f }}</span>@endif</span></span>
