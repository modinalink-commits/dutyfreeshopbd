
/* ---------- admin pages: dashboard, POS, sales ---------- */
const GETS = {}, POSTS = {};
const go = (p, msg) => p + (msg ? (p.includes('?') ? '&' : '?') + 'msg=' + encodeURIComponent(msg) : '');
const WD = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const saleStatus = s => s.total - s.paid <= 0 ? pill('Paid', 'ok') : s.paid > 0 ? pill('Partly due', 'warn') : pill('Due', 'bad');

GETS['/admin'] = ({ role, q }) => {
  const d = today(), ts = get('SELECT COUNT(*) n, COALESCE(SUM(total),0) s FROM sales WHERE date=?', d);
  const cashIn = get("SELECT COALESCE(SUM(amount),0) s FROM cash WHERE type='in' AND date=? AND head IN ('Sale','Customer payment','EMI installment')", d).s;
  const sv = get('SELECT COALESCE(SUM(MAX(stock,0)*cost),0) v, COUNT(*) n FROM products'), dues = get('SELECT COALESCE(SUM(balance),0) s, COUNT(*) n FROM customers WHERE balance>0');
  const days = []; for (let i = 6; i >= 0; i--) { const dd = addDays(d, -i); days.push({ l: i ? WD[new Date(dd + 'T00:00:00Z').getUTCDay()] : 'Today', v: get('SELECT COALESCE(SUM(total),0) s FROM sales WHERE date=?', dd).s }); }
  const mx = Math.max(1, ...days.map(x => x.v));
  const low = all('SELECT * FROM products WHERE stock<=reorder ORDER BY stock,name LIMIT 5');
  const recent = all('SELECT s.*,c.name cn FROM sales s LEFT JOIN customers c ON c.id=s.customer_id ORDER BY s.id DESC LIMIT 5');
  const nw = get("SELECT COUNT(*) n FROM orders WHERE status='New'").n, pend = get("SELECT COUNT(*) n FROM approvals WHERE status='Pending'").n;
  const alerts = (nw ? `<div class="msg" style="background:#FDEFD9;color:#8A4B00">${nw} new web order${nw > 1 ? 's' : ''} waiting. <a href="/admin/orders" style="text-decoration:underline">Open</a></div>` : '') + (pend && role === 'owner' ? `<div class="msg" style="background:#FDEFD9;color:#8A4B00">${pend} request${pend > 1 ? 's' : ''} waiting for your approval. <a href="/admin/approvals" style="text-decoration:underline">Review</a></div>` : '');
  return adminPage(role, '/admin', 'Dashboard', `${fmtDate(d)}`, lnk('/admin/inventory', 'View stock') + lnk('/admin/pos', '+ New sale', 'btn'),
    alerts + kpis([["Today's sales", money(ts.s), ts.n + ' invoices'], ["Today's cash received", money(cashIn), 'Cash sales + dues collected'], ['Total stock value', money(sv.v), sv.n + ' item types'], ['Customer dues', money(dues.s), dues.n + ' customers']]) +
    `<div class="g2"><div class="pn"><div style="display:flex;justify-content:space-between"><h2>Sales, last 7 days</h2><span class="mu" style="font-size:13px">In thousand taka</span></div><div class="bars">${days.map(x => `<div><b>${x.v ? Math.round(x.v / 1000) + 'K' : '0'}</b><i style="height:${Math.round(x.v / mx * 170)}px"></i><span>${x.l}</span></div>`).join('')}</div></div>
<div class="pn"><div style="display:flex;justify-content:space-between"><h2>Running low</h2><a href="/admin/reorder" style="color:#0B6B45;font-weight:700">View all</a></div>${low.map(p => `<div class="li"><span><b>${esc(p.name)}</b><small>${esc(p.sku)}</small></span>${p.stock <= 0 ? pill('Out of stock', 'bad') : pill(p.stock + ' left', 'warn')}</div>`).join('') || '<p class="mu">All stock levels are fine.</p>'}</div></div>
<div class="pn"><h2>Recent sales</h2>${tbl(['Invoice', 'Customer', 'Amount', 'Status'], recent.map(s => tr([`<b class="nm">${invNo(s.id)}</b>`, esc(s.cn || 'Walk-in customer'), money(s.total), saleStatus(s)])), 'No sales yet. Make your first sale from POS.')}</div>`, q);
};

/* ---- POS ---- */
GETS['/admin/pos'] = ({ role, q }) => {
  const ps = all('SELECT * FROM products ORDER BY name').map(p => ({ id: p.id, n: p.name, s: p.sku, c: p.category, pr: p.price, dp: p.dealer_price, wp: p.wholesale_price, cost: p.cost, st: p.stock, mm: p.min_margin, bc: p.barcode }));
  const cs = all("SELECT * FROM customers ORDER BY (name LIKE 'Walk-in%') DESC, name"), reps = all('SELECT * FROM reps WHERE active=1 ORDER BY name'), cats = [...new Set(ps.map(p => p.c))];
  const pre = Number(q.get('c')) || 0;
  const js = `const P=${JSON.stringify(ps).replace(/</g, '\\u003c')};let cart=[],cat='All',method='Cash',edited=false;const $=i=>document.getElementById(i),M=n=>'৳ '+Math.round(n).toLocaleString('en-US');
const typ=()=>{const o=$('cust').selectedOptions[0];return o?o.dataset.t:'Retail'};const pr=p=>typ()=='Dealer'&&p.dp>0?p.dp:typ()=='Wholesale'&&p.wp>0?p.wp:p.pr;
function grid(){const s=$('srch').value.toLowerCase().trim(),w=s.split(/\\s+/).filter(Boolean);$('grid').innerHTML=P.filter(p=>(cat=='All'||p.c==cat)&&w.every(t=>(p.n+' '+p.s+' '+p.bc).toLowerCase().includes(t))).map(p=>'<div class="pc'+(p.st<=0?' out':'')+'"><div class="t">'+(p.s||p.n).split('-')[0].slice(0,4).toUpperCase()+'</div><b>'+p.n.replace(/</g,'&lt;')+'</b><small>In stock: '+p.st+'</small><div class="pr"><span>'+M(pr(p))+'</span><button type="button" onclick="add('+p.id+')"'+(p.st<=0?' disabled':'')+'>+</button></div></div>').join('')||'<p class="mu">No product matches.</p>'}
function add(id){const p=P.find(x=>x.id==id);let l=cart.find(x=>x.id==id);if(l){if(l.qty<p.st)l.qty++;cart=[l,...cart.filter(x=>x!==l)]}else cart.unshift({id:id,qty:1,price:pr(p),frame:''});render()}
function chg(id,d){const l=cart.find(x=>x.id==id),p=P.find(x=>x.id==id);l.qty=Math.max(0,Math.min(p.st,l.qty+d));if(!l.qty)cart=cart.filter(x=>x!==l);render()}
function setP(id,v){cart.find(x=>x.id==id).price=+v||0;render(1)}function setF(id,v){cart.find(x=>x.id==id).frame=v}
function sub(){return cart.reduce((a,l)=>a+l.qty*l.price,0)}
function render(keep){const el=$('cart');el.innerHTML=cart.map(l=>{const p=P.find(x=>x.id==l.id),low=l.price<p.cost*(1+p.mm/100);return '<div class="cl"><div><b>'+p.n.replace(/</g,'&lt;')+'</b><br><small class="mu">৳ <input type="number" value="'+l.price+'" step="any" onchange="setP('+l.id+',this.value)" style="width:90px;padding:3px 6px"> × '+l.qty+'</small>'+(low?'<br><small class="warn">Below minimum margin</small>':'')+(p.c=='Bicycles'?'<br><input placeholder="Frame no. (optional)" value="'+l.frame.replace(/"/g,'&quot;')+'" onchange="setF('+l.id+',this.value)" style="margin-top:4px;padding:4px 8px;width:100%;font-size:13px">':'')+'</div><div class="qb"><button type="button" onclick="chg('+l.id+',-1)">−</button><b>'+l.qty+'</b><button type="button" onclick="chg('+l.id+',1)">+</button></div><b>'+M(l.qty*l.price)+'</b></div>'}).join('')||'<p class="mu">Cart is empty. Tap + on a product.</p>';totals()}
function totals(){const s=sub(),d=Math.min(s,+$('disc').value||0),t=s-d;$('sub').textContent=M(s);$('dpct').textContent=s?'('+(d/s*100).toFixed(1)+'%)':'';$('tot').textContent=M(t);if(!edited)$('paid').value=Math.round(t);const due=Math.max(0,t-(+$('paid').value||0));$('due').textContent=due?M(due)+' will remain due (added to the customer\\'s ledger)':'';$('cj').value=JSON.stringify(cart.map(l=>({id:l.id,qty:l.qty,price:l.price,frame:l.frame})))}
function setM(m,b){method=m;$('mth').value=m;document.querySelectorAll('.mb').forEach(x=>x.classList.toggle('on',x==b))}
function setC(c,b){cat=c;document.querySelectorAll('.cc').forEach(x=>x.classList.toggle('on',x==b));grid()}
$('cust').onchange=()=>{cart.forEach(l=>l.price=pr(P.find(x=>x.id==l.id)));grid();render()};
$('srch').onkeydown=e=>{if(e.key=='Enter'){e.preventDefault();const s=e.target.value.trim().toLowerCase();let p=P.find(x=>x.bc&&x.bc.toLowerCase()==s)||P.find(x=>x.s.toLowerCase()==s);if(!p){const m=P.filter(x=>(x.n+' '+x.s).toLowerCase().includes(s)&&x.st>0);if(m.length==1)p=m[0]}if(p){add(p.id);e.target.value='';grid()}}};
$('srch').oninput=grid;$('disc').oninput=()=>totals();$('paid').oninput=()=>{edited=true;totals()};
$('f').onsubmit=e=>{if(!cart.length){e.preventDefault();alert('Add at least one product');return}totals()};grid();render();$('srch').focus()`;
  return adminPage(role, '/admin/pos', 'New sale (POS)', 'Pick products, add to cart, take payment', lnk('/admin/sales', 'View sales'),
    `<form id="f" method="post" action="/admin/pos"><div class="pg"><div><div class="fl"><label style="flex:2 1 260px">Search a product or scan a barcode<input id="srch" placeholder="Name, SKU or barcode" autocomplete="off"></label><label>Customer<select id="cust" name="customer_id">${cs.map(c => `<option value="${c.id}" data-t="${esc(c.type)}" ${c.id === pre ? 'selected' : ''}>${esc(c.name)}${c.balance ? ' (due ' + money(c.balance) + ')' : ''}${c.type !== 'Retail' ? ' · ' + c.type : ''}</option>`).join('')}</select></label>${reps.length ? `<label>Sales rep<select name="rep_id"><option value="">None</option>${opts(reps)}</select></label>` : ''}</div>
<div class="chips"><button type="button" class="chip cc on" onclick="setC('All',this)">All</button>${cats.map(c => `<button type="button" class="chip cc" onclick="setC(${esc(JSON.stringify(c)).replace(/'/g, '&#39;')},this)">${esc(c)}</button>`).join('')}</div><div id="grid" class="pgrid"></div></div>
<div class="pn" style="position:sticky;top:12px"><h2>Cart</h2><div id="cart"></div><div class="row"><span>Subtotal</span><span id="sub">৳ 0</span></div><div class="row"><span>Discount <span id="dpct"></span></span><span>− ৳ <input id="disc" name="discount" type="number" step="any" value="0" min="0" style="width:100px;text-align:right;padding:4px 8px"></span></div><div class="row t"><span>Total</span><span id="tot">৳ 0</span></div>
<div class="mu" style="margin-top:12px;font-size:14px">Payment method</div><div class="chips" style="margin:6px 0"><input type="hidden" id="mth" name="method" value="Cash">${ACCTS.map((m, i) => `<button type="button" class="chip mb ${i ? '' : 'on'}" onclick="setM('${m}',this)">${m}</button>`).join('')}</div>
<label class="f">Amount received<input id="paid" name="paid" type="number" step="any" value="0" min="0"></label><div id="due" class="warn" style="min-height:22px;margin:6px 0"></div><label class="f" style="margin-bottom:12px">Note<input name="note" placeholder="Optional"></label><input type="hidden" id="cj" name="cart"><button class="btn full">Complete sale &amp; print invoice</button></div></div></form><script>${js}</script>`, q);
};
POSTS['/admin/pos'] = (f, { role }) => {
  const items = JSON.parse(f.get('cart') || '[]').map(i => ({ pid: +i.id, qty: num(i.qty), price: num(i.price), frame_no: i.frame || '' })).filter(i => i.pid && i.qty > 0);
  if (!items.length) throw new Error('Add at least one product');
  const sub = sum(items, i => i.qty * i.price), disc = Math.min(num(f.get('discount')), sub), total = sub - disc, paid = Math.min(num(f.get('paid')), total), cid = +f.get('customer_id') || null;
  const sale = { customer_id: cid, items, discount: disc, paid, note: f.get('note') || '', method: f.get('method') || 'Cash', rep_id: +f.get('rep_id') || null };
  if (role === 'staff') {
    const cu = cid && get('SELECT * FROM customers WHERE id=?', cid), pct = sub ? disc / sub * 100 : 0, over = cu && cu.credit_limit > 0 && cu.balance + (total - paid) > cu.credit_limit;
    const why = [S('disc_on') && pct > S('disc_pct') ? `discount ${pct.toFixed(1)}%` : '', S('credit_on') && over ? 'credit limit exceeded' : ''].filter(Boolean).join(' + ');
    if (why) { requestApproval('sale', 'Staff', `Sale of ${items.length} item(s) for ${cu ? cu.name : 'walk-in'}, ${why}`, total, sale); return go('/admin/pos', 'Sent for approval (' + why + '). The owner will confirm the sale.'); }
  }
  const id = createSale(sale); return `/invoice/${id}?k=${invKey(id)}`;
};
POSTS['/admin/sale-delete'] = (f, { role }) => { if (role !== 'owner') throw new Error('Only the owner can delete a sale'); deleteSale(+f.get('id')); return go('/admin/sales', 'Sale deleted, stock and balances restored'); };

const saleActions = (s, role) => { const k = invKey(s.id), l = `/invoice/${s.id}?k=${k}`, ph = waPhone(s.cp), txt = `Invoice ${invNo(s.id)} from M/S. Green Cycle Store\nTotal: ${money(s.total)}${s.total - s.paid > 0 ? ' (Due ' + money(s.total - s.paid) + ')' : ' (Paid)'}\nView / download: ${SITE}${l}`;
  return `<a class="btn o s" href="${l}" target="_blank">Invoice</a> <a class="btn o s" href="${l}&print=1" target="_blank">Download</a> <a class="btn o s" style="color:#128C7E" href="https://wa.me/${ph}?text=${encodeURIComponent(txt)}" target="_blank">WhatsApp</a> ${role === 'owner' ? confirmForm('/admin/sale-delete', hid('id', s.id), `Delete ${invNo(s.id)}? Stock, dues and cash will be reversed.`) : ''}`; };
GETS['/admin/sales'] = ({ role, q }) => {
  const rows = all('SELECT s.*,c.name cn,c.phone cp FROM sales s LEFT JOIN customers c ON c.id=s.customer_id ORDER BY s.id DESC LIMIT 150');
  return adminPage(role, '/admin/sales', 'Sales list', 'All invoices. Download, share on WhatsApp or delete', lnk('/admin/pos', '+ New sale', 'btn'), `<div class="pn">${tbl(['Invoice', 'Date', 'Customer', 'Items', 'Source', '>Total', '>Paid', '>Due', 'Status', ''], rows.map(s => { const it = all('SELECT p.name,i.qty FROM sale_items i JOIN products p ON p.id=i.product_id WHERE sale_id=?', s.id).map(i => esc(i.name) + ' ×' + i.qty).join(', '); return tr([`<b class="nm">${invNo(s.id)}</b>`, fmtDate(s.date), esc(s.cn || 'Walk-in customer'), `<span class="mu">${it}</span>`, esc(s.source), R(money(s.total)), R(money(s.paid)), R(money(s.total - s.paid)), saleStatus(s), `<span style="white-space:nowrap">${saleActions(s, role)}</span>`]); }), 'No sales yet.')}</div>`, q);
};
GETS['/admin/orders'] = ({ role, q }) => {
  const rows = all('SELECT * FROM orders ORDER BY id DESC LIMIT 100');
  return adminPage(role, '/admin/orders', 'Web orders', 'Orders placed on greencyclectg.com. Confirm to create a sale and reduce stock', '', `<div class="pn">${tbl(['#', 'Date', 'Customer', 'Items', '>Total', 'Payment', 'Status', ''], rows.map(o => tr([o.id, fmtDate(o.date), `<b class="nm">${esc(o.name)}</b><br><span class="mu">${esc(o.phone)}<br>${esc(o.address)}${o.city ? ', ' + esc(o.city) : ''}</span>`, JSON.parse(o.items).map(i => esc(i.name) + ' ×' + i.qty).join(', '), R(money(o.total)), esc(o.payment), pill(o.status, o.status === 'New' ? 'warn' : o.status === 'Confirmed' ? 'ok' : 'gr'),
    o.status === 'New' ? `<form method="post" action="/admin/orders" style="display:inline">${hid('id', o.id)}<button class="btn s" name="act" value="confirm">Confirm</button> <button class="btn r s" name="act" value="cancel">Cancel</button></form> <a class="btn o s" style="color:#128C7E" target="_blank" href="https://wa.me/${waPhone(o.phone)}">WhatsApp</a>` : ''])), 'No web orders yet.')}</div>`, q);
};
POSTS['/admin/orders'] = f => {
  const o = get('SELECT * FROM orders WHERE id=?', +f.get('id')); if (!o || o.status !== 'New') return '/admin/orders';
  if (f.get('act') === 'cancel') { run("UPDATE orders SET status='Cancelled' WHERE id=?", o.id); return go('/admin/orders', 'Order cancelled'); }
  const cust = get('SELECT * FROM customers WHERE phone=?', o.phone), cid = cust ? cust.id : +run('INSERT INTO customers(name,phone,address) VALUES(?,?,?)', o.name, o.phone, o.address).lastInsertRowid;
  const sid = createSale({ customer_id: cid, items: JSON.parse(o.items).map(i => ({ pid: i.id, qty: i.qty, price: i.price })), paid: 0, note: 'Web order #' + o.id + ' (' + o.payment + ')', source: 'Web' });
  run("UPDATE orders SET status='Confirmed' WHERE id=?", o.id); return go('/admin/orders', 'Order confirmed, invoice ' + invNo(sid) + ' created');
};
