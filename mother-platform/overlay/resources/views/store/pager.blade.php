@php $__n = $pg->hasMorePages() ? $pg->nextPageUrl() : ''; $__left = max(0, $pg->total() - $pg->lastItem()); @endphp
@if($pg->total() > 0)
<div class="lm" @if($__n) data-next="{{ $__n }}" @endif data-total="{{ $pg->total() }}">
<div class="lmi">Showing <b>1–{{ number_format($pg->lastItem()) }}</b> of <b>{{ number_format($pg->total()) }}</b> results</div>
<button type="button" class="lmb" @if(!$__n) hidden @endif>Load more <span>({{ number_format(min($pg->perPage(), $__left)) }} more)</span></button>
</div>
<style>
.lm{display:flex;flex-direction:column;align-items:center;gap:12px;margin:22px 0 8px}
.lmi{font-size:13px;color:#6B7280}.lmi b{color:var(--n,#0B1220)}
.lmb{min-width:220px;min-height:46px;padding:0 26px;border:0;border-radius:12px;background:var(--o,#FF5A00);color:#fff;font-weight:800;font-size:14px;font-family:inherit;cursor:pointer;box-shadow:0 4px 14px rgba(255,90,0,.3);transition:filter .15s,transform .15s}
.lmb:hover{filter:brightness(1.07)}.lmb:disabled{opacity:.65;cursor:progress}.lmb span{font-weight:600;opacity:.9}
.lmx{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
</style>
<div class="lmx">@include('pagination.global', ['paginator'=>$pg])</div>
<script>(function(){var lm=document.currentScript.parentNode.querySelector('.lm');if(!lm||lm.__i)return;lm.__i=1;var b=lm.querySelector('.lmb'),inf=lm.querySelector('.lmi');
b.addEventListener('click',function(){var u=lm.getAttribute('data-next');if(!u)return;var grid=lm.previousElementSibling;if(!grid||!grid.classList.contains('gr')){location.href=u;return}
b.disabled=true;var t=b.innerHTML;b.textContent='Loading…';
fetch(u,{credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(r){if(!r.ok)throw 0;return r.text()}).then(function(h){var d=new DOMParser().parseFromString(h,'text/html'),nl=d.querySelector('.lm'),ng=nl&&nl.previousElementSibling;if(!ng||!ng.classList.contains('gr'))throw 0;
[].slice.call(ng.children).forEach(function(c){grid.appendChild(document.importNode(c,true))});
var nx=nl.getAttribute('data-next');if(nx){lm.setAttribute('data-next',nx)}else{lm.removeAttribute('data-next')}
var ni=nl.querySelector('.lmi');if(ni)inf.innerHTML=ni.innerHTML;var nb=nl.querySelector('.lmb');
if(nx&&nb){b.innerHTML=nb.innerHTML;b.disabled=false}else{b.hidden=true}
}).catch(function(){location.href=u})});})();</script>
@endif
