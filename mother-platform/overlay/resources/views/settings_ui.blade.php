@once
<style>
.sbtn{display:inline-flex;align-items:center;gap:6px;height:32px;padding:0 13px;border:1px solid #E5E7EB;border-radius:8px;background:#fff;font-weight:600;font-size:12.5px;font-family:inherit;color:#0F172A;cursor:pointer}.sbtn:hover{border-color:#FF5A00;color:#FF5A00}
.sdr{position:fixed;inset:0;z-index:100001;display:none;background:rgba(15,23,42,.45)}.sdr.on{display:block}
.sdr-p{position:absolute;top:0;right:0;bottom:0;width:min(720px,100%);background:#F8FAFC;box-shadow:-12px 0 40px rgba(0,0,0,.25);display:flex;flex-direction:column;animation:sdrin .18s ease-out}
@keyframes sdrin{from{transform:translateX(30px);opacity:.4}to{transform:none;opacity:1}}
.sdr-h{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px 16px;background:#fff;border-bottom:1px solid #E5E7EB}.sdr-h b{font-size:15px}
.sdr-h button{border:0;background:#F1F5F9;border-radius:8px;width:30px;height:30px;font-size:18px;cursor:pointer}
.sdr-b{flex:1;overflow:auto;padding:12px}.sdr-b .card{box-shadow:none}.sdr-b h2{display:none}
</style>
<script>
(function(){function o(id){var e=document.getElementById(id);if(e){e.classList.add('on');document.documentElement.style.overflow='hidden'}}
function c(e){e.classList.remove('on');document.documentElement.style.overflow=''}
document.addEventListener('click',function(ev){var b=ev.target.closest('[data-sdr]');if(b){ev.preventDefault();o(b.getAttribute('data-sdr'));return}
if(ev.target.closest('[data-sdx]')){var d=ev.target.closest('.sdr');if(d)c(d);return}
if(ev.target.classList&&ev.target.classList.contains('sdr'))c(ev.target)});
document.addEventListener('keydown',function(ev){if(ev.key==='Escape')document.querySelectorAll('.sdr.on').forEach(c)});
if(location.hash==='#settings')o(document.querySelector('.sdr')&&document.querySelector('.sdr').id)})();
</script>
@endonce
