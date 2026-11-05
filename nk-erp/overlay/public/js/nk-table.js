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

// Wide tables: click-and-drag (mouse) or swipe (touch) to scroll sideways. Links, buttons and inputs still work.
(function () {
  document.querySelectorAll('.dt-wrap').forEach(function (w) {
    if (w.scrollWidth <= w.clientWidth + 2) return;
    w.classList.add('drag');
    var down = false, moved = false, x0 = 0, s0 = 0;
    w.addEventListener('mousedown', function (e) {
      if (e.button !== 0 || e.target.closest('a,button,input,select,textarea,label,summary,[contenteditable]')) return;
      down = true; moved = false; x0 = e.pageX; s0 = w.scrollLeft;
    });
    window.addEventListener('mousemove', function (e) {
      if (!down) return;
      var dx = e.pageX - x0;
      if (!moved && Math.abs(dx) < 5) return;
      moved = true; w.classList.add('dragging'); w.scrollLeft = s0 - dx; e.preventDefault();
    });
    window.addEventListener('mouseup', function () { if (down) { down = false; w.classList.remove('dragging'); } });
    w.addEventListener('click', function (e) { if (moved) { e.preventDefault(); e.stopPropagation(); moved = false; } }, true);
  });
})();
