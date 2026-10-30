
/* ---------- business logic ---------- */
const ACCTS = ['Cash', 'Bank', 'bKash', 'Nagad'];
const bal = a => get("SELECT COALESCE(SUM(CASE WHEN type='in' THEN amount ELSE -amount END),0) b FROM cash WHERE acct=?", a).b;
const cashAdd = (type, amount, head, note = '', acct = 'Cash', ref = '', rep_id = null, customer_id = null) =>
  run('INSERT INTO cash(date,type,amount,head,note,ts,acct,ref,rep_id,customer_id) VALUES(?,?,?,?,?,?,?,?,?,?)', today(), type, amount, head, note, nowTs(), ACCTS.includes(acct) ? acct : 'Cash', ref, rep_id || null, customer_id || null);
const stockLog = (pid, qty, reason, ref = '') => run('INSERT INTO stock_log(ts,product_id,qty,reason,ref) VALUES(?,?,?,?,?)', nowTs(), pid, qty, reason, ref);
function takeStock(p, qty) { const showroom = p.stock - p.wh, deficit = Math.max(0, qty - Math.max(0, showroom)); run('UPDATE products SET stock=stock-?, wh=MAX(0,wh-?) WHERE id=?', qty, deficit, p.id); }
const priceFor = (p, type) => (type === 'Dealer' && p.dealer_price > 0) ? p.dealer_price : (type === 'Wholesale' && p.wholesale_price > 0) ? p.wholesale_price : p.price;

function createSale({ customer_id, items, discount = 0, paid = 0, note = '', source = 'POS', method = 'Cash', rep_id = null }) {
  items = items.filter(i => i.pid && i.qty > 0);
  if (!items.length) throw new Error('Add at least one product');
  return tx(() => {
    let total = 0;
    items.forEach(i => { const p = get('SELECT * FROM products WHERE id=?', i.pid); if (!p) throw new Error('Product not found'); if (p.stock < i.qty) throw new Error('Not enough stock: ' + p.name + ' (have ' + p.stock + ')'); i.p = p; total += i.qty * i.price; });
    discount = Math.min(Math.max(0, discount), total);
    const net = Math.max(0, total - discount); paid = Math.min(Math.max(0, paid), net);
    const id = +run('INSERT INTO sales(date,customer_id,total,discount,paid,note,source,method,rep_id,ts) VALUES(?,?,?,?,?,?,?,?,?,?)', today(), customer_id, net, discount, paid, note, source, ACCTS.includes(method) ? method : 'Cash', rep_id || null, nowTs()).lastInsertRowid;
    items.forEach(i => { run('INSERT INTO sale_items(sale_id,product_id,qty,price,cost,frame_no) VALUES(?,?,?,?,?,?)', id, i.pid, i.qty, i.price, i.p.cost, String(i.frame_no || '').trim()); takeStock(i.p, i.qty); });
    if (customer_id) run('UPDATE customers SET balance=balance+? WHERE id=?', net - paid, customer_id);
    if (paid > 0) cashAdd('in', paid, 'Sale', invNo(id) + (customer_id ? '' : ''), method, 'S' + id, rep_id, customer_id);
    return id;
  });
}
function deleteSale(id) {
  return tx(() => {
    const s = get('SELECT * FROM sales WHERE id=?', id); if (!s) throw new Error('Sale not found');
    if (get('SELECT 1 FROM emi_plans WHERE sale_id=?', id)) throw new Error('This sale has an EMI plan. Delete the plan first.');
    all('SELECT * FROM sale_items WHERE sale_id=?', id).forEach(i => run('UPDATE products SET stock=stock+? WHERE id=?', i.qty, i.product_id));
    if (s.customer_id) run('UPDATE customers SET balance=balance-? WHERE id=?', s.total - s.paid, s.customer_id);
    run("DELETE FROM cash WHERE ref=? OR (head='Sale' AND note=?)", 'S' + id, 'Sale #' + id);
    run('DELETE FROM sale_items WHERE sale_id=?', id); run('DELETE FROM sales WHERE id=?', id);
  });
}
function createPO({ supplier_id, items, expected = '', note = '' }) {
  items = items.filter(i => i.pid && i.qty > 0);
  if (!items.length) throw new Error('Add at least one product');
  return tx(() => {
    const total = items.reduce((s, i) => s + i.qty * i.cost, 0);
    const id = +run('INSERT INTO purchase_orders(date,supplier_id,status,total,note,expected) VALUES(?,?,?,?,?,?)', today(), supplier_id || null, 'Ordered', total, note, expected).lastInsertRowid;
    items.forEach(i => run('INSERT INTO po_items(po_id,product_id,qty,cost) VALUES(?,?,?,?)', id, i.pid, i.qty, i.cost));
    return id;
  });
}
function receivePO(id, rcv) {
  return tx(() => {
    const po = get('SELECT * FROM purchase_orders WHERE id=?', id); if (!po) throw new Error('PO not found');
    let value = 0, any = false;
    all('SELECT * FROM po_items WHERE po_id=?', id).forEach(it => {
      const q = Math.min(Math.max(0, num(rcv[it.id])), it.qty - it.received); if (q <= 0) return; any = true;
      const p = get('SELECT * FROM products WHERE id=?', it.product_id), base = Math.max(0, p.stock), newCost = base + q > 0 ? (base * p.cost + q * it.cost) / (base + q) : it.cost;
      run('UPDATE po_items SET received=received+? WHERE id=?', q, it.id);
      run('UPDATE products SET stock=stock+?, wh=wh+?, cost=? WHERE id=?', q, q, Math.round(newCost * 100) / 100, p.id);
      stockLog(p.id, q, 'Goods received ' + poNo(id), 'PO' + id); value += q * it.cost;
    });
    if (!any) throw new Error('Enter a received quantity');
    run('INSERT INTO grn(po_id,date,value) VALUES(?,?,?)', id, today(), value);
    if (po.supplier_id) run('UPDATE suppliers SET balance=balance+? WHERE id=?', value, po.supplier_id);
    const left = get('SELECT SUM(qty-received) r, SUM(received) g FROM po_items WHERE po_id=?', id);
    run('UPDATE purchase_orders SET status=? WHERE id=?', left.r <= 0 ? 'Received' : 'Partly received', id);
    return value;
  });
}
function createEmi({ customer_id, product_id, price, down, months, first_due, frame_no = '' }) {
  const p = get('SELECT * FROM products WHERE id=?', product_id); if (!p) throw new Error('Choose a product');
  if (!customer_id) throw new Error('Choose a customer'); months = Math.max(1, Math.floor(months));
  return tx(() => {
    price = price > 0 ? price : p.price; down = Math.min(Math.max(0, down), price);
    const sid = createSale({ customer_id, items: [{ pid: p.id, qty: 1, price, frame_no }], paid: down, note: 'EMI plan', method: 'Cash' });
    const amt = Math.ceil((price - down) / months);
    const pid = +run('INSERT INTO emi_plans(customer_id,sale_id,product,total,down,months,amount,created) VALUES(?,?,?,?,?,?,?,?)', customer_id, sid, p.name, price, down, months, amt, today()).lastInsertRowid;
    for (let n = 1; n <= months; n++) run('INSERT INTO emi_inst(plan_id,no,due,amount) VALUES(?,?,?,?)', pid, n, addMonths(first_due || addDays(today(), 30), n - 1), n === months ? (price - down) - amt * (months - 1) : amt);
    return { sid, pid };
  });
}
const lateFee = i => { if (i.paid >= i.amount || i.due >= today()) return 0; return Math.round(i.amount * S('late_fee_pct') / 100); };
function payInstallment(id, amount, acct) {
  return tx(() => {
    const i = get('SELECT i.*,p.customer_id cid,p.product FROM emi_inst i JOIN emi_plans p ON p.id=i.plan_id WHERE i.id=?', id); if (!i) throw new Error('Installment not found');
    const fee = lateFee(i), due = i.amount - i.paid; amount = num(amount); if (amount <= 0) throw new Error('Enter an amount');
    const base = Math.min(due, Math.max(0, amount - fee)), feePaid = Math.min(fee, amount);
    run('UPDATE emi_inst SET paid=paid+?, paid_date=?, fee=fee+? WHERE id=?', base, today(), feePaid, id);
    run('UPDATE customers SET balance=balance-? WHERE id=?', base, i.cid);
    cashAdd('in', base, 'EMI installment', i.product + ' #' + i.no, acct, 'E' + id, null, i.cid);
    if (feePaid > 0) cashAdd('in', feePaid, 'Late fee', i.product + ' #' + i.no, acct, 'E' + id, null, i.cid);
    return base + feePaid;
  });
}
function adjustStock(pid, delta, reason, ref = '', fromWh = false) {
  const p = get('SELECT * FROM products WHERE id=?', pid); if (!p) return;
  if (fromWh) run('UPDATE products SET stock=MAX(0,stock+?), wh=MAX(0,wh+?) WHERE id=?', delta, delta, pid);
  else if (delta < 0) takeStock(p, -delta); else run('UPDATE products SET stock=stock+? WHERE id=?', delta, pid);
  stockLog(pid, delta, reason, ref);
}
/* approvals: staff requests that need the owner */
function requestApproval(type, by, details, amount, payload) { return +run('INSERT INTO approvals(ts,type,by,details,amount,payload) VALUES(?,?,?,?,?,?)', nowTs(), type, by, details, amount, JSON.stringify(payload)).lastInsertRowid; }
function executeApproval(a) {
  const p = JSON.parse(a.payload || '{}');
  if (a.type === 'sale') return createSale(p);
  if (a.type === 'po') return createPO(p);
  if (a.type === 'expense') return cashAdd('out', p.amount, p.head, p.note, p.acct, 'EXP');
  if (a.type === 'credit') return run('UPDATE customers SET credit_limit=? WHERE id=?', p.limit, p.id);
  if (a.type === 'price') return run('UPDATE products SET price=?, dealer_price=?, wholesale_price=? WHERE id=?', p.price, p.dealer, p.wholesale, p.id);
}
function decide(id, ok, note = '') {
  return tx(() => {
    const a = get('SELECT * FROM approvals WHERE id=?', id); if (!a || a.status !== 'Pending') return;
    if (ok) executeApproval(a);
    run('UPDATE approvals SET status=?, decided=?, note=? WHERE id=?', ok ? 'Approved' : 'Rejected', nowTs(), note, id);
  });
}
const hashPw = pw => crypto.scryptSync(String(pw), SECRET, 32).toString('hex');
