(function () {
  var D = window.DFD, esc = D.esc;
  function ls(k, v) { try { if (v === undefined) return JSON.parse(localStorage.getItem(k) || 'null'); localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} return null; }
  function post(url, body) {
    return fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(body) })
      .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Something went wrong. Please try again.' }; }); });
  }
  /* Direct order: #/checkout?buy=ID&qty=N checks out only that product (the cart is left as it is). No buy = the whole cart. */
  D.route(/^\/checkout$/, function (app, m, q) {
    var L = [], direct = false;
    if (q && q.buy) {
      var bp = D.byId(Number(q.buy)); direct = true;
      if (bp && D.priced(bp) && bp.stock > 0) L = [{ p: bp, qty: Math.max(1, Math.min(Number(q.qty) || 1, bp.stock)) }];
    } else { L = D.cart.lines().filter(function (l) { return D.priced(l.p) && l.p.stock > 0; }); }
    var head = D.crumbs([{ label: 'Home', href: '#/' }, { label: 'Checkout' }]) + '<h1 class="pagehead">Checkout</h1>';
    if (!L.length) { app.innerHTML = head + D.emptyCart(); return 'Checkout'; }
    var age = D.config.ageGate, ids = L.map(function (l) { return l.p.id + ':' + l.qty; }).join(',');
    var lines = L.map(function (l) { return '<div class="r"><span>' + esc(l.p.name) + ' × ' + l.qty + '</span><span>' + D.money(l.p.price * l.qty) + '</span></div>'; }).join('');
    var sub = L.reduce(function (n, l) { return n + l.p.price * l.qty; }, 0);
    app.innerHTML = head + '<div class="co"><form class="panel form" id="co-form" novalidate><h2>Delivery details</h2><p class="note ok" id="c-ver" hidden></p>' +
      '<label class="t2">Full name<input type="text" id="c-name" autocomplete="name" required aria-describedby="e-name"><span class="err" id="e-name"></span></label>' +
      '<label class="t2">Mobile number<input type="tel" id="c-phone" autocomplete="tel" inputmode="tel" required aria-describedby="e-phone"><span class="err" id="e-phone"></span></label>' +
      '<div id="c-otp" class="note" hidden><button class="btn line" type="button" id="c-otp-send" style="min-height:40px">Verify with SMS code to fill my saved details</button><span id="c-otp-box" hidden> <input type="text" id="c-otp-code" inputmode="numeric" maxlength="8" placeholder="Code" style="width:110px"> <button class="btn" type="button" id="c-otp-go" style="min-height:40px">Verify</button></span><span class="err" id="e-otp"></span></div>' +
      '<label class="t2">Delivery address<input type="text" id="c-addr" autocomplete="street-address" required aria-describedby="e-addr"><span class="err" id="e-addr"></span></label>' +
      '<p class="note"><b>Payment:</b> Cash on delivery.</p>' +
      (age ? '<label class="check"><input type="checkbox" id="c-age"><span>I am 18 or older and will show ID on delivery.</span></label><span class="err" id="e-age"></span>' : '') +
      '<button class="btn" type="submit" id="c-go">Place order</button><p class="ribbon" id="co-note" role="status"' + (D.config.liveOrders ? ' hidden' : '') + '>Online orders are not open on this store yet.</p></form>' +
      '<aside class="sumbox glass" aria-label="Order summary"><h2>Your order</h2>' + lines +
      '<div class="r"><span>Subtotal</span><span id="s-sub">' + D.money(sub) + '</span></div><div class="r" id="s-disc" style="display:none"><span>Discount</span><span></span></div><div class="r"><span>Delivery</span><span id="s-ship">Calculated…</span></div>' +
      '<div class="r tot"><span>Total</span><span id="s-tot">' + D.money(sub) + '</span></div>' + (direct ? '' : '<a class="btn line" href="#/cart">Edit cart</a>') + '</aside></div>';
    var $ = function (id) { return document.getElementById(id); };
    var f = $('co-form');

    /* server price: delivery, discounts and total come from the main order engine, the same one the order uses */
    var tmr;
    function quote() {
      clearTimeout(tmr);
      tmr = setTimeout(function () {
        var ph = $('c-phone').value.replace(/\D/g, '').slice(-11), ad = $('c-addr').value.trim();
        fetch('/cart-items?ids=' + encodeURIComponent(ids) + '&ph=' + (/^01\d{9}$/.test(ph) ? ph : '') + '&addr=' + encodeURIComponent(ad), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
          .then(function (r) { return r.json(); }).then(function (j) {
            if (!$('s-tot') || j.total == null) return;
            $('s-sub').textContent = D.money(j.sub); $('s-ship').textContent = j.ship > 0 ? D.money(j.ship) : 'Free';
            var dd = $('s-disc'); dd.style.display = j.disc > 0 ? '' : 'none'; dd.lastChild.textContent = '− ' + D.money(j.disc);
            $('s-tot').textContent = D.money(j.total);
          }).catch(function () { var s = $('s-ship'); if (s) s.textContent = 'Confirmed on order'; });
      }, 350);
    }
    $('c-phone').addEventListener('input', quote); $('c-addr').addEventListener('input', quote); quote();

    /* returning customer: saved details are filled only when the signed-in session is verified; they stay editable */
    var saved = ls('dfd-cust');
    function fill(n, p, a) { if (n && !$('c-name').value) $('c-name').value = n; if (p && !$('c-phone').value) $('c-phone').value = p; if (a && !$('c-addr').value) $('c-addr').value = a; quote(); }
    fetch('/channel/me', { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }).then(function (j) {
      if (j && j.verified) { fill(j.name, j.phone, j.address); var v = $('c-ver'); v.hidden = false; v.textContent = 'Verified. We filled your saved details. You can change them.'; }
      else { if (saved) fill(saved.n, saved.p, saved.a); $('c-otp').hidden = false; }
    }).catch(function () { if (saved) fill(saved.n, saved.p, saved.a); });
    $('c-otp-send').addEventListener('click', function () {
      var ph = $('c-phone').value.trim(), e = $('e-otp');
      if (!/^(\+?88)?01[3-9]\d{8}$/.test(ph.replace(/[\s-]/g, ''))) { e.textContent = 'Enter your mobile number first.'; return; }
      e.textContent = ''; post('/account/otp', { phone: ph, purpose: 'login' }).then(function (j) {
        if (j && j.sent) { $('c-otp-box').hidden = false; e.className = 'err ok'; e.textContent = 'If this number has an account, a code was sent by SMS.'; } else { e.className = 'err'; e.textContent = (j && j.error) || 'Could not send the code.'; }
      });
    });
    $('c-otp-go').addEventListener('click', function () {
      var e = $('e-otp'); post('/account/otp-login', { phone: $('c-phone').value.trim(), code: $('c-otp-code').value.trim() }).then(function (j) {
        if (j && j.ok) { D.toast('Verified'); D.parseHash && (location.hash = location.hash.replace(/&?_v=\d+/, '') + (location.hash.indexOf('?') < 0 ? '?' : '&') + '_v=' + Date.now()); }
        else { e.className = 'err'; e.textContent = (j && j.error) || 'Code is not correct.'; }
      });
    });

    f.addEventListener('submit', function (e) {
      e.preventDefault();
      var n = $('c-name').value.trim(), ph = $('c-phone').value.trim(), ad = $('c-addr').value.trim(), ok = true;
      function set(id, msg) { $(id).textContent = msg; if (msg) ok = false; }
      set('e-name', n ? '' : 'Enter your name.');
      set('e-phone', /^(\+?88)?01[3-9]\d{8}$/.test(ph.replace(/[\s-]/g, '')) ? '' : 'Enter a valid Bangladesh mobile number, e.g. 01XXXXXXXXX.');
      set('e-addr', ad.length >= 6 ? '' : 'Enter your delivery address.');
      if (age) set('e-age', $('c-age').checked ? '' : 'Please confirm to continue.');
      if (!ok) { var first = f.querySelector('.err:not(:empty)'); if (first && first.previousElementSibling) first.previousElementSibling.focus(); return; }
      var cn = $('co-note');
      if (!D.config.liveOrders) { cn.hidden = false; cn.textContent = 'Orders are not open on this store yet. Nothing was sent.'; cn.scrollIntoView({ block: 'center' }); return; }
      var btn = $('c-go'); btn.disabled = true; btn.textContent = 'Placing order…'; cn.hidden = true;
      post('/order', { name: n, phone: ph, address: ad, pay: 'cod', age18: 1, items: ids, website: '' }).then(function (j) {
        if (!j || !j.ok) { btn.disabled = false; btn.textContent = 'Place order'; cn.hidden = false; cn.textContent = (j && j.error) || 'Could not place the order. Please try again.'; cn.scrollIntoView({ block: 'center' }); return; }
        ls('dfd-cust', { n: n, p: ph, a: ad });
        if (!direct) D.cart.clear();
        app.innerHTML = head + '<div class="done"><svg width="84" height="84" viewBox="0 0 84 84" aria-hidden="true"><circle cx="42" cy="42" r="40" fill="#fbeeee"/><path d="M26 43l11 11 22-24" fill="none" stroke="#c9000b" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/></svg>' +
          '<h2>Order placed</h2><p>Invoice <b>' + esc(j.invoice) + '</b>. Pay cash on delivery. We will call you to confirm.</p><a class="btn" href="#/store">Back to store</a></div>';
        window.scrollTo(0, 0);
      }).catch(function () { btn.disabled = false; btn.textContent = 'Place order'; cn.hidden = false; cn.textContent = 'Connection problem. Please try again.'; });
    });
    return 'Checkout';
  });
})();
