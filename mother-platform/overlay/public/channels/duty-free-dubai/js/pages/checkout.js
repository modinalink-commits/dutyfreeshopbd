(function () {
  var D = window.DFD, esc = D.esc;
  D.route(/^\/checkout$/, function (app) {
    var L = D.cart.lines();
    var head = D.crumbs([{ label: 'Home', href: '#/' }, { label: 'Cart', href: '#/cart' }, { label: 'Checkout' }]) + '<h1 class="pagehead">Checkout</h1>';
    if (!L.length) { app.innerHTML = head + D.emptyCart(); return 'Checkout'; }
    var age = D.config.ageGate;
    var lines = L.map(function (l) { return '<div class="r"><span>' + esc(l.p.name) + ' × ' + l.qty + '</span><span>' + D.money(l.total) + '</span></div>'; }).join('');
    app.innerHTML = head + '<div class="co"><form class="panel form" id="co-form" novalidate><h2>Delivery details</h2>' +
      '<label class="t2">Full name<input type="text" id="c-name" autocomplete="name" required aria-describedby="e-name"><span class="err" id="e-name"></span></label>' +
      '<label class="t2">Mobile number<input type="tel" id="c-phone" autocomplete="tel" inputmode="tel" required aria-describedby="e-phone"><span class="err" id="e-phone"></span></label>' +
      '<label class="t2">Delivery address<input type="text" id="c-addr" autocomplete="street-address" required aria-describedby="e-addr"><span class="err" id="e-addr"></span></label>' +
      '<h2 style="margin-top:10px">Payment</h2><p class="note">Cash on delivery.</p>' +
      (age ? '<label class="check"><input type="checkbox" id="c-age"><span>I am 18 or older and will show ID on delivery.</span></label><span class="err" id="e-age"></span>' : '') +
      '<button class="btn" type="submit">Place order</button><p class="ribbon" id="co-note">Online orders are not open on this store yet.</p></form>' +
      '<aside class="sumbox glass" aria-label="Order summary"><h2>Your order</h2>' + lines + '<div class="r tot"><span>Total</span><span>' + D.money(D.cart.total()) + '</span></div><a class="btn line" href="#/cart">Edit cart</a></aside></div>';
    var f = document.getElementById('co-form');
    f.addEventListener('submit', function (e) {
      e.preventDefault();
      var n = document.getElementById('c-name').value.trim(), ph = document.getElementById('c-phone').value.trim(), ad = document.getElementById('c-addr').value.trim(), ok = true;
      function set(id, msg) { document.getElementById(id).textContent = msg; if (msg) ok = false; }
      set('e-name', n ? '' : 'Enter your name.');
      set('e-phone', /^(\+?88)?01[3-9]\d{8}$/.test(ph.replace(/[\s-]/g, '')) ? '' : 'Enter a valid Bangladesh mobile number, e.g. 01XXXXXXXXX.');
      set('e-addr', ad.length >= 6 ? '' : 'Enter your delivery address.');
      if (age) set('e-age', document.getElementById('c-age').checked ? '' : 'Please confirm to continue.');
      if (!ok) { var first = f.querySelector('.err:not(:empty)'); if (first) first.previousElementSibling.focus(); return; }
      if (!D.config.liveOrders) { var cn = document.getElementById('co-note'); cn.textContent = 'Orders are not open on this store yet. Nothing was sent.'; cn.focus && cn.scrollIntoView({ block: 'center' }); return; }
      var total = D.cart.total(), cnt = D.cart.count();
      /* sample only: nothing leaves the browser */
      app.innerHTML = head + '<div class="done"><svg width="84" height="84" viewBox="0 0 84 84" aria-hidden="true"><circle cx="42" cy="42" r="40" fill="#fbeeee"/><path d="M26 43l11 11 22-24" fill="none" stroke="#c9000b" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/></svg>' +
        '<h2>Order complete</h2><p>' + cnt + (cnt === 1 ? ' item' : ' items') + ' · ' + D.money(total) + '</p><a class="btn" href="#/store" id="again">Back to store</a></div>';
      D.cart.clear();
    });
    return 'Checkout';
  });
})();
