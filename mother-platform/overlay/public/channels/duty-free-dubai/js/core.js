/* Core: helpers, cart store, toast, drawer, router. No framework. */
(function () {
  var D = window.DFD;
  var $ = function (id) { return document.getElementById(id); };
  D.$ = $;
  D.esc = function (s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  D.fmt = function (n) { return Number(n).toLocaleString('en-US'); };
  D.money = function (n) { return D.config.currency + ' ' + D.fmt(n); };
  D.byId = function (id) { for (var i = 0; i < D.products.length; i++) if (D.products[i].id === id) return D.products[i]; return null; };
  D.bySlug = function (s) { for (var i = 0; i < D.products.length; i++) if (D.products[i].slug === s) return D.products[i]; return null; };
  D.catName = function (id) { for (var i = 0; i < D.categories.length; i++) if (D.categories[i].id === id) return D.categories[i].name; return id; };
  D.priced = function (p) { return p.price > 0; };
  D.stockInfo = function (p) {
    if (p.stock <= 0) return { cls: 's-out', label: 'Out of stock' };
    if (p.stock <= 10) return { cls: 's-low', label: 'Low stock · ' + p.stock + ' left' };
    return { cls: 's-ok', label: 'In stock' };
  };
  D.unitLabel = function (p) { return p.unit ? 'per ' + p.unit : ''; };

  /* ---------- toast ---------- */
  var tt;
  D.toast = function (msg) {
    var t = $('toast'); t.innerHTML = '<i aria-hidden="true">✓</i>' + D.esc(msg);
    t.classList.add('show'); clearTimeout(tt); tt = setTimeout(function () { t.classList.remove('show'); }, 2000);
  };

  /* ---------- cart store ---------- */
  var cart = [];
  var subs = [];
  function load() {
    try { var raw = localStorage.getItem(D.config.storageKey); if (raw) cart = JSON.parse(raw).filter(function (l) { return D.byId(l.id); }); } catch (e) { cart = []; }
  }
  function save() { try { localStorage.setItem(D.config.storageKey, JSON.stringify(cart)); } catch (e) { /* storage may be unavailable; cart stays in memory */ } }
  function emit() { save(); subs.forEach(function (f) { f(); }); }
  D.cart = {
    lines: function () { return cart.map(function (l) { var p = D.byId(l.id); return { p: p, qty: l.qty, total: p.price * l.qty }; }); },
    count: function () { return cart.reduce(function (n, l) { return n + l.qty; }, 0); },
    total: function () { return cart.reduce(function (n, l) { return n + D.byId(l.id).price * l.qty; }, 0); },
    qty: function (id) { for (var i = 0; i < cart.length; i++) if (cart[i].id === id) return cart[i].qty; return 0; },
    add: function (id, q) {
      var p = D.byId(id); if (!p || !D.priced(p) || p.stock <= 0) return false;
      var have = D.cart.qty(id), want = Math.min(p.stock, have + (q || 1));
      if (want === have) { D.toast('Only ' + p.stock + ' in stock'); return false; }
      var hit = cart.filter(function (l) { return l.id === id; })[0];
      if (hit) hit.qty = want; else cart.push({ id: id, qty: want });
      emit(); return true;
    },
    set: function (id, q) {
      var p = D.byId(id); q = Math.max(0, Math.min(p.stock, q));
      cart = cart.filter(function (l) { return l.id !== id; });
      if (q > 0) cart.push({ id: id, qty: q });
      /* keep original order stable */
      emit();
    },
    remove: function (id) { cart = cart.filter(function (l) { return l.id !== id; }); emit(); },
    clear: function () { cart = []; emit(); },
    subscribe: function (f) { subs.push(f); }
  };
  load();

  /* ---------- quantity stepper (shared markup) ---------- */
  D.stepper = function (id, qty, labelName) {
    return '<div class="step" role="group" aria-label="Quantity for ' + D.esc(labelName) + '">' +
      '<button type="button" data-act="dec" data-id="' + id + '" aria-label="Decrease quantity">−</button>' +
      '<span aria-live="polite">' + qty + '</span>' +
      '<button type="button" data-act="inc" data-id="' + id + '" aria-label="Increase quantity">+</button></div>';
  };

  /* ---------- header badge + drawer ---------- */
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var lastCount = 0;
  function renderBadge() {
    var n = D.cart.count(), b = $('count'); b.textContent = n;
    if (n !== lastCount) { b.classList.remove('bump'); void b.offsetWidth; b.classList.add('bump'); }
    lastCount = n;
    $('open-cart').setAttribute('aria-label', 'Open cart, ' + n + (n === 1 ? ' item' : ' items'));
  }
  function renderDrawer() {
    var L = D.cart.lines(), h = '';
    if (!L.length) {
      h = '<div class="empty" style="margin-top:14px"><b>Your cart is empty</b><br>Add a product to see it here.</div>';
    } else {
      L.forEach(function (l) {
        h += '<div class="li"><b>' + D.esc(l.p.name) + '</b>' + D.stepper(l.p.id, l.qty, l.p.name) +
          '<small>' + D.money(l.p.price) + ' ' + D.unitLabel(l.p) + ' · ' + D.money(l.total) + '</small>' +
          '<button class="rmx" type="button" data-act="rm" data-id="' + l.p.id + '">Remove</button></div>';
      });
    }
    $('lines').innerHTML = h;
    $('total').textContent = D.money(D.cart.total());
    var has = L.length > 0;
    $('go-cart').hidden = !has; $('go-checkout').hidden = !has;
  }
  D.cart.subscribe(renderBadge); D.cart.subscribe(renderDrawer);

  var cartEl;
  D.openCart = function () { cartEl.classList.remove('out'); if (!cartEl.open) cartEl.showModal(); };
  D.closeCart = function () {
    if (!cartEl.open) return;
    if (reduce) { cartEl.close(); return; }
    cartEl.classList.add('out'); setTimeout(function () { cartEl.classList.remove('out'); cartEl.close(); }, 200);
  };

  /* one delegated handler for every stepper / remove / add button in the app */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-act]'); if (!b) return;
    var act = b.getAttribute('data-act'), id = Number(b.getAttribute('data-id'));
    if (act === 'inc') { var p = D.byId(id); if (D.cart.qty(id) >= p.stock) D.toast('Only ' + p.stock + ' in stock'); else D.cart.set(id, D.cart.qty(id) + 1); }
    else if (act === 'dec') { D.cart.set(id, D.cart.qty(id) - 1); }
    else if (act === 'rm') { var n = D.byId(id).name; D.cart.remove(id); D.toast(n + ' · Removed'); }
  });

  /* ---------- router ---------- */
  var routes = [];
  D.route = function (re, fn) { routes.push({ re: re, fn: fn }); };
  D.parseHash = function () {
    var h = location.hash.replace(/^#/, '') || '/';
    var q = {}, i = h.indexOf('?');
    if (i >= 0) { h.slice(i + 1).split('&').forEach(function (kv) { if (!kv) return; var a = kv.split('='); q[decodeURIComponent(a[0])] = decodeURIComponent((a[1] || '').replace(/\+/g, ' ')); }); h = h.slice(0, i); }
    return { path: h || '/', query: q };
  };
  D.href = function (path, query) {
    var s = Object.keys(query || {}).filter(function (k) { return query[k] !== '' && query[k] != null; }).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(query[k]); }).join('&');
    return '#' + path + (s ? '?' + s : '');
  };
  var titles = { '/': 'Home', '/store': 'Store', '/cart': 'Cart', '/checkout': 'Checkout' };
  function render() {
    var r = D.parseHash(), app = $('app'), done = false;
    for (var i = 0; i < routes.length && !done; i++) {
      var m = r.path.match(routes[i].re);
      if (m) { app.innerHTML = ''; var t = routes[i].fn(app, m, r.query); done = true; document.title = (r.path === '/' && D.config.homeTitle) ? D.config.homeTitle : (t || titles[r.path] || 'Store') + ' · ' + D.config.brand; }
    }
    if (!done) { app.innerHTML = '<div class="emptybox"><h2>Page not found</h2><p>This page does not exist.</p><a class="btn" href="#/">Back to home</a></div>'; document.title = 'Not found · ' + D.config.brand; }
    document.querySelectorAll('.nav a, .mnav a').forEach(function (a) {
      var on = a.getAttribute('data-nav') === (r.path.indexOf('/product') === 0 ? '/store' : r.path);
      if (on) a.setAttribute('aria-current', 'page'); else a.removeAttribute('aria-current');
    });
    if (document.activeElement && document.activeElement.blur && !/INPUT|SELECT|TEXTAREA/.test(document.activeElement.tagName)) document.activeElement.blur();
    window.scrollTo(0, 0);
    var m1 = document.querySelector('#app h1'); if (m1) { m1.setAttribute('tabindex', '-1'); }
    $('q').value = (r.path === '/store' && r.query.q) ? r.query.q : '';
  }
  D.start = function () {
    cartEl = $('cart');
    renderBadge(); renderDrawer(); lastCount = D.cart.count();
    $('open-cart').addEventListener('click', D.openCart);
    $('close-cart').addEventListener('click', D.closeCart);
    cartEl.addEventListener('click', function (e) { if (e.target === cartEl) D.closeCart(); });
    cartEl.addEventListener('cancel', function (e) { e.preventDefault(); D.closeCart(); });
    cartEl.querySelectorAll('a').forEach(function (a) { a.addEventListener('click', function () { D.closeCart(); }); });
    $('searchform').addEventListener('submit', function (e) { e.preventDefault(); location.hash = D.href('/store', { q: $('q').value.trim() }); });
    window.addEventListener('hashchange', render);
    render();
  };
})();
