// Click-to-sort for every .dt table (sorts the rows on this page; server filters and paging keep working).
(function () {
  function val(td) {
    var t = (td ? td.innerText : '').trim();
    var n = t.replace(/[৳,+\s]/g, '').replace('−', '-');
    return /^-?\d+(\.\d+)?$/.test(n) ? parseFloat(n) : t.toLowerCase();
  }
  document.querySelectorAll('table.dt').forEach(function (table) {
    var body = table.tBodies[0];
    if (!body || body.rows.length < 2) return;
    table.querySelectorAll('thead th').forEach(function (th, i) {
      if (th.classList.contains('no-sort') || !th.textContent.trim()) return;
      th.classList.add('sortable'); th.tabIndex = 0; th.setAttribute('aria-sort', 'none');
      function go() {
        var dir = th.getAttribute('aria-sort') === 'ascending' ? -1 : 1;
        table.querySelectorAll('thead th').forEach(function (o) { if (o !== th && o.classList.contains('sortable')) o.setAttribute('aria-sort', 'none'); });
        var rows = Array.prototype.slice.call(body.rows).filter(function (r) { return r.cells.length > i; });
        rows.sort(function (a, b) { var x = val(a.cells[i]), y = val(b.cells[i]); return (x > y ? 1 : x < y ? -1 : 0) * dir; });
        rows.forEach(function (r) { body.appendChild(r); });
        th.setAttribute('aria-sort', dir === 1 ? 'ascending' : 'descending');
      }
      th.addEventListener('click', go);
      th.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); go(); } });
    });
  });
})();
