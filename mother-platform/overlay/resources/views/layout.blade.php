<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>@yield('title','Mother Commerce')</title>
<style>
:root{--o:#FF5A00;--n:#0B1220;--bg:#FFFBF8;--bd:#FDC2A5}
*{box-sizing:border-box}body{margin:0;font-family:system-ui,-apple-system,'Segoe UI',Roboto,Arial,sans-serif;background:var(--bg);color:var(--n);display:flex;min-height:100vh}
aside{width:230px;background:var(--n);color:#D1D5DB;padding:20px 12px;display:flex;flex-direction:column;gap:6px;flex-shrink:0}
aside .logo{display:flex;gap:10px;align-items:center;color:#fff;font-weight:600;margin-bottom:14px}aside .logo b{background:var(--o);color:var(--n);width:32px;height:32px;border-radius:9px;display:flex;align-items:center;justify-content:center}
aside a{color:#D1D5DB;text-decoration:none;padding:10px 12px;border-radius:9px;font-size:14px}aside a:hover,aside a.on{background:rgba(255,255,255,.12);color:#fff}
main{flex:1;padding:24px;min-width:0}h1{margin:0 0 16px;font-size:22px}
.card{background:#fff;border:1px solid var(--bd);border-radius:14px;padding:16px;margin-bottom:16px}
table{width:100%;border-collapse:collapse;font-size:13px}th{text-align:left;color:#6B7280;font-size:12px;padding:8px 8px 8px 0}td{padding:9px 8px 9px 0;border-top:1px solid #FFE7D6}
.btn,button{background:var(--o);color:#fff;border:0;border-radius:9px;padding:9px 14px;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block}
.btn.g,button.g{background:#fff;color:var(--n);border:1px solid var(--bd)}
input,select,textarea{border:1px solid var(--bd);border-radius:9px;padding:9px 10px;font:inherit;width:100%;background:#fff}
label{font-size:12px;color:#6B7280;display:block;margin:10px 0 4px}
.chips{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px}.chip{border:1px solid #FED7AA;background:#fff;border-radius:20px;padding:5px 12px;font-size:12px;color:var(--n);text-decoration:none}.chip.on{background:var(--o);color:#fff;border-color:var(--o)}.chip.red{border-color:#DC2626;color:#B91C1C}
.pill{font-size:11px;font-weight:600;border-radius:6px;padding:2px 8px;background:#FFE7D6;color:#C2410C}.pill.red{background:#FEE2E2;color:#B91C1C}.pill.ok{background:#DCFCE7;color:#166534}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px}.k b{font-size:24px;display:block}.k span{font-size:12px;color:#6B7280}
.err{color:#B91C1C;font-size:12px}
</style></head><body>
@auth<aside><div class="logo"><b>M</b>Mother Commerce</div>
<a href="/admin" class="{{ request()->is('admin') ? 'on':'' }}">Dashboard</a>
<a href="/admin/orders" class="{{ request()->is('admin/orders*') ? 'on':'' }}">Order Management</a>
<a href="/admin/products" class="{{ request()->is('admin/products*') ? 'on':'' }}">Catalog</a>
<a href="/admin/taxonomy" class="{{ request()->is('admin/taxonomy') ? 'on':'' }}">Categories &amp; Brands</a>
<a href="/admin/discounts" class="{{ request()->is('admin/discounts') ? 'on':'' }}">Discount Center</a>
<a href="/admin/staff-log" class="{{ request()->is('admin/staff-log') ? 'on':'' }}">Staff Tracking</a>
<div style="margin-top:auto;font-size:12px">{{ auth()->user()->name }}<form method="post" action="/logout" style="margin-top:8px">@csrf<button class="g">Sign out</button></form></div></aside>@endauth
<main>@yield('body')</main></body></html>
