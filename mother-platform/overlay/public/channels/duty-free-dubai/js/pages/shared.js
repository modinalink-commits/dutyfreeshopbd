/* Shared UI pieces: product card, empty states, add-to-cart handler. */
(function () {
  var D = window.DFD, esc = D.esc;
  D.picHtml = function (p, eager) {
    return '<img src="' + p.image + '" alt="' + esc(p.name) + '" width="800" height="640"' + (eager ? '' : ' loading="lazy"') + ' referrerpolicy="no-referrer" onerror="this.onerror=null;this.src=\'/product-placeholder\'">';
  };
  D.priceHtml = function (p, cls) {
    if (!D.priced(p)) return '<div class="price call' + (cls ? ' ' + cls : '') + '">Call for price</div>';
    return '<div class="price' + (cls ? ' ' + cls : '') + '">' + D.money(p.price) + '<small>' + D.unitLabel(p) + '</small></div>';
  };
  D.card = function (p) {
    var s = D.stockInfo(p), href = '#/product/' + p.slug, can = D.priced(p) && p.stock > 0;
    var btn = !D.priced(p) ? '<a class="btn line" href="' + href + '">Call for price</a>'
      : p.stock <= 0 ? '<button class="btn" type="button" disabled>Out of stock</button>'
      : '<button class="btn" type="button" data-add="' + p.id + '" aria-label="Add ' + esc(p.name) + ' to cart">Add to cart</button>';
    return '<article class="card"><a class="pic" href="' + href + '" tabindex="-1" aria-hidden="true">' + D.picHtml(p) + '</a>' +
      '<div class="info"><span class="kind">' + esc(D.catName(p.category)) + '</span>' +
      '<h3><a href="' + href + '">' + esc(p.name) + '</a></h3>' +
      '<span class="stock ' + s.cls + '">' + s.label + '</span><span class="fdel">Free Delivery</span>' + D.priceHtml(p) + '</div>' +
      '<div class="buy">' + btn + '</div></article>';
  };
  D.emptyCart = function () {
    return '<div class="emptybox"><svg viewBox="0 0 120 120" aria-hidden="true"><circle cx="60" cy="60" r="56" fill="#fbeeee"/><path d="M28 40h10l8 36h40l8-28H44" fill="none" stroke="#232323" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/><circle cx="52" cy="90" r="6" fill="#c9000b"/><circle cx="82" cy="90" r="6" fill="#c9000b"/></svg>' +
      '<h1 class="pagehead" style="font-size:1.8rem">Your cart is empty</h1><p>Browse the store and add a product. It will show up here with its total.</p><a class="btn" href="#/store">Go to store</a></div>';
  };
  /* delegated Add-to-cart for cards (qty 1) */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-add]'); if (!b) return;
    var id = Number(b.getAttribute('data-add')), q = Number(b.getAttribute('data-qty') || 1), p = D.byId(id);
    if (D.cart.add(id, q)) D.toast(p.name + ' · Added');
  });
  D.crumbs = function (items) {
    return '<nav class="crumbs" aria-label="Breadcrumb">' + items.map(function (it, i) {
      return it.href ? '<a href="' + it.href + '">' + esc(it.label) + '</a><span aria-hidden="true">›</span>' : '<span aria-current="page">' + esc(it.label) + '</span>';
    }).join('') + '</nav>';
  };
})();
