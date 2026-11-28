(function () {
  var D = window.DFD, esc = D.esc;
  var PRICES = [['', 'Any price'], ['u2', 'Under 2,000'], ['m', '2,000 – 3,000'], ['o3', 'Over 3,000']];
  var SORTS = [['name', 'Name'], ['lo', 'Price: low to high'], ['hi', 'Price: high to low'], ['stock', 'Most in stock']];
  function inBand(p, b) { if (!b) return true; if (!D.priced(p)) return false; return b === 'u2' ? p.price < 2000 : b === 'm' ? p.price >= 2000 && p.price <= 3000 : p.price > 3000; }
  function filter(q) {
    var term = (q.q || '').toLowerCase().trim();
    var list = D.products.filter(function (p) {
      if (q.cat && p.category !== q.cat) return false;
      if (q.top && p.top !== q.top) return false;
      if (!inBand(p, q.price)) return false;
      if (q.stock === '1' && p.stock <= 0) return false;
      if (term && (p.name + ' ' + D.catName(p.category) + ' ' + p.description).toLowerCase().indexOf(term) < 0) return false;
      return true;
    });
    var s = q.sort || 'name';
    list.sort(function (a, b) {
      if (s === 'lo' || s === 'hi') { var pa = D.priced(a) ? a.price : (s === 'lo' ? 1e12 : -1), pb = D.priced(b) ? b.price : (s === 'lo' ? 1e12 : -1); return s === 'lo' ? pa - pb : pb - pa; }
      if (s === 'stock') return b.stock - a.stock;
      return a.name.localeCompare(b.name);
    });
    return list;
  }
  D.route(/^\/store$/, function (app, m, q) {
    var h = D.crumbs([{ label: 'Home', href: '#/' }, { label: 'Store' }]) + '<h1 class="pagehead">Store</h1>';
    h += '<div class="shop"><aside class="filters glass" id="filters" aria-label="Filters"><div class="fhead"><h2>Filters</h2><button class="ftoggle" id="ftoggle" type="button" aria-expanded="true" aria-controls="fin">Show / Hide</button></div><div class="fwrap"><div class="fin" id="fin">';
    h += '<div class="grp"><span class="lb" id="lb-cat">Category</span><div class="chips" role="group" aria-labelledby="lb-cat"><button class="chip" type="button" data-k="cat" data-v="" aria-pressed="' + (!q.cat) + '">All</button>' +
      D.categories.map(function (c) { return '<button class="chip" type="button" data-k="cat" data-v="' + c.id + '" aria-pressed="' + (q.cat === c.id) + '">' + esc(c.name) + '</button>'; }).join('') + '</div></div>';
    h += '<div class="grp"><span class="lb" id="lb-price">Price (BDT)</span><div class="chips" role="group" aria-labelledby="lb-price">' +
      PRICES.map(function (p) { return '<button class="chip" type="button" data-k="price" data-v="' + p[0] + '" aria-pressed="' + ((q.price || '') === p[0]) + '">' + p[1] + '</button>'; }).join('') + '</div></div>';
    h += '<div class="grp"><label class="check" style="display:flex;gap:10px;align-items:center;font-weight:500"><input type="checkbox" id="f-stock" style="width:20px;height:20px;accent-color:#a3000a"' + (q.stock === '1' ? ' checked' : '') + '> In stock only</label></div>';
    h += '<div class="grp"><label class="lb" for="sort">Sort by</label><select class="sel" id="sort">' + SORTS.map(function (s) { return '<option value="' + s[0] + '"' + ((q.sort || 'name') === s[0] ? ' selected' : '') + '>' + s[1] + '</option>'; }).join('') + '</select></div>';
    h += '<a class="btn line" id="reset" href="#/store" role="button">Clear filters</a></div></div></aside>';
    h += '<section class="listing" aria-label="Products"><p class="result" id="result" role="status"></p><div class="grid" id="grid"></div></section></div>';
    app.innerHTML = h;
    var list = filter(q);
    var what = [q.top && D.tops[q.top] ? D.tops[q.top] : '', q.cat ? D.catName(q.cat) : '', q.q ? '“' + q.q + '”' : ''].filter(Boolean).join(' · ');
    document.getElementById('result').textContent = list.length + (list.length === 1 ? ' product' : ' products') + (what ? ' · ' + what : '');
    document.getElementById('grid').innerHTML = list.length ? list.map(D.card).join('') :
      '<div class="empty"><b>No products match</b><br>Try another search or clear the filters.<br><a class="btn" href="#/store" style="margin-top:12px">Clear filters</a></div>';
    function go(patch) { var n = Object.assign({}, q, patch); if ('cat' in patch) n.top = ''; location.hash = D.href('/store', n); }
    app.querySelectorAll('.chip').forEach(function (c) { c.addEventListener('click', function () { go({ [c.dataset.k]: c.dataset.v }); }); });
    document.getElementById('sort').addEventListener('change', function (e) { go({ sort: e.target.value === 'name' ? '' : e.target.value }); });
    document.getElementById('f-stock').addEventListener('change', function (e) { go({ stock: e.target.checked ? '1' : '' }); });
    var f = document.getElementById('filters'), t = document.getElementById('ftoggle');
    if (window.matchMedia('(max-width: 980px)').matches) { f.classList.add('closed'); t.setAttribute('aria-expanded', 'false'); }
    t.addEventListener('click', function () { var c = f.classList.toggle('closed'); t.setAttribute('aria-expanded', String(!c)); });
    return 'Store';
  });
})();
