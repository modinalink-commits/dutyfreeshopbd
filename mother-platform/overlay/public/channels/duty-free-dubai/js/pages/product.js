(function () {
  var D = window.DFD, esc = D.esc;
  D.route(/^\/product\/([a-z0-9-]+)$/, function (app, m) {
    var p = D.bySlug(m[1]);
    if (!p) { app.innerHTML = '<div class="emptybox"><h1 class="pagehead" style="font-size:1.8rem">Product not found</h1><a class="btn" href="#/store">Back to store</a></div>'; return 'Not found'; }
    var s = D.stockInfo(p), can = D.priced(p) && p.stock > 0, qty = 1;
    var h = D.crumbs([{ label: 'Home', href: '#/' }, { label: 'Store', href: '#/store' }, { label: D.catName(p.category), href: '#/store?cat=' + p.category }, { label: p.name }]);
    h += '<div class="pdp"><div class="gal"><div class="pic">' + D.picHtml(p, true) + '</div></div>';
    h += '<div class="buybox glass"><span class="kind">' + esc(D.catName(p.category)) + '</span><h1>' + esc(p.name) + '</h1>';
    h += '<div class="pp">' + (D.priced(p) ? D.money(p.price) + '<small>' + D.unitLabel(p) + '</small>' : 'Call for price') + '</div>';
    h += '<span class="badges"><span class="stock ' + s.cls + '">' + s.label + '</span><span class="fdel">Free Delivery</span></span><p class="desc">' + esc(p.description) + '</p>';
    if (can) {
      h += '<div class="qrow"><label for="q-val">Quantity</label><div class="step" role="group" aria-label="Quantity"><button type="button" id="q-dec" aria-label="Decrease quantity">−</button><span id="q-val" aria-live="polite">1</span><button type="button" id="q-inc" aria-label="Increase quantity">+</button></div><span class="subtotal">Subtotal <b id="sub">' + D.money(p.price) + '</b></span></div>';
      h += '<div class="pbtns"><button class="btn line" id="add" type="button">Add to cart</button><button class="btn" id="order" type="button">Order now</button></div><p class="note" id="inc" hidden></p><a class="btn line" href="#/cart" id="viewcart" style="display:none">View cart</a>';
    } else if (!D.priced(p)) {
      h += '<div class="note">Price is confirmed by phone.</div><a class="btn" href="tel:+8801871600600">Call for price</a>';
    } else { h += '<button class="btn" type="button" disabled>Out of stock</button>'; }
    h += '<dl class="specs">' + p.specs.map(function (r) { return '<div><dt>' + esc(r[0]) + '</dt><dd>' + esc(r[1]) + '</dd></div>'; }).join('') + '</dl></div></div>';
    var rel = D.products.filter(function (x) { return x.category === p.category && x.id !== p.id; }).slice(0, 4);
    if (rel.length) h += '<section class="related" aria-label="Related products"><h2>More in ' + esc(D.catName(p.category)) + '</h2><div class="grid feat" id="rel"></div></section>';
    app.innerHTML = h;
    if (rel.length) document.getElementById('rel').innerHTML = rel.map(D.card).join('');
    if (can) {
      var v = document.getElementById('q-val'), sub = document.getElementById('sub'), inc = document.getElementById('inc');
      function paint() { v.textContent = qty; sub.textContent = D.money(p.price * qty); }
      document.getElementById('q-dec').addEventListener('click', function () { qty = Math.max(1, qty - 1); paint(); inc.hidden = true; });
      document.getElementById('q-inc').addEventListener('click', function () { if (qty >= p.stock) { inc.hidden = false; inc.textContent = 'Only ' + p.stock + ' in stock.'; return; } qty++; paint(); });
      document.getElementById('order').addEventListener('click', function () {
        if (D.cart.add(p.id, qty)) location.hash = '#/checkout';
      });
      document.getElementById('add').addEventListener('click', function () {
        if (D.cart.add(p.id, qty)) { D.toast(p.name + ' · Added'); document.getElementById('viewcart').style.display = ''; inc.hidden = false; inc.className = 'note ok'; inc.textContent = D.cart.qty(p.id) + ' in your cart.'; }
      });
    }
    return p.name;
  });
})();
