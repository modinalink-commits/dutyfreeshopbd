<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>@yield('title','Mother Commerce')</title>
<style>
:root{--o:#FF5A00;--n:#0B1220;--bg:#FFFBF8;--bd:#FDC2A5}
*{box-sizing:border-box}body{margin:0;font-family:system-ui,-apple-system,'Segoe UI',Roboto,Arial,sans-serif;background:var(--bg);color:var(--n);display:flex;min-height:100vh}
aside{width:240px;background:#0B1220;color:#C7CEDB;padding:16px 12px;display:flex;flex-direction:column;gap:6px;flex-shrink:0;overflow-y:auto;max-height:100vh;position:sticky;top:0}
aside .brand{display:flex;gap:10px;align-items:center;color:#fff;font-weight:600;text-decoration:none;padding:4px 6px}aside .brand b{background:var(--o);color:#fff;width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px}aside .brand small{display:block;font-weight:400;font-size:12px;color:#9AA4B5}
aside .viewing{background:#161E30;border:1px solid #232D44;border-radius:12px;padding:10px 12px;margin:8px 0}aside .viewing small{display:block;font-size:10px;letter-spacing:.08em;color:#8A94A8}aside .viewing strong{display:block;font-size:13px;color:#fff;margin-top:2px}
aside nav{display:flex;flex-direction:column;gap:2px}
aside a.nv,aside details summary{display:flex;align-items:center;gap:12px;color:#C7CEDB;text-decoration:none;padding:10px 12px;border-radius:10px;font-size:14px;cursor:pointer;list-style:none}aside a.nv:hover,aside details summary:hover{background:#161E30;color:#fff}
aside a.nv.on{background:#232B3D;color:#fff}aside a.nv.on svg{color:var(--o)}aside a.nv svg,aside details summary svg{flex-shrink:0;opacity:.9}aside a.nv span,aside details summary span{flex:1}
aside em{font-style:normal;background:var(--o);color:#fff;font-size:11px;font-weight:700;border-radius:20px;padding:1px 8px}
aside .gh{font-size:10px;letter-spacing:.08em;text-transform:uppercase;color:#7C879B;padding:10px 12px 2px}aside a.sub{display:block;color:#C7CEDB;text-decoration:none;padding:6px 12px 6px 42px;font-size:13px;border-radius:8px}aside a.sub:hover,aside a.sub.on{background:#161E30;color:#fff}
aside .me{margin-top:auto;font-size:12px;padding:10px 6px;color:#C7CEDB}aside .me form{margin-top:8px}
@media(max-width:800px){body{display:block}aside{width:auto;max-height:none;position:static}}
main{flex:1;padding:24px;min-width:0}h1{margin:0 0 16px;font-size:22px}
.card{background:#fff;border:1px solid var(--bd);border-radius:14px;padding:16px;margin-bottom:16px}
table{width:100%;border-collapse:collapse;font-size:13px}th{text-align:left;color:#6B7280;font-size:12px;padding:8px 8px 8px 0}td{padding:9px 8px 9px 0;border-top:1px solid #FFE7D6}
.btn,button{background:var(--o);color:#fff;border:0;border-radius:9px;padding:9px 14px;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block}
.btn.g,button.g{background:#fff;color:var(--n);border:1px solid var(--bd)}
input,select,textarea{border:1px solid var(--bd);border-radius:9px;padding:9px 10px;font:inherit;width:100%;background:#fff}
label{font-size:12px;color:#6B7280;display:block;margin:10px 0 4px}
.s-new{--c:#4F46E5;--b:#E0E7FF}.s-incomplete{--c:#6B7280;--b:#F3F4F6}.s-pre-order{--c:#CA8A04;--b:#FEF9C3}.s-assign{--c:#0D9488;--b:#CCFBF1}.s-pending{--c:#EA580C;--b:#FFEDD5}.s-ai-order{--c:#7C3AED;--b:#EDE9FE}.s-approved{--c:#16A34A;--b:#DCFCE7}.s-scheduled{--c:#0284C7;--b:#E0F2FE}.s-wfp{--c:#D97706;--b:#FEF3C7}.s-packaging{--c:#2563EB;--b:#DBEAFE}.s-shipment{--c:#0891B2;--b:#CFFAFE}.s-partial-delivered{--c:#65A30D;--b:#ECFCCB}.s-delivered{--c:#166534;--b:#BBF7D0}.s-late-delivery{--c:#DC2626;--b:#FEE2E2}.s-return-pending{--c:#9333EA;--b:#F3E8FF}.s-returned{--c:#6B21A8;--b:#E9D5FF}.s-exchange-received{--c:#C026D3;--b:#FAE8FF}.s-cancel{--c:#DC2626;--b:#FEE2E2}.chip[class*=' s-']{border:1.5px solid var(--c);color:var(--c);font-weight:700;background:var(--b)}.chip.on[class*=' s-']{background:var(--c);color:#fff}.pill[class*=' s-']{background:var(--b);color:var(--c);border:1px solid var(--c);font-weight:800;padding:3px 10px;border-radius:999px;font-size:12px}.qa{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 10px}.qa form{margin:0}.ab{display:inline-flex;align-items:center;justify-content:center;gap:7px;min-height:44px;padding:10px 18px;border-radius:12px;border:0;background:var(--c);color:#fff;font-size:14px;font-weight:800;letter-spacing:.2px;cursor:pointer;box-shadow:0 2px 0 rgba(0,0,0,.12)}.ab:hover{filter:brightness(.92)}.ab:active{transform:translateY(1px)}@media(max-width:700px){.qa .ab,.qa form{flex:1 1 46%}.qa form .ab{width:100%}.ab{min-height:50px;font-size:15px}}.chips{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px}.chip{border:1px solid #FED7AA;background:#fff;border-radius:20px;padding:5px 12px;font-size:12px;color:var(--n);text-decoration:none}.chip.on{background:var(--o);color:#fff;border-color:var(--o)}.chip.red{border-color:#DC2626;color:#B91C1C}
.pill{font-size:11px;font-weight:600;border-radius:6px;padding:2px 8px;background:#FFE7D6;color:#C2410C}.pill.red{background:#FEE2E2;color:#B91C1C}.pill.ok{background:#DCFCE7;color:#166534}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px}.k b{font-size:24px;display:block}.k span{font-size:12px;color:#6B7280}
.err{color:#B91C1C;font-size:12px}
</style></head><body>
@auth @include('sidebar') @endauth
<main>@yield('body')</main>
@auth @include('chat_widget') @endauth
@yield('js')
</body></html>
