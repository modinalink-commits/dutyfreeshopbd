'use strict';
const http = require('http'), crypto = require('crypto'), fs = require('fs');
const { DatabaseSync } = require('node:sqlite');
const PORT = +process.env.PORT || 3000, DATA = process.env.DATA_DIR || '/data';
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD || 'admin123';
const SECRET = process.env.SECRET || 'change-me';
const WA = process.env.WHATSAPP || '8801611747299', PHONE = process.env.PHONE || '01711 747 299';
const LOGO = fs.readFileSync(require('path').join(__dirname, 'logo.png'));
fs.mkdirSync(DATA, { recursive: true });
const db = new DatabaseSync(DATA + '/gc.db');
db.exec(`PRAGMA journal_mode=WAL;
CREATE TABLE IF NOT EXISTS products(id INTEGER PRIMARY KEY,name TEXT,sku TEXT,category TEXT,price REAL,cost REAL DEFAULT 0,stock REAL DEFAULT 0,reorder REAL DEFAULT 0,image TEXT DEFAULT '',description TEXT DEFAULT '',web INTEGER DEFAULT 1);
CREATE TABLE IF NOT EXISTS customers(id INTEGER PRIMARY KEY,name TEXT,phone TEXT,address TEXT DEFAULT '',type TEXT DEFAULT 'Retail',balance REAL DEFAULT 0);
CREATE TABLE IF NOT EXISTS suppliers(id INTEGER PRIMARY KEY,name TEXT,phone TEXT DEFAULT '',balance REAL DEFAULT 0);
CREATE TABLE IF NOT EXISTS sales(id INTEGER PRIMARY KEY,date TEXT,customer_id INTEGER,total REAL,discount REAL DEFAULT 0,paid REAL DEFAULT 0,note TEXT DEFAULT '',source TEXT DEFAULT 'POS');
CREATE TABLE IF NOT EXISTS sale_items(id INTEGER PRIMARY KEY,sale_id INTEGER,product_id INTEGER,qty REAL,price REAL,cost REAL);
CREATE TABLE IF NOT EXISTS purchases(id INTEGER PRIMARY KEY,date TEXT,supplier_id INTEGER,total REAL,paid REAL DEFAULT 0,note TEXT DEFAULT '');
CREATE TABLE IF NOT EXISTS purchase_items(id INTEGER PRIMARY KEY,purchase_id INTEGER,product_id INTEGER,qty REAL,cost REAL);
CREATE TABLE IF NOT EXISTS cash(id INTEGER PRIMARY KEY,date TEXT,type TEXT,amount REAL,head TEXT,note TEXT DEFAULT '');
CREATE TABLE IF NOT EXISTS orders(id INTEGER PRIMARY KEY,date TEXT,name TEXT,phone TEXT,address TEXT,city TEXT,payment TEXT,total REAL,status TEXT DEFAULT 'New',items TEXT,note TEXT DEFAULT '');`);
const all = (s, ...a) => db.prepare(s).all(...a), get = (s, ...a) => db.prepare(s).get(...a), run = (s, ...a) => db.prepare(s).run(...a);
const tx = fn => { db.exec('BEGIN'); try { const r = fn(); db.exec('COMMIT'); return r; } catch (e) { db.exec('ROLLBACK'); throw e; } };
const today = () => new Date(Date.now() + 6 * 3600e3).toISOString().slice(0, 10);
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const money = n => '৳ ' + Math.round(+n || 0).toLocaleString('en-US');
const num = v => { const n = parseFloat(v); return isFinite(n) ? n : 0; };

db.exec('CREATE TABLE IF NOT EXISTS meta(k TEXT PRIMARY KEY,v TEXT)');
if (!get("SELECT 1 FROM meta WHERE k='demo_cleared'")) {
  run('DELETE FROM products WHERE id NOT IN (SELECT product_id FROM sale_items) AND id NOT IN (SELECT product_id FROM purchase_items)');
  run("INSERT INTO meta(k,v) VALUES('demo_cleared','1')");
}
if (!get('SELECT 1 FROM customers')) run("INSERT INTO customers(name,phone,type) VALUES('Walk-in customer','','Retail')");
if (!get('SELECT 1 FROM suppliers')) run("INSERT INTO suppliers(name) VALUES('General supplier')");

/* ---------- business logic ---------- */
function createSale({ customer_id, items, discount = 0, paid = 0, note = '', source = 'POS' }) {
  items = items.filter(i => i.pid && i.qty > 0);
  if (!items.length) throw new Error('Add at least one product');
  return tx(() => {
    let total = 0;
    items.forEach(i => { const p = get('SELECT * FROM products WHERE id=?', i.pid); if (!p) throw new Error('Product not found'); if (p.stock < i.qty) throw new Error('Not enough stock: ' + p.name + ' (have ' + p.stock + ')'); i.p = p; total += i.qty * i.price; });
    const net = Math.max(0, total - discount); paid = Math.min(paid, net);
    const id = +run('INSERT INTO sales(date,customer_id,total,discount,paid,note,source) VALUES(?,?,?,?,?,?,?)', today(), customer_id, net, discount, paid, note, source).lastInsertRowid;
    items.forEach(i => { run('INSERT INTO sale_items(sale_id,product_id,qty,price,cost) VALUES(?,?,?,?,?)', id, i.pid, i.qty, i.price, i.p.cost); run('UPDATE products SET stock=stock-? WHERE id=?', i.qty, i.pid); });
    if (customer_id) run('UPDATE customers SET balance=balance+? WHERE id=?', net - paid, customer_id);
    if (paid > 0) run("INSERT INTO cash(date,type,amount,head,note) VALUES(?,?,?,?,?)", today(), 'in', paid, 'Sale', 'Sale #' + id);
    return id;
  });
}
function createPurchase({ supplier_id, items, paid = 0, note = '' }) {
  items = items.filter(i => i.pid && i.qty > 0);
  if (!items.length) throw new Error('Add at least one product');
  return tx(() => {
    const total = items.reduce((s, i) => s + i.qty * i.cost, 0); paid = Math.min(paid, total);
    const id = +run('INSERT INTO purchases(date,supplier_id,total,paid,note) VALUES(?,?,?,?,?)', today(), supplier_id, total, paid, note).lastInsertRowid;
    items.forEach(i => { run('INSERT INTO purchase_items(purchase_id,product_id,qty,cost) VALUES(?,?,?,?)', id, i.pid, i.qty, i.cost); run('UPDATE products SET stock=stock+?,cost=? WHERE id=?', i.qty, i.cost, i.pid); });
    if (supplier_id) run('UPDATE suppliers SET balance=balance+? WHERE id=?', total - paid, supplier_id);
    if (paid > 0) run("INSERT INTO cash(date,type,amount,head,note) VALUES(?,?,?,?,?)", today(), 'out', paid, 'Purchase', 'Purchase #' + id);
    return id;
  });
}

/* ---------- helpers: http ---------- */
const sign = v => crypto.createHmac('sha256', SECRET).update(v).digest('base64url');
const makeToken = () => { const e = String(Date.now() + 7 * 864e5); return e + '.' + sign(e); };
const authed = req => { const m = /(?:^|; )gcs=([^;]+)/.exec(req.headers.cookie || ''); if (!m) return false; const [e, s] = m[1].split('.'); return !!e && s === sign(e) && +e > Date.now(); };
const readBody = req => new Promise(r => { let b = ''; req.on('data', c => { b += c; if (b.length > 1e6) req.destroy(); }); req.on('end', () => r(b)); });
const send = (res, code, body, type = 'text/html; charset=utf-8', h = {}) => { res.writeHead(code, { 'Content-Type': type, ...h }); res.end(body); };
const redirect = (res, loc, extra = {}) => { res.writeHead(303, { Location: loc, ...extra }); res.end(); };

/* ---------- public site ---------- */
const PUB_CSS = `*{box-sizing:border-box}body{margin:0;background:#F6F8F4;color:#12201A;font:16px/1.5 Inter,system-ui,sans-serif}a{color:inherit;text-decoration:none}a:hover{opacity:.85}h1,h2,h3{font-family:Manrope,sans-serif;margin:0}
.w{max-width:1200px;margin:0 auto;padding:0 24px}.bar{background:#1F3F6E;color:#fff;font-size:14px;text-align:center;padding:10px 16px}
header.top{background:#fff;border-bottom:1px solid #DDE5DF}.hd{display:flex;flex-wrap:wrap;align-items:center;gap:16px 32px;padding:16px 24px;max-width:1200px;margin:0 auto}
.brand{display:flex;align-items:center;gap:10px;font:800 22px Manrope,sans-serif}.brand img{width:48px;height:48px;object-fit:contain}
nav.m{display:flex;flex-wrap:wrap;gap:8px 28px;font-weight:500;flex:1 1 380px}.cartb{height:44px;padding:0 18px;border-radius:12px;background:#1F3F6E;color:#fff;display:flex;align-items:center;font-weight:600}
.btn{display:inline-block;padding:14px 26px;border-radius:12px;background:#1F3F6E;color:#fff;font-weight:600;border:0;font:600 16px Inter,sans-serif;cursor:pointer}.btn.o{background:transparent;color:#12201A;border:1px solid #12201A}.btn.y{background:#F6C32B;color:#12201A}
.grid{display:grid;gap:20px}.card{background:#fff;border:1px solid #DDE5DF;border-radius:18px;padding:16px;display:flex;flex-direction:column;gap:14px}
.ph{height:170px;border-radius:12px;background:#E7EEF8;color:#1F3F6E;font:800 28px Manrope,sans-serif;display:flex;align-items:center;justify-content:center;overflow:hidden;position:relative}.ph img{width:100%;height:100%;object-fit:cover}
.price{font:800 20px Manrope,sans-serif}.muted{color:#4B5C54}.tag{display:inline-block;background:#FFF1E0;color:#8A3B00;font-weight:600;font-size:12px;padding:3px 10px;border-radius:999px}
input,select,textarea{font:inherit;padding:0 14px;height:46px;border-radius:12px;border:1px solid #8FA398;width:100%;background:#fff}textarea{height:90px;padding:12px}label{display:flex;flex-direction:column;gap:6px;font-weight:500}
footer.f{background:#12201A;color:#E6EEE9;margin-top:64px}footer.f a{color:#B8C7BF}.sec{padding:48px 0 8px}
@media(max-width:640px){h1{font-size:36px!important}}`;
const PUB_JS = `const K='gcs_cart';const cart=()=>{try{return JSON.parse(localStorage.getItem(K))||{}}catch(e){return{}}};const save=c=>{localStorage.setItem(K,JSON.stringify(c));badge()};
function badge(){const n=Object.values(cart()).reduce((a,b)=>a+b,0);const e=document.getElementById('cc');if(e)e.textContent=n}
function add(id,q){const c=cart();c[id]=(c[id]||0)+(q||1);save(c)}badge();
document.addEventListener('click',e=>{const b=e.target.closest('[data-add]');if(b){e.preventDefault();const q=document.getElementById('q');add(b.dataset.add,q?+q.value||1:1);b.textContent='Added ✓';setTimeout(()=>b.textContent=b.dataset.label||'Add to cart',1200)}});`;
function pubPage(title, body) {
  return `<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>${esc(title)}</title><link rel="icon" href="/logo.png"><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;700;800&family=Inter:wght@400;500;600&display=swap"><style>${PUB_CSS}</style></head><body>
<div class="bar">Cycle and rickshaw parts · Retail and wholesale · 458/P, Omar Chand Road, Chattogram</div>
<header class="top"><div class="hd"><a class="brand" href="/"><img src="/logo.png" alt="Green Cycle Store logo">Green Cycle Store</a>
<nav class="m"><a href="/shop?cat=Bicycles">Bicycles</a><a href="/shop?cat=Rickshaw+parts">Rickshaw parts</a><a href="/shop">All parts</a><a href="/#dealers">Dealers</a><a href="/#contact">Contact</a></nav>
<a class="cartb" href="/cart">Cart (<span id="cc">0</span>)</a></div></header>
${body}
<footer class="f" id="contact"><div class="w" style="padding-top:48px;padding-bottom:32px;display:flex;flex-wrap:wrap;gap:32px;justify-content:space-between"><div style="flex:1 1 280px"><div style="font:800 22px Manrope">M/S. Green Cycle Store</div><p style="color:#B8C7BF">458/P, Omar Chand Road (City College Mor), Chattogram<br>Call ${esc(PHONE)} · WhatsApp 01611 747 299<br>greencycle.ctg@gmail.com</p></div>
<div style="flex:1 1 160px;display:flex;flex-direction:column;gap:8px"><b>Shop</b><a href="/shop?cat=Bicycles">Bicycles</a><a href="/shop?cat=Rickshaw+parts">Rickshaw parts</a><a href="/shop">All parts</a></div></div>
<div style="border-top:1px solid #2B3B33;text-align:center;padding:16px;color:#B8C7BF;font-size:14px">© M/S. Green Cycle Store. All rights reserved.</div></footer><script>${PUB_JS}</script></body></html>`;
}
const code = p => (p.sku || p.name).split('-')[0].slice(0, 4).toUpperCase();
const pcard = p => `<a class="card" href="/product/${p.id}"><span class="ph">${p.image ? `<img src="${esc(p.image)}" alt="${esc(p.name)}">` : esc(code(p))}${p.stock <= 0 ? '<span class="tag" style="position:absolute;top:10px;left:10px">Out of stock</span>' : p.stock <= p.reorder ? '<span class="tag" style="position:absolute;top:10px;left:10px">Only few left</span>' : ''}</span>
<span><span class="muted" style="font-size:13px">${esc(p.category)}</span><br><b style="font-size:17px">${esc(p.name)}</b></span><span style="display:flex;justify-content:space-between;align-items:center"><span class="price">${money(p.price)}</span><span class="btn" style="padding:8px 16px;font-size:14px">View</span></span></a>`;
function pageHome() {
  const cats = all("SELECT category,COUNT(*) n FROM products WHERE web=1 GROUP BY category ORDER BY category");
  const feat = all('SELECT * FROM products WHERE web=1 ORDER BY id LIMIT 8');
  return pubPage('M/S. Green Cycle Store — Cycle and rickshaw parts, Chattogram', `
<section class="w" style="padding-top:56px;display:flex;flex-wrap:wrap;gap:32px"><div style="flex:1 1 440px;display:flex;flex-direction:column;justify-content:center;gap:24px"><span style="align-self:flex-start;background:#E7EEF8;color:#1F3F6E;font-weight:600;font-size:14px;padding:6px 14px;border-radius:999px">Importer &amp; Dealer · Chattogram</span>
<h1 style="font-size:54px;line-height:1.05;letter-spacing:-.02em">Cycle and rickshaw parts, all in one place.</h1><p class="muted" style="font-size:18px;margin:0;max-width:480px">All kinds of cycle and rickshaw parts seller. Visit our shop at City College Mor or order online.</p>
<div style="display:flex;flex-wrap:wrap;gap:12px"><a class="btn" href="/shop">Shop parts</a><a class="btn o" href="https://wa.me/${WA}">Order on WhatsApp</a></div></div>
<div style="flex:1 1 440px;min-height:340px;border-radius:24px;background:#F6C32B;display:flex;align-items:center;justify-content:center"><img src="/logo.png" alt="" style="width:260px;height:260px;object-fit:contain;border-radius:24px"></div></section>
<section class="w sec"><h2 style="font-size:32px;margin-bottom:20px">Shop by category</h2><div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(170px,1fr))">${cats.map(c => `<a class="card" href="/shop?cat=${encodeURIComponent(c.category)}"><span class="ph" style="height:90px;font-size:22px">${esc(c.category.slice(0, 4).toUpperCase())}</span><b>${esc(c.category)}</b><span class="muted" style="font-size:14px">${c.n} products</span></a>`).join('')}</div></section>
<section class="w sec"><div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:20px"><h2 style="font-size:32px">Featured products</h2><a href="/shop" style="color:#1F3F6E;font-weight:600">See all</a></div><div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(250px,1fr))">${feat.map(pcard).join('')}</div></section>
<section class="w sec" id="dealers"><div class="card" style="border-radius:24px;padding:40px;flex-direction:row;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:24px"><div style="flex:1 1 380px"><h2 style="font-size:32px">Are you a dealer?</h2><p class="muted">Get wholesale price lists, credit terms and priority stock. Call ${esc(PHONE)} or message us on WhatsApp.</p></div><a class="btn" href="https://wa.me/${WA}">Request dealer prices</a></div></section>`);
}
function pageShop(q) {
  const cat = q.get('cat') || '', s = (q.get('q') || '').trim();
  const cats = all('SELECT category,COUNT(*) n FROM products WHERE web=1 GROUP BY category ORDER BY category');
  let sql = 'SELECT * FROM products WHERE web=1', a = [];
  if (cat) { sql += ' AND category=?'; a.push(cat); } if (s) { sql += ' AND (name LIKE ? OR sku LIKE ?)'; a.push('%' + s + '%', '%' + s + '%'); }
  const ord = { low: 'price ASC', high: 'price DESC' }[q.get('sort')] || 'id ASC';
  const ps = all(sql + ' ORDER BY ' + ord, ...a);
  return pubPage('Shop — Green Cycle Store', `<main class="w" style="padding-top:32px"><h1 style="font-size:40px">${esc(cat || 'All products')}</h1><p class="muted">${ps.length} products</p>
<div style="display:flex;flex-wrap:wrap;gap:32px;align-items:flex-start;margin-top:20px"><form method="get" class="card" style="flex:1 1 240px;max-width:280px;border-radius:18px;padding:24px"><label>Search<input name="q" value="${esc(s)}" placeholder="Name or SKU"></label>
<label>Category<select name="cat"><option value="">All</option>${cats.map(c => `<option ${c.category === cat ? 'selected' : ''} value="${esc(c.category)}">${esc(c.category)} (${c.n})</option>`).join('')}</select></label>
<label>Sort by<select name="sort"><option value="">Featured</option><option value="low" ${q.get('sort') === 'low' ? 'selected' : ''}>Price: low to high</option><option value="high" ${q.get('sort') === 'high' ? 'selected' : ''}>Price: high to low</option></select></label><button class="btn">Apply</button></form>
<section style="flex:999 1 560px;min-width:0"><div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(240px,1fr))">${ps.map(pcard).join('') || '<p class="muted">No products found.</p>'}</div></section></div></main>`);
}
function pageProduct(id) {
  const p = get('SELECT * FROM products WHERE id=? AND web=1', id); if (!p) return null;
  const rel = all('SELECT * FROM products WHERE web=1 AND id<>? AND category=? LIMIT 4', p.id, p.category);
  return pubPage(p.name + ' — Green Cycle Store', `<main class="w" style="padding-top:32px"><div class="muted" style="font-size:14px"><a href="/">Home</a> / <a href="/shop?cat=${encodeURIComponent(p.category)}">${esc(p.category)}</a> / ${esc(p.name)}</div>
<div style="display:flex;flex-wrap:wrap;gap:40px;margin-top:20px;align-items:flex-start"><div class="ph" style="flex:1 1 440px;height:420px;border-radius:24px;font-size:56px">${p.image ? `<img src="${esc(p.image)}" alt="${esc(p.name)}">` : esc(code(p))}</div>
<div style="flex:1 1 400px;display:flex;flex-direction:column;gap:18px"><span class="tag" style="align-self:flex-start;background:${p.stock > 0 ? '#E7EEF8' : '#FDE8E8'};color:${p.stock > 0 ? '#1F3F6E' : '#A32020'}">${p.stock > 0 ? 'In stock' : 'Out of stock'}</span><h1 style="font-size:40px;line-height:1.1">${esc(p.name)}</h1><span class="muted">SKU ${esc(p.sku)} · ${esc(p.category)}</span>
<div class="price" style="font-size:36px">${money(p.price)}</div>${p.description ? `<p class="muted" style="margin:0">${esc(p.description)}</p>` : ''}
<div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center"><input id="q" type="number" min="1" value="1" style="width:90px" aria-label="Quantity"><button class="btn" data-add="${p.id}" data-label="Add to cart" ${p.stock > 0 ? '' : 'disabled'}>Add to cart</button><a class="btn o" href="https://wa.me/${WA}?text=${encodeURIComponent('I want to order: ' + p.name)}">Order on WhatsApp</a></div>
<div class="card" style="border-radius:18px;padding:8px 20px"><div style="padding:12px 0;display:flex;justify-content:space-between"><b>Pickup</b><span class="muted">458/P, Omar Chand Road, Chattogram</span></div><div style="padding:12px 0;border-top:1px solid #EEF2F0;display:flex;justify-content:space-between"><b>Call</b><span class="muted">${esc(PHONE)}</span></div></div></div></div>
${rel.length ? `<section class="sec"><h2 style="font-size:26px;margin-bottom:20px">Related products</h2><div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(240px,1fr))">${rel.map(pcard).join('')}</div></section>` : ''}</main>`);
}
function pageCart() {
  return pubPage('Cart — Green Cycle Store', `<main class="w" style="padding-top:32px"><h1 style="font-size:40px;margin-bottom:24px">Cart and checkout</h1><div id="app"></div></main>
<script>(async()=>{const app=document.getElementById('app');const money=n=>'৳ '+Math.round(n).toLocaleString('en-US');
async function draw(){const c=cart();const ids=Object.keys(c);if(!ids.length){app.innerHTML='<p class="muted">Your cart is empty.</p><a class="btn" href="/shop">Browse products</a>';return}
const ps=await (await fetch('/api/cart?ids='+ids.join(','))).json();let t=0;const rows=ps.map(p=>{const q=c[p.id];t+=q*p.price;return '<div style="display:flex;flex-wrap:wrap;align-items:center;gap:16px;padding:16px 0;border-top:1px solid #EEF2F0"><b style="flex:1 1 180px">'+p.name+'</b><span>'+money(p.price)+'</span><input type="number" min="1" value="'+q+'" style="width:84px" onchange="setq('+p.id+',this.value)"><b style="min-width:96px;text-align:right">'+money(q*p.price)+'</b><button class="btn o" style="padding:8px 14px" onclick="setq('+p.id+',0)">Remove</button></div>'}).join('');
app.innerHTML='<div style="display:flex;flex-wrap:wrap;gap:32px;align-items:flex-start"><div style="flex:999 1 520px"><div class="card" style="border-radius:20px;padding:8px 24px">'+rows+'</div>'+
'<form id="f" class="card" style="border-radius:20px;padding:24px;margin-top:24px"><h2 style="font-size:22px">Delivery details</h2><div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(240px,1fr))"><label>Full name<input name="name" required></label><label>Phone number<input name="phone" required placeholder="01XXX XXX XXX"></label><label>City<input name="city" required></label><label>Full address<input name="address" required></label></div>'+
'<label>Payment method<select name="payment"><option>Cash on delivery</option><option>bKash</option><option>Nagad</option><option>Pay at shop</option></select></label><label>Note (optional)<textarea name="note"></textarea></label><p id="err" style="color:#A32020;margin:0"></p><button class="btn y">Place order</button></form></div>'+
'<aside class="card" style="flex:1 1 300px;max-width:380px;border-radius:20px;padding:24px"><h2 style="font-size:22px">Order summary</h2><div style="display:flex;justify-content:space-between"><span class="muted">Subtotal</span><span>'+money(t)+'</span></div><div style="display:flex;justify-content:space-between;border-top:1px solid #DDE5DF;padding-top:14px"><b>Total</b><span class="price" style="font-size:28px">'+money(t)+'</span></div><span class="muted" style="font-size:13px">Delivery charge is confirmed by phone.</span></aside></div>';
document.getElementById('f').onsubmit=async e=>{e.preventDefault();const fd=Object.fromEntries(new FormData(e.target));fd.items=ids.map(id=>({id:+id,qty:c[id]}));const r=await fetch('/api/order',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(fd)});const j=await r.json();
if(!j.ok){document.getElementById('err').textContent=j.error||'Could not place order';return}localStorage.removeItem(K);badge();app.innerHTML='<div class="card" style="border-radius:20px;padding:32px"><h2>Thank you! Order #'+j.no+' received.</h2><p class="muted">We will call you shortly to confirm delivery and payment.</p><a class="btn" href="https://wa.me/${WA}?text='+encodeURIComponent('Order #'+j.no)+'">Message us on WhatsApp</a></div>'}}
window.setq=(id,q)=>{const c=cart();q=+q;if(q>0)c[id]=q;else delete c[id];save(c);draw()};draw()})()</script>`);
}

/* ---------- admin ---------- */
const A_CSS = `*{box-sizing:border-box}body{margin:0;background:#F4F7F5;color:#14211C;font:15px/1.5 Inter,system-ui,sans-serif}a{color:inherit;text-decoration:none}
.sh{display:flex;flex-wrap:wrap;min-height:100vh}aside{flex:1 1 220px;max-width:240px;min-width:200px;background:#fff;border-right:1px solid #E3E9E6;padding:20px 14px}main{flex:999 1 560px;min-width:0;padding:28px 32px}
.br{display:flex;align-items:center;gap:10px;font-weight:700;font-size:17px;padding:0 8px 14px}.br img{width:40px;height:40px}aside a{display:block;padding:9px 14px;border-radius:10px;font-weight:500}aside a.on{background:#E7EEF8;color:#1F3F6E;font-weight:700}aside small{display:block;padding:14px 14px 4px;font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#5B6B64}
h1{margin:0 0 4px;font-size:26px}.sub{color:#5B6B64;margin:0 0 20px}.kp{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin-bottom:20px}.k{background:#fff;border:1px solid #E3E9E6;border-radius:14px;padding:14px 18px}.k span{color:#5B6B64;font-size:14px}.k b{display:block;font-size:24px}
.pn{background:#fff;border:1px solid #E3E9E6;border-radius:14px;padding:18px 22px;margin-bottom:18px;overflow-x:auto}.pn h2{margin:0 0 10px;font-size:17px}table{width:100%;border-collapse:collapse;min-width:520px}th{text-align:left;color:#5B6B64;font-size:13px;font-weight:600;padding:8px 8px 8px 0}td{padding:10px 8px 10px 0;border-top:1px solid #EEF2F0}.r{text-align:right}
input,select,textarea{font:inherit;padding:8px 10px;border:1px solid #B9C7C0;border-radius:9px;background:#fff;max-width:100%}.btn{background:#1F3F6E;color:#fff;border:0;border-radius:10px;padding:10px 18px;font:600 15px Inter;cursor:pointer;display:inline-block}.btn.g{background:#fff;color:#14211C;border:1px solid #B9C7C0}.btn.s{padding:6px 12px;font-size:13px}
.fr{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;margin-bottom:12px}.fr label{display:flex;flex-direction:column;gap:4px;font-size:13px;color:#5B6B64}.pill{padding:2px 10px;border-radius:999px;font-size:12px;font-weight:600;background:#EEF2F0}.bad{background:#FDE8E8;color:#A32020}.warn{background:#FFF1E0;color:#8A3B00}.ok{background:#E3F3EC;color:#0B6B4F}.msg{background:#E7EEF8;border-radius:10px;padding:10px 14px;margin-bottom:16px;font-weight:600}.err{background:#FDE8E8;color:#A32020}`;
const NAV = [['Dashboard', '/admin'], ['POS / New sale', '/admin/pos'], ['Sales', '/admin/sales'], ['Web orders', '/admin/orders'], ['Purchase', '/admin/purchase'], ['Products & stock', '/admin/products'], ['Customers', '/admin/customers'], ['Suppliers', '/admin/suppliers'], ['Cash book', '/admin/cash'], ['Reports', '/admin/reports']];
function adminPage(path, title, body, q) {
  const m = q && q.get('msg'), e = q && q.get('err');
  return `<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>${esc(title)} — Green Cycle ERP</title><link rel="icon" href="/logo.png"><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap"><style>${A_CSS}</style></head><body><div class="sh"><aside><div class="br"><img src="/logo.png" alt="">Green Cycle ERP</div>
${NAV.map(n => `<a href="${n[1]}" class="${n[1] === path ? 'on' : ''}">${n[0]}</a>`).join('')}<small>Site</small><a href="/" target="_blank">View website</a><a href="/admin/logout">Log out</a></aside><main>${m ? `<div class="msg">${esc(m)}</div>` : ''}${e ? `<div class="msg err">${esc(e)}</div>` : ''}<h1>${esc(title)}</h1>${body}</main></div></body></html>`;
}
const kpi = (l, v) => `<div class="k"><span>${l}</span><b>${v}</b></div>`;
const cashBal = () => { const r = get("SELECT COALESCE(SUM(CASE WHEN type='in' THEN amount ELSE -amount END),0) b FROM cash"); return r.b; };
function pDash(q) {
  const t = today(), mo = t.slice(0, 7);
  const ts = get('SELECT COALESCE(SUM(total),0) s,COUNT(*) n FROM sales WHERE date=?', t), ms = get('SELECT COALESCE(SUM(total),0) s FROM sales WHERE date LIKE ?', mo + '%');
  const sv = get('SELECT COALESCE(SUM(stock*cost),0) v FROM products').v, rec = get('SELECT COALESCE(SUM(balance),0) v FROM customers').v, pay = get('SELECT COALESCE(SUM(balance),0) v FROM suppliers').v;
  const low = all('SELECT * FROM products WHERE stock<=reorder ORDER BY stock LIMIT 10'), recent = all('SELECT s.*,c.name cn FROM sales s LEFT JOIN customers c ON c.id=s.customer_id ORDER BY s.id DESC LIMIT 8');
  const nw = get("SELECT COUNT(*) n FROM orders WHERE status='New'").n;
  return adminPage('/admin', 'Dashboard', `<p class="sub">${today()}</p>
<div class="kp">${kpi("Today's sales", money(ts.s) + ` <small style="font-size:13px;color:#5B6B64">(${ts.n})</small>`)}${kpi('This month', money(ms.s))}${kpi('Cash in hand', money(cashBal()))}${kpi('Stock value (cost)', money(sv))}${kpi('Customers owe', money(rec))}${kpi('You owe suppliers', money(pay))}${kpi('New web orders', nw)}</div>
<div class="pn"><h2>Low stock</h2><table><tr><th>Product</th><th>SKU</th><th class="r">In stock</th><th class="r">Reorder level</th></tr>${low.map(p => `<tr><td>${esc(p.name)}</td><td>${esc(p.sku)}</td><td class="r"><span class="pill ${p.stock <= 0 ? 'bad' : 'warn'}">${p.stock}</span></td><td class="r">${p.reorder}</td></tr>`).join('') || '<tr><td colspan=4>All stock levels are fine.</td></tr>'}</table></div>
<div class="pn"><h2>Recent sales</h2><table><tr><th>#</th><th>Date</th><th>Customer</th><th class="r">Total</th><th class="r">Paid</th></tr>${recent.map(s => `<tr><td>${s.id}</td><td>${s.date}</td><td>${esc(s.cn || '-')}</td><td class="r">${money(s.total)}</td><td class="r">${money(s.paid)}</td></tr>`).join('') || '<tr><td colspan=5>No sales yet.</td></tr>'}</table></div>`, q);
}
const lineJS = (mode, ps) => `<script>const OPTS=${JSON.stringify('<option value="">Select product</option>' + ps.map(p => `<option value="${p.id}" data-p="${p.price}" data-c="${p.cost}">${esc(p.name)} (stock ${p.stock})</option>`).join('')).replace(/</g, '\\u003c')};const MODE='${mode}';
function addRow(){const d=document.createElement('div');d.className='fr';d.innerHTML='<label>Product<select name="pid" onchange="pick(this)" style="min-width:240px">'+OPTS+'</select></label><label>Qty<input name="qty" type="number" min="1" step="any" value="1" oninput="calc()" style="width:80px"></label><label>'+(MODE=='sale'?'Price':'Cost')+'<input name="price" type="number" step="any" oninput="calc()" style="width:110px"></label><button type="button" class="btn g s" onclick="this.parentNode.remove();calc()">Remove</button>';document.getElementById('rows').appendChild(d);calc()}
function pick(s){const o=s.selectedOptions[0];s.closest('.fr').querySelector('[name=price]').value=o?o.dataset[MODE=='sale'?'p':'c']||'':'';calc()}
function calc(){let t=0;document.querySelectorAll('#rows .fr').forEach(r=>{t+=(+r.querySelector('[name=qty]').value||0)*(+r.querySelector('[name=price]').value||0)});const d=document.querySelector('[name=discount]');t-=d?(+d.value||0):0;document.getElementById('tot').textContent='৳ '+Math.round(t).toLocaleString('en-US')}addRow()</script>`;
function pPos(q) {
  const ps = all('SELECT * FROM products ORDER BY name'), cs = all('SELECT * FROM customers ORDER BY name');
  return adminPage('/admin/pos', 'New sale (POS)', `<form method="post" action="/admin/pos" class="pn"><div class="fr"><label>Customer<select name="customer_id">${cs.map(c => `<option value="${c.id}">${esc(c.name)}${c.balance ? ' (due ' + money(c.balance) + ')' : ''}</option>`).join('')}</select></label></div><div id="rows"></div><button type="button" class="btn g s" onclick="addRow()">+ Add product</button>
<div class="fr" style="margin-top:16px"><label>Discount<input name="discount" type="number" step="any" value="0" oninput="calc()" style="width:110px"></label><label>Amount received<input name="paid" type="number" step="any" value="0" style="width:130px"></label><label>Note<input name="note"></label></div><p style="font-size:20px;font-weight:700">Total: <span id="tot">৳ 0</span></p><button class="btn">Complete sale</button></form>${lineJS('sale', ps)}`, q);
}
const lines = f => { const p = f.getAll('pid'), qn = f.getAll('qty'), pr = f.getAll('price'); return p.map((x, i) => ({ pid: +x, qty: num(qn[i]), price: num(pr[i]), cost: num(pr[i]) })); };
function pSales(q) {
  const rows = all('SELECT s.*,c.name cn FROM sales s LEFT JOIN customers c ON c.id=s.customer_id ORDER BY s.id DESC LIMIT 100');
  return adminPage('/admin/sales', 'Sales', `<div class="pn"><table><tr><th>#</th><th>Date</th><th>Customer</th><th>Items</th><th>Source</th><th class="r">Total</th><th class="r">Paid</th><th class="r">Due</th></tr>${rows.map(s => { const it = all('SELECT p.name,i.qty FROM sale_items i JOIN products p ON p.id=i.product_id WHERE sale_id=?', s.id).map(i => esc(i.name) + ' ×' + i.qty).join(', '); return `<tr><td>${s.id}</td><td>${s.date}</td><td>${esc(s.cn || '-')}</td><td>${it}</td><td>${s.source}</td><td class="r">${money(s.total)}</td><td class="r">${money(s.paid)}</td><td class="r">${money(s.total - s.paid)}</td></tr>`; }).join('') || '<tr><td colspan=8>No sales yet.</td></tr>'}</table></div>`, q);
}
function pOrders(q) {
  const rows = all('SELECT * FROM orders ORDER BY id DESC LIMIT 100');
  return adminPage('/admin/orders', 'Web orders', `<div class="pn"><table><tr><th>#</th><th>Date</th><th>Customer</th><th>Items</th><th>Payment</th><th class="r">Total</th><th>Status</th><th></th></tr>${rows.map(o => { const it = JSON.parse(o.items).map(i => esc(i.name) + ' ×' + i.qty).join(', '); return `<tr><td>${o.id}</td><td>${o.date}</td><td>${esc(o.name)}<br><small>${esc(o.phone)} · ${esc(o.city)}, ${esc(o.address)}</small></td><td>${it}</td><td>${esc(o.payment)}</td><td class="r">${money(o.total)}</td><td><span class="pill ${o.status === 'New' ? 'warn' : o.status === 'Confirmed' ? 'ok' : ''}">${o.status}</span></td><td>${o.status === 'New' ? `<form method="post" action="/admin/orders" style="display:flex;gap:6px"><input type="hidden" name="id" value="${o.id}"><button name="act" value="confirm" class="btn s">Confirm &amp; make sale</button><button name="act" value="cancel" class="btn g s">Cancel</button></form>` : ''}</td></tr>`; }).join('') || '<tr><td colspan=8>No web orders yet.</td></tr>'}</table></div>`, q);
}
function pPurchase(q) {
  const ps = all('SELECT * FROM products ORDER BY name'), ss = all('SELECT * FROM suppliers ORDER BY name');
  const recent = all('SELECT p.*,s.name sn FROM purchases p LEFT JOIN suppliers s ON s.id=p.supplier_id ORDER BY p.id DESC LIMIT 20');
  return adminPage('/admin/purchase', 'Purchase', `<form method="post" action="/admin/purchase" class="pn"><div class="fr"><label>Supplier<select name="supplier_id">${ss.map(s => `<option value="${s.id}">${esc(s.name)}</option>`).join('')}</select></label></div><div id="rows"></div><button type="button" class="btn g s" onclick="addRow()">+ Add product</button>
<div class="fr" style="margin-top:16px"><label>Paid now<input name="paid" type="number" step="any" value="0" style="width:130px"></label><label>Note<input name="note"></label></div><p style="font-size:20px;font-weight:700">Total: <span id="tot">৳ 0</span></p><button class="btn">Save purchase &amp; add stock</button></form>${lineJS('purchase', ps)}
<div class="pn"><h2>Recent purchases</h2><table><tr><th>#</th><th>Date</th><th>Supplier</th><th class="r">Total</th><th class="r">Paid</th></tr>${recent.map(p => `<tr><td>${p.id}</td><td>${p.date}</td><td>${esc(p.sn || '-')}</td><td class="r">${money(p.total)}</td><td class="r">${money(p.paid)}</td></tr>`).join('') || '<tr><td colspan=5>No purchases yet.</td></tr>'}</table></div>`, q);
}
function pProducts(q) {
  const ps = all('SELECT * FROM products ORDER BY category,name');
  return adminPage('/admin/products', 'Products & stock', `<p><a class="btn" href="/admin/product">+ New product</a></p><div class="pn"><table><tr><th>Name</th><th>SKU</th><th>Category</th><th class="r">Price</th><th class="r">Cost</th><th class="r">Stock</th><th class="r">Reorder</th><th>On web</th><th></th></tr>${ps.map(p => `<tr><td>${esc(p.name)}</td><td>${esc(p.sku)}</td><td>${esc(p.category)}</td><td class="r">${money(p.price)}</td><td class="r">${money(p.cost)}</td><td class="r"><span class="pill ${p.stock <= 0 ? 'bad' : p.stock <= p.reorder ? 'warn' : 'ok'}">${p.stock}</span></td><td class="r">${p.reorder}</td><td>${p.web ? 'Yes' : 'No'}</td><td><a class="btn g s" href="/admin/product?id=${p.id}">Edit</a></td></tr>`).join('')}</table></div>`, q);
}
function pProduct(id, q) {
  const p = (id && get('SELECT * FROM products WHERE id=?', id)) || { name: '', sku: '', category: '', price: '', cost: '', stock: 0, reorder: 0, image: '', description: '', web: 1 };
  const f = (n, l, t = 'text', w = 220) => `<label>${l}<input name="${n}" type="${t}" step="any" value="${esc(p[n])}" style="width:${w}px"></label>`;
  return adminPage('/admin/products', id ? 'Edit product' : 'New product', `<form method="post" action="/admin/product" class="pn"><input type="hidden" name="id" value="${id || ''}"><div class="fr">${f('name', 'Name', 'text', 280)}${f('sku', 'SKU')}${f('category', 'Category')}</div><div class="fr">${f('price', 'Sale price', 'number', 120)}${f('cost', 'Cost price', 'number', 120)}${f('stock', 'Stock', 'number', 100)}${f('reorder', 'Reorder level', 'number', 100)}</div>
<div class="fr">${f('image', 'Image URL (optional)', 'text', 420)}</div><div class="fr"><label>Description<textarea name="description" rows="3" style="width:520px">${esc(p.description)}</textarea></label></div><label style="flex-direction:row;gap:8px;font-size:15px;color:#14211C"><input type="checkbox" name="web" value="1" ${p.web ? 'checked' : ''}> Show on website</label><p><button class="btn">Save</button> <a class="btn g" href="/admin/products">Cancel</a></p></form>
${id ? `<form method="post" action="/admin/product/delete" onsubmit="return confirm('Delete this product?')"><input type="hidden" name="id" value="${id}"><button class="btn g s">Delete product</button></form>` : ''}`, q);
}
function pParties(kind, q) {
  const t = kind === 'c' ? 'customers' : 'suppliers', rows = all(`SELECT * FROM ${t} ORDER BY name`), path = '/admin/' + t;
  return adminPage(path, kind === 'c' ? 'Customers' : 'Suppliers', `<form method="post" action="${path}" class="pn"><h2>Add new</h2><div class="fr"><label>Name<input name="name" required></label><label>Phone<input name="phone"></label>${kind === 'c' ? '<label>Type<select name="type"><option>Retail</option><option>Dealer</option></select></label><label>Address<input name="address"></label>' : ''}<button class="btn">Add</button></div></form>
<div class="pn"><table><tr><th>Name</th><th>Phone</th>${kind === 'c' ? '<th>Type</th>' : ''}<th class="r">${kind === 'c' ? 'Due from customer' : 'We owe'}</th><th>${kind === 'c' ? 'Receive payment' : 'Make payment'}</th></tr>${rows.map(r => `<tr><td>${esc(r.name)}</td><td>${esc(r.phone)}</td>${kind === 'c' ? `<td>${r.type}</td>` : ''}<td class="r"><span class="pill ${r.balance > 0 ? 'warn' : 'ok'}">${money(r.balance)}</span></td><td><form method="post" action="${path}/pay" style="display:flex;gap:6px"><input type="hidden" name="id" value="${r.id}"><input name="amount" type="number" step="any" min="1" placeholder="Amount" style="width:110px"><button class="btn s">Save</button></form></td></tr>`).join('')}</table></div>`, q);
}
function pCash(q) {
  const rows = all('SELECT * FROM cash ORDER BY id DESC LIMIT 100'), t = today();
  const i = get("SELECT COALESCE(SUM(amount),0) v FROM cash WHERE type='in' AND date=?", t).v, o = get("SELECT COALESCE(SUM(amount),0) v FROM cash WHERE type='out' AND date=?", t).v;
  return adminPage('/admin/cash', 'Cash book', `<div class="kp">${kpi('Cash in hand', money(cashBal()))}${kpi('Received today', money(i))}${kpi('Paid out today', money(o))}</div>
<form method="post" action="/admin/cash" class="pn"><h2>Add entry</h2><div class="fr"><label>Type<select name="type"><option value="out">Expense / money out</option><option value="in">Money in</option></select></label><label>Head<input name="head" placeholder="Rent, transport, salary..." required></label><label>Amount<input name="amount" type="number" step="any" min="1" required></label><label>Note<input name="note"></label><button class="btn">Add</button></div></form>
<div class="pn"><table><tr><th>Date</th><th>Head</th><th>Note</th><th class="r">In</th><th class="r">Out</th></tr>${rows.map(r => `<tr><td>${r.date}</td><td>${esc(r.head)}</td><td>${esc(r.note)}</td><td class="r">${r.type === 'in' ? money(r.amount) : ''}</td><td class="r">${r.type === 'out' ? money(r.amount) : ''}</td></tr>`).join('') || '<tr><td colspan=5>No entries yet.</td></tr>'}</table></div>`, q);
}
function pReports(q) {
  const days = all('SELECT date,COUNT(*) n,SUM(total) t FROM sales GROUP BY date ORDER BY date DESC LIMIT 14');
  const top = all("SELECT p.name,SUM(i.qty) q,SUM(i.qty*i.price) r,SUM(i.qty*(i.price-i.cost)) pr FROM sale_items i JOIN products p ON p.id=i.product_id JOIN sales s ON s.id=i.sale_id WHERE s.date>=date('now','-30 day') GROUP BY p.id ORDER BY q DESC LIMIT 10");
  const mo = today().slice(0, 7), prof = get('SELECT COALESCE(SUM(i.qty*(i.price-i.cost)),0) v FROM sale_items i JOIN sales s ON s.id=i.sale_id WHERE s.date LIKE ?', mo + '%').v, exp = get("SELECT COALESCE(SUM(amount),0) v FROM cash WHERE type='out' AND head NOT IN ('Purchase','Supplier payment') AND date LIKE ?", mo + '%').v;
  const dues = all('SELECT name,balance FROM customers WHERE balance>0 ORDER BY balance DESC');
  return adminPage('/admin/reports', 'Reports', `<div class="kp">${kpi('Gross profit, this month', money(prof))}${kpi('Expenses, this month', money(exp))}${kpi('Net, this month', money(prof - exp))}</div>
<div class="pn"><h2>Sales by day</h2><table><tr><th>Date</th><th class="r">Invoices</th><th class="r">Sales</th></tr>${days.map(d => `<tr><td>${d.date}</td><td class="r">${d.n}</td><td class="r">${money(d.t)}</td></tr>`).join('') || '<tr><td colspan=3>No data yet.</td></tr>'}</table></div>
<div class="pn"><h2>Top products, last 30 days</h2><table><tr><th>Product</th><th class="r">Qty</th><th class="r">Sales</th><th class="r">Profit</th></tr>${top.map(d => `<tr><td>${esc(d.name)}</td><td class="r">${d.q}</td><td class="r">${money(d.r)}</td><td class="r">${money(d.pr)}</td></tr>`).join('') || '<tr><td colspan=4>No data yet.</td></tr>'}</table></div>
<div class="pn"><h2>Customer dues</h2><table><tr><th>Customer</th><th class="r">Due</th></tr>${dues.map(d => `<tr><td>${esc(d.name)}</td><td class="r">${money(d.balance)}</td></tr>`).join('') || '<tr><td colspan=2>No dues.</td></tr>'}</table></div>`, q);
}
const loginPage = err => `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Log in — Green Cycle ERP</title><style>${A_CSS}</style></head><body><main style="max-width:380px;margin:12vh auto"><div class="pn"><div class="br"><img src="/logo.png" alt="">Green Cycle ERP</div>${err ? '<div class="msg err">Wrong password</div>' : ''}<form method="post" action="/admin/login"><div class="fr"><label style="width:100%">Password<input type="password" name="password" autofocus style="width:100%"></label></div><button class="btn">Log in</button></form></div></main></body></html>`;

/* ---------- router ---------- */
const server = http.createServer(async (req, res) => {
  try {
    const u = new URL(req.url, 'http://x'), path = u.pathname.replace(/\/+$/, '') || '/', q = u.searchParams, post = req.method === 'POST';
    if (path === '/healthz') return send(res, 200, 'ok', 'text/plain');
    if (path === '/logo.png') return send(res, 200, LOGO, 'image/png', { 'Cache-Control': 'public,max-age=86400' });
    if (path === '/robots.txt') return send(res, 200, 'User-agent: *\nDisallow: /admin\n', 'text/plain');
    if (!path.startsWith('/admin')) {
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
      if (post) { const f = new URLSearchParams(await readBody(req)); if (f.get('password') === ADMIN_PASSWORD) return redirect(res, '/admin', { 'Set-Cookie': `gcs=${makeToken()}; Path=/; HttpOnly; Secure; SameSite=Lax; Max-Age=604800` }); return send(res, 401, loginPage(true)); }
      return send(res, 200, loginPage(false));
    }
    if (path === '/admin/logout') return redirect(res, '/admin/login', { 'Set-Cookie': 'gcs=; Path=/; Max-Age=0' });
    if (!authed(req)) return redirect(res, '/admin/login');
    const back = (to, msg, err) => redirect(res, to + (msg ? '?msg=' + encodeURIComponent(msg) : '') + (err ? '?err=' + encodeURIComponent(err) : ''));
    if (!post) {
      const pages = { '/admin': pDash, '/admin/pos': pPos, '/admin/sales': pSales, '/admin/orders': pOrders, '/admin/purchase': pPurchase, '/admin/products': pProducts, '/admin/customers': q2 => pParties('c', q2), '/admin/suppliers': q2 => pParties('s', q2), '/admin/cash': pCash, '/admin/reports': pReports };
      if (pages[path]) return send(res, 200, pages[path](q));
      if (path === '/admin/product') return send(res, 200, pProduct(+q.get('id') || 0, q));
      return send(res, 404, 'Not found', 'text/plain');
    }
    const f = new URLSearchParams(await readBody(req));
    try {
      if (path === '/admin/pos') { const id = createSale({ customer_id: +f.get('customer_id') || null, items: lines(f), discount: num(f.get('discount')), paid: num(f.get('paid')), note: f.get('note') || '' }); return back('/admin/sales', 'Sale #' + id + ' saved'); }
      if (path === '/admin/purchase') { const id = createPurchase({ supplier_id: +f.get('supplier_id') || null, items: lines(f).map(i => ({ ...i, cost: i.price })), paid: num(f.get('paid')), note: f.get('note') || '' }); return back('/admin/purchase', 'Purchase #' + id + ' saved, stock updated'); }
      if (path === '/admin/product') {
        const v = ['name', 'sku', 'category', 'image', 'description'].map(k => (f.get(k) || '').trim()); const n = ['price', 'cost', 'stock', 'reorder'].map(k => num(f.get(k))), web = f.get('web') ? 1 : 0, id = +f.get('id');
        if (!v[0] || !v[2]) return back('/admin/product' + (id ? '?id=' + id : ''), '', 'Name and category are required');
        if (id) run('UPDATE products SET name=?,sku=?,category=?,image=?,description=?,price=?,cost=?,stock=?,reorder=?,web=? WHERE id=?', ...v, ...n, web, id);
        else run('INSERT INTO products(name,sku,category,image,description,price,cost,stock,reorder,web) VALUES(?,?,?,?,?,?,?,?,?,?)', ...v, ...n, web);
        return back('/admin/products', 'Product saved');
      }
      if (path === '/admin/product/delete') { const id = +f.get('id'); if (get('SELECT 1 FROM sale_items WHERE product_id=?', id) || get('SELECT 1 FROM purchase_items WHERE product_id=?', id)) { run('UPDATE products SET web=0 WHERE id=?', id); return back('/admin/products', 'Product has history, so it was hidden from the website instead of deleted'); } run('DELETE FROM products WHERE id=?', id); return back('/admin/products', 'Product deleted'); }
      if (path === '/admin/customers') { run('INSERT INTO customers(name,phone,type,address) VALUES(?,?,?,?)', f.get('name'), f.get('phone') || '', f.get('type') || 'Retail', f.get('address') || ''); return back('/admin/customers', 'Customer added'); }
      if (path === '/admin/suppliers') { run('INSERT INTO suppliers(name,phone) VALUES(?,?)', f.get('name'), f.get('phone') || ''); return back('/admin/suppliers', 'Supplier added'); }
      if (path === '/admin/customers/pay' || path === '/admin/suppliers/pay') {
        const c = path.includes('customers'), id = +f.get('id'), amt = num(f.get('amount')); if (amt <= 0) throw new Error('Enter an amount');
        tx(() => { run(`UPDATE ${c ? 'customers' : 'suppliers'} SET balance=balance-? WHERE id=?`, amt, id); run('INSERT INTO cash(date,type,amount,head,note) VALUES(?,?,?,?,?)', today(), c ? 'in' : 'out', amt, c ? 'Customer payment' : 'Supplier payment', (c ? 'Customer #' : 'Supplier #') + id); });
        return back(c ? '/admin/customers' : '/admin/suppliers', 'Payment saved');
      }
      if (path === '/admin/cash') { const a = num(f.get('amount')); if (a <= 0) throw new Error('Enter an amount'); run('INSERT INTO cash(date,type,amount,head,note) VALUES(?,?,?,?,?)', today(), f.get('type') === 'in' ? 'in' : 'out', a, f.get('head'), f.get('note') || ''); return back('/admin/cash', 'Entry added'); }
      if (path === '/admin/orders') {
        const o = get('SELECT * FROM orders WHERE id=?', +f.get('id'));
        if (o && o.status === 'New') {
          if (f.get('act') === 'cancel') { run("UPDATE orders SET status='Cancelled' WHERE id=?", o.id); return back('/admin/orders', 'Order cancelled'); }
          let cust = get('SELECT * FROM customers WHERE phone=?', o.phone); const cid = cust ? cust.id : +run('INSERT INTO customers(name,phone,address) VALUES(?,?,?)', o.name, o.phone, o.address).lastInsertRowid;
          const sid = createSale({ customer_id: cid, items: JSON.parse(o.items).map(i => ({ pid: i.id, qty: i.qty, price: i.price })), paid: 0, note: 'Web order #' + o.id + ' (' + o.payment + ')', source: 'Web' });
          run("UPDATE orders SET status='Confirmed' WHERE id=?", o.id); return back('/admin/orders', 'Order confirmed, sale #' + sid + ' created');
        }
        return back('/admin/orders');
      }
    } catch (e) { return back(req.headers.referer ? new URL(req.headers.referer).pathname : '/admin', '', e.message); }
    send(res, 404, 'Not found', 'text/plain');
  } catch (e) { console.error(e); send(res, 500, 'Server error', 'text/plain'); }
});
server.listen(PORT, () => console.log('Green Cycle app on ' + PORT));
