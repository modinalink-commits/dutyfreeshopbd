/* Global Export component: every data table in the admin is exportable by default.
   Filter -> Select -> Export PDF / Excel / DOCX / Print (+CSV). One component, no per-module code. */
(function (root, factory) {
  var api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  else { root.dfExport = api; if (typeof document !== 'undefined') api.boot(); }
})(typeof window !== 'undefined' ? window : this, function () {
  'use strict';
  var enc = typeof TextEncoder !== 'undefined' ? new TextEncoder() : null;
  function u8(s) { return enc ? enc.encode(s) : Buffer.from(s, 'utf8'); }

  /* ---------- minimal ZIP (store) ---------- */
  var T = null;
  function crc32(b) {
    if (!T) { T = new Uint32Array(256); for (var n = 0; n < 256; n++) { var c = n; for (var k = 0; k < 8; k++) c = c & 1 ? 0xEDB88320 ^ (c >>> 1) : c >>> 1; T[n] = c >>> 0; } }
    var x = 0xFFFFFFFF; for (var i = 0; i < b.length; i++) x = T[(x ^ b[i]) & 255] ^ (x >>> 8); return (x ^ 0xFFFFFFFF) >>> 0;
  }
  function zip(files) {
    var parts = [], central = [], off = 0, d = new Date();
    var dt = ((d.getHours() << 11) | (d.getMinutes() << 5) | (d.getSeconds() >> 1)) & 0xFFFF, dd = (((d.getFullYear() - 1980) << 9) | ((d.getMonth() + 1) << 5) | d.getDate()) & 0xFFFF;
    files.forEach(function (f) {
      var name = u8(f.name), data = u8(f.data), crc = crc32(data);
      var h = new DataView(new ArrayBuffer(30)); h.setUint32(0, 0x04034b50, true); h.setUint16(4, 20, true); h.setUint16(6, 0x0800, true); h.setUint16(8, 0, true); h.setUint16(10, dt, true); h.setUint16(12, dd, true); h.setUint32(14, crc, true); h.setUint32(18, data.length, true); h.setUint32(22, data.length, true); h.setUint16(26, name.length, true); h.setUint16(28, 0, true);
      var c = new DataView(new ArrayBuffer(46)); c.setUint32(0, 0x02014b50, true); c.setUint16(4, 20, true); c.setUint16(6, 20, true); c.setUint16(8, 0x0800, true); c.setUint16(10, 0, true); c.setUint16(12, dt, true); c.setUint16(14, dd, true); c.setUint32(16, crc, true); c.setUint32(20, data.length, true); c.setUint32(24, data.length, true); c.setUint16(28, name.length, true); c.setUint32(42, off, true);
      parts.push(new Uint8Array(h.buffer), name, data); central.push(new Uint8Array(c.buffer), name);
      off += 30 + name.length + data.length;
    });
    var csz = 0; central.forEach(function (p) { csz += p.length; });
    var e = new DataView(new ArrayBuffer(22)); e.setUint32(0, 0x06054b50, true); e.setUint16(8, files.length, true); e.setUint16(10, files.length, true); e.setUint32(12, csz, true); e.setUint32(16, off, true);
    return parts.concat(central, [new Uint8Array(e.buffer)]);
  }

  /* ---------- builders ---------- */
  function esc(s) { return String(s == null ? '' : s).replace(/[\u0000-\u0008\u000B\u000C\u000E-\u001F]/g, '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
  function col(i) { var s = ''; i++; while (i > 0) { var m = (i - 1) % 26; s = String.fromCharCode(65 + m) + s; i = Math.floor((i - 1) / 26); } return s; }
  function num(v) { return /^-?[1-9]\d{0,14}(\.\d+)?$/.test(v) || /^0(\.\d+)?$/.test(v) ? parseFloat(v) : (/^-?[1-9]\d{0,2}(,\d{3})+(\.\d+)?$/.test(v) ? parseFloat(v.replace(/,/g, '')) : null); }

  function xlsxParts(title, head, rows) {
    var x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols>';
    head.forEach(function (h, i) { var w = Math.min(60, Math.max(10, h.length + 4)); rows.slice(0, 200).forEach(function (r) { w = Math.max(w, Math.min(60, String(r[i] || '').length + 2)); }); x += '<col min="' + (i + 1) + '" max="' + (i + 1) + '" width="' + w + '" customWidth="1"/>'; });
    x += '</cols><sheetData>';
    function cell(r, c, v, s) { var ref = col(c) + r, n = s ? null : num(v); return n !== null ? '<c r="' + ref + '"><v>' + n + '</v></c>' : '<c r="' + ref + '" t="inlineStr"' + (s ? ' s="1"' : '') + '><is><t xml:space="preserve">' + esc(v) + '</t></is></c>'; }
    x += '<row r="1">' + head.map(function (h, i) { return cell(1, i, h, 1); }).join('') + '</row>';
    rows.forEach(function (r, ri) { x += '<row r="' + (ri + 2) + '">' + r.map(function (v, i) { return cell(ri + 2, i, v, 0); }).join('') + '</row>'; });
    x += '</sheetData><autoFilter ref="A1:' + col(Math.max(0, head.length - 1)) + (rows.length + 1) + '"/></worksheet>';
    var sheet = String(title || 'Sheet1').replace(/[\\\/\?\*\[\]:]/g, ' ').slice(0, 31) || 'Sheet1';
    return zip([
      { name: '[Content_Types].xml', data: '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>' },
      { name: '_rels/.rels', data: '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>' },
      { name: 'xl/workbook.xml', data: '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' + esc(sheet) + '" sheetId="1" r:id="rId1"/></sheets></workbook>' },
      { name: 'xl/_rels/workbook.xml.rels', data: '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>' },
      { name: 'xl/styles.xml', data: '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFFE7D6"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>' },
      { name: 'xl/worksheets/sheet1.xml', data: x }
    ]);
  }

  function docxParts(title, head, rows) {
    var w = Math.floor(15000 / Math.max(1, head.length));
    function tc(t, b) { return '<w:tc><w:tcPr><w:tcW w:w="' + w + '" w:type="dxa"/>' + (b ? '<w:shd w:val="clear" w:color="auto" w:fill="FFE7D6"/>' : '') + '</w:tcPr><w:p><w:r><w:rPr>' + (b ? '<w:b/>' : '') + '<w:sz w:val="18"/></w:rPr><w:t xml:space="preserve">' + esc(t) + '</w:t></w:r></w:p></w:tc>'; }
    var tbl = '<w:tbl><w:tblPr><w:tblW w:w="0" w:type="auto"/><w:tblBorders>' + ['top', 'left', 'bottom', 'right', 'insideH', 'insideV'].map(function (s) { return '<w:' + s + ' w:val="single" w:sz="4" w:space="0" w:color="BBBBBB"/>'; }).join('') + '</w:tblBorders></w:tblPr><w:tr><w:trPr><w:tblHeader/></w:trPr>' + head.map(function (h) { return tc(h, 1); }).join('') + '</w:tr>' + rows.map(function (r) { return '<w:tr>' + r.map(function (v) { return tc(v, 0); }).join('') + '</w:tr>'; }).join('') + '</w:tbl>';
    var body = '<w:p><w:r><w:rPr><w:b/><w:sz w:val="32"/></w:rPr><w:t xml:space="preserve">' + esc(title) + '</w:t></w:r></w:p><w:p><w:r><w:rPr><w:color w:val="666666"/><w:sz w:val="18"/></w:rPr><w:t>' + esc(new Date().toLocaleString()) + ' | ' + rows.length + ' rows</w:t></w:r></w:p>' + tbl + '<w:sectPr><w:pgSz w:w="16838" w:h="11906" w:orient="landscape"/><w:pgMar w:top="720" w:right="720" w:bottom="720" w:left="720" w:header="400" w:footer="400" w:gutter="0"/></w:sectPr>';
    return zip([
      { name: '[Content_Types].xml', data: '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>' },
      { name: '_rels/.rels', data: '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>' },
      { name: 'word/document.xml', data: '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>' + body + '</w:body></w:document>' }
    ]);
  }

  function csvText(head, rows) {
    function q(v) { v = String(v == null ? '' : v); return /[",\n\r]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v; }
    return '﻿' + [head].concat(rows).map(function (r) { return r.map(q).join(','); }).join('\r\n');
  }

  function printHtml(title, head, rows) {
    return '<!doctype html><html><head><meta charset="utf-8"><title>' + esc(title) + '</title><style>@page{size:A4 landscape;margin:10mm}body{font-family:system-ui,"Noto Sans Bengali",Arial,sans-serif;color:#111;margin:0}h1{font-size:18px;margin:0 0 4px}.m{color:#666;font-size:11px;margin-bottom:10px}table{border-collapse:collapse;width:100%;font-size:10.5px}th,td{border:1px solid #bbb;padding:4px 6px;text-align:left;vertical-align:top;word-break:break-word}th{background:#FFE7D6}tr{page-break-inside:avoid}thead{display:table-header-group}</style></head><body><h1>' + esc(title) + '</h1><div class="m">' + esc(new Date().toLocaleString()) + ' | ' + rows.length + ' rows</div><table><thead><tr>' + head.map(function (h) { return '<th>' + esc(h) + '</th>'; }).join('') + '</tr></thead><tbody>' + rows.map(function (r) { return '<tr>' + r.map(function (v) { return '<td>' + esc(v) + '</td>'; }).join('') + '</tr>'; }).join('') + '</tbody></table></body></html>';
  }

  var api = { zip: zip, xlsxParts: xlsxParts, docxParts: docxParts, csvText: csvText, printHtml: printHtml, crc32: crc32 };
  if (typeof document === 'undefined') return api;

  /* ---------- DOM component ---------- */
  var MIME = { xlsx: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', docx: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', csv: 'text/csv;charset=utf-8' };
  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function stamp() { var d = new Date(); return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + '_' + pad(d.getHours()) + '-' + pad(d.getMinutes()); }
  function slug(s) { return String(s || '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''); }
  function moduleName(idx, tbl) {
    var segs = location.pathname.split('/').filter(function (x) { return x && !/^\d+$/.test(x) && x !== 'admin' && x !== 'm'; });
    var n = segs.length ? segs[segs.length - 1] : 'dashboard';
    if (idx > 0) { var h = tbl.closest('.card,section,div'); var hh = prevHeading(tbl); if (hh) n += '-' + slug(hh).slice(0, 24); else n += '-' + (idx + 1); }
    return n.replace(/_/g, '-');
  }
  function prevHeading(tbl) { var e = tbl.closest('.card') || tbl; var p = e.previousElementSibling; for (var i = 0; i < 3 && p; i++, p = p.previousElementSibling) if (/^H[1-4]$/.test(p.tagName)) return p.textContent.trim(); return ''; }
  function pageTitle() { var h = document.querySelector('main h1'); return (h ? h.textContent : document.title || 'Export').trim(); }
  function txt(c) { return (c.innerText != null ? c.innerText : c.textContent).replace(/ /g, ' ').replace(/[ \t]*\n[ \t\n]*/g, ' | ').replace(/\s+/g, ' ').replace(/^\| /, '').trim(); }
  function isHidden(el) { return !el || (el.offsetWidth === 0 && el.offsetHeight === 0 && getComputedStyle(el).position !== 'fixed') || getComputedStyle(el).display === 'none'; }

  function readTable(tbl) {
    var htr = tbl.tHead && tbl.tHead.rows[0] ? tbl.tHead.rows[0] : tbl.rows[0];
    var heads = [].slice.call(htr.cells).map(function (c, i) { return { i: i, sel: c.classList.contains('dfx-c'), name: txt(c) || '', hidden: isHidden(c) }; });
    var body = [].slice.call(tbl.rows).filter(function (r) { return r !== htr && r.parentNode.tagName !== 'THEAD' && r.cells.length > 1 && !r.classList.contains('dfx-skip'); });
    return { heads: heads, rows: body, htr: htr };
  }
  function actionCol(h, rows) { // empty header whose cells only hold buttons/forms/links => action column
    if (h.sel) return true; if (h.name) return false;
    return rows.slice(0, 20).every(function (r) { var c = r.cells[h.i]; return !c || (!txt(c)) || c.querySelector('form,button'); });
  }

  function extract(tbl, opts) {
    var d = readTable(tbl), cols = d.heads.filter(function (h) { return !actionCol(h, d.rows); });
    if (opts.cols === 'visible') cols = cols.filter(function (h) { return !h.hidden; });
    else if (opts.cols === 'selected') cols = cols.filter(function (h) { return opts.pick.indexOf(h.i) > -1; });
    var rows = d.rows; if (opts.onlySel) rows = rows.filter(function (r) { var b = r.querySelector('.dfx-c input'); return b && b.checked; });
    return { head: cols.map(function (h) { return h.name || 'Column ' + (h.i + 1); }), rows: rows.map(function (r) { return cols.map(function (h) { return r.cells[h.i] ? txt(r.cells[h.i]) : ''; }); }), cols: cols };
  }

  function pageCount() {
    var max = 1; [].forEach.call(document.querySelectorAll('a[href*="page="]'), function (a) { var m = /[?&]page=(\d+)/.exec(a.getAttribute('href')); if (m && +m[1] > max) max = +m[1]; }); return max;
  }
  function fetchAllPages(tidx, opts, done, fail) {
    var max = Math.min(pageCount(), 300), cur = +(new URLSearchParams(location.search).get('page') || 1), out = null, n = 1;
    (function next() {
      if (n > max) return done(out);
      if (n === cur) { var cd = extract(document.querySelectorAll('table.dfx')[tidx], opts); out = out ? { head: out.head, rows: out.rows.concat(cd.rows) } : cd; n++; return next(); }
      var u = new URL(location.href); u.searchParams.set('page', n);
      fetch(u.href, { credentials: 'same-origin' }).then(function (r) { return r.text(); }).then(function (h) {
        var doc = new DOMParser().parseFromString(h, 'text/html'), t = doc.querySelectorAll('table.dfx-src')[tidx];
        if (!t) { var all = [].filter.call(doc.querySelectorAll('main table'), isDataTable); t = all[tidx]; }
        if (t) { var live = document.querySelectorAll('table.dfx')[tidx], rd = readTable(t), lheads = readTable(live).heads, pick = opts.cols === 'selected' ? opts.pick : null;
          var cols = lheads.filter(function (h) { return !actionCol(h, readTable(live).rows); });
          if (opts.cols === 'visible') cols = cols.filter(function (h) { return !h.hidden; }); else if (pick) cols = cols.filter(function (h) { return pick.indexOf(h.i) > -1; });
          // remote doc has no injected checkbox column, so shift indexes
          var shift = live.querySelector('th.dfx-c') ? -1 : 0, rows = rd.rows.map(function (r) { return cols.map(function (h) { var c = r.cells[h.i + shift]; return c ? txt(c) : ''; }); });
          out = out ? { head: out.head, rows: out.rows.concat(rows) } : { head: cols.map(function (h) { return h.name; }), rows: rows };
        }
        n++; next();
      }).catch(function (e) { fail(e); });
    })();
  }

  function download(name, parts, mime) { var b = new Blob(parts, { type: mime }), a = document.createElement('a'); a.href = URL.createObjectURL(b); a.download = name; document.body.appendChild(a); a.click(); setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 1500); }
  function printWin(html, auto) { var w = window.open('', '_blank'); if (!w) { alert('Allow pop-ups to print / save as PDF.'); return; } w.document.open(); w.document.write(html); w.document.close(); w.focus(); setTimeout(function () { try { w.print(); } catch (e) {} }, auto ? 400 : 400); }

  function isDataTable(t) {
    if (t.classList.contains('no-export') || t.closest('.no-export,#dfFab,.dfx-print,[data-no-export]')) return false;
    if (t.querySelector('table')) return false;
    var htr = t.tHead && t.tHead.rows[0] ? t.tHead.rows[0] : null; if (!htr || htr.cells.length < 2) return false;
    return t.rows.length >= 2;
  }

  function injectStyle() {
    if (document.getElementById('dfxCss')) return; var s = document.createElement('style'); s.id = 'dfxCss';
    s.textContent = '.dfx-bar{display:flex;justify-content:flex-end;align-items:center;gap:8px;margin:0 0 8px}.dfx-btn{border:1px solid #FFD7BD;background:#fff;color:#C2410C;border-radius:8px;padding:5px 12px;font:600 12.5px system-ui;cursor:pointer}.dfx-btn:hover{background:#FFF4EC}.dfx-menu{position:fixed;z-index:99999;background:#fff;border:1px solid #E5E7EB;border-radius:10px;box-shadow:0 12px 32px rgba(0,0,0,.18);padding:10px;width:250px;font:13px system-ui;color:#111;max-height:80vh;overflow:auto}.dfx-menu h4{margin:0 0 6px;font-size:12px;color:#6B7280;font-weight:600}.dfx-menu label{display:flex;gap:6px;align-items:center;margin:3px 0;font-size:12.5px;cursor:pointer}.dfx-menu input{width:auto;margin:0}.dfx-menu .dfx-f{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:8px}.dfx-menu .dfx-f button{border:1px solid #E5E7EB;background:#F9FAFB;border-radius:7px;padding:7px;font:600 12.5px system-ui;cursor:pointer}.dfx-menu .dfx-f button:hover{background:#FFF4EC;border-color:#FDBA74}.dfx-menu .dfx-pick{max-height:140px;overflow:auto;border:1px solid #F1F5F9;border-radius:6px;padding:4px 6px;margin:4px 0 6px}.dfx-c{width:28px;text-align:center}.dfx-c input{width:15px;height:15px;cursor:pointer}.dfx-note{font-size:11px;color:#6B7280;margin-top:6px}';
    document.head.appendChild(s);
  }

  var openMenu = null;
  function closeMenu() { if (openMenu) { openMenu.remove(); openMenu = null; } }
  document.addEventListener('click', function (e) { if (openMenu && !e.target.closest('.dfx-menu,.dfx-btn')) closeMenu(); });

  function addSelection(tbl) {
    var d = readTable(tbl); if (!d.htr || d.htr.querySelector('.dfx-c')) return;
    var th = document.createElement('th'); th.className = 'dfx-c'; th.innerHTML = '<input type="checkbox" title="Select all rows" aria-label="Select all rows">'; d.htr.insertBefore(th, d.htr.cells[0]);
    d.rows.forEach(function (r) { var td = document.createElement('td'); td.className = 'dfx-c'; td.innerHTML = '<input type="checkbox" aria-label="Select row">'; r.insertBefore(td, r.cells[0]); });
    th.firstChild.addEventListener('change', function () { d.rows.forEach(function (r) { var b = r.querySelector('.dfx-c input'); if (b) b.checked = th.firstChild.checked; }); });
    d.htr.querySelectorAll('.dfx-c input').forEach(function (b) { b.addEventListener('click', function (e) { e.stopPropagation(); }); });
    d.rows.forEach(function (r) { var b = r.querySelector('.dfx-c input'); if (b) b.addEventListener('click', function (e) { e.stopPropagation(); }); });
    tbl.addEventListener('click', function (e) { if (e.target.closest('.dfx-c')) e.stopPropagation(); }, true);
  }

  function attach(tbl, idx) {
    tbl.classList.add('dfx'); addSelection(tbl);
    var host = tbl.closest('.card') || tbl.parentNode, bar = document.createElement('div'); bar.className = 'dfx-bar';
    var btn = document.createElement('button'); btn.type = 'button'; btn.className = 'dfx-btn'; btn.textContent = 'Export ▾'; bar.appendChild(btn);
    if (host !== tbl.parentNode) host.insertBefore(bar, host.firstChild); else tbl.parentNode.insertBefore(bar, tbl);
    // the old per-module PDF/Excel/Word/CSV links are replaced by this one component
    [].forEach.call(document.querySelectorAll('a[href*="/export/pdf"],a[href*="/export/xls"],a[href*="/export/doc"],a[href*="/export/csv"]'), function (a) { a.style.display = 'none'; });
    btn.addEventListener('click', function (e) { e.stopPropagation(); if (openMenu) { closeMenu(); return; } showMenu(tbl, idx, btn); });
  }

  function showMenu(tbl, idx, btn) {
    var d = readTable(tbl), selN = d.rows.filter(function (r) { var b = r.querySelector('.dfx-c input'); return b && b.checked; }).length, pages = pageCount();
    var m = document.createElement('div'); m.className = 'dfx-menu';
    var names = d.heads.filter(function (h) { return !actionCol(h, d.rows); });
    m.innerHTML = '<h4>' + (selN ? 'Export Selected (' + selN + ')' : 'Export Current Result (' + d.rows.length + ' rows)') + '</h4>' +
      '<h4>Columns</h4><label><input type="radio" name="dfxc" value="all"> All Columns</label><label><input type="radio" name="dfxc" value="visible" checked> Visible Columns</label><label><input type="radio" name="dfxc" value="selected"> Selected Columns</label>' +
      '<div class="dfx-pick" style="display:none">' + names.map(function (h) { return '<label><input type="checkbox" data-i="' + h.i + '" checked> ' + esc(h.name || 'Column ' + (h.i + 1)) + '</label>'; }).join('') + '</div>' +
      (pages > 1 && !selN ? '<label><input type="checkbox" class="dfx-all"> All ' + pages + ' pages of this filter</label>' : '') +
      '<div class="dfx-f"><button data-f="pdf">PDF</button><button data-f="xlsx">Excel</button><button data-f="docx">DOCX</button><button data-f="print">Print</button><button data-f="csv" style="grid-column:span 2;font-weight:500">CSV (optional)</button></div><div class="dfx-note"></div>';
    document.body.appendChild(m); openMenu = m;
    var r = btn.getBoundingClientRect(); m.style.top = Math.min(innerHeight - m.offsetHeight - 8, r.bottom + 6) + 'px'; m.style.left = Math.max(8, Math.min(innerWidth - 260, r.right - 250)) + 'px';
    var pick = m.querySelector('.dfx-pick');
    [].forEach.call(m.querySelectorAll('input[name=dfxc]'), function (x) { x.addEventListener('change', function () { pick.style.display = x.value === 'selected' && x.checked ? 'block' : 'none'; }); });
    m.addEventListener('click', function (e) {
      var b = e.target.closest('button[data-f]'); if (!b) return;
      var f = b.dataset.f, opts = { cols: m.querySelector('input[name=dfxc]:checked').value, pick: [].map.call(pick.querySelectorAll('input:checked'), function (c) { return +c.dataset.i; }), onlySel: selN > 0 };
      var all = m.querySelector('.dfx-all'), note = m.querySelector('.dfx-note');
      var finish = function (data) {
        if (!data || !data.rows.length) { note.textContent = 'No rows to export.'; return; }
        var base = moduleName(idx, tbl), file = base + '_' + stamp(), title = pageTitle() + (idx > 0 && prevHeading(tbl) ? ' - ' + prevHeading(tbl) : '');
        if (f === 'xlsx') download(file + '.xlsx', xlsxParts(base, data.head, data.rows), MIME.xlsx);
        else if (f === 'docx') download(file + '.docx', docxParts(title, data.head, data.rows), MIME.docx);
        else if (f === 'csv') download(file + '.csv', [csvText(data.head, data.rows)], MIME.csv);
        else { var html = printHtml(title, data.head, data.rows); printWin(html, f === 'pdf'); }
        closeMenu();
      };
      if (all && all.checked) { note.textContent = 'Collecting all pages...'; fetchAllPages(idx, opts, finish, function () { note.textContent = 'Could not load every page. Try again.'; }); }
      else finish(extract(tbl, opts));
    });
  }

  function scan() {
    var list = [].filter.call(document.querySelectorAll('main table'), isDataTable);
    list.forEach(function (t, i) { if (!t.classList.contains('dfx')) attach(t, i); });
  }
  api.scan = scan;
  api.boot = function () {
    injectStyle(); var run = function () { try { scan(); } catch (e) { if (window.console) console.warn('dfExport', e); } };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run); else run();
    var t; new MutationObserver(function () { clearTimeout(t); t = setTimeout(run, 400); }).observe(document.body, { childList: true, subtree: true });
  };
  return api;
});
