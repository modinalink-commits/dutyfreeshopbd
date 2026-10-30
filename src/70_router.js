const STAFF_POST = new Set(['/admin/pos', '/admin/purchase', '/admin/cash/expense', '/admin/customers', '/admin/customers/credit', '/admin/customers/pay', '/admin/prices', '/admin/product', '/admin/orders', '/admin/service', '/admin/service/next', '/admin/service/remind']);
const loginPage = err => `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Log in — Green Cycle Manager</title><style>${A_CSS}</style></head><body><main style="max-width:380px;margin:12vh auto;padding:0 16px"><div class="pn"><div class="br"><img src="/logo.png" alt=""><div><b>Cycle Manager</b><small>M/S. Green Cycle Store</small></div></div>${err ? '<div class="msg err">Wrong password</div>' : ''}<form method="post" action="/admin/login"><div class="fr"><label style="width:100%">Password<input type="password" name="password" autofocus style="width:100%"></label></div><button class="btn full">Log in</button></form></div></main></body></html>`;

/* ---------- router ---------- */
const server = http.createServer(async (req, res) => {
  try {
    const u = new URL(req.url, 'http://x'), path = u.pathname.replace(/\/+$/, '') || '/', q = u.searchParams, post = req.method === 'POST';
    if (path === '/healthz') return send(res, 200, 'ok', 'text/plain');
    if (path.startsWith('/img/') && path.endsWith('.svg')) return send(res, 200, placeholderSvg(decodeURIComponent(path.slice(5, -4))), 'image/svg+xml', { 'Cache-Control': 'public,max-age=86400' });
    if (path === '/owner.jpg') return send(res, 200, OWNER, 'image/jpeg', { 'Cache-Control': 'public,max-age=86400' });
    if (path === '/logo.png') return send(res, 200, LOGO, 'image/png', { 'Cache-Control': 'public,max-age=86400' });
    if (path === '/robots.txt') return send(res, 200, 'User-agent: *\nDisallow: /admin\n', 'text/plain');
    if (!path.startsWith('/admin')) {
      { const m = /^\/invoice\/(\d+)$/.exec(path); if (m) { const id = +m[1]; const k = q.get('k') || ''; if (k !== invKey(id) && !authed(req)) return send(res, 403, 'Invalid link', 'text/plain'); const h = invoicePage(id, (req.headers['x-forwarded-proto'] || 'https') + '://' + req.headers.host); return h ? send(res, 200, h) : send(res, 404, 'Not found', 'text/plain'); } }
      if (path === '/') return send(res, 200, pageHome());
      if (path === '/shop') return send(res, 200, pageShop(q));
      if (path === '/cart') return send(res, 200, pageCart());
      let m = /^\/product\/(\d+)$/.exec(path);
      if (m) { const h = pageProduct(+m[1]); return h ? send(res, 200, h) : send(res, 404, pubPage('Not found', '<main class="w sec"><h1>Product not found</h1></main>')); }
      if (path === '/api/cart') { const ids = (q.get('ids') || '').split(',').map(Number).filter(Boolean); return send(res, 200, JSON.stringify(ids.length ? all(`SELECT id,name,price FROM products WHERE web=1 AND id IN (${ids.map(() => '?').join(',')})`, ...ids) : []), 'application/json'); }
      if (path === '/api/order' && post) {
        const b = JSON.parse(await readBody(req) || '{}'); const items = [];
        let total = 0;
        for (const i of b.items || []) { const p = get('SELECT * FROM products WHERE id=? AND web=1', +i.id), qty = Math.floor(+i.qty); if (p && qty > 0) { items.push({ id: p.id, name: p.name, qty, price: p.price }); total += qty * p.price; } }
        if (!items.length || !b.name || !b.phone || !b.address) return send(res, 400, JSON.stringify({ ok: false, error: 'Please fill in all fields' }), 'application/json');
        const id = run('INSERT INTO orders(date,name,phone,address,city,payment,total,items,note) VALUES(?,?,?,?,?,?,?,?,?)', today(), String(b.name).slice(0, 100), String(b.phone).slice(0, 30), String(b.address).slice(0, 300), String(b.city || '').slice(0, 80), String(b.payment || '').slice(0, 40), total, JSON.stringify(items), String(b.note || '').slice(0, 500)).lastInsertRowid;
        return send(res, 200, JSON.stringify({ ok: true, no: +id }), 'application/json');
      }
      return send(res, 404, pubPage('Not found', '<main class="w sec"><h1>Page not found</h1><p><a class="btn" href="/">Go home</a></p></main>'));
    }
    /* admin */
    if (path === '/admin/login') {
      if (post) { const f = new URLSearchParams(await readBody(req)), pw = f.get('password') || ''; const r = checkOwner(pw) ? 'owner' : checkStaff(pw) ? 'staff' : null; if (r) return redirect(res, '/admin', { 'Set-Cookie': `gcs=${makeToken(r)}; Path=/; HttpOnly; Secure; SameSite=Lax; Max-Age=604800` }); return send(res, 401, loginPage(true)); }
      return send(res, 200, loginPage(false));
    }
    if (path === '/admin/logout') return redirect(res, '/admin/login', { 'Set-Cookie': 'gcs=; Path=/; Max-Age=0' });
    const role = roleOf(req); if (!role) return redirect(res, '/admin/login');
    const denied = () => redirect(res, '/admin?err=' + encodeURIComponent('That page is for the owner only'));
    const ctx = { role, q, req };
    if (!post) {
      if (role === 'staff' && !STAFF_PAGES.has(path) && path !== '/admin/statement') return denied();
      const h = GETS[path]; if (!h) return send(res, 404, 'Not found', 'text/plain');
      const out = h(ctx); if (out === null || out === undefined) return denied();
      if (out.csv) return send(res, 200, out.body, 'text/csv; charset=utf-8', { 'Content-Disposition': `attachment; filename="${out.name}"` });
      return send(res, 200, out);
    }
    const f = new URLSearchParams(await readBody(req));
    try {
      const h = POSTS[path]; if (!h) return send(res, 404, 'Not found', 'text/plain');
      if (role === 'staff' && !STAFF_POST.has(path)) return denied();
      return redirect(res, h(f, ctx));
    } catch (e) { const ref = req.headers.referer ? new URL(req.headers.referer) : null; return redirect(res, (ref ? ref.pathname + (ref.search ? ref.search.replace(/[?&](msg|err)=[^&]*/g, '').replace(/^&/, '?') : '') : '/admin').replace(/\?$/, '') + ((ref && ref.search.replace(/[?&](msg|err)=[^&]*/g, '').length > 1) ? '&' : '?') + 'err=' + encodeURIComponent(e.message)); }
  } catch (e) { console.error(e); send(res, 500, 'Server error', 'text/plain'); }
});
server.listen(PORT, () => console.log('Green Cycle app on ' + PORT));
