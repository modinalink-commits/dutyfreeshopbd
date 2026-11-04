<?php
namespace App\Support;

/**
 * Hybrid Costing Model, database side (PostgreSQL). One place for the SQL so the migration, the tests and the app agree.
 *
 *   Weighted average cost  = reporting (inventory value)            products.avg_cost
 *   Current FIFO cost      = cost of the next batch to be consumed  products.fifo_cost
 *   Actual COGS            = the batches really consumed at shipment (order_item_batches, order_items.cogs)
 *   Protected profit       = actual batch cost x 1.15
 *
 * Every purchase / stock-in makes its own batch (stock_batches). Shipment consumes the oldest batches first inside the same
 * transaction that deducts stock (row locks, idempotent per order item). A confirmed return puts the goods back on the batch they came from.
 */
class CostingSql {
  public static function schema(): string {
    return <<<'SQL'
CREATE TABLE IF NOT EXISTS stock_batches (
  id bigserial PRIMARY KEY,
  product_id bigint NOT NULL,
  purchase_id bigint NULL,
  supplier_id bigint NULL,
  purchase_date date NOT NULL DEFAULT CURRENT_DATE,
  qty_received integer NOT NULL,
  qty_remaining integer NOT NULL,
  unit_cost numeric(14,4) NOT NULL DEFAULT 0,
  total_cost numeric(16,2) NOT NULL DEFAULT 0,
  warehouse_id bigint NULL,
  reference varchar(120) NULL,
  source varchar(24) NOT NULL DEFAULT 'purchase',
  status varchar(16) NOT NULL DEFAULT 'open',
  origin_batch_id bigint NULL,
  origin_order_id bigint NULL,
  created_by varchar(120) NULL,
  created_at timestamp NULL,
  updated_at timestamp NULL
);
CREATE INDEX IF NOT EXISTS stock_batches_fifo_idx ON stock_batches (product_id, purchase_date, id) WHERE qty_remaining > 0;
CREATE INDEX IF NOT EXISTS stock_batches_product_idx ON stock_batches (product_id);
CREATE INDEX IF NOT EXISTS stock_batches_purchase_idx ON stock_batches (purchase_id);
CREATE TABLE IF NOT EXISTS order_item_batches (
  id bigserial PRIMARY KEY,
  order_id bigint NOT NULL,
  order_item_id bigint NOT NULL,
  product_id bigint NOT NULL,
  batch_id bigint NULL,
  qty integer NOT NULL,
  returned_qty integer NOT NULL DEFAULT 0,
  unit_cost numeric(14,4) NOT NULL DEFAULT 0,
  shipment_ref varchar(120) NULL,
  mode varchar(12) NOT NULL DEFAULT 'FIFO',
  created_at timestamp NULL DEFAULT now()
);
CREATE UNIQUE INDEX IF NOT EXISTS order_item_batches_uq ON order_item_batches (order_item_id, (coalesce(batch_id, 0)));
CREATE INDEX IF NOT EXISTS order_item_batches_order_idx ON order_item_batches (order_id);
CREATE INDEX IF NOT EXISTS order_item_batches_product_idx ON order_item_batches (product_id);
ALTER TABLE products ADD COLUMN IF NOT EXISTS fifo_cost numeric(14,4) NOT NULL DEFAULT 0;
ALTER TABLE order_items ADD COLUMN IF NOT EXISTS cogs numeric(14,2) NULL;
ALTER TABLE stock_logs ADD COLUMN IF NOT EXISTS unit_cost numeric(14,4) NULL;
ALTER TABLE stock_logs ADD COLUMN IF NOT EXISTS batch_note text NULL;
SQL;
  }

  public static function functions(): string {
    return <<<'SQL'
-- Recompute the two cached cost figures of a product from its open batches.
CREATE OR REPLACE FUNCTION costing_refresh(p_pid bigint) RETURNS void AS $$
DECLARE f numeric; r bigint; a numeric; nf numeric; na numeric; cur record;
BEGIN
  SELECT unit_cost INTO f FROM stock_batches WHERE product_id = p_pid AND qty_remaining > 0 ORDER BY purchase_date, id LIMIT 1;
  SELECT coalesce(sum(qty_remaining), 0), sum(qty_remaining * unit_cost) / nullif(sum(qty_remaining), 0) INTO r, a FROM stock_batches WHERE product_id = p_pid AND qty_remaining > 0;
  SELECT fifo_cost, avg_cost, cost INTO cur FROM products WHERE id = p_pid;
  IF NOT FOUND THEN RETURN; END IF;
  nf := round(coalesce(f, 0), 4);
  IF r > 0 AND coalesce(a, 0) > 0 THEN na := round(a, 4); ELSIF coalesce(cur.cost, 0) > 0 THEN na := cur.cost; ELSE na := coalesce(cur.avg_cost, 0); END IF;
  IF cur.fifo_cost IS DISTINCT FROM nf OR cur.avg_cost IS DISTINCT FROM na THEN UPDATE products SET fifo_cost = nf, avg_cost = na WHERE id = p_pid; END IF;
END; $$ LANGUAGE plpgsql;

-- Stock in: every purchase (or return / adjustment) is its own batch.
CREATE OR REPLACE FUNCTION costing_add_batch(p_pid bigint, p_qty int, p_cost numeric, p_purchase bigint, p_supplier bigint, p_date date, p_ref text, p_source text, p_by text, p_origin_batch bigint DEFAULT NULL, p_origin_order bigint DEFAULT NULL) RETURNS bigint AS $$
DECLARE nid bigint;
BEGIN
  IF p_qty IS NULL OR p_qty <= 0 THEN RETURN NULL; END IF;
  INSERT INTO stock_batches(product_id, purchase_id, supplier_id, purchase_date, qty_received, qty_remaining, unit_cost, total_cost, reference, source, status, origin_batch_id, origin_order_id, created_by, created_at, updated_at)
  VALUES (p_pid, p_purchase, p_supplier, coalesce(p_date, current_date), p_qty, p_qty, round(coalesce(p_cost, 0), 4), round(p_qty * coalesce(p_cost, 0), 2), left(p_ref, 120), p_source, 'open', p_origin_batch, p_origin_order, left(p_by, 120), now(), now())
  RETURNING id INTO nid;
  PERFORM costing_refresh(p_pid);
  RETURN nid;
END; $$ LANGUAGE plpgsql;

-- Shipment: consume the oldest batches first and remember exactly which ones (Actual COGS). Idempotent per order item. Returns the COGS of the item.
CREATE OR REPLACE FUNCTION costing_consume(p_pid bigint, p_qty int, p_order bigint, p_item bigint, p_ref text) RETURNS numeric AS $$
DECLARE need int := p_qty; bt record; take int; total numeric := 0; fb numeric;
BEGIN
  IF p_qty IS NULL OR p_qty <= 0 THEN RETURN 0; END IF;
  IF p_item IS NOT NULL AND EXISTS (SELECT 1 FROM order_item_batches WHERE order_item_id = p_item) THEN
    RETURN (SELECT coalesce(sum(qty * unit_cost), 0) FROM order_item_batches WHERE order_item_id = p_item);
  END IF;
  FOR bt IN SELECT id, qty_remaining, unit_cost FROM stock_batches WHERE product_id = p_pid AND qty_remaining > 0 AND status = 'open' ORDER BY purchase_date, id FOR UPDATE LOOP
    EXIT WHEN need <= 0;
    take := LEAST(need, bt.qty_remaining);
    UPDATE stock_batches SET qty_remaining = qty_remaining - take, status = CASE WHEN qty_remaining - take = 0 THEN 'consumed' ELSE status END, updated_at = now() WHERE id = bt.id;
    INSERT INTO order_item_batches(order_id, order_item_id, product_id, batch_id, qty, unit_cost, shipment_ref, mode, created_at) VALUES (p_order, p_item, p_pid, bt.id, take, bt.unit_cost, p_ref, 'FIFO', now());
    total := total + take * bt.unit_cost; need := need - take;
  END LOOP;
  IF need > 0 THEN
    -- stock that no batch covers (shipped without stock, or stock that pre-dates batches): cost it at the last purchase price
    SELECT coalesce(nullif(cost, 0), nullif(avg_cost, 0), 0) INTO fb FROM products WHERE id = p_pid;
    INSERT INTO order_item_batches(order_id, order_item_id, product_id, batch_id, qty, unit_cost, shipment_ref, mode, created_at) VALUES (p_order, p_item, p_pid, NULL, need, coalesce(fb, 0), p_ref, 'FIFO', now());
    total := total + need * coalesce(fb, 0);
  END IF;
  RETURN round(total, 2);
END; $$ LANGUAGE plpgsql;

-- Stock out without an order (damage, purchase return, manual decrease): oldest batches first. Returns the cost of the units removed.
CREATE OR REPLACE FUNCTION costing_out(p_pid bigint, p_qty int) RETURNS numeric AS $$
DECLARE need int := p_qty; bt record; take int; total numeric := 0;
BEGIN
  IF p_qty IS NULL OR p_qty <= 0 THEN RETURN 0; END IF;
  FOR bt IN SELECT id, qty_remaining, unit_cost FROM stock_batches WHERE product_id = p_pid AND qty_remaining > 0 AND status = 'open' ORDER BY purchase_date, id FOR UPDATE LOOP
    EXIT WHEN need <= 0;
    take := LEAST(need, bt.qty_remaining);
    UPDATE stock_batches SET qty_remaining = qty_remaining - take, status = CASE WHEN qty_remaining - take = 0 THEN 'consumed' ELSE status END, updated_at = now() WHERE id = bt.id;
    total := total + take * bt.unit_cost; need := need - take;
  END LOOP;
  PERFORM costing_refresh(p_pid);
  RETURN round(total, 2);
END; $$ LANGUAGE plpgsql;

-- Confirmed return: put the goods back on the batch(es) they were shipped from (the batch re-opens). Without a record, a return batch at the average cost is made.
CREATE OR REPLACE FUNCTION costing_restock(p_item bigint, p_qty int, p_order bigint, p_ref text) RETURNS numeric AS $$
DECLARE a record; bid bigint; bst text; left_q int := p_qty; take int; total numeric := 0; pid bigint; fb numeric;
BEGIN
  IF p_qty IS NULL OR p_qty <= 0 THEN RETURN 0; END IF;
  SELECT product_id INTO pid FROM order_items WHERE id = p_item;
  FOR a IN SELECT * FROM order_item_batches WHERE order_item_id = p_item AND qty > returned_qty ORDER BY id DESC FOR UPDATE LOOP
    EXIT WHEN left_q <= 0;
    take := LEAST(left_q, a.qty - a.returned_qty);
    bid := NULL; bst := NULL;
    IF a.batch_id IS NOT NULL THEN SELECT id, status INTO bid, bst FROM stock_batches WHERE id = a.batch_id FOR UPDATE; END IF;
    IF bid IS NOT NULL AND bst <> 'reversed' THEN
      UPDATE stock_batches SET qty_remaining = qty_remaining + take, status = 'open', updated_at = now() WHERE id = bid;
    ELSE
      PERFORM costing_add_batch(a.product_id, take, a.unit_cost, NULL, NULL, current_date, p_ref, 'return', 'system', a.batch_id, a.order_id);
    END IF;
    UPDATE order_item_batches SET returned_qty = returned_qty + take WHERE id = a.id;
    total := total + take * a.unit_cost; left_q := left_q - take;
  END LOOP;
  IF left_q > 0 AND pid IS NOT NULL THEN
    SELECT coalesce(nullif(avg_cost, 0), nullif(cost, 0), 0) INTO fb FROM products WHERE id = pid;
    PERFORM costing_add_batch(pid, left_q, coalesce(fb, 0), NULL, NULL, current_date, p_ref, 'return', 'system', NULL, p_order);
    total := total + left_q * coalesce(fb, 0);
  END IF;
  IF pid IS NOT NULL THEN PERFORM costing_refresh(pid); END IF;
  RETURN round(total, 2);
END; $$ LANGUAGE plpgsql;

-- A purchase is reversed / edited: its batches leave the queue (units already shipped stay shipped).
CREATE OR REPLACE FUNCTION costing_reverse_purchase(p_purchase bigint) RETURNS int AS $$
DECLARE r record; n int := 0;
BEGIN
  FOR r IN SELECT DISTINCT product_id FROM stock_batches WHERE purchase_id = p_purchase AND status <> 'reversed' LOOP
    UPDATE stock_batches SET qty_remaining = 0, status = 'reversed', updated_at = now() WHERE purchase_id = p_purchase AND product_id = r.product_id AND status <> 'reversed';
    PERFORM costing_refresh(r.product_id); n := n + 1;
  END LOOP;
  RETURN n;
END; $$ LANGUAGE plpgsql;

-- Safety net: make the open batches add up to the stock on hand (a manual stock edit, an import, an oversold shipment later covered by a purchase).
CREATE OR REPLACE FUNCTION costing_sync(p_pid bigint) RETURNS int AS $$
DECLARE s int; r int; c numeric; a numeric; d int := 0;
BEGIN
  SELECT stock, cost, avg_cost INTO s, c, a FROM products WHERE id = p_pid;
  IF NOT FOUND OR s IS NULL THEN RETURN 0; END IF;
  s := greatest(s, 0);
  SELECT coalesce(sum(qty_remaining), 0) INTO r FROM stock_batches WHERE product_id = p_pid AND qty_remaining > 0;
  IF r < s THEN d := s - r; PERFORM costing_add_batch(p_pid, d, coalesce(nullif(c, 0), nullif(a, 0), 0), NULL, NULL, current_date, 'stock adjustment', 'adjustment', 'system');
  ELSIF r > s THEN d := -(r - s); PERFORM costing_out(p_pid, r - s);
  END IF;
  PERFORM costing_refresh(p_pid);
  RETURN d;
END; $$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION costing_sync_all() RETURNS int AS $$
DECLARE r record; n int := 0;
BEGIN
  FOR r IN SELECT p.id FROM products p WHERE p.stock IS NOT NULL AND greatest(p.stock, 0) <> coalesce((SELECT sum(b.qty_remaining) FROM stock_batches b WHERE b.product_id = p.id AND b.qty_remaining > 0), 0) LOOP
    PERFORM costing_sync(r.id); n := n + 1;
  END LOOP;
  RETURN n;
END; $$ LANGUAGE plpgsql;

-- Shipment is the one stock deduction point: stock goes down, the exact batches are consumed (FIFO), the item keeps its Actual COGS. Runs once per order (stock_deducted_at).
CREATE OR REPLACE FUNCTION orders_ship_stock() RETURNS trigger AS $$
DECLARE r record; it record; b int; c numeric; tot numeric; note text; sub numeric;
BEGIN
  FOR r IN SELECT oi.product_id pid, sum(oi.qty)::int q FROM order_items oi WHERE oi.order_id = NEW.id AND oi.product_id IS NOT NULL GROUP BY oi.product_id ORDER BY oi.product_id LOOP
    SELECT stock INTO b FROM products WHERE id = r.pid FOR UPDATE;
    IF b IS NULL THEN CONTINUE; END IF;
    tot := 0;
    FOR it IN SELECT id, qty FROM order_items WHERE order_id = NEW.id AND product_id = r.pid ORDER BY id LOOP
      c := costing_consume(r.pid, it.qty, NEW.id, it.id, NEW.invoice);
      UPDATE order_items SET cogs = c WHERE id = it.id;
      tot := tot + c;
    END LOOP;
    UPDATE products SET stock = b - r.q WHERE id = r.pid;
    PERFORM costing_refresh(r.pid);
    SELECT string_agg('B' || coalesce(x.batch_id::text, '-') || ' x' || x.q || ' @' || trim(trailing '.' from trim(trailing '0' from x.uc::text)), ', ' ORDER BY x.batch_id NULLS LAST)
      INTO note FROM (SELECT batch_id, sum(qty) q, unit_cost uc FROM order_item_batches WHERE order_id = NEW.id AND product_id = r.pid GROUP BY batch_id, unit_cost) x;
    INSERT INTO stock_logs(product_id, source, reference, before_qty, change_qty, after_qty, user_id, user_name, unit_cost, batch_note, created_at, updated_at)
    VALUES (r.pid, 'order-shipment', NEW.invoice, b, -r.q, b - r.q, NULL, 'System', CASE WHEN r.q > 0 THEN round(tot / r.q, 4) END, note, now(), now());
  END LOOP;
  NEW.stock_deducted_at := now();
  RETURN NEW;
END; $$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS orders_ship_stock_t ON orders;
CREATE TRIGGER orders_ship_stock_t BEFORE UPDATE OF status ON orders FOR EACH ROW
  WHEN (NEW.status = 'Shipment' AND OLD.status IS DISTINCT FROM 'Shipment' AND NEW.stock_deducted_at IS NULL AND NEW.duplicate_of_order_id IS NULL)
  EXECUTE FUNCTION orders_ship_stock();
SQL;
  }
}
