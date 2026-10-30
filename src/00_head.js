'use strict';
const http = require('http'), crypto = require('crypto'), fs = require('fs');
const { DatabaseSync } = require('node:sqlite');
const PORT = +process.env.PORT || 3000, DATA = process.env.DATA_DIR || '/data';
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD || 'admin123';
const SITE = process.env.SITE || 'https://greencyclectg.com';
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
CREATE TABLE IF NOT EXISTS orders(id INTEGER PRIMARY KEY,date TEXT,name TEXT,phone TEXT,address TEXT,city TEXT,payment TEXT,total REAL,status TEXT DEFAULT 'New',items TEXT,note TEXT DEFAULT '');
CREATE TABLE IF NOT EXISTS web_products(id INTEGER PRIMARY KEY,name TEXT,category TEXT,price REAL DEFAULT 0,image TEXT DEFAULT '',description TEXT DEFAULT '',active INTEGER DEFAULT 1,created TEXT DEFAULT '');
CREATE TABLE IF NOT EXISTS web_orders(id INTEGER PRIMARY KEY,date TEXT,name TEXT,phone TEXT,address TEXT,city TEXT,payment TEXT,total REAL,status TEXT DEFAULT 'New',items TEXT,note TEXT DEFAULT '');
CREATE TABLE IF NOT EXISTS banks(id INTEGER PRIMARY KEY,name TEXT UNIQUE);
CREATE TABLE IF NOT EXISTS settings(k TEXT PRIMARY KEY,v TEXT);
CREATE TABLE IF NOT EXISTS purchase_orders(id INTEGER PRIMARY KEY,date TEXT,supplier_id INTEGER,status TEXT DEFAULT 'Ordered',total REAL DEFAULT 0,note TEXT DEFAULT '',expected TEXT DEFAULT '');
CREATE TABLE IF NOT EXISTS po_items(id INTEGER PRIMARY KEY,po_id INTEGER,product_id INTEGER,qty REAL,cost REAL,received REAL DEFAULT 0);
CREATE TABLE IF NOT EXISTS grn(id INTEGER PRIMARY KEY,po_id INTEGER,date TEXT,value REAL);
CREATE TABLE IF NOT EXISTS reps(id INTEGER PRIMARY KEY,name TEXT,phone TEXT DEFAULT '',area TEXT DEFAULT '',target REAL DEFAULT 0,active INTEGER DEFAULT 1);
CREATE TABLE IF NOT EXISTS offers(id INTEGER PRIMARY KEY,name TEXT,kind TEXT DEFAULT 'Percent off',value REAL DEFAULT 0,valid_until TEXT DEFAULT '');
CREATE TABLE IF NOT EXISTS emi_plans(id INTEGER PRIMARY KEY,customer_id INTEGER,sale_id INTEGER,product TEXT,total REAL,down REAL,months INTEGER,amount REAL,created TEXT);
CREATE TABLE IF NOT EXISTS emi_inst(id INTEGER PRIMARY KEY,plan_id INTEGER,no INTEGER,due TEXT,amount REAL,paid REAL DEFAULT 0,paid_date TEXT DEFAULT '',fee REAL DEFAULT 0);
CREATE TABLE IF NOT EXISTS jobs(id INTEGER PRIMARY KEY,date TEXT,customer_id INTEGER,frame_no TEXT DEFAULT '',work TEXT,status TEXT DEFAULT 'Open',claim INTEGER DEFAULT 0,charge REAL DEFAULT 0);
CREATE TABLE IF NOT EXISTS reminder_log(id INTEGER PRIMARY KEY,ts TEXT,frame_no TEXT,kind TEXT);
CREATE TABLE IF NOT EXISTS audits(id INTEGER PRIMARY KEY,started TEXT,location TEXT,status TEXT DEFAULT 'In progress',by TEXT DEFAULT 'Admin');
CREATE TABLE IF NOT EXISTS audit_items(id INTEGER PRIMARY KEY,audit_id INTEGER,product_id INTEGER,sys REAL,counted REAL);
CREATE TABLE IF NOT EXISTS stock_log(id INTEGER PRIMARY KEY,ts TEXT,product_id INTEGER,qty REAL,reason TEXT,ref TEXT DEFAULT '');
CREATE TABLE IF NOT EXISTS damages(id INTEGER PRIMARY KEY,date TEXT,kind TEXT,product_id INTEGER,qty REAL,reason TEXT DEFAULT '',supplier_id INTEGER,customer_id INTEGER,value REAL DEFAULT 0,status TEXT DEFAULT 'Logged');
CREATE TABLE IF NOT EXISTS approvals(id INTEGER PRIMARY KEY,ts TEXT,type TEXT,by TEXT,details TEXT,amount REAL DEFAULT 0,payload TEXT,status TEXT DEFAULT 'Pending',decided TEXT DEFAULT '',note TEXT DEFAULT '');
CREATE TABLE IF NOT EXISTS shipments(id INTEGER PRIMARY KEY,date TEXT,supplier_id INTEGER,lc_no TEXT DEFAULT '',rate REAL DEFAULT 1,freight REAL DEFAULT 0,duty REAL DEFAULT 0,clearing REAL DEFAULT 0,arrival TEXT DEFAULT '',status TEXT DEFAULT 'At sea');
CREATE TABLE IF NOT EXISTS ship_items(id INTEGER PRIMARY KEY,shipment_id INTEGER,product_id INTEGER,qty REAL,unit_usd REAL);
CREATE TABLE IF NOT EXISTS categories(name TEXT PRIMARY KEY);
CREATE TABLE IF NOT EXISTS closings(id INTEGER PRIMARY KEY,date TEXT,opening REAL,tin REAL,tout REAL,system REAL,counted REAL,diff REAL);`);
const all = (s, ...a) => db.prepare(s).all(...a), get = (s, ...a) => db.prepare(s).get(...a), run = (s, ...a) => db.prepare(s).run(...a);
let txd = 0;
const tx = fn => { if (txd) return fn(); db.exec('BEGIN'); txd = 1; try { const r = fn(); db.exec('COMMIT'); return r; } catch (e) { db.exec('ROLLBACK'); throw e; } finally { txd = 0; } };
const ensureCol = (t, c, d) => { if (!all(`PRAGMA table_info(${t})`).some(x => x.name === c)) db.exec(`ALTER TABLE ${t} ADD COLUMN ${c} ${d}`); };
[['products', 'wh', 'REAL DEFAULT 0'], ['products', 'dealer_price', 'REAL DEFAULT 0'], ['products', 'wholesale_price', 'REAL DEFAULT 0'], ['products', 'min_margin', 'REAL DEFAULT 10'], ['products', 'supplier_id', 'INTEGER'], ['products', 'barcode', "TEXT DEFAULT ''"],
 ['customers', 'credit_limit', 'REAL DEFAULT 0'], ['customers', 'bin', "TEXT DEFAULT ''"], ['customers', 'area', "TEXT DEFAULT ''"], ['customers', 'rep_id', 'INTEGER'],
 ['sales', 'method', "TEXT DEFAULT 'Cash'"], ['sales', 'rep_id', 'INTEGER'], ['sales', 'ts', "TEXT DEFAULT ''"],
 ['sale_items', 'frame_no', "TEXT DEFAULT ''"],
 ['cash', 'ts', "TEXT DEFAULT ''"], ['cash', 'acct', "TEXT DEFAULT 'Cash'"], ['cash', 'ref', "TEXT DEFAULT ''"], ['cash', 'rep_id', 'INTEGER'], ['cash', 'customer_id', 'INTEGER']].forEach(a => ensureCol(...a));
const today = () => new Date(Date.now() + 6 * 3600e3).toISOString().slice(0, 10);
const nowTs = () => new Date(Date.now() + 6 * 3600e3).toISOString().slice(0, 19).replace('T', ' ');
const addDays = (d, n) => { const x = new Date(d + 'T00:00:00Z'); x.setUTCDate(x.getUTCDate() + n); return x.toISOString().slice(0, 10); };
const addMonths = (d, n) => { const x = new Date(d + 'T00:00:00Z'); x.setUTCMonth(x.getUTCMonth() + n); return x.toISOString().slice(0, 10); };
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const money = n => '৳ ' + Math.round(+n || 0).toLocaleString('en-US');
const num = v => { const n = parseFloat(v); return isFinite(n) ? n : 0; };
const pad = (n, w) => String(n).padStart(w, '0');
const invNo = id => String(id), poNo = id => 'PO-' + (2000 + id), jobNo = id => 'JC-' + pad(id + 300, 4), audNo = id => 'AUD-' + pad(id, 2), shpNo = id => 'SHP-' + pad(id + 12, 3);

/* settings */
const DEFAULTS = { vat_rate: 15, late_fee_pct: 2, lead_days: 9, disc_pct: 5, disc_on: 1, po_amt: 100000, po_on: 1, exp_amt: 10000, exp_on: 1, credit_on: 1, price_on: 1 };
const setting = k => { const r = get('SELECT v FROM settings WHERE k=?', k); return r ? r.v : (DEFAULTS[k] ?? ''); };
const setSetting = (k, v) => run('INSERT INTO settings(k,v) VALUES(?,?) ON CONFLICT(k) DO UPDATE SET v=excluded.v', k, String(v));
const S = k => num(setting(k));

/* one-time seeds */
if (!get('SELECT 1 FROM products') && !get("SELECT 1 FROM settings WHERE k='seeded'")) {
  const seed = [['Mountain bike 26"', 'MTB-26', 'Bicycles', 38000, 33000, 12, 3], ['Road bike 700C', 'RD-700', 'Bicycles', 52000, 46000, 4, 2], ['Kids bike 16"', 'KID-16', 'Bicycles', 14500, 12000, 6, 2],
    ['Safety helmet', 'HLM-01', 'Helmets', 1800, 1300, 24, 5], ['Tyre 26×2.1', 'TYR-26', 'Tyres & tubes', 1200, 900, 5, 8], ['Chain 8-speed', 'CHN-8', 'Chains & gears', 950, 700, 3, 6],
    ['Brake pad set', 'BRK-01', 'Chains & gears', 650, 450, 2, 6], ['LED headlight', 'LGT-01', 'Lights & locks', 850, 600, 4, 6], ['Hand pump', 'PMP-01', 'Accessories', 350, 250, 30, 5],
    ['Combination lock', 'LCK-01', 'Lights & locks', 450, 300, 18, 5], ['Rickshaw hub set', 'RKS-HUB', 'Rickshaw parts', 2400, 1900, 10, 3], ['Rickshaw wheel rim', 'RKS-RIM', 'Rickshaw parts', 1800, 1400, 14, 4]];
  seed.forEach(p => run('INSERT INTO products(name,sku,category,price,cost,stock,reorder) VALUES(?,?,?,?,?,?,?)', ...p));
}
if (!get('SELECT 1 FROM customers')) run("INSERT INTO customers(name,phone,type) VALUES('Walk-in customer','','Retail')");
if (!get('SELECT 1 FROM suppliers')) run("INSERT INTO suppliers(name) VALUES('General supplier')");
if (!get("SELECT 1 FROM settings WHERE k='seeded'")) {
  setSetting('seeded', 1);
  run("UPDATE products SET wh=CAST(stock/3 AS INTEGER) WHERE wh=0");
  run("UPDATE products SET dealer_price=ROUND(price*0.92), wholesale_price=ROUND(price*0.88) WHERE dealer_price=0");
  run("UPDATE products SET supplier_id=(SELECT id FROM suppliers ORDER BY id LIMIT 1) WHERE supplier_id IS NULL");
}
run("UPDATE products SET image='/img/'||replace(replace(category,' ','%20'),'&','and')||'.svg' WHERE image=''");
run("UPDATE sales SET ts=date||' 12:00:00' WHERE ts=''");
run("UPDATE cash SET ts=date||' 12:00:00' WHERE ts=''");

if (!get("SELECT 1 FROM settings WHERE k='banks_seeded'")) { setSetting('banks_seeded', 1); ['United Commercial Bank PLC (UCB)', 'Uttara Bank PLC', 'National Bank PLC (NBL)', 'Islami Bank Bangladesh PLC', 'Eastern Bank PLC (EBL)'].forEach(n => run('INSERT OR IGNORE INTO banks(name) VALUES(?)', n)); }
if (!get("SELECT 1 FROM settings WHERE k='banks_seeded2'")) { setSetting('banks_seeded2', 1); run('INSERT OR IGNORE INTO banks(name) VALUES(?)', 'Rupali Bank PLC'); }
/* website module: its own catalogue and orders, no link to inventory. Copied once from the old data, independent afterwards */
if (!get("SELECT 1 FROM settings WHERE k='web_split'")) {
  setSetting('web_split', 1);
  if (!get('SELECT 1 FROM web_products')) run("INSERT INTO web_products(name,category,price,image,description,created) SELECT name,category,price,image,description,date('now') FROM products WHERE web=1 ORDER BY id");
  if (!get('SELECT 1 FROM web_orders')) run("INSERT INTO web_orders(id,date,name,phone,address,city,payment,total,status,items,note) SELECT id,date,name,phone,address,city,payment,total,status,items,note FROM orders");
}
['Veloce', 'Prince', 'Reflex', 'Seventy One Bicycle'].forEach(n => run('INSERT OR IGNORE INTO categories(name) VALUES(?)', n));
const allCats = () => [...new Set([...all('SELECT DISTINCT category c FROM products').map(x => x.c), ...all('SELECT name c FROM categories').map(x => x.c)])].filter(Boolean).sort();
