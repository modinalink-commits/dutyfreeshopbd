(function () {
  var D = window.DFD, esc = D.esc, host = null;
  function paint() {
    if (!host) return;
    var L = D.cart.lines();
    if (!L.length) { host.innerHTML = D.emptyCart(); return; }
    var items = L.map(function (l) {
      return '<div class="cl"><a class="pic" href="#/product/' + l.p.slug + '" tabindex="-1" aria-hidden="true">' + D.picHtml(l.p) + '</a>' +
        '<div><h3><a href="#/product/' + l.p.slug + '">' + esc(l.p.name) + '</a></h3><small>' + D.money(l.p.price) + ' ' + D.unitLabel(l.p) + ' · ' + esc(D.catName(l.p.category)) + '</small></div>' +
        '<div class="lt" aria-label="Line total">' + D.money(l.total) + '</div>' +
        '<div class="ctrl">' + D.stepper(l.p.id, l.qty, l.p.name) + '<button class="rm" type="button" data-act="rm" data-id="' + l.p.id + '">Remove</button></div></div>';
    }).join('');
    var n = D.cart.count();
    host.innerHTML = '<div class="cartpage"><div>' + items + '<p><a class="btn line" href="#/store">Continue shopping</a> <button class="rm" id="clear" type="button">Clear cart</button></p></div>' +
      '<aside class="sumbox glass" aria-label="Order summary"><h2>Order summary</h2><div class="r"><span>Items</span><span>' + n + '</span></div>' +
      '<div class="r"><span>Subtotal</span><span>' + D.money(D.cart.total()) + '</span></div><div class="r"><span>Delivery</span><span>Confirmed at checkout</span></div>' +
      '<div class="r tot"><span>Total</span><span>' + D.money(D.cart.total()) + '</span></div><a class="btn" href="#/checkout">Checkout</a></aside></div>';
    var c = document.getElementById('clear'); if (c) c.addEventListener('click', function () { D.cart.clear(); D.toast('Cart cleared'); });
  }
  D.cart.subscribe(function () { if (D.parseHash().path === '/cart') paint(); });
  D.route(/^\/cart$/, function (app) {
    app.innerHTML = D.crumbs([{ label: 'Home', href: '#/' }, { label: 'Cart' }]) + '<h1 class="pagehead">Your cart</h1><div id="cartpage"></div>';
    host = document.getElementById('cartpage'); paint();
    return 'Cart';
  });
})();
