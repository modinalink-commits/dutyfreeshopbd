<style>
.cs2{padding-top:34px}.cs2-b{position:relative;overflow:hidden;border-radius:20px;padding:22px 22px 26px;background:var(--c1);isolation:isolate}
.cs2-bg{position:absolute;inset:-20% -10% auto auto;width:60%;height:140%;background:radial-gradient(closest-side,var(--c2),transparent 70%);opacity:.9;z-index:-1;will-change:transform;transform:translate3d(0,var(--py,0px),0)}
.cs2-h{display:flex;align-items:flex-end;justify-content:space-between;gap:12px;margin-bottom:16px}
.cs2-t{margin:0;font:800 clamp(20px,3vw,30px)/1.15 Inter,system-ui,sans-serif;color:#121A2B;letter-spacing:-.01em;overflow:hidden}
.cs2-t span{display:inline-block;transform:translate3d(0,110%,0);opacity:0;transition:transform .8s cubic-bezier(.2,.8,.2,1),opacity .8s}
.cs2-d{margin:6px 0 0;font-size:13px;color:#4b5563;max-width:560px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.cs2-all{flex:none;display:inline-flex;align-items:center;gap:6px;background:#FF5A00;color:#fff;font-weight:700;font-size:13px;padding:9px 16px;border-radius:999px;text-decoration:none;transition:transform .3s,box-shadow .3s}
.cs2-all:hover{transform:translateY(-1px);box-shadow:0 6px 16px rgba(255,90,0,.3)}
.cs2-vp{position:relative;overflow:hidden;touch-action:pan-y;--v:6}
.cs2-tr{display:flex;transition:transform .8s cubic-bezier(.25,.8,.25,1);will-change:transform}
.cs2-i{flex:0 0 calc(100%/var(--v));padding:6px 7px 14px;box-sizing:border-box;min-width:0}
.cs2-c{display:flex;flex-direction:column;height:100%;background:#fff;border-radius:16px;box-shadow:0 2px 10px rgba(18,26,43,.08);overflow:hidden;text-decoration:none;color:#121A2B;opacity:0;transform:perspective(900px) translate3d(0,22px,0) rotateX(10deg);transition:opacity .6s ease,transform .7s cubic-bezier(.2,.8,.2,1),box-shadow .35s;transition-delay:calc(var(--k)*100ms)}
.cs2.in .cs2-c{opacity:1;transform:none}.cs2.in .cs2-t span{transform:none;opacity:1}
.cs2-c:hover{box-shadow:0 12px 26px rgba(18,26,43,.16);transition-delay:0s}
.cs2-im{position:relative;aspect-ratio:1/1;background:linear-gradient(100deg,#f3f4f6 30%,#fafafa 50%,#f3f4f6 70%) 0 0/200% 100%;animation:cs2sk 1.4s linear infinite}
.cs2-im img{position:absolute;inset:0;width:100%;height:100%;object-fit:contain;background:#fff;padding:8%;box-sizing:border-box;transition:transform .5s cubic-bezier(.2,.8,.2,1)}
.cs2.in .cs2-im img{animation:cs2fl 5s ease-in-out infinite;animation-delay:calc(var(--k)*-.7s)}
.cs2-c:hover .cs2-im img{animation:none;transform:scale(1.04)}
.cs2-pbs{position:absolute;left:8px;top:8px;z-index:3;pointer-events:none}.cs2-im .dp{position:relative;display:inline-flex;align-items:center;gap:4px;overflow:hidden;background:#121A2B;color:#fff;font-size:10.5px;font-weight:800;letter-spacing:.3px;border-radius:6px;padding:3px 8px 3px 6px;margin:0}.cs2-im .dp::before{content:"";border-left:4px solid transparent;border-right:4px solid transparent;border-top:6px solid #FF5A00;animation:dpDn 1.4s ease-in-out infinite}.cs2-im .dp::after{content:"";position:absolute;top:0;bottom:0;width:35%;left:-50%;background:linear-gradient(100deg,transparent,rgba(255,255,255,.4),transparent);animation:dpSh 3s ease-in-out infinite}
.cs2-im .fds{position:absolute;left:0;bottom:8px;display:flex;font-size:10.5px;font-weight:800;line-height:1;letter-spacing:.2px;z-index:3;pointer-events:none;animation:fdsIn 4.5s ease-in-out infinite}.cs2-im .fds b{font-weight:800;padding:5px 8px;background:#FF5A00;color:#fff}.cs2-im .fds b+b{background:#121A2B;padding:5px 10px 5px 8px;border-radius:0 999px 999px 0}@media(max-width:760px){.cs2-im .fds{font-size:9px;bottom:5px}.cs2-im .fds b{padding:4px 6px}.cs2-im .fds b+b{padding:4px 8px 4px 6px}}
.cs2-n{padding:10px 12px 0;font-size:13px;font-weight:600;line-height:1.35;height:2.7em;box-sizing:content-box;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.cs2-p{padding:6px 12px 12px;margin-top:auto;font-size:15px;font-weight:800}.cs2-p s{font-size:12px;font-weight:400;color:#6b7280;margin-left:6px}
.cs2-ar{position:absolute;top:42%;width:38px;height:38px;border-radius:50%;border:1px solid #e5e7eb;background:#fff;color:#121A2B;box-shadow:0 2px 8px rgba(18,26,43,.15);cursor:pointer;z-index:2;display:none;align-items:center;justify-content:center;font-size:18px;line-height:1;padding:0}
.cs2-ar:hover{background:#FF5A00;color:#fff;border-color:#FF5A00}.cs2-ar.p{left:2px}.cs2-ar.n{right:2px}
.cs2-pg{display:none;height:3px;background:rgba(18,26,43,.12);border-radius:99px;overflow:hidden;margin-top:6px}
.cs2-pg i{display:block;height:100%;width:100%;background:#FF5A00;transform-origin:0 50%;transform:scaleX(0)}
.cs2.run .cs2-pg i{animation:cs2pg var(--iv,5s) linear forwards}.cs2.hov .cs2-pg i{animation-play-state:paused}
.cs2.multi .cs2-ar{display:flex}.cs2.multi .cs2-pg{display:block}
@keyframes cs2pg{to{transform:scaleX(1)}}@keyframes cs2sk{to{background-position:-200% 0}}@keyframes cs2fl{50%{transform:translate3d(0,-4px,0)}}
@media(max-width:1023px){.cs2-vp{--v:3}}@media(max-width:640px){.cs2-vp{--v:2}.cs2-b{padding:16px 12px 18px;border-radius:16px}.cs2-d{display:none}.cs2-all{padding:7px 12px;font-size:12px}.cs2-i{padding:4px 5px 10px}.cs2-n{font-size:12px}.cs2-p{font-size:14px}.cs2-ar{width:32px;height:32px}}
.cs2r{opacity:0;transform:translate3d(0,22px,0);transition:opacity .7s ease,transform .8s cubic-bezier(.2,.8,.2,1)}.cs2r.in{opacity:1;transform:none}
@media(prefers-reduced-motion:reduce){.cs2r{opacity:1!important;transform:none!important}}
.cs2e .pc{opacity:0;transform:perspective(900px) translate3d(0,22px,0) rotateX(10deg)}
.cs2e.in .pc{opacity:1;transform:none;transition-delay:calc(var(--k,0)*100ms)}
@media(prefers-reduced-motion:reduce){.cs2e .pc{opacity:1!important;transform:none!important}}
@media(prefers-reduced-motion:reduce){.cs2-c,.cs2-t span{opacity:1!important;transform:none!important;transition:none!important}.cs2-tr{transition:none}.cs2-im,.cs2-im img,.cs2-pg i,.cs2-im .fds,.cs2-im .dp::before,.cs2-im .dp::after{animation:none!important}.cs2-bg{transform:none!important}}
.cs2-tm{display:inline-flex;align-items:center;gap:6px;margin-top:6px;font-size:12px;font-weight:700;color:#4b5563}.cs2-tm b{background:#121A2B;color:#fff;border-radius:6px;padding:3px 7px;font-variant-numeric:tabular-nums}.cs2-tm span.c{color:#121A2B}
/* Recently ordered - same light tinted banner as the sliders */
body .w.sec-lv{padding-top:34px}
body .w.sec-lv .lvbox{background:#F6F2FA;border:0;border-radius:20px;padding:22px 22px 26px;box-shadow:none;gap:14px}
body .w.sec-lv .lvh h2{font:800 clamp(20px,3vw,30px)/1.15 Inter,system-ui,sans-serif;color:#121A2B;letter-spacing:-.01em}
body .w.sec-lv .lvh h2 em{font-style:normal;-webkit-text-fill-color:#121A2B;color:#121A2B}
body .w.sec-lv .lvh .chip{background:#fff;border:1px solid #E4D9F1;color:#121A2B}
body .w.sec-lv .lvc{background:#fff;border:1px solid #E4D9F1}
body .w.sec-lv .lo .bx{border:1px solid #E4D9F1;border-radius:14px;box-shadow:none}
@media(max-width:640px){body .w.sec-lv .lvbox{padding:16px 12px 18px;border-radius:16px}}
</style>
