{{-- Mother Commerce — Grid-Based Enterprise UI (one shared design system). Included once by layout.blade.php after the sidebar.
     Colours are CSS variables only: change them in the :root block below and the whole admin follows. Visual layer only — no markup, route or logic is changed. --}}
<style id="admin-grid">
/* ===== 1. design tokens (placeholders = current palette; the owner will set final colours here) ===== */
:root{
  --primary:var(--o,#FF5A00);--primary-soft:#FFF1E8;--secondary:#374151;
  --bg:#FFFBF8;--surface:#FFFFFF;--surface-2:#F8FAFC;
  --text-primary:#111827;--text-secondary:#374151;--text-muted:#6B7280;
  --border-color:#D5DBE3;--border-soft:#E8ECF1;
  --row-hover:#FFF8F3;--row-selected:#FFF1E8;
  --success:#16A34A;--warning:#D97706;--danger:#DC2626;--info:#2563EB;
  --g-radius:8px;--g-ctl-h:34px;--g-btn-h:32px;
  --g-font:"Inter","Hind Siliguri","Noto Sans Bengali","Segoe UI",system-ui,Arial,sans-serif;
}
body{font-family:var(--g-font)}
main{padding:14px 16px;color:var(--text-primary);line-height:1.35}

/* ===== 2. page shell: cards become bordered panels, no floating shadows ===== */
main .card{background:var(--surface);border:1px solid var(--border-color);border-radius:var(--g-radius);box-shadow:none;padding:10px 12px;margin-bottom:10px}
main h1{font-size:17px;font-weight:700;color:var(--text-primary)}
main h2{font-size:14px}main h3{font-size:13px}

/* ===== 3. data tables: bordered grid, compact rows, sticky header ===== */
main table:not(.ordt):has(th){border-collapse:separate;border-spacing:0;border:1px solid var(--border-color);border-radius:var(--g-radius);background:var(--surface)}
main table:not(.ordt):has(th) th{position:sticky;top:0;z-index:2;background:var(--surface-2);color:var(--text-secondary);font-size:11.5px;font-weight:700;text-align:left;padding:7px 9px;border-bottom:1px solid var(--border-color);border-right:1px solid var(--border-soft);white-space:nowrap}
main table:not(.ordt):has(th) td{padding:7px 9px;border-top:0;border-bottom:1px solid var(--border-soft);border-right:1px solid var(--border-soft);vertical-align:middle;color:var(--text-primary)}
main table:not(.ordt):has(th) th:last-child,main table:not(.ordt):has(th) td:last-child{border-right:0}
main table:not(.ordt):has(th) tbody tr:last-child>td{border-bottom:0}
main table:not(.ordt):has(th) tbody tr:hover>td{background:var(--row-hover)}
main table:not(.ordt):has(th) tbody tr:has(input[type=checkbox]:checked)>td{background:var(--row-selected)}
main table:not(.ordt):has(th) thead tr:first-child th:first-child{border-top-left-radius:calc(var(--g-radius) - 1px)}
main table:not(.ordt):has(th) thead tr:first-child th:last-child{border-top-right-radius:calc(var(--g-radius) - 1px)}
main table:not(.ordt):has(th) tfoot td,main table:not(.ordt):has(th) tr.g-total>td{background:var(--surface-2);font-weight:700;border-top:1px solid var(--border-color);text-align:right}
main table:not(.ordt):has(th) tr.g-group>td{background:var(--surface-2);font-weight:700;border-bottom:1px solid var(--border-color);text-align:left}
main table td table{border:0;border-radius:0}main table td table th,main table td table td{border-right:0}
main td.num,main th.num{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
main td.ctr,main th.ctr{text-align:center}
main .g-empty,main td.empty{color:var(--text-muted);font-size:12px;text-align:center;padding:14px}

/* Orders list (.ordt): the same grid instead of floating coloured cards */
main table.ordt{border-collapse:collapse;border-spacing:0;border:1px solid var(--border-color);background:var(--surface)}
main table.ordt>thead>tr>th{position:sticky;top:0;z-index:3;background:var(--surface-2);color:var(--text-secondary);font-size:11.5px;font-weight:700;text-align:left;padding:7px 9px;border:1px solid var(--border-color);border-top:0;white-space:nowrap}
main table.ordt>tbody>tr>td{background:var(--surface);border:0;border-bottom:1px solid var(--border-soft);border-right:1px solid var(--border-soft);border-radius:0;padding:8px 9px;vertical-align:top}
main table.ordt>tbody>tr>td:first-child{border-left:3px solid var(--border-color);border-radius:0;padding-left:9px}
main table.ordt>tbody>tr>td:last-child{border-right:0;border-radius:0;padding-right:9px}
main table.ordt>tbody>tr[style*="FFFBEB"]>td{background:#FEF3C7}main table.ordt>tbody>tr[style*="FFFBEB"]>td:first-child{border-left-color:var(--warning)}
main table.ordt>tbody>tr:hover>td{background:var(--row-hover)}main table.ordt>tbody>tr:hover{filter:none}
main table.ordt>tbody>tr:has(input[type=checkbox]:checked)>td{background:var(--row-selected)}main table.ordt>tbody>tr:has(input[type=checkbox]:checked)>td:first-child{border-left-color:var(--primary)}
main table.ordt>tbody>tr>td.action-column,main table.ordt>thead>tr>th.action-column{text-align:right}

/* ===== 4. toolbar / filters / controls: one height, one look ===== */
.g-toolbar{display:flex;flex-wrap:wrap;gap:8px;align-items:center;padding:8px 10px;background:var(--surface-2);border:1px solid var(--border-color);border-bottom:0;border-radius:var(--g-radius) var(--g-radius) 0 0}
.g-toolbar+table,.g-toolbar+.otw{border-top-left-radius:0;border-top-right-radius:0}
main input:not([type=checkbox]):not([type=radio]):not([type=file]):not([type=range]),main select{height:var(--g-ctl-h);border:1px solid var(--border-color);border-radius:6px;background:var(--surface);color:var(--text-primary);font-size:12.5px}
main textarea{border:1px solid var(--border-color);border-radius:6px;font-size:12.5px}
main input:focus-visible,main select:focus-visible,main textarea:focus-visible{outline:0;border-color:var(--primary);box-shadow:0 0 0 3px rgba(255,90,0,.14)}
main .btn:focus-visible,main button:focus-visible,main a:focus-visible,aside a:focus-visible,aside summary:focus-visible{outline:2px solid var(--primary);outline-offset:2px}
main .btn,main button{min-height:var(--g-btn-h);border-radius:6px}
main .btn.g,main button.g{background:var(--surface);color:var(--text-primary);border:1px solid var(--border-color)}
.ocb{border:1px solid var(--border-color)!important;border-radius:var(--g-radius)!important;box-shadow:none!important;background:var(--surface)!important}

/* ===== 5. status pills: one style everywhere ===== */
main .pill,main table.ordt .pill{display:inline-flex;align-items:center;min-height:20px;border-radius:5px;padding:1px 8px;font-size:11px;font-weight:700;line-height:1.3;white-space:nowrap}

/* ===== 6. pagination: compact grid strip ===== */
.pgx{margin:8px 0 4px;padding:6px 8px;border:1px solid var(--border-color);border-radius:var(--g-radius);background:var(--surface-2)}
.pgx .b{height:30px;min-width:30px;border-radius:6px;padding:0 10px;font-size:12.5px;border-color:var(--border-color);transform:none!important}
.pgx .b.on{box-shadow:none}.pgx .ps select{height:30px}

/* ===== 7. sidebar: every menu is a bordered row, submenus hang off a guide line ===== */
aside nav{gap:0;border-top:1px solid var(--border-soft)}
aside nav a.nv,aside nav details.mg>summary{border-radius:0;border-bottom:1px solid var(--border-soft);min-height:38px;padding-top:7px;padding-bottom:7px}
aside nav a.nv.on{background:var(--surface-2);box-shadow:inset 3px 0 0 var(--primary)}
aside nav details.mg{margin:0}
aside nav details.mg[open]>summary{background:var(--surface-2);border-bottom-color:var(--border-color)}
aside nav details.mg>a.sub,aside nav details.rpg>summary{position:relative;min-height:31px;margin-left:18px;border-radius:0;border-left:1px solid var(--border-color);border-bottom:1px solid var(--border-soft);padding-left:14px!important;font-size:12px}
aside nav details.mg>a.sub::before,aside nav details.rpg>summary::before{content:'';position:absolute;left:0;top:50%;width:7px;border-top:1px solid var(--border-color)}
aside nav details.rpg a.sub{margin-left:36px;border-radius:0;border-left:1px solid var(--border-color);border-bottom:1px solid var(--border-soft);padding-left:12px!important;min-height:30px}
aside nav a.sub.on{background:var(--surface-2);color:var(--text-primary);font-weight:700;border-left:2px solid var(--primary);box-shadow:none}
aside nav details.mg[open]{border-bottom:0}
aside nav details.mg>a.sub:last-child{border-bottom:1px solid var(--border-color)}

/* ===== 8. responsive: wide tables scroll, nothing is hidden ===== */
@media(max-width:800px){main table:not(.ordt):has(th){display:block;overflow-x:auto;max-width:100%}}
</style>
<script>
/* right-align numeric columns (amount, total, price, qty…) without touching any markup. Columns only; phone numbers and ids with leading zero are left alone. */
(function(){
  var NUM=/^[৳$]?\s*[-+]?[0-9০-৯][0-9০-৯,\.]*\s*(%|৳|tk|taka|টাকা)?$/i;
  function isNum(t){t=(t||'').replace(/\s+/g,' ').trim();return t!==''&&NUM.test(t)&&!/^0\d{9,}$/.test(t.replace(/[,\s]/g,''))}
  function run(){
    document.querySelectorAll('main table').forEach(function(tb){
      if(tb.dataset.gn||tb.closest('td'))return;tb.dataset.gn='1';
      var head=tb.querySelector('thead tr');var rows=[].slice.call(tb.querySelectorAll('tbody>tr'));if(!head||rows.length<2)return;
      var cols=head.children.length;if(rows.slice(0,40).some(function(r){return r.children.length!==cols||[].some.call(r.children,function(c){return c.colSpan>1})}))return;
      for(var i=0;i<cols;i++){
        var n=0,t=0;rows.slice(0,40).forEach(function(r){var c=r.children[i];if(!c||c.children.length&&c.querySelector('input,select,textarea,button,a'))return;var x=c.textContent.trim();if(x==='')return;t++;if(isNum(x))n++});
        if(t>=2&&n/t>=0.8){head.children[i].classList.add('num');rows.forEach(function(r){if(r.children[i])r.children[i].classList.add('num')})}
      }
    });
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',run);else run();
})();
</script>
