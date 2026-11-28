(function () {
  var D = window.DFD, esc = D.esc;
  function ls(k, v) { try { if (v === undefined) return JSON.parse(localStorage.getItem(k) || 'null'); localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} return null; }
  function post(url, body) {
    return fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(body) })
      .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Something went wrong. Please try again.' }; }); });
  }
  /* Like the main site: Order now puts the product in the cart and opens this same checkout. */
  D.route(/^\/checkout$/, function (app, m, q) {
    if (q && q.buy) {
      var bp = D.byId(Number(q.buy));
      if (bp && D.priced(bp) && bp.stock > 0) D.cart.set(bp.id, Math.max(D.cart.qty(bp.id), Math.max(1, Math.min(Number(q.qty) || 1, bp.stock))));
      location.replace('#/checkout'); return 'Checkout';
    }
    var direct = false;
    var L = D.cart.lines().filter(function (l) { return D.priced(l.p) && l.p.stock > 0; });
    var head = D.crumbs([{ label: 'Home', href: '#/' }, { label: 'Checkout' }]) + '<h1 class="pagehead">Checkout</h1>';
    if (!L.length) { app.innerHTML = head + D.emptyCart(); return 'Checkout'; }
    var age = D.config.ageGate, ids = '';
    var tok = ls('dfd-inc'); if (!tok || !/^[A-Za-z0-9]{16,40}$/.test(tok)) { tok = ''; for (var i = 0; i < 24; i++) tok += 'abcdefghijklmnopqrstuvwxyz0123456789'.charAt(Math.floor(Math.random() * 36)); ls('dfd-inc', tok); }
    var code = '';
    function rows() {
      L = D.cart.lines().filter(function (l) { return D.priced(l.p) && l.p.stock > 0; });
      ids = L.map(function (l) { return l.p.id + ':' + l.qty; }).join(',');
      return L.map(function (l) { return '<div class="cr" data-id="' + l.p.id + '"><span class="cn">' + esc(l.p.name) + '</span><span class="cq"><button type="button" data-d="-1" aria-label="Decrease">−</button><b>' + l.qty + '</b><button type="button" data-d="1" aria-label="Increase">+</button></span><span class="cp">' + D.money(l.p.price * l.qty) + '</span><button type="button" class="cx" data-rm aria-label="Remove">×</button></div>'; }).join('');
    }
    var sub = L.reduce(function (n, l) { return n + l.p.price * l.qty; }, 0);
    app.innerHTML = head + '<div class="co"><form class="panel form" id="co-form" novalidate><h2>Delivery details</h2>' +
      '<label class="t2">Mobile number<input type="tel" id="c-phone" autocomplete="tel" inputmode="tel" required aria-describedby="e-phone"><span class="err" id="e-phone"></span></label>' +
      '<label class="t2">Full name<input type="text" id="c-name" autocomplete="name" required aria-describedby="e-name"><span class="err" id="e-name"></span></label>' +
      '<label class="t2">Email <small>(optional)</small><input type="email" id="c-mail" autocomplete="email" aria-describedby="e-mail"><span class="err" id="e-mail"></span></label>' +
      '<label class="check"><input type="checkbox" id="c-offers"><span>Send me offers and new product news by email</span></label>' +
      '<label class="t2">Delivery address<input type="text" id="c-addr" autocomplete="street-address" required aria-describedby="e-addr"><span class="err" id="e-addr"></span></label>' +
      '<label class="t2">Delivery note <small>(optional)</small><input type="text" id="c-note" maxlength="300"></label>' +
      '<p class="note"><b>Payment:</b> Cash on delivery.</p>' +
      '<button class="btn" type="submit" id="c-go">Order submit</button><p class="ribbon" id="co-note" role="status"' + (D.config.liveOrders ? ' hidden' : '') + '>Online orders are not open on this store yet.</p>' +
      '<p class="note help">Need help? Call <a href="tel:+8801871600600">01871600600</a> or <a href="https://wa.me/8801871600600" rel="noopener">WhatsApp</a>.</p></form>' +
      '<aside class="sumbox glass" aria-label="Order summary"><h2>Your order</h2><div id="c-rows">' + rows() + '</div>' +
      (D.config.coupon ? '<div class="cpbox"><input type="text" id="cpc" placeholder="Coupon code" maxlength="40" aria-label="Coupon code"><button type="button" class="btn line" id="cpb">Apply</button></div><p class="note" id="cpm" role="status"></p>' : '') +
      '<div class="r"><span>Subtotal</span><span id="s-sub">' + D.money(sub) + '</span></div><div class="r" id="s-disc" style="display:none"><span>Discount</span><span></span></div><div class="r"><span>Delivery</span><span id="s-ship">Calculated…</span></div><p class="note" id="s-msg" hidden></p>' +
      '<div class="r tot"><span>Total</span><span id="s-tot">' + D.money(sub) + '</span></div></aside></div>';
    var $ = function (id) { return document.getElementById(id); };
    var f = $('co-form');

    /* server price: delivery, discounts and total come from the main order engine, the same one the order uses */
    var tmr;
    function quote() {
      clearTimeout(tmr);
      tmr = setTimeout(function () {
        var ph = $('c-phone').value.replace(/\D/g, '').slice(-11), ad = $('c-addr').value.trim();
        if (!ids) return;
        fetch('/cart-items?ids=' + encodeURIComponent(ids) + '&ph=' + (/^01\d{9}$/.test(ph) ? ph : '') + '&addr=' + encodeURIComponent(ad) + '&code=' + encodeURIComponent(code), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
          .then(function (r) { return r.json(); }).then(function (j) {
            if (!$('s-tot') || j.total == null) return;
            var go = $('c-go'), pn = $('co-note');
            if (j.prepaid_only) { go.disabled = true; pn.hidden = false; pn.innerHTML = 'This product cannot be ordered with cash on delivery, and online payment is not open on this store yet. Please call <a href="tel:+8801871600600">01871600600</a> to order.'; }
            else if (go.disabled && !go.getAttribute('data-busy')) { go.disabled = false; if (D.config.liveOrders) pn.hidden = true; }
            $('s-sub').textContent = D.money(j.sub); $('s-ship').textContent = j.ship > 0 ? D.money(j.ship) : 'Free';
            var dd = $('s-disc'); dd.style.display = j.disc > 0 ? '' : 'none'; dd.lastChild.textContent = '− ' + D.money(j.disc);
            $('s-tot').textContent = D.money(j.total);
            var sm = $('s-msg'); if (sm) { var t = j.ship_unlocked ? 'Free delivery unlocked.' : ''; sm.hidden = !t; sm.textContent = t; }
            var cm = $('cpm'); if (cm && code) { cm.textContent = (j.applied && j.applied.length) ? 'Coupon applied.' : 'Coupon is not valid for this order.'; }
          }).catch(function () { var s = $('s-ship'); if (s) s.textContent = 'Confirmed on order'; });
      }, 350);
    }
    $('c-phone').addEventListener('input', quote); $('c-addr').addEventListener('input', quote); quote();

    /* returning customer: saved details are filled only when the signed-in session is verified; they stay editable */
    var saved = ls('dfd-cust');
    function fill(n, p, a) { if (n && !$('c-name').value) $('c-name').value = n; if (p && !$('c-phone').value) $('c-phone').value = p; if (a && !$('c-addr').value) $('c-addr').value = a; quote(); }
    fetch('/channel/me', { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }).then(function (j) {
      if (j && j.verified) fill(j.name, j.phone, j.address); else if (saved) fill(saved.n, saved.p, saved.a);
    }).catch(function () { if (saved) fill(saved.n, saved.p, saved.a); });
    f.addEventListener('submit', function (e) {
      e.preventDefault();
      var n = $('c-name').value.trim(), ph = $('c-phone').value.trim(), ad = $('c-addr').value.trim(), ok = true;
      function set(id, msg) { $(id).textContent = msg; if (msg) ok = false; }
      set('e-name', n ? '' : 'Enter your name.');
      set('e-phone', /^(\+?88)?01[3-9]\d{8}$/.test(ph.replace(/[\s-]/g, '')) ? '' : 'Enter a valid Bangladesh mobile number, e.g. 01XXXXXXXXX.');
      set('e-addr', ad.length >= 6 ? '' : 'Enter your delivery address.');
      if (!ok) { var first = f.querySelector('.err:not(:empty)'); if (first && first.previousElementSibling) first.previousElementSibling.focus(); return; }
      var cn = $('co-note');
      if (!D.config.liveOrders) { cn.hidden = false; cn.textContent = 'Orders are not open on this store yet. Nothing was sent.'; cn.scrollIntoView({ block: 'center' }); return; }
      var btn = $('c-go'); btn.disabled = true; btn.setAttribute('data-busy', '1'); btn.textContent = 'Placing order…'; cn.hidden = true;
      post('/order', { name: n, phone: ph, email: $('c-mail').value.trim(), address: ad, note: $('c-note').value.trim(), coupon: code, inc: tok, pay: 'cod', age18: 1, offers: $('c-offers').checked ? 1 : 0, items: ids, website: '' }).then(function (j) {
        if (!j || !j.ok) { btn.disabled = false; btn.textContent = 'Order submit'; cn.hidden = false; cn.textContent = (j && j.error) || 'Could not place the order. Please try again.'; cn.scrollIntoView({ block: 'center' }); return; }
        ls('dfd-cust', { n: n, p: ph, a: ad }); ls('dfd-inc', ''); try { localStorage.removeItem('dfd-inc'); } catch (e) {}
        D.cart.clear();
        app.innerHTML = head + '<div class="done"><svg width="84" height="84" viewBox="0 0 84 84" aria-hidden="true"><circle cx="42" cy="42" r="40" fill="#fbeeee"/><path d="M26 43l11 11 22-24" fill="none" stroke="#c9000b" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/></svg>' +
          '<h2>Order placed</h2><p>Invoice <b>' + esc(j.invoice) + '</b>. Pay cash on delivery. We will call you to confirm.</p><a class="btn" href="#/store">Back to store</a></div>';
        window.scrollTo(0, 0);
      }).catch(function () { btn.disabled = false; btn.textContent = 'Order submit'; cn.hidden = false; cn.textContent = 'Connection problem. Please try again.'; });
    });
    return 'Checkout';
  });
})();
