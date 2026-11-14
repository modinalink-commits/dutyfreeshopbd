{{-- Call Center pages: one shared look (14 Nov 2026). Nothing here scrolls sideways at 100% zoom: tables use a fixed layout and wrap inside their cells. --}}
<style>
.cc-h{display:flex;align-items:baseline;gap:10px;flex-wrap:wrap;margin:0 0 10px}.cc-h h1{margin:0;font-size:18px}.cc-h span{font-size:12.5px;color:#475467}
.cc-k{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;margin-bottom:12px}.cc-k>div,.cc-k>a{background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:9px 12px;text-decoration:none;color:#0B1B33}.cc-k span{display:block;font-size:12px;color:#475467}.cc-k b{font-size:20px}
.cc-k .bad{border-color:#FECDCA}.cc-k .bad span,.cc-k .bad b{color:#B42318}.cc-k .ok b{color:#067647}
.cc-f{display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin-bottom:10px}.cc-f a.c{height:32px;padding:0 12px;border-radius:8px;border:1px solid #D0D5DD;background:#fff;color:#0B1B33;font-size:12.5px;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:6px}.cc-f a.c.on{background:#0B1B33;border-color:#0B1B33;color:#fff}.cc-f input[type=text],.cc-f input:not([type]){height:32px;max-width:260px}
.cc-box{background:#fff;border:1px solid #E5E7EB;border-radius:12px;overflow:hidden;margin-bottom:12px}.cc-box>b.t{display:block;padding:10px 14px;font-size:14px}
.cc-t{width:100%;border-collapse:collapse;table-layout:fixed;font-size:13px}.cc-t th{text-align:left;font-size:11px;letter-spacing:.04em;text-transform:uppercase;color:#475467;font-weight:700;padding:8px;border-bottom:1px solid #E5E7EB;border-top:1px solid #E5E7EB}.cc-t td{padding:8px;border-top:1px solid #F2F4F7;vertical-align:middle;overflow-wrap:anywhere}.cc-t small{display:block;color:#475467;font-size:12px}.cc-t tr.hot td{background:#FFFBFA}
.cc-p{display:inline-block;font-size:11px;font-weight:700;border-radius:6px;padding:1px 6px;background:#F2F4F7;color:#344054;white-space:nowrap}.cc-p.ok{background:#ECFDF3;color:#067647}.cc-p.bad{background:#FEF3F2;color:#B42318}.cc-p.warn{background:#FFFAEB;color:#B54708}.cc-p.info{background:#EEF4FF;color:#1E3A8A}
main button.cc-call{width:34px;height:34px;border:1px solid #ABEFC6;background:#ECFDF3;color:#067647;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;padding:0}main button.cc-call.go{background:#15803D;border-color:#15803D;color:#fff}
.cc-bar{height:8px;background:#F2F4F7;border-radius:999px;overflow:hidden}.cc-bar i{display:block;height:8px;background:#15803D}.cc-bar i.w{background:#B54708}
.cc-two{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:12px}
.cc-rk{width:24px;height:24px;border-radius:50%;background:#F2F4F7;color:#344054;font-weight:700;display:inline-flex;align-items:center;justify-content:center;font-size:12px}.cc-rk.g{background:#FEF0C7;color:#93370D}
.cc-note{font-size:12px;color:#475467;padding:10px 14px;border-top:1px solid #F2F4F7}
@media(max-width:760px){.cc-t .hm{display:none}.cc-t{font-size:12.5px}}
</style>
